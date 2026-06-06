<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (User $user): bool {
            if (app()->environment('local')) {
                return true;
            }

            $allowedEmails = config('horizon.allowed_emails', []);

            return in_array(strtolower($user->email), $allowedEmails, true);
        });
    }
}
