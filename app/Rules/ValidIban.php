<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an IBAN (International Bank Account Number) per ISO 13616.
 *
 * Performs:
 * 1. Format check (2-letter country + 2 check digits + up to 30 alphanumeric)
 * 2. Length check per country (UAE = 23 chars)
 * 3. Mod-97 check digit verification
 */
class ValidIban implements ValidationRule
{
    /** IBAN lengths by country code (ISO 3166-1 alpha-2). */
    private const LENGTHS = [
        'AE' => 23, 'SA' => 24, 'BH' => 22, 'QA' => 29, 'KW' => 30,
        'OM' => 23, 'JO' => 30, 'LB' => 28, 'EG' => 29,
        'GB' => 22, 'DE' => 22, 'FR' => 27, 'IT' => 27, 'ES' => 24,
        'NL' => 18, 'BE' => 16, 'AT' => 20, 'CH' => 21,
        'US' => 0, // US does not use IBAN
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) $value));

        // Basic format
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{4,30}$/', $iban)) {
            $fail(__('validation.iban_format'));

            return;
        }

        $country = substr($iban, 0, 2);

        // Country-specific length check
        if (isset(self::LENGTHS[$country]) && self::LENGTHS[$country] > 0) {
            if (strlen($iban) !== self::LENGTHS[$country]) {
                $fail(__('validation.iban_length', ['country' => $country, 'length' => self::LENGTHS[$country]]));

                return;
            }
        }

        // UAE-specific: must start with AE followed by 2 digits and 19 digits
        if ($country === 'AE' && ! preg_match('/^AE\d{21}$/', $iban)) {
            $fail(__('validation.iban_uae'));

            return;
        }

        // Mod-97 check
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (ord($char) - 55) : $char;
        }

        if (bcmod($numeric, '97') !== '1') {
            $fail(__('validation.iban_checksum'));
        }
    }
}
