<?php
declare(strict_types=1);

namespace Tendari\Notifiers;

/** Telegram Bot API via cURL. */
final class TelegramNotifier implements NotifierInterface
{
    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? (string)config('telegram.bot_token', '');
    }

    public function apiBase(): string
    {
        return 'https://api.telegram.org/bot' . $this->token;
    }

    public function send(string $to, string $subject, string $html, string $textAlt = ''): bool
    {
        return $this->sendMessage($to, $textAlt !== '' ? $textAlt : strip_tags($html));
    }

    public function sendTender(string $chatId, array $tender): bool
    {
        $lines = ['*' . self::escapeMarkdownV2((string)$tender['title']) . '*'];
        if (!empty($tender['organization'])) {
            $lines[] = self::escapeMarkdownV2((string)$tender['organization']);
        }
        if (!empty($tender['deadline_at'])) {
            $lines[] = '⏰ Deadline: ' . self::escapeMarkdownV2(substr((string)$tender['deadline_at'], 0, 10));
        }
        $text = implode("\n", $lines) . "\n[Open](" . self::escapeUrl((string)$tender['source_url']) . ")";
        return $this->sendMessage($chatId, $text, 'MarkdownV2');
    }

    public function sendMessage(string $chatId, string $text, string $parseMode = ''): bool
    {
        if ($this->token === '') {
            return false;
        }
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }
        $resp = $this->post('sendMessage', $payload);
        return is_array($resp) && ($resp['ok'] ?? false) === true;
    }

    /** @return array|null decoded Bot API response */
    public function post(string $method, array $payload): ?array
    {
        if ($this->token === '') {
            return null;
        }
        $ch = curl_init($this->apiBase() . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            logger('error', 'Telegram ' . $method . ' failed: ' . $err);
            return null;
        }
        return json_decode((string)$body, true);
    }

    /** Escape plain text for MarkdownV2. */
    public static function escapeMarkdownV2(string $text): string
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9\p{L}\p{N}\s]/u',
            fn($m) => '\\' . $m[0],
            $text
        ) ?? $text;
    }

    /** Escape a URL for MarkdownV2 links: ')' and '\' must be escaped. */
    public static function escapeUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }
}
