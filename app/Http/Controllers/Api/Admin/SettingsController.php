<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Models\Admin;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SettingsController
{
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('settings.view');

        $settings = AppSetting::query()->orderBy('key')->get(['key', 'value', 'description']);

        return response()->json([
            'success' => true,
            'data' => $settings->mapWithKeys(fn(AppSetting $setting) => [$setting->key => $setting->value]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->can('settings.manage'), 403, 'Forbidden.');

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['required'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            AppSetting::query()->updateOrCreate(
                ['key' => (string) $key],
                [
                    'value' => $value,
                    'updated_by_admin_id' => $admin->id,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'data' => AppSetting::query()->orderBy('key')->pluck('value', 'key'),
        ]);
    }
}