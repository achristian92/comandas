<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OkfacCallbackService
{
    public static function sendCommandPrintStatus(
        string $companyUuid,
        string $commandUuid,
        bool $success,
        ?string $errorMessage = null
    ): bool {
        $apiUrl = config('okfac.api_url');
        $apiToken = config('okfac.api_token');

        if (!$apiUrl) {
            Log::warning('OKFAC API URL no configurada, saltando callback');
            return false;
        }

        $url = sprintf('%s/%s/commands/%s', $apiUrl, $companyUuid, $commandUuid);

        $payload = [
            'status' => $success ? '2' : '3',
            'printed_at' => now()->toISOString(),
        ];

        if (!$success && $errorMessage) {
            $payload['error_message'] = $errorMessage;
        }

        Log::info('Enviando callback a OKFAC', [
            'url' => $url,
            'company_uuid' => $companyUuid,
            'command_uuid' => $commandUuid,
            'success' => $success,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->when($apiToken, function ($http) use ($apiToken) {
                    return $http->withToken($apiToken);
                })
                ->get($url, $payload);

            if ($response->successful()) {
                Log::info('Callback a OKFAC exitoso', [
                    'company_uuid' => $companyUuid,
                    'command_uuid' => $commandUuid,
                    'status_code' => $response->status(),
                ]);
                return true;
            } else {
                Log::error('Callback a OKFAC falló', [
                    'company_uuid' => $companyUuid,
                    'command_uuid' => $commandUuid,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Throwable $e) {
            Log::error('Error enviando callback a OKFAC', [
                'company_uuid' => $companyUuid,
                'command_uuid' => $commandUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
