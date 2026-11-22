<?php
/** Shared option lists for filter + search-form templates. Pure functions only. */
declare(strict_types=1);

function th_country_options(): array
{
    return [
        'DZ' => 'Algeria', 'BH' => 'Bahrain', 'BJ' => 'Benin', 'BF' => 'Burkina Faso', 'CM' => 'Cameroon',
        'CG' => 'Congo', 'CD' => 'DR Congo', 'DJ' => 'Djibouti', 'EG' => 'Egypt', 'ET' => 'Ethiopia',
        'GA' => 'Gabon', 'GM' => 'Gambia', 'GH' => 'Ghana', 'GN' => 'Guinea', 'IQ' => 'Iraq',
        'JO' => 'Jordan', 'KE' => 'Kenya', 'KW' => 'Kuwait', 'LB' => 'Lebanon', 'LR' => 'Liberia',
        'LY' => 'Libya', 'MA' => 'Morocco', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MZ' => 'Mozambique',
        'MW' => 'Malawi', 'NA' => 'Namibia', 'NE' => 'Niger', 'NG' => 'Nigeria', 'OM' => 'Oman',
        'PS' => 'Palestine', 'QA' => 'Qatar', 'RW' => 'Rwanda', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
        'SL' => 'Sierra Leone', 'SO' => 'Somalia', 'SS' => 'South Sudan', 'ZA' => 'South Africa',
        'SZ' => 'Eswatini', 'TD' => 'Chad', 'TG' => 'Togo', 'TN' => 'Tunisia', 'TR' => 'Türkiye',
        'TZ' => 'Tanzania', 'UG' => 'Uganda', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    ];
}

function th_sector_options(): array
{
    return ['health', 'water', 'education', 'energy', 'transport', 'agriculture',
            'ict', 'governance', 'construction', 'environment', 'logistics', 'finance'];
}
