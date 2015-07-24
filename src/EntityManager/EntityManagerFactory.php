<?php namespace LaravelDoctrine\ORM\EntityManager;

class EntityManagerFactory {


    /**
     * @param EntityManagerConfiguration $configuration
     * @return mixed
     */
    public function make(EntityManagerConfiguration $configuration)
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
        $dbalConnection = ConnectionFactory::create(Config::getDBALConnectionProperties($name), $doctrineConfig);

        // Allow the user to modify the connection if they'd like before we use it to create the EntityManager
        $this->callHook(static::POST_CONNECTION_HOOK, $dbalConnection);

        // Configure the ORM specific settings (includes cache and metadata configuration)
        Config::configureORMSettings($doctrineConfig, $userConfigs);

        // Allow the user to edit the configuration after we've applied the ORM settings, but before we create the EntityManager
        $this->callHook(static::POST_ORM_HOOK, $doctrineConfig);

        // Create the Entity Manager
        return EntityManager::create($dbalConnection, $doctrineConfig);
    }

}