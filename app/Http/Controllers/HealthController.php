<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Health and readiness probes.
 *
 *  - `GET /api/health`    — shallow liveness: is the PHP process alive.
 *                           Called by load balancers every few seconds.
 *                           Must NOT touch external dependencies so a
 *                           transient DB/Redis blip doesn't take the app
 *                           out of rotation while it could still serve
 *                           cached reads.
 *
 *  - `GET /api/health/ready` — deep readiness: can this instance serve
 *                           real traffic right now. Touches DB, Redis,
 *                           and the object-storage disk. Returns 503 on
 *                           any failure so Kubernetes / ECS keeps pulling
 *                           the instance from the routing pool.
 */
class HealthController extends Controller
{
    public function shallow(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'version' => config('app.version', 'dev'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'db' => $this->check(fn () => DB::select('select 1 as ok')),
            'cache' => $this->check(fn () => Cache::store()->put('health:ready', '1', 5) && Cache::store()->get('health:ready') === '1'),
            'storage' => $this->check(fn () => is_writable(storage_path())),
        ];

        if (config('database.redis.default.host')) {
            $checks['redis'] = $this->check(fn () => Redis::ping() !== null);
        }

        $allHealthy = collect($checks)->every(fn ($c) => $c['healthy']);
        $status = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $allHealthy ? 'ready' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }

    /**
     * @param  \Closure():mixed  $probe
     * @return array{healthy: bool, latency_ms?: int, error?: string}
     */
    private function check(\Closure $probe): array
    {
        $start = microtime(true);
        try {
            $probe();

            return [
                'healthy' => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'error' => class_basename($e).': '.$e->getMessage(),
            ];
        }
    }
}
