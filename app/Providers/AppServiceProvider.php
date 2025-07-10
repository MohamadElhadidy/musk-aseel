<?php

namespace App\Providers;

use App\Models\Currency;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
       
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         // Use Bootstrap for pagination
        Paginator::useBootstrapFive();
        
        // Set locale
        if (session()->has('locale')) {
            app()->setLocale(session('locale'));
        }
        
        // Share common data with all views
        View::composer('*', function ($view) {
            $view->with('currentLocale', app()->getLocale());
            $view->with('isRtl', app()->getLocale() === 'ar');
        });
        
        // Custom Blade directives
        Blade::directive('money', function ($expression) {
            return "<?php echo app('currency')->format($expression); ?>";
        });
        
        Blade::directive('rtl', function () {
            return "<?php if(app()->getLocale() === 'ar'): ?>";
        });
        
        Blade::directive('endrtl', function () {
            return "<?php endif; ?>";
        });
        
        Blade::directive('ltr', function () {
            return "<?php if(app()->getLocale() !== 'ar'): ?>";
        });
        
        Blade::directive('endltr', function () {
            return "<?php endif; ?>";
        });
    }
}
