<?php

namespace LaravelDoctrine\ORM;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Illuminate\Contracts\Container\Container;

final class IlluminateRegistry extends AbstractManagerRegistry implements ManagerRegistry
{
    /**
     * @var Container
     */
    protected $container;


    /**
     * @var EntityManagerCollection
     */
    protected $entityManagerCollection;

    /**
     * Constructor.
     *
     * @param array     $entityManagers
     * @param string    $proxyInterfaceName
     * @param Container $container
     */
    public function __construct(Container $container, Proxy $proxyInterface) {

        $this->container = $container;

        parent::__construct(
            config('doctrine.manager_registery_name','LaravelDoctrineRegistry'),
            $emc->connections()->byKey('name'),
            $emc->byKey('name'),
            isEmpty($emc->findBy(['name'=>'default'])) ? $emc->first() : $emc->findBy(['name'=>'default']),
            isEmpty($emc->connections()->findBy(['name'=>'default'])) ? $emc->connections()->first() : $emc->connections()->findBy(['name'=>'default']),
            Proxy::class
        );
    }

    public function createEntityManagers(array $managerConfigs)
    {
        foreach($managerConfigs as $name => $managerConfig)
        {
            $em = $this->createEntityManager($name, $managerConfig);
        }
    }

    public function createEntityManager($name, $userConfigs)
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
        return EntityManager::create($dbalConnection, $doctrineConfig);
    }

    /**
     * @param array $typeMap
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addCustomTypes(array $typeMap)
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
     * Fetches/creates the given services.
     * A service in this context is connection or a manager instance.
     *
     * @param string $name The name of the service.
     *
     * @return object The instance of the given service.
     */
    protected function getService($name)
    {
        return $this->container->make(self::getManagerNamePrefix() . $name);
    }


    /**
     * Resets the given services.
     * A service in this context is connection or a manager instance.
     *
     * @param string $name The name of the service.
     *
     * @return void
     */
    protected function resetService($name)
    {
        $this->container->forgetInstance(self::getManagerNamePrefix() . $name);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     * This method looks for the alias in all registered object managers.
     *
     * @param string $alias The alias.
     *
     * @throws ORMException
     * @return string       The full namespace.
     */
    public function getAliasNamespace($alias)
    {
        foreach (array_keys($this->getManagers()) as $name) {
            try {
                return $this->getManager($name)->getConfiguration()->getEntityNamespace($alias);
            } catch (ORMException $e) {
            }
        }
        throw ORMException::unknownEntityNamespace($alias);
    }

    /**
     * @param string $class
     *
     * @return mixed|object
     */
    public function getManagerForClass($class = 'default')
    {
        return $this->getService($class);
    }

    /**
     * @return string
     */
    public static function getManagerNamePrefix()
    {
        return 'doctrine.manager.';
    }

    /**
     * @return string
     */
    public static function getConnectionNamePrefix()
    {
        return 'doctrine.connection.';
    }
}
