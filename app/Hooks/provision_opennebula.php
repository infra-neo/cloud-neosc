<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hook para la aprovisionación automática en OpenNebula al activar/comprar un producto.
 */
add_hook('AfterModuleCreate', 1, function (array $vars) {
    $service = $vars['service'] ?? null;

    if (! $service) {
        Log::warning('PNLCS OpenNebula hook called without service context');
        return;
    }

    $service->loadMissing('product', 'client');

    $serverType = strtolower((string) ($service->server?->type ?? $service->product?->server_type ?? ''));
    $productName = strtolower((string) ($service->product?->name ?? ''));

    $shouldProvision = $serverType === 'custom' || $serverType === 'opennebula' || str_contains($productName, 'windows 11 pro');

    if (! $shouldProvision) {
        Log::info('PNLCS OpenNebula hook skipped', [
            'service_id' => $service->id,
            'server_type' => $serverType,
            'product_name' => $service->product?->name,
        ]);
        return;
    }

    $client = $service->client;

    // Configuración API OpenNebula Backend
    $apiUrl = 'http://149.56.241.64:3000/api/vm/instantiate';
    $bearerToken = '9c632dc1ac0b26ab925717ba62a2b0e535476a1d1b57ecbd7b58aae84cbb9113';

    // Generar nombre dinámico para la VM basado en el ID de cliente y servicio
    $vmName = 'NEOSC-VDI-CLI' . ($client->id ?? '0') . '-SRV' . $service->id;

    $payload = [
        'templateId' => 14,
        'vmName'     => $vmName,
        'cpu'        => 2,
        'memory'     => 8192
    ];

    Log::info('PNLCS OpenNebula hook triggered', [
        'service_id' => $service->id,
        'server_type' => $serverType,
        'product_name' => $service->product?->name,
        'vm_name' => $vmName,
    ]);

    try {
        $response = Http::withToken($bearerToken)
            ->timeout(15)
            ->post($apiUrl, $payload);

        if ($response->successful()) {
            Log::info("PNLCS OpenNebula API Success for Service #{$service->id}: " . $response->body());

            $service->update([
                'notes' => 'VM desplegada con éxito en OpenNebula: ' . $vmName
            ]);
        } else {
            Log::error("PNLCS OpenNebula API Error ({$response->status()}): " . $response->body());
        }
    } catch (\Exception $e) {
        Log::error("PNLCS OpenNebula Exception: " . $e->getMessage());
    }
});
