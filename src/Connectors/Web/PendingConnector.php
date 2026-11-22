<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Connectors\AbstractConnector;

/** Stub for open-access sources whose HTML scraper is pending (v1.1). */
final class PendingConnector extends AbstractConnector
{
    public function fetch(): array
    {
        logger('notice', 'Scraper for "' . $this->codeName . '" is pending implementation (v1.1)');
        return [];
    }

    public function normalize(array $raw): ?array
    {
        return null;
    }
}
