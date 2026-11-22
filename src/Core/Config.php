<?php
declare(strict_types=1);

namespace Tendari\Core;

final class Config
{
    private static ?array $data = null;

    public static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $file = dirname(__DIR__, 2) . '/config/config.php';
        self::$data = is_file($file) ? (array)require $file : [];
        return self::$data;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);
        $val = self::load();
        foreach ($parts as $p) {
            if (!is_array($val) || !array_key_exists($p, $val)) {
                return $default;
            }
            $val = $val[$p];
        }
        return $val;
    }

    public static function set(array $data): void
    {
        self::$data = $data;
    }
}
