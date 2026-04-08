<?php

/*
 * ISO 3166-1 alpha-2 country codes used across the trade pipeline.
 *
 * The list is intentionally curated, not the full 249-country ISO set:
 *
 *   - GCC + MENA come first (the platform's primary market — these get
 *     intra-GCC duty-free treatment under the Common Customs Tariff).
 *   - Major exporting partners follow (China, India, Turkey, Germany, ...)
 *     since they account for the vast majority of GCC import lines.
 *   - The rest of the world is omitted to keep the dropdown scannable.
 *     Add codes here as use cases come up; the column is just a 2-char
 *     string so additions are zero-cost.
 *
 * The English names live in this file because they double as fallback
 * labels when the active locale doesn't have a translation in
 * `lang/{locale}.json` under the `country.<CODE>` key.
 */

return [

    'list' => [
        // ===== GCC =====
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'KW' => 'Kuwait',
        'QA' => 'Qatar',
        'BH' => 'Bahrain',
        'OM' => 'Oman',

        // ===== Wider MENA =====
        'EG' => 'Egypt',
        'JO' => 'Jordan',
        'LB' => 'Lebanon',
        'MA' => 'Morocco',
        'TN' => 'Tunisia',
        'IQ' => 'Iraq',
        'YE' => 'Yemen',
        'SD' => 'Sudan',
        'LY' => 'Libya',
        'DZ' => 'Algeria',
        'PS' => 'Palestine',
        'SY' => 'Syria',

        // ===== Major exporters into GCC =====
        'CN' => 'China',
        'IN' => 'India',
        'TR' => 'Turkey',
        'PK' => 'Pakistan',
        'BD' => 'Bangladesh',
        'ID' => 'Indonesia',
        'MY' => 'Malaysia',
        'TH' => 'Thailand',
        'VN' => 'Vietnam',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'TW' => 'Taiwan',
        'SG' => 'Singapore',
        'PH' => 'Philippines',

        // ===== Europe =====
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'GB' => 'United Kingdom',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'SE' => 'Sweden',
        'PL' => 'Poland',

        // ===== Americas =====
        'US' => 'United States',
        'CA' => 'Canada',
        'BR' => 'Brazil',
        'MX' => 'Mexico',

        // ===== Africa =====
        'ZA' => 'South Africa',
        'KE' => 'Kenya',
        'NG' => 'Nigeria',
        'ET' => 'Ethiopia',

        // ===== Oceania =====
        'AU' => 'Australia',
        'NZ' => 'New Zealand',

        // ===== Other =====
        'RU' => 'Russia',
        'IR' => 'Iran',
    ],

];
