<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Connectors\AbstractConnector;
use Tendari\Connectors\RegistrationRequiredException;

/**
 * Stub for sources that require credentials/cookies (Etimad, JONEPS, IsDB, TANePS…).
 * Enable later by storing them in sources.config.
 */
final class RegistrationConnector extends AbstractConnector
{
    public function fetch(): array
    {
        throw new RegistrationRequiredException(
            'Source "' . $this->codeName . '" requires registration/credentials. ' .
            'Store cookies or a token in sources.config to enable it.'
        );
    }

    public function normalize(array $raw): ?array
    {
        return null;
    }
}
