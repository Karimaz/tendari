<?php
declare(strict_types=1);

namespace Tendari\Notifiers;

use PHPMailer\PHPMailer\PHPMailer;

final class EmailNotifier implements NotifierInterface
{
    private PHPMailer $mailer;

    public function __construct(?PHPMailer $mailer = null)
    {
        $this->mailer = $mailer ?? self::buildMailer();
    }

    public static function buildMailer(): PHPMailer
    {
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host = (string)config('smtp.host');
        $m->Port = (int)config('smtp.port', 587);
        $m->SMTPAuth = (string)config('smtp.user') !== '';
        if ($m->SMTPAuth) {
            $m->Username = (string)config('smtp.user');
            $m->Password = (string)config('smtp.pass');
        }
        $secure = (string)config('smtp.encryption', 'tls');
        if ($secure === 'tls') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $m->CharSet = 'UTF-8';
        $m->setFrom((string)config('smtp.from', 'noreply@tendari.org'), 'Tendari');
        $m->isHTML(true);
        return $m;
    }

    public function send(string $to, string $subject, string $html, string $textAlt = ''): bool
    {
        if ((string)config('smtp.host') === '') {
            logger('warning', 'SMTP not configured; dropping email to ' . $to);
            return false;
        }
        try {
            $m = clone $this->mailer;
            $m->clearAddresses();
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->Body = $html;
            $m->AltBody = $textAlt !== '' ? $textAlt : self::toText($html);
            return $m->send();
        } catch (\Throwable $e) {
            logger('error', 'Email send failed to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch: subject "🎯 {n} new tenders match "{name}""; max 20 tenders per mail, rest linked.
     * $items = [['row' => notificationRow, 'tender' => tenderRow], ...]
     */
    public function sendTenderBatch(string $to, array $items, string $modeLabel = 'instant'): bool
    {
        $n = count($items);
        $first = $items[0]['tender'];
        $subject = '🎯 ' . $n . ' new tenders match your search';
        $max = 20;
        $html = '<div style="font-family:sans-serif;max-width:640px;margin:0 auto;">';
        $html .= '<h2 style="color:#005bac;">🎯 ' . $n . ' new tender' . ($n === 1 ? '' : 's') . ' for you</h2>';
        $shown = 0;
        $text = '';
        foreach ($items as $i => $item) {
            $t = $item['tender'];
            if ($i >= $max) {
                continue;
            }
            $shown++;
            $html .= '<div style="border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin:10px 0;">'
                . '<div style="font-weight:bold;font-size:15px;">' . e($t['title']) . '</div>'
                . '<div style="color:#666;font-size:13px;margin:4px 0;">' . e($t['organization'])
                . ' · ' . e($t['source_name'] ?? '')
                . ($t['deadline_at'] ? ' · Deadline: ' . e($t['deadline_at']) : '')
                . '</div>'
                . '<a href="' . e($t['source_url']) . '" style="color:#005bac;">Open tender →</a>'
                . '</div>';
            $text .= '* ' . $t['title'] . "\n  " . $t['organization'] . ($t['deadline_at'] ? ' · deadline ' . $t['deadline_at'] : '') . "\n  " . $t['source_url'] . "\n\n";
        }
        if ($n > $max) {
            $html .= '<p>… and ' . ($n - $max) . ' more. <a href="' . e(url('/dashboard')) . '">See all on your dashboard</a>.</p>';
            $text .= '… and ' . ($n - $max) . " more: " . url('/dashboard') . "\n";
        }
        $html .= '<p style="color:#999;font-size:12px;">Sent by Tendari · ' . e($modeLabel) . ' · ' . e(url('/settings')) . '</p></div>';
        return $this->send($to, $subject, $html, $text);
    }

    public function sendSimple(string $to, string $subject, string $html): bool
    {
        return $this->send($to, $subject, $html);
    }

    private static function toText(string $html): string
    {
        return trim(preg_replace('/[ \t]+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) ?? '');
    }
}
