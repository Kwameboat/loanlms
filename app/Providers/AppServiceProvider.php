<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Use Bootstrap pagination
        Paginator::useBootstrapFive();

        // @active('route-pattern') helper for sidebar
        Blade::directive('active', function ($expression) {
            return "<?php echo (request()->is({$expression})) ? 'active' : ''; ?>";
        });

        // Share company settings with all views
        \View::composer('*', function ($view) {
            $view->with('_company', [
                'name'    => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
                'logo'    => \App\Models\Setting::get('company_logo'),
                'symbol'  => config('bigcash.company.currency_symbol', '₵'),
                'currency'=> config('bigcash.company.currency', 'GHS'),
            ]);
        });
    }
}
