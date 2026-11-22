<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Models\Source;

/** Seed / refresh the v1 source catalog (idempotent, INSERT OR IGNORE). */
final class SourceCatalog
{
    private const CATALOG = [
        ['worldbank', 'World Bank Procurement', 'global', 'Tendari\Connectors\Api\WorldBank', 360],
        ['reliefweb', 'ReliefWeb', 'global', 'Tendari\Connectors\Api\ReliefWeb', 360],
        ['fts', 'OCHA FTS (grants & flows)', 'global', 'Tendari\Connectors\Api\OchaFts', 720],
        ['ted', 'TED (EU notices, Africa/MENA)', 'global', 'Tendari\Connectors\Api\Ted', 720],
        ['afdb', 'African Development Bank', 'africa', 'Tendari\Connectors\Web\Afdb', 720],
        ['ebrd', 'EBRD Procurement', 'mena', 'Tendari\Connectors\Web\Ebrd', 720],
        ['adb', 'Asian Development Bank', 'global', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['giz', 'GIZ Procurement', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['afd', 'AFD Appels d\'offres', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['isdb', 'Islamic Development Bank', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 1440],
        ['sa_etimad', 'Saudi Etimad', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 720],
        ['sa_aramco', 'Saudi Aramco (e-Marketplace)', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 1440],
        ['ae_adgpg', 'Abu Dhabi Procurement Gate', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 720],
        ['ae_dubai_esupply', 'Dubai eSupply', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 720],
        ['ae_federal', 'UAE Federal Supplier Register', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 1440],
        ['qa_monaqasat', 'Qatar Monaqasat', 'mena', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['bh_tenderboard', 'Bahrain Tender Board', 'mena', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['jo_joneps', 'Jordan JONEPS', 'mena', 'Tendari\Connectors\Web\RegistrationConnector', 720],
        ['ke_tenders', 'Kenya tenders.go.ke', 'africa', 'Tendari\Connectors\Web\KeTenders', 360],
        ['za_etenders', 'South Africa eTenders', 'africa', 'Tendari\Connectors\Web\ZaEtenders', 360],
        ['ng_nocopo', 'Nigeria NOCOPO', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['gh_ghaneps', 'Ghana GHANEPS / PPA', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['tz_taneps', 'Tanzania NeST (ex-TANePS)', 'africa', 'Tendari\Connectors\Web\RegistrationConnector', 720],
        ['rw_umucyo', 'Rwanda Umucyo', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['ug_ppda', 'Uganda PPDA', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['ma_pmp', 'Morocco Marchés Publics', 'mena', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['et_egp', 'Ethiopia e-GP', 'africa', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['eg_etenders', 'Egypt eTenders', 'mena', 'Tendari\Connectors\Web\PendingConnector', 720],
        ['dz_marches', 'Algeria marchespublics', 'mena', 'Tendari\Connectors\Web\PendingConnector', 720],
    ];

    public static function seed(): void
    {
        foreach (self::CATALOG as [$code, $name, $region, $class, $interval]) {
            Source::insert([
                'code' => $code, 'name' => $name, 'region' => $region,
                'connector_class' => $class, 'fetch_interval_min' => $interval,
            ]);
        }
    }
}
