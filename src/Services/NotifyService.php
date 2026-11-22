<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;
use Tendari\Models\Notification;
use Tendari\Models\SavedSearch;
use Tendari\Models\User;

/**
 * Match pipeline: find new tenders since last run, queue notifications, dispatch.
 */
final class NotifyService
{
    /** Run the pipeline; returns summary counts. */
    public static function run(): array
    {
        $windowStart = self::state('notify.last_run', gmdate('Y-m-d H:i:s', time() - 600));
        $windowEnd = gmdate('Y-m-d H:i:s');

        $tenders = Db::all(
            "SELECT * FROM tenders WHERE first_seen_at >= ? AND first_seen_at <= ?",
            [$windowStart, $windowEnd]
        );

        $queuedEmail = 0;
        $queuedTelegram = 0;
        $searches = SavedSearch::active();

        foreach ($tenders as $tender) {
            foreach ($searches as $search) {
                if ((int)$search['user_active'] !== 1) {
                    continue;
                }
                $tenderArr = self::tenderArray($tender);
                if (!MatchEngine::matches($search, $tenderArr)) {
                    continue;
                }
                $userId = (int)$search['user_id'];
                $channels = User::notifyChannels(User::find($userId) ?? []);
                $isDigest = (int)$search['notify_digest'] === 1;

                if (in_array('email', $channels, true) && !Notification::exists($userId, (int)$tender['id'], 'email')) {
                    // digest users: buffer by storing pending; dispatched by digest cron at 07:00
                    Notification::create($userId, (int)$tender['id'], 'email');
                    $queuedEmail++;
                }
                if (in_array('telegram', $channels, true) && !Notification::exists($userId, (int)$tender['id'], 'telegram')) {
                    Notification::create($userId, (int)$tender['id'], 'telegram');
                    $queuedTelegram++;
                }
            }
        }

        self::state('notify.last_run', $windowEnd);
        $dispatched = self::dispatchInstant();
        return [
            'new_tenders' => count($tenders),
            'email_queued' => $queuedEmail,
            'telegram_queued' => $queuedTelegram,
            'dispatched' => $dispatched,
        ];
    }

    /** Send pending email (non-digest users) + telegram rows now. */
    public static function dispatchInstant(): int
    {
        $rows = Notification::pending();
        $sent = 0;
        $emailGroups = [];
        $telegramRows = [];

        foreach ($rows as $row) {
            $userId = (int)$row['user_id'];
            $tender = \Tendari\Models\Tender::find((int)$row['tender_id']);
            if ($tender === null) {
                Notification::markSent($row['id']); // tender gone; drop it
                continue;
            }
            if ($row['channel'] === 'telegram') {
                $telegramRows[] = ['row' => $row, 'tender' => $tender];
            } elseif ((int)$row['notify_digest'] === 0 && (int)$row['email_verified'] === 1) {
                $emailGroups[$userId . '|' . $row['email']][] = ['row' => $row, 'tender' => $tender];
            }
        }

        $mailer = new \Tendari\Notifiers\EmailNotifier();
        foreach ($emailGroups as $items) {
            if ($mailer->sendTenderBatch($items[0]['row']['email'], $items, 'instant')) {
                foreach ($items as $item) {
                    Notification::markSent($item['row']['id']);
                    $sent++;
                }
            } else {
                foreach ($items as $item) {
                    Notification::markFailed($item['row']['id'], 'SMTP send failed');
                }
            }
        }

        $tg = new \Tendari\Notifiers\TelegramNotifier();
        foreach ($telegramRows as $item) {
            $ok = $tg->sendTender($item['row']['telegram_chat_id'], $item['tender']);
            if ($ok) {
                Notification::markSent($item['row']['id']);
            } else {
                Notification::markFailed($item['row']['id'], 'Telegram sendMessage failed');
            }
            $sent += $ok ? 1 : 0;
        }
        return $sent;
    }

    /** Daily digest at 07:00 user-local time. */
    public static function runDigest(): int
    {
        $rows = Notification::pending();
        $groups = [];
        foreach ($rows as $row) {
            if ($row['channel'] !== 'email' || (int)$row['notify_digest'] !== 1 || (int)$row['email_verified'] !== 1) {
                continue;
            }
            $tz = new \DateTimeZone($row['timezone'] ?: 'UTC');
            $local = new \DateTime('now', $tz);
            if ((int)$local->format('H') !== 7) {
                continue; // only run during user's 07:00 hour
            }
            $tender = \Tendari\Models\Tender::find((int)$row['tender_id']);
            if ($tender === null) {
                Notification::markSent($row['id']);
                continue;
            }
            $groups[$row['user_id'] . '|' . $row['email']][] = ['row' => $row, 'tender' => $tender];
        }
        $count = 0;
        $mailer = new \Tendari\Notifiers\EmailNotifier();
        foreach ($groups as $items) {
            if ($mailer->sendTenderBatch($items[0]['row']['email'], $items, 'daily digest')) {
                foreach ($items as $item) {
                    Notification::markSent($item['row']['id']);
                    $count++;
                }
            } else {
                foreach ($items as $item) {
                    Notification::markFailed($item['row']['id'], 'SMTP send failed (digest)');
                }
            }
        }
        return $count;
    }

    public static function state(string $key, ?string $default = null): ?string
    {
        if (func_num_args() === 2) {
            Db::q('INSERT INTO app_state (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
                [$key, $default]);
            return $default;
        }
        $v = Db::scalar('SELECT value FROM app_state WHERE key = ?', [$key]);
        return $v === null ? $default : (string)$v;
    }

    private static function tenderArray(array $tender): array
    {
        return $tender; // DB rows already carry the fields MatchEngine reads
    }
}
