<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Service;
use App\Support\ClientOrg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\ServiceProvider;

/**
 * In the client area, a person who is not the account owner and not an
 * organization administrator only sees the services the organization granted
 * them (Organización > Accesos): directly or through a group. Everyone else,
 * and everything outside the client area, is unchanged.
 */
class NeoscOrgServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Service::addGlobalScope('neosc_org_access', new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $allowed = self::allowedIds();
                if ($allowed !== null) {
                    $builder->whereIn($model->getQualifiedKeyName(), $allowed ?: [0]);
                }
            }

            /** @return list<int>|null null = no restriction */
            private static function allowedIds(): ?array
            {
                static $busy = false;
                if ($busy || app()->runningInConsole() || ! app()->bound('request')) {
                    return null;
                }
                $route = request()->route();
                $name = (string) ($route?->getName() ?? '');
                if ($route === null || ! (preg_match('/^client\.(home|services|vps|lxd|one)\b/', $name) || $route->hasParameter('service'))) {
                    return null;
                }
                $user = auth()->user();
                if (! $user) {
                    return null;
                }

                $busy = true;
                try {
                    $clients = $user->clients()->orderBy('clients.id')->get();
                    $active = (int) session('active_client_id');
                    $client = $clients->firstWhere('id', $active) ?: $clients->first();
                    if (! $client instanceof Client || $client->pivot?->owner) {
                        return null;
                    }
                    $org = ClientOrg::get($client);
                    foreach ($org['members'] as $m) {
                        if (mb_strtolower((string) ($m['email'] ?? '')) === mb_strtolower($user->email)) {
                            if (($m['role'] ?? 'user') === 'admin') {
                                return null;
                            }

                            return array_map('intval', ClientOrg::serviceIdsFor($org, (string) $m['id']));
                        }
                    }

                    return null; // not listed in an organization: leave the account as it is
                } finally {
                    $busy = false;
                }
            }
        });
    }
}
