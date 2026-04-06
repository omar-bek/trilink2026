<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function publicSettings(): JsonResponse
    {
        $settings = Setting::where('group', 'public')
            ->get()
            ->keyBy('key');

        return $this->success($settings);
    }

    public function index(Request $request): JsonResponse
    {
        $settings = Setting::query()
            ->when($request->group, fn ($q, $v) => $q->where('group', $v))
            ->get()
            ->keyBy('key');

        return $this->success($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.group' => 'nullable|string',
        ]);

        foreach ($data['settings'] as $item) {
            Setting::setValue($item['key'], $item['value'], $item['group'] ?? 'general');
        }

        return $this->success(null, 'Settings updated');
    }
}
