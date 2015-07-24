<?php

namespace LaravelDoctrine\ORM;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\ServiceProvider;
use LaravelDoctrine\ORM\Configuration\Config as ConfigHelper;
use LaravelDoctrine\ORM\Console as Console;
use LaravelDoctrine\ORM\EntityManager\EntityManagerConfiguration;
use LaravelDoctrine\ORM\EntityManager\EntityManagerFactory;

class DoctrineServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = true;

    /**
     * Boot service provider.
     */
    public function boot(ManagerRegistry $managerRegistry, DoctrineExtender $doctrineExtender, EntityManagerFactory $emFactory)
    {
        $doctrineExtender->addCustomTypes(ConfigHelper::getCustomTypes());
        $doctrineExtender->registerAnnotations(ConfigHelper::getCustomAnnotations());

        foreach(ConfigHelper::getEntityManagerConfigurations() as $name => $config)
        {
            $managerRegistry->registerManager(
                $name,
                $emFactory->make(new EntityManagerConfiguration($config))
            );
        }

        $this->publishes([__DIR__ . '/../config/doctrine.php' => config_path('doctrine.php')], 'config');
    }

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
       // Bind the Manager Registry
        $this->app->singleton(ManagerRegistry::class, function($app){
            return new IlluminateRegistry();
        });

        // Bind the Manager Registry
        $this->app->singleton(EntityManagerInterface::class, function($app){
            return $app[ManagerRegistry::class]->getDefaultManager();
        });

        // Register Console Commands
        $this->commands([
            Console\InfoCommand::class,
            Console\SchemaCreateCommand::class,
            Console\SchemaUpdateCommand::class,
            Console\SchemaDropCommand::class,
            Console\SchemaValidateCommand::class,
            Console\ClearMetadataCacheCommand::class,
            Console\ClearResultCacheCommand::class,
            Console\ClearQueryCacheCommand::class,
            Console\EnsureProductionSettingsCommand::class,
            Console\GenerateProxiesCommand::class,
            Console\ConvertConfigCommand::class
        ]);
    }

    /**
     * Get the services provided by the provider.
     * @return string[]
     */
    public function provides()
    {
        return [
            ManagerRegistry::class,
            EntityManagerInterface::class
        ];
    }

}
