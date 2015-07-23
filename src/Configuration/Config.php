<?php namespace LaravelDoctrine\ORM\Configuration;

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Str;

class Config {

    public static function getEntityManagerConfigurations($name = null)
    {
        if(isNull($name)) return config('doctrine.entity_managers');

        $managerConfig = config("doctrine.entity_manager.{$name}");
        if(isNull($managerConfig)) throw new EntityNotFoundException($name);

        return $managerConfig;
    }

    public static function getCustomTypes()
    {
        return config('doctrine.custom_types');
    }

    public static function mergeGlobalEntityManagerConfigurations($specificEntityManagerConfigurations)
    {
        $globalEntityManagerConfigurations = static::getEntityManagerConfigurations('global');
        if(isNull($globalEntityManagerConfigurations)) return $specificEntityManagerConfigurations;

        return array_merge_recursive($specificEntityManagerConfigurations, $globalEntityManagerConfigurations);
    }

    public static function getDBALConnectionProperties($emName)
    {
        $emConfig = static::getEntityManagerConfigurations($emName);
        return $emConfig['dbal'];
    }

    public static function configureDBALSettings(&$doctrineConfig, $userConfigs)
    {

    }

    public static function configureORMSettings(Configuration &$doctrineConfig, $userConfigs)
    {
        // Metadata Mapping Driver(s)
        $metadataDrivers = array_get($userConfigs, 'orm.metadata_mapping');
        if(count($metadataDrivers) == 1 )
        {
            $doctrineConfig->setMetadataDriverImpl(MetadataFactory::resolve(head($metadataDrivers)));
        }

        else
        {
            $driverChain = new MappingDriverChain();
            foreach($metadataDrivers as $mappingDriver)
            {
                $driverChain->addDriver(MetadataDriverFactory::resolve($mappingDriver), $mappingDriver['namespace']);
            }

            $doctrineConfig->setMetadataDriverImpl($driverChain);
        }

        // todo: Set caches

        // todo: Set custom functions

        // Automatically make table, column names, etc. like Laravel
        $doctrineConfig->setNamingStrategy(
            new LaravelNamingStrategy(new Str())
        );

        // Repository
        $doctrineConfig->setDefaultRepositoryClassName(
            array_get($userConfigs, 'orm.default_repository_class', EntityRepository::class)
        );

        // Proxies
        $doctrineConfig->setProxyDir(
            array_get($userConfigs, 'orm.proxy_settings.path', storage_path('proxies'))
        );

        $doctrineConfig->setAutoGenerateProxyClasses(
            array_get($userConfigs, 'orm.proxy_settings.auto_generate', false)
        );

        if ($namespace = array_get($userConfigs, 'orm.proxy_settings.namespace', false)) {
            $doctrineConfig->setProxyNamespace($namespace);
        }
    }

}