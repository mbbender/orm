<?php

namespace LaravelDoctrine\ORM;

use DebugBar\Bridge\DoctrineCollector;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\ServiceProvider;
use LaravelDoctrine\ORM\Auth\DoctrineUserProvider;
use LaravelDoctrine\ORM\Configuration\Cache\CacheManager;
use LaravelDoctrine\ORM\Configuration\Config;
use LaravelDoctrine\ORM\Configuration\Connections\ConnectionFactory;
use LaravelDoctrine\ORM\Configuration\Connections\ConnectionManager;
use LaravelDoctrine\ORM\Configuration\LaravelNamingStrategy;
use LaravelDoctrine\ORM\Configuration\MetaData\MetaDataManager;
use LaravelDoctrine\ORM\Console\ClearMetadataCacheCommand;
use LaravelDoctrine\ORM\Console\ClearQueryCacheCommand;
use LaravelDoctrine\ORM\Console\ClearResultCacheCommand;
use LaravelDoctrine\ORM\Console\ConvertConfigCommand;
use LaravelDoctrine\ORM\Console\EnsureProductionSettingsCommand;
use LaravelDoctrine\ORM\Console\GenerateProxiesCommand;
use LaravelDoctrine\ORM\Console\InfoCommand;
use LaravelDoctrine\ORM\Console\SchemaCreateCommand;
use LaravelDoctrine\ORM\Console\SchemaDropCommand;
use LaravelDoctrine\ORM\Console\SchemaUpdateCommand;
use LaravelDoctrine\ORM\Console\SchemaValidateCommand;
use LaravelDoctrine\ORM\Exceptions\ExtensionNotFound;
use LaravelDoctrine\ORM\Extensions\DriverChain;
use LaravelDoctrine\ORM\Extensions\ExtensionManager;
use LaravelDoctrine\ORM\Validation\DoctrinePresenceVerifier;

class DoctrineServiceProvider extends ServiceProvider
{

    const POST_DBAL_HOOK = 'post_DBAL_hook';
    const POST_ORM_HOOK = 'post_ORM_hook';

    /**
     * @var array
     */
    protected $config;

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
        $this->addCustomTypes(Config::getCustomTypes());
        $this->extendAuthManager();

        // Boot the extension manager
        $this->app->make(ExtensionManager::class)->boot();

        $this->publishes([
            $this->getConfigPath() => config_path('doctrine.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        // Get global stuff handled
        // Todo: Add a config file validator to throw exceptions if config is not good
        $this->registerPresenceVerifier();
        $this->registerConsoleCommands();

        // Create and register the Entity Managers
        $entityManagers = $this->createEntityManagers(Config::getEntityManagerConfigurations());
        $this->registerManagerRegistry($entityManagers);
        $this->registerDefaultEntityManager();
    }

    /**
     * Workhorse of the Laravel / Doctrine integration. This function will create and setup
     * all the entity managers including the cache providers and metadata mapping drivers.
     *
     * @param $entityManagersConfigurations
     * @return array
     */
    protected function createEntityManagers($entityManagersConfigurations)
    {
        $entityManagers = [];

        foreach($entityManagersConfigurations as $emName => $userConfigs)
        {
            // Apply any global configurations to the entity manager
            $userConfigs = Config::mergeGlobalEntityManagerConfigurations($userConfigs);

            // Create a blank Doctrine configuration
            $doctrineConfig = new Configuration();

            // Configure the DBAL specific settings, if any, before we create the connection
            Config::configureDBALSettings($doctrineConfig, $userConfigs);

            // Allow the user to modify the configuration if they want before we create the connection
            $this->callHook(static::POST_DBAL_HOOK, $doctrineConfig);

            // Create a DBAL connection to use with this entity manager
            $dbalConnection = ConnectionFactory::create(Config::getDBALConnectionProperties($emName), $doctrineConfig);

            // Allow the user to modify the connection if they'd like before we use it to create the EntityManager
            $this->callHook(static::POST_CONNECTION_HOOK, $dbalConnection);

            // Configure the ORM specific settings (includes cache and metadata configuration)
            Config::configureORMSettings($doctrineConfig, $userConfigs);

            // Allow the user to edit the configuration after we've applied the ORM settings, but before we create the EntityManager
            $this->callHook(static::POST_ORM_HOOK, $doctrineConfig);

            // Create the Entity Manager
            $entityManagers[$emName] = EntityManager::create($dbalConnection, $doctrineConfig);
        }

        return $entityManagers;
    }

    /**
     * Register the manager registry.
     *
     * This registry contains access to all entity managers. Just inject the ManagerRegistry
     * into your class and call `$managerRegistry->getManager('manager_name')`
     *
     * @param $entityManagers
     */
    protected function registerManagerRegistry($entityManagers)
    {
        $this->app->singleton(
            IlluminateRegistry::class,
            function ($app) use ($entityManagers){
                return new IlluminateRegistry(
                    $entityManagers,
                    Proxy::class,
                    $app
                );
            }
        );

        $this->app->alias(IlluminateRegistry::class, ManagerRegistry::class);
    }

    /**
     * Register the default entity manager
     *
     * This will register your default managers so it can be accessed via injecting the
     * EntityManager dependency.
     */
    protected function registerDefaultEntityManager()
    {
        // Bind the default Entity Manager
        $this->app->singleton('em', function ($app) {
            return $app->make(ManagerRegistry::class)->getManager();
        });

        $this->app->alias('em', EntityManager::class);
        $this->app->alias('em', EntityManagerInterface::class);
    }

    protected function callHook($hook, &$payload)
    {
        //todo: Implement hook system
    }

    //----------------



    /**
     * Setup the entity managers
     * @return array

    protected function setUpEntityManagers()
    {
        $managers    = [];
        $connections = [];

        foreach ($this->app->config->get('doctrine.managers', []) as $manager => $settings) {

            // Bind manager
            $this->app->singleton($managerName, function () use ($settings) {



                // Listeners
                if (isset($settings['events']['listeners'])) {
                    foreach ($settings['events']['listeners'] as $event => $listener) {
                        $manager->getEventManager()->addEventListener($event, $listener);
                    }
                }

                // Subscribers
                if (isset($settings['events']['subscribers'])) {
                    foreach ($settings['events']['subscribers'] as $subscriber) {
                        $manager->getEventManager()->addEventSubscriber($subscriber);
                    }
                }

                // Filters
                if (isset($settings['filters'])) {
                    foreach ($settings['filters'] as $name => $filter) {
                        $configuration->getMetadataDriverImpl()->addFilter($name, $filter);
                        $manager->getFilters()->enable($name);
                    }
                }

                // Paths
                $paths = array_get($settings, 'paths', []);
                $meta = $configuration->getMetadataDriverImpl();

                if (method_exists($meta, 'addPaths')) {
                    $meta->addPaths($paths);
                } elseif (method_exists($meta, 'getLocator')) {
                    $meta->getLocator()->addPaths($paths);
                }



                return $manager;
            });


            $managers[$manager]    = $manager;
            $connections[$manager] = $manager;
        }

        return [$managers, $connections];
    }
     */


    /**




    /**
     * Register the meta data drivers

    protected function setupMetaData()
    {
        MetaDataManager::registerDrivers(
            $this->app->config->get('doctrine.meta.drivers', []),
            $this->app->config->get('doctrine.dev', false)
        );

        MetaDataManager::resolved(function (Configuration $configuration) {

            // Debugbar
            if ($this->app->config->get('doctrine.debugbar', false) === true) {
                $debugStack = new DebugStack();
                $configuration->setSQLLogger($debugStack);
                $this->app['debugbar']->addCollector(
                    new DoctrineCollector($debugStack)
                );
            }



            // Second level caching
            if ($this->app->config->get('cache.second_level', false)) {
                $configuration->setSecondLevelCacheEnabled(true);

                $cacheConfig = $configuration->getSecondLevelCacheConfiguration();
                $cacheConfig->setCacheFactory(
                    new DefaultCacheFactory(
                        $cacheConfig->getRegionsConfiguration(),
                        CacheManager::resolve(
                            $this->app->config->get('cache.default')
                        )
                    )
                );
            }
        });
    }*/


    /**
     * Register the driver chain

    protected function registerDriverChain()
    {
        $this->app->singleton(DriverChain::class, function ($app) {

            $configuration = $app['em']->getConfiguration();

            $chain = new DriverChain(
                $configuration->getMetadataDriverImpl()
            );

            // Register namespaces
            $namespaces = array_merge($app->config->get('doctrine.meta.namespaces', ['App']), ['LaravelDoctrine']);
            foreach ($namespaces as $namespace) {
                $chain->addNamespace($namespace);
            }

            // Register default paths
            $chain->addPaths(array_merge(
                $app->config->get('doctrine.meta.paths', []),
                [__DIR__ . '/Auth/Passwords']
            ));

            $configuration->setMetadataDriverImpl($chain->getChain());

            return $chain;
        });
    }
     * */

    /**
     * Register doctrine extensions

    protected function registerExtensions()
    {
        // Bind extension manager as singleton,
        // so user can call it and add own extensions
        $this->app->singleton(ExtensionManager::class, function ($app) {

            $manager = new ExtensionManager(
                $this->app[ManagerRegistry::class],
                $this->app[DriverChain::class]
            );

            // Register the extensions
            foreach ($this->app->config->get('doctrine.extensions', []) as $extension) {
                if (!class_exists($extension)) {
                    throw new ExtensionNotFound("Extension {$extension} not found");
                }

                $manager->register(
                    $app->make($extension)
                );
            }

            return $manager;
        });
    }

     */

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function addCustomTypes($typeMap)
    {
        foreach ($typeMap as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            } else {
                Type::overrideType($name, $class);
            }
        }
    }

    /**
     * Register the validation presence verifier
     */
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', DoctrinePresenceVerifier::class);
    }

    /**
     * Register console commands
     */
    protected function registerConsoleCommands()
    {
        $this->commands([
            InfoCommand::class,
            SchemaCreateCommand::class,
            SchemaUpdateCommand::class,
            SchemaDropCommand::class,
            SchemaValidateCommand::class,
            ClearMetadataCacheCommand::class,
            ClearResultCacheCommand::class,
            ClearQueryCacheCommand::class,
            EnsureProductionSettingsCommand::class,
            GenerateProxiesCommand::class,
            ConvertConfigCommand::class
        ]);
    }

    /**
     * Extend the auth manager
     */
    protected function extendAuthManager()
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
     * @return string
     */
    protected function getConfigPath()
    {
        return __DIR__ . '/../config/doctrine.php';
    }

    /**
     * Get the services provided by the provider.
     * @return string[]
     */
    public function provides()
    {
        return [
            'em',
            'validation.presence',
            'migration.repository',
            AuthManager::class,
            EntityManager::class,
            EntityManagerInterface::class,
            ManagerRegistry::class
        ];
    }

}
