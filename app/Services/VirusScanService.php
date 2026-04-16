<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Virus scanning service for uploaded files.
 *
 * Supports multiple backends via VIRUS_SCAN_DRIVER env var:
 * - clamav: ClamAV daemon (clamd) via socket or TCP
 * - virustotal: VirusTotal API (v3)
 * - none: skip scanning (development only)
 *
 * Usage:
 *   $result = app(VirusScanService::class)->scan($uploadedFile);
 *   if (! $result['clean']) { abort(422, 'Infected file'); }
 */
class VirusScanService
{
    /**
     * Scan a file for malware.
     *
     * @return array{clean: bool, engine: string, details: string|null}
     */
    public function scan(UploadedFile|string $file): array
    {
        $driver = config('services.virus_scan.driver', 'none');

        return match ($driver) {
            'clamav'     => $this->scanWithClamAv($file),
            'virustotal' => $this->scanWithVirusTotal($file),
            default      => ['clean' => true, 'engine' => 'none', 'details' => 'Scanning disabled'],
        };
    }

    /** Scan via ClamAV daemon (clamd) TCP or Unix socket. */
    private function scanWithClamAv(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $host = config('services.virus_scan.clamav_host', '127.0.0.1');
        $port = (int) config('services.virus_scan.clamav_port', 3310);

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);

            if (! $socket) {
                Log::warning('ClamAV unreachable', ['error' => $errstr]);
                // Fail-open in development, fail-closed in production
                return [
                    'clean'   => app()->isLocal(),
                    'engine'  => 'clamav',
                    'details' => "Connection failed: {$errstr}",
                ];
            }

            $fileContent = file_get_contents($path);
            $size = strlen($fileContent);

            // INSTREAM protocol: send chunk size (4 bytes big-endian) + data, end with zero-length chunk
            fwrite($socket, "zINSTREAM\0");
            fwrite($socket, pack('N', $size));
            fwrite($socket, $fileContent);
            fwrite($socket, pack('N', 0));

            $response = trim(fgets($socket, 4096));
            fclose($socket);

            $clean = str_contains($response, 'OK') && ! str_contains($response, 'FOUND');

            if (! $clean) {
                Log::alert('Virus detected', ['file' => basename($path), 'response' => $response]);
            }

            return [
                'clean'   => $clean,
                'engine'  => 'clamav',
                'details' => $response,
            ];
        } catch (\Throwable $e) {
            Log::error('ClamAV scan error', ['error' => $e->getMessage()]);
            return ['clean' => app()->isLocal(), 'engine' => 'clamav', 'details' => $e->getMessage()];
        }
    }

    /** Scan via VirusTotal API v3. */
    private function scanWithVirusTotal(UploadedFile|string $file): array
    {
        $apiKey = config('services.virus_scan.virustotal_key');

        if (! $apiKey) {
            Log::warning('VirusTotal API key not configured');
            return ['clean' => app()->isLocal(), 'engine' => 'virustotal', 'details' => 'API key missing'];
        }

        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        try {
            // Upload file
            $ch = curl_init('https://www.virustotal.com/api/v3/files');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["x-apikey: {$apiKey}"],
                CURLOPT_POSTFIELDS     => ['file' => new \CURLFile($path)],
                CURLOPT_TIMEOUT        => 60,
            ]);
            $response = json_decode(curl_exec($ch), true);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || ! isset($response['data']['id'])) {
                return ['clean' => false, 'engine' => 'virustotal', 'details' => 'Upload failed: HTTP ' . $httpCode];
            }

            // For async scanning, we just log the analysis ID
            // The result should be polled via a queued job
            Log::info('VirusTotal scan submitted', ['analysis_id' => $response['data']['id']]);

            return [
                'clean'   => true, // Optimistic — actual result arrives async
                'engine'  => 'virustotal',
                'details' => 'Scan submitted: ' . $response['data']['id'],
            ];
        } catch (\Throwable $e) {
            Log::error('VirusTotal scan error', ['error' => $e->getMessage()]);
            return ['clean' => false, 'engine' => 'virustotal', 'details' => $e->getMessage()];
        }
    }
}
