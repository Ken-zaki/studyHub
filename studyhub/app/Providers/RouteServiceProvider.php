<?php

namespace App\Providers;

use App\Models\FriendRequest;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Explicitly bind FriendRequest model with UUID resolution
        \Illuminate\Support\Facades\Route::bind('friendRequest', function ($value) {
            return FriendRequest::where('id', $value)->firstOrFail();
        });
    }
}
