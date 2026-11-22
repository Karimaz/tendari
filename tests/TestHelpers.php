<?php
/** Shared test helpers. */
declare(strict_types=1);

if (!function_exists('str_contains')) {
    // PHP 8+ has it; keep a polyfill guard for older runtimes anyway.
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
