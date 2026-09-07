<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminTwoFactorVerify;
use App\Http\Middleware\AffiliateTracking;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\BlockBannedIp;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TwoFactorVerify;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
            Route::middleware('web')
                ->group(base_path('routes/client.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Exclusión de encriptación para cookie de tema oscuro
        $middleware->encryptCookies(except: ['pnlcs_theme']);

        // Confía en las cabeceras X-Forwarded-* enviadas por el reverse proxy (NetBird/Cloudflare/Docker)
        $middleware->trustProxies(at: '*');

        $middleware->prependToGroup('web', RedirectToInstaller::class);
        $middleware->appendToGroup('web', AffiliateTracking::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', MaintenanceMode::class);

        $middleware->throttleApi('api');
        $middleware->appendToGroup('api', ApiKeyAuth::class);
        $middleware->alias([
            'banned.ip' => BlockBannedIp::class,
            'admin.auth' => AdminAuthenticate::class,
            'admin.2fa' => AdminTwoFactorVerify::class,
            '2fa' => TwoFactorVerify::class,
            'admin.permission' => CheckAdminPermission::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            return str_starts_with($request->path(), 'client') ? route('client.login') : route('admin.login');
        });
    })
    ->withEvents(false)
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            $previous = $e->getPrevious();
            if (! $previous instanceof ModelNotFoundException) {
                return null;
            }
            $e = $previous;
            $model = class_basename($e->getModel());
            $message = __('admin.errors.record_not_found', ['model' => $model]);

            if ($request->is('admin/*')) {
                $segments = explode('/', trim($request->path(), '/'));
                $section = $segments[1] ?? null;
                $route = 'admin.'.$section.'.index';
                try {
                    $target = Illuminate\Support\Facades\Route::has($route) ? route($route) : route('admin.dashboard');
                } catch (Throwable $inner) {
                    $target = url('/admin');
                }

                return redirect($target)->with('error', $message);
            }

            if ($request->is('client/*')) {
                $segments = explode('/', trim($request->path(), '/'));
                $section = $segments[1] ?? null;
                $route = 'client.'.$section.'.index';
                try {
                    $target = Illuminate\Support\Facades\Route::has($route) ? route($route) : route('customer.dashboard');
                } catch (Throwable $inner) {
                    $target = url('/customer');
                }

                return redirect($target)->with('error', $message);
            }

            return null;
        });
    })->create();
