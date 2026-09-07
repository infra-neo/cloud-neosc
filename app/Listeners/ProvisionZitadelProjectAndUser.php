<?php

namespace App\Listeners;

use App\Events\ClientCreated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProvisionZitadelProjectAndUser
{
    /**
     * Manejar el evento de Cliente Creado.
     */
    public function handle(ClientCreated $event): void
    {
        $client = $event->client;

        // Configuración de Zitadel Cloud
        $zitadelDomain = config('services.zitadel.domain', 'https://tu-instancia.zitadel.cloud');
        $zitadelToken  = config('services.zitadel.pat_token'); // Personal Access Token de cuenta de servicio
        $orgId         = config('services.zitadel.org_id');    // ID de la Organización central

        $headers = [
            'Authorization' => "Bearer {$zitadelToken}",
            'x-zitadel-orgid' => $orgId,
            'Content-Type'  => 'application/json',
        ];

        try {
            // -----------------------------------------------------------------
            // Paso 1: Crear el Proyecto en Zitadel Cloud
            // -----------------------------------------------------------------
            $projectName = "Project - " . ($client->company_name ?? "Client #{$client->id}");
            
            $projectResponse = Http::withHeaders($headers)
                ->post("{$zitadelDomain}/v2/projects", [
                    'name' => $projectName,
                    'projectRoleAssertion' => true,
                    'projectRoleCheck'     => true,
                ]);

            if ($projectResponse->failed()) {
                Log::error("Zitadel: Falla al crear proyecto para Cliente #{$client->id}: " . $projectResponse->body());
                return;
            }

            $projectId = $projectResponse->json('id');
            Log::info("Zitadel: Proyecto creado exitosamente [ID: {$projectId}] para Cliente #{$client->id}");

            // -----------------------------------------------------------------
            // Paso 2: Crear el Usuario (Human User) + Email Onboarding
            // -----------------------------------------------------------------
            $userResponse = Http::withHeaders($headers)
                ->post("{$zitadelDomain}/v2/users/human", [
                    'username' => $client->email,
                    'profile'  => [
                        'firstName'   => $client->first_name ?? 'Cliente',
                        'lastName'    => $client->last_name ?? 'PNLCS',
                        'displayName' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    ],
                    'email'    => [
                        'email'               => $client->email,
                        'isVerified'          => false,
                        'sendVerificationEmail' => true, // Dispara el correo de verificación/bienvenida de Zitadel
                    ],
                ]);

            if ($userResponse->failed()) {
                Log::error("Zitadel: Falla al crear usuario para Cliente #{$client->id}: " . $userResponse->body());
                return;
            }

            $userId = $userResponse->json('userId');
            Log::info("Zitadel: Usuario creado [ID: {$userId}]");

            // -----------------------------------------------------------------
            // Paso 3: Asignar al Usuario como ADMIN del Proyecto (Project Grant / Owner Role)
            // -----------------------------------------------------------------
            // En Zitadel, se asigna el rol de Project Owner o Project Administrator
            $grantResponse = Http::withHeaders($headers)
                ->post("{$zitadelDomain}/v2/projects/{$projectId}/members", [
                    'userId' => $userId,
                    'roles'  => ['PROJECT_OWNER'] // Rol de administración sobre el proyecto
                ]);

            if ($grantResponse->failed()) {
                Log::error("Zitadel: Falla al asignar rol Admin sobre el proyecto {$projectId}: " . $grantResponse->body());
                return;
            }

            // -----------------------------------------------------------------
            // Paso 4: Guardar los IDs de Zitadel en el registro del cliente
            // -----------------------------------------------------------------
            $client->update([
                'notes' => trim(($client->notes ?? '') . "\nZitadel Project ID: {$projectId} | User ID: {$userId}")
            ]);

            Log::info("Zitadel: Onboarding completo finalizado para Cliente #{$client->id}");

        } catch (\Exception $e) {
            Log::error("Zitadel Exception en ClientCreated Listener: " . $e->getMessage());
        }
    }
}
