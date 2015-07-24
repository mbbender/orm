<?php

namespace LaravelDoctrine\ORM;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\ServiceProvider;
use LaravelDoctrine\ORM\Auth\DoctrineUserProvider;

class DoctrineAuthServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = true;

    /**
     * Boot service provider.
     */
    public function boot()
    {

        $this->app[AuthManager::class]->extend('doctrine', function ($app) {
            return new DoctrineUserProvider(
                $app[Hasher::class],
                $app['em'],
                $app['config']['auth.model']
            );
        });
    }

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {

    }

    /**
     * Get the services provided by the provider.
     * @return string[]
     */
    public function provides()
    {
        return [
            AuthManager::class
        ];
    }

}
