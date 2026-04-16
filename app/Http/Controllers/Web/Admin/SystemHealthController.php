<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $health = [];

        // Database
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $health['db_latency_ms'] = round((microtime(true) - $start) * 1000, 1);
            $health['db_status'] = 'healthy';
        } catch (\Throwable $e) {
            $health['db_latency_ms'] = 0;
            $health['db_status'] = 'down';
        }

        // Cache
        try {
            Cache::put('_health_check', true, 10);
            $health['cache_status'] = Cache::get('_health_check') ? 'healthy' : 'down';
        } catch (\Throwable $e) {
            $health['cache_status'] = 'down';
        }

        // Failed jobs
        try {
            $health['failed_jobs'] = DB::table('failed_jobs')->count();
            $health['failed_jobs_24h'] = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        } catch (\Throwable $e) {
            $health['failed_jobs'] = 0;
            $health['failed_jobs_24h'] = 0;
        }

        // Queue size (pending jobs)
        try {
            $health['pending_jobs'] = DB::table('jobs')->count();
        } catch (\Throwable $e) {
            $health['pending_jobs'] = 0;
        }

        // Disk usage
        $totalDisk = @disk_total_space(base_path());
        $freeDisk = @disk_free_space(base_path());
        $health['disk_total_gb'] = $totalDisk ? round($totalDisk / 1073741824, 1) : 0;
        $health['disk_free_gb'] = $freeDisk ? round($freeDisk / 1073741824, 1) : 0;
        $health['disk_used_pct'] = $totalDisk ? round((1 - $freeDisk / $totalDisk) * 100, 1) : 0;

        // PHP info
        $health['php_version'] = PHP_VERSION;
        $health['laravel_version'] = app()->version();
        $health['memory_limit'] = ini_get('memory_limit');
        $health['memory_usage_mb'] = round(memory_get_usage(true) / 1048576, 1);

        // Storage size (uploads)
        try {
            $health['uploads_count'] = DB::table('uploads')->count();
            $health['uploads_size_mb'] = round((float) DB::table('uploads')->sum('file_size') / 1048576, 1);
        } catch (\Throwable $e) {
            $health['uploads_count'] = 0;
            $health['uploads_size_mb'] = 0;
        }

        // Recent errors in audit log
        try {
            $health['audit_entries_24h'] = DB::table('audit_logs')
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Throwable $e) {
            $health['audit_entries_24h'] = 0;
        }

        // E-invoice queue
        try {
            $health['einvoice_failed'] = DB::table('e_invoice_submissions')
                ->whereIn('status', ['failed', 'rejected'])
                ->count();
        } catch (\Throwable $e) {
            $health['einvoice_failed'] = 0;
        }

        // Overall status
        $issues = 0;
        if ($health['db_status'] !== 'healthy') $issues += 3;
        if ($health['cache_status'] !== 'healthy') $issues += 2;
        if ($health['failed_jobs_24h'] > 10) $issues += 2;
        if ($health['disk_used_pct'] > 90) $issues += 2;
        if ($health['pending_jobs'] > 1000) $issues += 1;

        $health['overall'] = $issues === 0 ? 'healthy' : ($issues <= 2 ? 'warning' : 'critical');

        return view('dashboard.admin.system-health.index', compact('health'));
    }
}
