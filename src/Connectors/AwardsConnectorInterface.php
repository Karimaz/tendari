<?php
declare(strict_types=1);

namespace Tendari\Connectors;

/**
 * Optional connector capability: return richer award records (winner, amount,
 * award date) instead of only deriving awards from notices marked "awarded".
 */
interface AwardsConnectorInterface
{
    /** Map one raw record to an award shape, or null when it is not an award. */
    public function normalizeAward(array $raw): ?array;
}
