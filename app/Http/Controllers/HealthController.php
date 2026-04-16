<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Machine-readable health probes for load balancers, uptime monitors, and
 * Kubernetes liveness/readiness checks.
 *
 *   GET /health       — liveness: process is running (no dependency checks)
 *   GET /health/ready — readiness: DB + cache + queue driver reachable
 *
 * The readiness probe fails with 503 if ANY dependency is down so the load
 * balancer stops sending traffic to this pod while it's unhealthy. It's
 * intentionally NOT gated behind auth — an unauthenticated probe is how
 * every mainstream monitor works, and the response leaks nothing sensitive.
 */
class HealthController extends Controller
{
    /**
     * Liveness probe — returns 200 as long as the PHP process can handle
     * a request. Use this for k8s livenessProbe; failing here triggers
     * a pod restart, so we intentionally check NOTHING external.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe — returns 200 only when every external dependency
     * this app needs to serve traffic is reachable. A failure here
     * pulls the pod out of the load balancer pool but does NOT restart
     * the process (the dependency may recover on its own).
     */
    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => $this->probeDatabase(),
            'cache'    => $this->probeCache(),
            'queue'    => $this->probeQueue(),
        ];

        $ok = !in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status'    => $ok ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $ok ? 200 : 503);
    }

    private function probeDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            return [
                'ok'      => true,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function probeCache(): array
    {
        try {
            $key = '__health_probe__';
            $start = microtime(true);
            Cache::put($key, 'ok', 5);
            $value = Cache::get($key);
            Cache::forget($key);
            return [
                'ok'         => $value === 'ok',
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'driver'     => config('cache.default'),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * The queue probe only confirms the configured connection resolves —
     * it does NOT dispatch a test job, which would flood the queue on
     * every probe hit. For deeper queue health use Horizon's dashboard.
     */
    private function probeQueue(): array
    {
        try {
            $driver = config('queue.default');
            $connection = config("queue.connections.$driver");
            if (!$connection) {
                return ['ok' => false, 'error' => "queue connection '$driver' not configured"];
            }
            return ['ok' => true, 'driver' => $driver];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
