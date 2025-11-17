<?php

namespace AgencyOrgo\StringTranslations;

use AgencyOrgo\StringTranslations\Controllers\TranslationController;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        if ($this->app->runningInConsole()) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/console.php');
            $this->publishes([
                __DIR__.'/../config/string-translations.php' => config_path('string-translations.php'),
            ], 'string-translations-config');
        }


        Utility::extend(function () {
            $this->loadViewsFrom(__DIR__ . '/../resources/views', 'string-translations');
            $utility = Utility::make('string-translations')
                ->title('String Translations')
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 8.25v-3a1.5 1.5 0 0 1 3 0v3m-3-1.5h3m9 3.75V12m-3 0h6M18 12s-1.5 4.5-4.5 4.5m3-1.733a3.932 3.932 0 0 0 3 1.733"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.25 18.75a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5h10.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5h-1.5v4.5l-4.5-4.5z"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6.75 12.75-3 3v-4.5h-1.5a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5h10.5a1.5 1.5 0 0 1 1.5 1.5v3"/></svg>')
                ->navTitle('String Translations')
                ->description('Manage string translations.')
                ->action([TranslationController::class, 'index'])
                ->routes(function ($router) {
                    $router->post('/', [TranslationController::class, 'make'])->name('make');
                });

            Utility::register($utility);

        });
    }
}