<?php

namespace App\Providers;


use App\Interfaces\CardTypes\CardTypeInterface;
use App\Interfaces\Documents\DocumentInterface;

use App\Interfaces\Gyms\GymInterface;
use App\Interfaces\Partners\PartnerInterface;
use App\Interfaces\PaymentMethods\PaymentMethodInterface;
use App\Interfaces\Roles\RoleInterface;
use App\Interfaces\Users\UserInterface;
use App\Interfaces\Warehouses\WarehouseInterface;
use App\Repositories\CardTypes\CardTypeRepository;
use App\Repositories\Documents\DocumentRepository;

use App\Repositories\Gyms\GymRepository;
use App\Repositories\Partners\PartnerRepository;
use App\Repositories\PaymentMethods\PaymentMethodRepository;
use App\Repositories\Roles\RoleRepository;
use App\Repositories\Users\UserRepository;
use App\Repositories\Warehouses\WarehouseRepository;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {





        $this->app->bind(GymInterface::class, GymRepository::class);
        $this->app->bind(UserInterface::class, UserRepository::class);
        $this->app->bind(RoleInterface::class, RoleRepository::class);
        $this->app->bind(DocumentInterface::class, DocumentRepository::class);

        $this->app->bind(PaymentMethodInterface::class, PaymentMethodRepository::class);
        $this->app->bind(CardTypeInterface::class, CardTypeRepository::class);
        $this->app->bind(PartnerInterface::class, PartnerRepository::class);
        $this->app->bind(WarehouseInterface::class, WarehouseRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Inertia::share([
            'locale' => fn() => Session::get('locale', config('app.locale')),
        ]);
    }
}
