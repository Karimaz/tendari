<?php
declare(strict_types=1);

namespace Tendari\Connectors;

use Tendari\Core\Db;
use Tendari\Services\AwardService;
use Tendari\Services\Deduper;

/**
 * Runs enabled (or forced) sources through their connectors and ingests results.
 */
final class FetchRunner
{
    /** @return array<array{code:string,status:string,fetched:int,new:int,updated:int,awards:int,error:?string}> */
    public static function run(array $sources): array
    {
        $summary = [];
        foreach ($sources as $source) {
            $code = (string)$source['code'];
            $class = (string)$source['connector_class'];
            $config = [];
            if (!empty($source['config'])) {
                $config = json_decode((string)$source['config'], true) ?: [];
            }
            try {
                if (!class_exists($class)) {
                    throw new \RuntimeException('Connector class not found: ' . $class);
                }
                $connector = new $class($config, $code);
                if (!$connector instanceof ConnectorInterface) {
                    throw new \RuntimeException($class . ' does not implement ConnectorInterface');
                }
                $raw = $connector->fetch();
                $stats = ['fetched' => 0, 'new' => 0, 'updated' => 0, 'dups' => 0, 'awards' => 0];
                foreach ($raw as $record) {
                    $stats['fetched']++;
                    $norm = $connector->normalize($record);
                    if ($norm === null) {
                        continue;
                    }
                    [$tenderId, $result] = Deduper::ingest((int)$source['id'], $norm);
                    if (!empty($norm['documents']) && is_array($norm['documents'])) {
                        \Tendari\Services\DocumentFetcher::queue((int)$tenderId, $norm['documents']);
                    }
                    $awardRecorded = false;
                    if ($connector instanceof AwardsConnectorInterface) {
                        $award = $connector->normalizeAward($record);
                        if ($award !== null) {
                            $award['tender_id'] = (int)$tenderId;
                            $awardRecorded = AwardService::record((int)$source['id'], $award) !== null;
                            if ($awardRecorded) {
                                $stats['awards']++;
                            }
                        }
                    }
                    if (!$awardRecorded && AwardService::recordFromTender((int)$source['id'], $norm, (int)$tenderId) !== null) {
                        $stats['awards']++;
                    }
                    if ($result === 'new') {
                        $stats['new']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    }
                }
                $status = $stats['fetched'] === 0 ? 'empty' : 'ok';
                \Tendari\Models\Source::setStatus((int)$source['id'], $status);
                logger('info', "Fetch {$code}: {$status}", $stats);
                $summary[] = array_merge(['code' => $code, 'status' => $status, 'error' => null], $stats);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                \Tendari\Models\Source::setStatus((int)$source['id'], 'error', mb_substr($msg, 0, 500));
                logger('error', "Fetch {$code} failed: " . $msg);
                $summary[] = ['code' => $code, 'status' => 'error', 'error' => $msg,
                              'fetched' => 0, 'new' => 0, 'updated' => 0, 'awards' => 0];
            }
        }
        return $summary;
    }
}
