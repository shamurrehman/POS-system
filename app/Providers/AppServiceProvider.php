<?php

namespace App\Providers;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination::tailwind');

        // Alert counter shown on the bell icon in the top bar. Cached briefly to keep pages fast.
        View::composer('layouts.app', function ($view) {
            $view->with('alertCount', Cache::remember('alert_count', 60, fn () => Product::lowStock()->count()
                + Product::where('is_active', true)->outOfStock()->count()
                + Batch::inStock()->nearExpiry()->count()
                + Batch::inStock()->expired()->count()));
        });
    }
}
