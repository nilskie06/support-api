<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemConfigurationRequest;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;

class SystemConfigurationController extends Controller
{
    /**
     * Return the system-wide configuration.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->configuration(),
        ]);
    }

    /**
     * Update the submitted system configuration fields.
     */
    public function update(UpdateSystemConfigurationRequest $request): JsonResponse
    {
        $configuration = $this->configuration();
        $configuration->update($request->validated());

        return response()->json([
            'message' => 'System configuration updated successfully',
            'data' => $configuration->fresh(),
        ]);
    }

    private function configuration(): SystemConfiguration
    {
        return SystemConfiguration::query()->firstOrCreate([
            'configuration_key' => 'default',
        ])->refresh();
    }
}
