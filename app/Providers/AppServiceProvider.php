<?php

namespace App\Providers;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        Gate::define('access-deck', function (User $user, Deck $deck) {
            return $user->id === $deck->user_id
                ? Response::allow()
                : Response::deny('Unauthorized access to this deck.');
        });
    }
}
