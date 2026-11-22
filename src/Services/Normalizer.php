<?php
declare(strict_types=1);

namespace Tendari\Services;

/**
 * Parses messy input into canonical values (dates, countries, funding types, money).
 * Never guesses: unparseable input becomes null.
 */
final class Normalizer
{
    private const DATE_FORMATS = ['Y-m-d|', 'd/m/Y|', 'd.m.Y|', 'F j, Y|', 'Y-m-d H:i:s', 'd/m/Y H:i', 'd.m.Y H:i', 'j F Y|', 'Y/m/d|', 'Ymd|'];

    /** Parse a date string to UTC 'Y-m-d H:i:s'; null if unparseable. */
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '' || !is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        // Unix timestamp
        if (ctype_digit($value) && strlen($value) >= 9) {
            return gmdate('Y-m-d H:i:s', (int)$value);
        }
        foreach (self::DATE_FORMATS as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $value, new \DateTimeZone('UTC'));
            if ($dt !== false) {
                $errs = \DateTime::getLastErrors();
                if (is_array($errs) && ($errs['warning_count'] || $errs['error_count'])) {
                    continue;
                }
                if ($dt->format('Y') < 1990 || $dt->format('Y') > 2100) {
                    continue;
                }
                return $dt->format('Y-m-d H:i:s');
            }
        }
        // ISO-ish strings with T/Z
        $ts = strtotime($value);
        if ($ts !== false) {
            $dt = gmdate('Y-m-d H:i:s', $ts);
            if (strpos($dt, '1970') === false || $ts > 100000000) {
                return $dt;
            }
        }
        return null;
    }

    /** Country names / codes -> ISO2 upper-case list. */
    public static function countries(mixed $value): array
    {
        $raw = [];
        if (is_array($value)) {
            $raw = $value;
        } elseif (is_string($value)) {
            $raw = preg_split('/[,;]+/', $value) ?: [];
        }
        $out = [];
        foreach ($raw as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $probe = mb_strtolower($item);
            $code = null;
            if (preg_match('/^[A-Za-z]{2}$/', $item)) {
                $code = strtoupper($item);
                if (!in_array($code, Countries::CODES, true)) {
                    $code = null;
                }
            } elseif (isset(Countries::MAP[$probe])) {
                $code = Countries::MAP[$probe];
            } elseif (preg_match('/[A-Za-z]{3,}/', $item, $m) && isset(Countries::MAP[mb_strtolower($m[0])])) {
                $code = Countries::MAP[mb_strtolower($m[0])];
            }
            if ($code !== null && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    public static function fundingType(mixed $value): string
    {
        $v = strtolower(trim((string)$value));
        return match (true) {
            $v === '' => 'unknown',
            str_contains($v, 'procure') || str_contains($v, 'tender') || str_contains($v, 'rfq') || str_contains($v, 'rfp') || str_contains($v, 'quotation') || str_contains($v, 'bid') || str_contains($v, 'supply') || str_contains($v, 'works') || str_contains($v, 'goods') => 'procurement',
            str_contains($v, 'grant') || str_contains($v, 'pledge') || str_contains($v, 'contribution') => 'grant',
            str_contains($v, 'loan') || str_contains($v, 'credit') || str_contains($v, 'financing') => 'loan',
            str_contains($v, 'framework') || str_contains($v, 'consult') || str_contains($v, 'expr') || str_contains($v, 'eoi') || str_contains($v, 'selection') => 'framework',
            default => 'unknown',
        };
    }

    public static function status(string $publishedAt, ?string $deadlineAt): string
    {
        if ($deadlineAt !== null && $publishedAt !== null) {
            if (strtotime($deadlineAt) < strtotime($publishedAt)) {
                return 'closed';
            }
        }
        if ($deadlineAt !== null && strtotime($deadlineAt) < time()) {
            return 'closed';
        }
        return 'open';
    }

    /** Lowercase, strip punctuation, collapse spaces. */
    public static function textKey(string $text): string
    {
        $t = mb_strtolower(trim($text));
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? '';
        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }

    public static function title(string $title): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if (mb_strlen($t) > 30) {
            $t = ucfirst(mb_strtolower(mb_substr($t, 0, 1))) . mb_substr($t, 1);
        }
        return $t;
    }

    /** "EUR 1.2 million" / "$150,000" style strings -> [amount, currency] */
    public static function money(mixed $raw): array
    {
        if (is_numeric($raw)) {
            return [(float)$raw, null];
        }
        $s = trim((string)$raw);
        if ($s === '') {
            return [null, null];
        }
        $currency = null;
        if (preg_match('/(EUR|USD|GBP|CHF|AED|SAR|KES|NGN|ZAR|TZS|UGX|RWF|GHS|MAD|DZD|BHD|JOD|JPY|CNY|AUD|CAD|XOF|XAF)\b/i', $s, $m)) {
            $currency = strtoupper($m[1]);
        } elseif (str_contains($s, '€')) {
            $currency = 'EUR';
        } elseif (str_contains($s, '$')) {
            $currency = 'USD';
        } elseif (str_contains($s, '£')) {
            $currency = 'GBP';
        }
        $mult = 1.0;
        if (preg_match('/\b(million|mln|m)\b/i', $s)) {
            $mult = 1_000_000;
        } elseif (preg_match('/\b(billion|bln|b)\b/i', $s)) {
            $mult = 1_000_000_000;
        } elseif (preg_match('/\b(thousand|k)\b/i', $s)) {
            $mult = 1_000;
        }
        if (preg_match('/([\d.,]+)\s*(million|billion|billion|mln|bln|k|thousand)?/i', $s, $m)) {
            $num = str_replace(',', '', $m[1]);
            if (is_numeric($num)) {
                return [(float)$num * $mult, $currency];
            }
        }
        return [null, $currency];
    }

    /** Strip HTML, collapse whitespace, cap length. */
    public static function cleanText(string $html, int $maxLen = 20000): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';
        $text = trim($text);
        return mb_substr($text, 0, $maxLen);
    }
}

/** ISO2 map for common countries in our coverage area (extensible). */
class Countries
{
    public const MAP = [
        'kenya' => 'KE', 'uganda' => 'UG', 'rwanda' => 'RW', 'tanzania' => 'TZ',
        'nigeria' => 'NG', 'ghana' => 'GH', 'ethiopia' => 'ET', 'somalia' => 'SO',
        'south africa' => 'ZA', 'zambia' => 'ZM', 'zimbabwe' => 'ZW', 'mozambique' => 'MZ',
        'malawi' => 'MW', 'senegal' => 'SN', 'mali' => 'ML', 'burkina faso' => 'BF',
        'niger' => 'NE', 'chad' => 'TD', 'cameroon' => 'CM', 'ivory coast' => 'CI',
        "cote d'ivoire" => 'CI', 'gabon' => 'GA', 'congo' => 'CG', 'dr congo' => 'CD',
        'democratic republic of the congo' => 'CD', 'morocco' => 'MA', 'algeria' => 'DZ',
        'tunisia' => 'TN', 'libya' => 'LY', 'egypt' => 'EG', 'sudan' => 'SD',
        'south sudan' => 'SS', 'eritrea' => 'ER', 'djibouti' => 'DJ', 'benin' => 'BJ',
        'togo' => 'TG', 'sierra leone' => 'SL', 'liberia' => 'LR', 'guinea' => 'GN',
        'mauritania' => 'MR', 'mauritius' => 'MU', 'madagascar' => 'MG', 'botswana' => 'BW',
        'namibia' => 'NA', 'lesotho' => 'LS', 'eswatini' => 'SZ', 'swaziland' => 'SZ',
        'angola' => 'AO', 'burundi' => 'BI', 'comoros' => 'KM', 'gambia' => 'GM',
        'saudi arabia' => 'SA', 'bahrain' => 'BH', 'qatar' => 'QA', 'kuwait' => 'KW',
        'united arab emirates' => 'AE', 'oman' => 'OM', 'yemen' => 'YE',
        'jordan' => 'JO', 'lebanon' => 'LB', 'iraq' => 'IQ', 'iran' => 'IR',
        'israel' => 'IL', 'palestine' => 'PS', 'west bank and gaza' => 'PS',
        'syria' => 'SY', 'turkey' => 'TR', 'türkiye' => 'TR',
        'afghanistan' => 'AF', 'pakistan' => 'PK', 'bangladesh' => 'BD',
        'india' => 'IN', 'sri lanka' => 'LK', 'nepal' => 'NP',
        'uzbekistan' => 'UZ', 'kazakhstan' => 'KZ', 'kyrgyzstan' => 'KG',
        'tajikistan' => 'TJ', 'turkmenistan' => 'TM', 'azerbaijan' => 'AZ',
        'armenia' => 'AM', 'georgia' => 'GE', 'moldova' => 'MD', 'ukraine' => 'UA',
        'belarus' => 'BY', 'malaysia' => 'MY', 'indonesia' => 'ID', 'philippines' => 'PH',
        'vietnam' => 'VN', 'thailand' => 'TH', 'myanmar' => 'MM', 'cambodia' => 'KH',
        'lao people s democratic republic' => 'LA', 'laos' => 'LA', 'mongolia' => 'MN',
        'china' => 'CN', 'japan' => 'JP', 'korea' => 'KR', 'republic of korea' => 'KR',
        'united states' => 'US', 'united kingdom' => 'UK', 'france' => 'FR',
        'germany' => 'DE', 'netherlands' => 'NL', 'belgium' => 'BE', 'spain' => 'ES',
        'italy' => 'IT', 'switzerland' => 'CH', 'austria' => 'AT', 'sweden' => 'SE',
        'norway' => 'NO', 'denmark' => 'DK', 'finland' => 'FI', 'ireland' => 'IE',
        'portugal' => 'PT', 'poland' => 'PL', 'romania' => 'RO', 'canada' => 'CA',
        'australia' => 'AU', 'new zealand' => 'NZ', 'brazil' => 'BR', 'mexico' => 'MX',
        'argentina' => 'AR', 'chile' => 'CL', 'colombia' => 'CO', 'peru' => 'PE',
    ];

    /** All valid ISO2 codes accepted by Normalizer::countries(). */
    public const CODES = [
        'DZ','AD','AO','AG','AR','AM','AU','AT','AZ','BS','BH','BD','BB','BY','BE','BZ','BJ','BT','BO','BA',
        'BW','BR','BN','BG','BF','BI','KH','CM','CA','CV','CF','TD','CL','CN','CO','KM','CG','CD','CR','CI',
        'HR','CU','CY','CZ','DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','SZ','ET','FJ','FI','FR','GA',
        'GM','GE','DE','GH','GR','GD','GT','GN','GW','GY','HT','HN','HU','IS','IN','ID','IR','IQ','IE','IL',
        'IT','JM','JP','JO','KZ','KE','KI','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU','MG','MW',
        'MY','MV','ML','MT','MH','MR','MU','MX','FM','MD','MC','MN','ME','MA','MZ','MM','NA','NR','NP','NL',
        'NZ','NI','NE','NG','KP','MK','NO','OM','PK','PW','PS','PA','PG','PY','PE','PH','PL','PT','QA','RO',
        'RU','RW','KN','LC','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SK','SI','SB','SO','ZA','KR',
        'SS','ES','LK','SD','SR','SE','CH','SY','TJ','TZ','TH','TL','TG','TO','TT','TN','TR','TM','TV','UG',
        'UA','AE','GB','US','UY','UZ','VU','VA','VE','VN','YE','ZM','ZW',
    ];
}
