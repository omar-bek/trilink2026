<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Minimal RFC 6238 TOTP (Time-based One-Time Password) service.
 *
 * No external dependencies: uses PHP's built-in hash_hmac() and a tiny
 * base32 encoder. Compatible with Google Authenticator, Authy, 1Password,
 * Microsoft Authenticator, etc. The generated provisioning URI follows
 * the Key URI Format spec so any TOTP app can import it by QR scan.
 *
 * Constants below match the defaults most authenticator apps expect:
 *   algorithm: SHA1
 *   digits: 6
 *   period: 30s
 *
 * Use the window parameter of verify() to tolerate small clock drift.
 */
class TwoFactorService
{
    private const DIGITS = 6;

    private const PERIOD = 30;

    private const ALGORITHM = 'sha1';

    /**
     * Generate a new cryptographically random base32 secret (160 bits).
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Compute the 6-digit OTP for a given secret at the given Unix time
     * (defaults to current wall clock). Used for display during setup and
     * when comparing user input.
     */
    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);

        $binary = $this->base32Decode($secret);
        $counterBytes = pack('N*', 0).pack('N*', $counter);

        $hash = hash_hmac(self::ALGORITHM, $counterBytes, $binary, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = (ord($hash[$offset]) & 0x7F) << 24
            | (ord($hash[$offset + 1]) & 0xFF) << 16
            | (ord($hash[$offset + 2]) & 0xFF) << 8
            | (ord($hash[$offset + 3]) & 0xFF);

        $code = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-supplied 6-digit code against the secret.
     *
     * @param  int  $window  how many 30s steps before/after "now" to accept,
     *                       to tolerate clock drift between phone and server
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->codeAt($secret, $now + ($i * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the otpauth:// provisioning URI that authenticator apps import
     * via QR scan. Follows the Key URI Format spec:
     *   otpauth://totp/Issuer:accountName?secret=XXX&issuer=Issuer
     */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountName);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Generate a fresh set of one-time recovery codes. Each is 10 chars
     * split with a dash for readability: "abcde-12345". We store these
     * hashed and consume one on each use.
     *
     * @return array<int,string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5)))
            ->all();
    }

    // ------------------------------------------------------------------
    // Base32 (RFC 4648) encode/decode — used for the secret. Authenticator
    // apps expect base32 because base64 padding and alphabet cause issues
    // with manual entry.
    // ------------------------------------------------------------------

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $output .= $alphabet[($buffer >> ($bitsLeft - 5)) & 0x1F];
                $bitsLeft -= 5;
            }
        }
        if ($bitsLeft > 0) {
            $output .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($encoded); $i < $len; $i++) {
            $index = strpos($alphabet, $encoded[$i]);
            if ($index === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $index;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
