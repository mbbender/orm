<?php namespace LaravelDoctrine\ORM\EntityManager;

class EntityManagerFactory {


    /**
     * @param EntityManagerConfiguration $configuration
     * @return mixed
     */
    public function make(EntityManagerConfiguration $configuration)
    {


        // Check on the existence of the requested DBAL connection for this manager and get one if it already
        // exists or create and register a new one.



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