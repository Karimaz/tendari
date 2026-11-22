<?php
/**
 * Application bootstrap: autoloader + helpers. Include this in every entry point.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

// PHPMailer: prefer Composer autoload if present, otherwise the vendored copy in lib/.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    foreach (['Exception', 'PHPMailer', 'SMTP'] as $pf) {
        require_once __DIR__ . '/../lib/phpmailer/' . $pf . '.php';
    }
}
require __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Tendari\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Tendari\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

\Tendari\Core\Config::load();
\Tendari\Core\I18n::boot();
