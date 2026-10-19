<?php

namespace App\Providers;

use App\Http\Middleware\EnsureRequestIntegrity;
use App\Mail\GodaddySmtpTransport;
use App\Services\ActiveRoleService;
use App\Services\SystemIntegrityService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActiveRoleService::class, function ($app) {
            return new ActiveRoleService;
        });
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Validator::replacer('max', function ($message, $attribute, $rule, $parameters, $data) {
            $value = data_get($data, str_replace('->', '.', $attribute));
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $maxMb = (int) round((int) $parameters[0] / 1024);
                return str_replace(':max', $maxMb . ' MB', $message);
            }
            return str_replace(':max', $parameters[0], $message);
        });

        //call the permission fix method
        $this->changePermissions();

        // Skip integrity check during console commands
        if (app()->runningInConsole()) {
            return;
        }

        $this->verifyServiceComponents();

        // Centralized licensing and integrity check
        $path = request()->path();
        $bypass = ['login', 'logout', 'install', 'install/*', 'clear', 'migrate', 'storage-link'];
        $isBypass = false;
        foreach ($bypass as $bp) {
            if (request()->is($bp)) {
                $isBypass = true;
                break;
            }
        }
        if (! $isBypass) {
            SystemIntegrityService::check();
        }

        // Scramble: Allow access to docs in all environments (change as needed)
        Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        $this->app->make('mail.manager')->extend('godaddy', function () {
            return new GodaddySmtpTransport('localhost', 25);
        });

    }

    protected function changePermissions()
    {
        LogViewer::auth(function () {
            return auth()->check(); // Allow access only if the user is authenticated
        });
        $paths = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0777, true);
            }

            $this->fixPermissions($path);
        }
    }

    protected function fixPermissions($path)
    {
        if (is_dir($path)) {
            @chmod($path, 0777);
            foreach (scandir($path) as $item) {
                if ($item === '.' || $item === '..') continue;
                $this->fixPermissions($path . DIRECTORY_SEPARATOR . $item);
            }
        } else {
            @chmod($path, 0664);
        }
    }

    /**
     * Verify that required service components are properly initialized.
     * This is an integrity check to prevent users from removing or disabling the licensing middleware.
     */
    private function verifyServiceComponents(): void
    {
        // Verify Middleware
        $cn = base64_decode('RW5zdXJlUmVxdWVzdEludGVncml0eQ=='); // 'EnsureRequestIntegrity'
        $path = app_path('Http/Middleware/'.$cn.'.php');
        if (! file_exists($path)) {
            abort(403, 'System integrity check failed. Please contact support.');
        }

        // Verify SystemIntegrityService logic
        $sn = base64_decode('U3lzdGVtSW50ZWdyaXR5U2VydmljZQ=='); // 'SystemIntegrityService'
        $sm = base64_decode('Y2hlY2s='); // 'check'
        $sPath = app_path('Services/'.$sn.'.php');
        if (! file_exists($sPath) || strpos(file_get_contents($sPath), 'function '.$sm) === false) {
            abort(403, 'System integrity check failed. Please contact support.');
        }

        // Ensure the middleware is still registered
        try {
            $isL11 = ! file_exists(app_path('Http/Kernel.php'));
            if (! $isL11) {
                $kernel = app(Kernel::class);
                $ref = new \ReflectionProperty($kernel, 'middlewareGroups');
                $ref->setAccessible(true);
                $groups = $ref->getValue($kernel);
                $cls = EnsureRequestIntegrity::class;
                if (! in_array($cls, $groups['web'] ?? [])) {
                    abort(403, 'System integrity check failed. Please contact support.');
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
