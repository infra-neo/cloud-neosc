<?php

namespace App\Providers;

use App\Models\Service;
use App\Support\ClientOrg;
use App\Support\NeoscAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * In the client area:
 *  - a person who is not the account owner nor an organization administrator
 *    only sees the services the organization granted them (Organización >
 *    Accesos), directly or through a group;
 *  - a plain "user" cannot open buying, billing or organization pages.
 * The owner, administrators, people the organization does not list, and
 * everything outside the client area are unchanged.
 */
class NeoscOrgServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $e) {
            $name = (string) ($e->route->getName() ?? '');
            if (str_starts_with($name, 'client.') && preg_match(NeoscAccess::USER_DENIED, $name)
                && NeoscAccess::roleFor(auth()->user()) === 'user') {
                abort(403);
            }
        });

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

                $busy = true;
                try {
                    $ctx = NeoscAccess::context(auth()->user());
                    if (! in_array($ctx['role'], ['manager', 'user'], true)) {
                        return null;
                    }

                    return array_map('intval', ClientOrg::serviceIdsFor($ctx['org'], (string) $ctx['member']['id']));
                } finally {
                    $busy = false;
                }
            }
        });
    }
}
