<?php

namespace App\Providers;

use App\Services\CachingService;
use App\Services\HelperService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider {
    /**
     * Register services.
     *
     * @return void
     */
    public function register() {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot() {
        $cache = app(CachingService::class);

        /*** Main Blade File ***/
        View::composer('layouts.main', static function (\Illuminate\View\View $view) use ($cache) {
            $lang = Session::get('language');
            if($lang){
                $view->with('language', $lang);
            }else{
                $cache = app(CachingService::class);
                $defaultLanguage = $cache->getDefaultLanguage();
                Session::put('language', $defaultLanguage);
                Session::put('locale', $defaultLanguage->code);
                Session::save();
                app()->setLocale($defaultLanguage->code);
                Artisan::call('cache:clear');
                $view->with('language', $cache->getDefaultLanguage());
            }

            // Add global language data for TinyMCE RTL support
            $allLanguages = HelperService::getActiveLanguages(null, true);
            $view->with('allLanguages', $allLanguages);
        });

        View::composer('auth.login', static function (\Illuminate\View\View $view) use ($cache) {
            $cache = app(CachingService::class);
            $defaultLanguage = $cache->getDefaultLanguage();
            Session::put('language', $defaultLanguage);
            Session::put('locale', $defaultLanguage->code);
            Session::save();
            app()->setLocale($defaultLanguage->code);
            Artisan::call('cache:clear');
            $settings = HelperService::getMultipleSettingData(['company_logo', 'favicon_icon', 'login_image']);
            $view->with('language', $cache->getDefaultLanguage());
            $view->with('settings', $settings);
        });

        View::composer('customers.reset-password', static function (\Illuminate\View\View $view) use ($cache) {
            $cache = app(CachingService::class);
            $defaultLanguage = $cache->getDefaultLanguage();
            Session::put('language', $defaultLanguage);
            Session::put('locale', $defaultLanguage->code);
            Session::save();
            app()->setLocale($defaultLanguage->code);
            Artisan::call('cache:clear');
            $view->with('language', $cache->getDefaultLanguage());
        });


        View::composer('layouts.footer_script', static function (\Illuminate\View\View $view) use ($cache) {
            $view->with('language', $cache->getDefaultLanguage());
        });
    }
}
