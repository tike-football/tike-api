<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        $roles = config('roles', []);
        $scopes = [];

        foreach ($roles as $role => $permissions) {
            if (isset($permissions['scopes'])) {
                foreach ($permissions['scopes'] as $scope) {
                    $scopes[$scope] = $scope . ' access';
                }
            }
        }

        Passport::tokensCan($scopes);
    }
}
