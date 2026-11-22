<?php
declare(strict_types=1);

namespace Tendari\Core;

/**
 * Minimal i18n: locale cookie, per-locale PHP string maps, RTL direction.
 * Missing keys fall back to English, then to the key itself.
 */
final class I18n
{
    public const SUPPORTED = ['en', 'ar'];
    private static string $locale = 'en';
    /** @var array<string,string> */
    private static array $strings = [];
    /** @var array<string,string> */
    private static array $fallback = [];

    public static function boot(): void
    {
        $requested = (string)($_COOKIE['td_lang'] ?? $_GET['lang'] ?? '');
        self::set(in_array($requested, self::SUPPORTED, true) ? $requested : 'en');
    }

    public static function set(string $locale): void
    {
        if (!in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }
        self::$locale = $locale;
        self::$fallback = self::load('en');
        self::$strings = $locale === 'en' ? self::$fallback : self::load($locale);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function dir(): string
    {
        return self::$locale === 'ar' ? 'rtl' : 'ltr';
    }

    public static function isRtl(): bool
    {
        return self::dir() === 'rtl';
    }

    /** @param array<int,scalar> $params sprintf arguments */
    public static function t(string $key, array $params = [], ?string $default = null): string
    {
        $text = self::$strings[$key] ?? self::$fallback[$key] ?? $default ?? $key;
        if ($params !== []) {
            $text = @vsprintf($text, $params);
        }
        return $text;
    }

    /** @return array<string,string> */
    private static function load(string $locale): array
    {
        $file = dirname(__DIR__, 2) . '/lang/' . $locale . '.php';
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }
}
