<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development state
    |--------------------------------------------------------------------------
    |
    | If set to false, metadata caching will become active
    |
    */
    'dev'                       => config('app.debug'),

   'dbal_connections' => [
       'default' => [
           'connection' => '',
           'sqllogger' => '',
           'result_cache' => '',
           'auto_commit' => ''
       ]
   ],
    /*
    |--------------------------------------------------------------------------
    | Entity Mangers
    |--------------------------------------------------------------------------
    |
    */
    'entity_managers'                  => [
        // The global configuration block is special. It is not configured as an entity manager but is used to
        // apply settings to all entity managers unless overridden by the specific entity manager configurations.
        'global' => [
            'dbal_connection_name' => 'default',

            'orm' => [
                // If multiple mapping drivers are defined here a DriverChain will be created and each
                // MappingDriver added to the chain.
                'metadata_mapping' => [
                    'annotations' => [
                        'paths' => '',
                        'namespaces' => '',
                        'reader' => '', // Class to use as reader
                        'registry' => '',
                        'autoloader' => '',
                        'filters' => [],
                    ],
                    'yaml' => '',
                    'xml' => '',
                    'static_php' => '',
                    'custom' => ''
                ],
                'caches' => [
                    'query_cache' => '',
                    'hydration_cache' => '',
                    'second_level_cache' => ''
                ],
                'proxy_settings'    => [
                    // 'namespace'     => '', // Set the namespace or leave commented out for no namespace
                    'path'          => storage_path('proxies'),
                    'auto_generate' => env('DOCTRINE_PROXY_AUTOGENERATE', false)
                ],
                'custom_functions' => [
                    'numeric' => '',
                    'datetime' => '',
                    'string' => ''
                ],
                'naming_strategy' => '',
                'quote_strategy' => '',
                'default_repository_class' => Doctrine\ORM\EntityRepository::class,
            ],

            'events'     => [
                'listeners'   => [],
                'subscribers' => []
            ],

            'hooks' => [
                'post_DBAL_config' => '', // Called after configuration object is created and set with DBAL specific configurations, but before Connection object is created. Passes in configuration object.
                'post_connection' => '', // Called after Connection is created. Passes in Connection object for modification.
                'pre_EM_creation' => '', // Called after all ORM specific configurations have been applied to the configuration object, but before we create the EntityManager. Last chance to edit the configuration. Passes in configuration object for modification.
                'post_EM_creation' => '', // Called after EM is created but before it is registered with ManagerRegistry. Passes in EM.
            ]
        ],

        // The Entity Manager with the name 'default' will be bound as the entity manager used in Laravel if
        // no Entity Manager is specifically requested. If you resolve out the Entity Manager it will be 'default'.
        'default' => [

        ]
    ],


    /*
    |--------------------------------------------------------------------------
    | Doctrine custom types
    |--------------------------------------------------------------------------
    */
    'custom_types'              => [
        'json' => LaravelDoctrine\ORM\Types\Json::class
    ],
    /*
    |--------------------------------------------------------------------------
    | Enable Debugbar Doctrine query collection
    |--------------------------------------------------------------------------
    */
    'debugbar'                  => env('DOCTRINE_DEBUGBAR', false)


];
