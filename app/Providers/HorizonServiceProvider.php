<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * TZ 15/16: Horizon dashboard'ini faqat administratorlar ko'ra oladi.
     * Guest (null) yoki content_editor rad etiladi.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user) {
            return $user !== null && $user->isAdministrator();
        });
    }
}
