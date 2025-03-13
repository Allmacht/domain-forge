<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Forge Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para la generación de dominios y estructura
    | de arquitectura hexagonal.
    |
    */

    // Ruta de los dominios generados
    'domain_path' => base_path('src'),

    // Configuración de namespace
    'namespace' => 'Src',

    // Capas de la arquitectura hexagonal a generar
    'layers' => [
        'application' => true,
        'domain' => true,
        'infrastructure' => true,
    ],

    // Componentes a generar por defecto
    'components' => [
        // Application Layer
        'commands' => true,
        'queries' => true,
        'services' => true,
        
        // Domain Layer
        'entities' => true,
        'repositories' => true,
        'value_objects' => true,
        'events' => true,
        'exceptions' => true,
        
        // Infrastructure Layer
        'controllers' => true,
        'repositories_impl' => true,
        'models' => true,
        'migrations' => true,
    ],
];