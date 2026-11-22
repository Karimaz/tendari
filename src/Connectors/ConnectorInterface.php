<?php
declare(strict_types=1);

namespace Tendari\Connectors;

interface ConnectorInterface
{
    /** Source code, matches sources.code. */
    public function code(): string;

    /** Fetch raw records from the remote source. @return array of raw records */
    public function fetch(): array;

    /** Map one raw record to the canonical normalized shape. @return array|null (null = skip) */
    public function normalize(array $raw): ?array;
}
