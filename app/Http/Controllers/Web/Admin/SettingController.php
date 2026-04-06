<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * System-wide settings editor. Settings are key/value pairs grouped by domain.
 * Admin can add, edit, or remove keys but the underlying schema (which keys
 * the application reads) is owned by the codebase — admin actions here only
 * change runtime values, never code paths.
 */
class SettingController extends Controller
{
    public function index(): View
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('dashboard.admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings'           => ['required', 'array'],
            'settings.*.key'     => ['required', 'string', 'max:191'],
            'settings.*.value'   => ['nullable'],
            'settings.*.group'   => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data['settings'] as $row) {
            $value = $row['value'] ?? null;

            // Try to decode JSON-ish values so the user can store arrays/objects
            // through a single text field. Fall back to the raw string.
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            Setting::setValue($row['key'], $value, $row['group'] ?? 'general');
        }

        AuditLog::create([
            'user_id'       => auth()->id(),
            'company_id'    => auth()->user()?->company_id,
            'action'        => AuditAction::UPDATE,
            'resource_type' => 'Setting',
            'resource_id'   => 0,
            'after'         => ['count' => count($data['settings'])],
            'ip_address'    => request()->ip(),
            'user_agent'    => substr((string) request()->userAgent(), 0, 255),
            'status'        => 'success',
        ]);

        return back()->with('status', __('admin.settings.saved'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $setting = Setting::findOrFail($id);

        AuditLog::create([
            'user_id'       => auth()->id(),
            'company_id'    => auth()->user()?->company_id,
            'action'        => AuditAction::DELETE,
            'resource_type' => 'Setting',
            'resource_id'   => $setting->id,
            'before'        => $setting->toArray(),
            'ip_address'    => request()->ip(),
            'user_agent'    => substr((string) request()->userAgent(), 0, 255),
            'status'        => 'success',
        ]);

        $setting->delete();

        return back()->with('status', __('admin.settings.deleted'));
    }
}
