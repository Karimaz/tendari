<?php
declare(strict_types=1);

namespace Tendari\Notifiers;

interface NotifierInterface
{
    /** @return bool success */
    public function send(string $to, string $subject, string $html, string $textAlt = ''): bool;
}
