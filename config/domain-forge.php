<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Base Path & Namespace
	|--------------------------------------------------------------------------
	*/
	'domain_path' => base_path('src/Contexts'),
	'namespace' => 'Src\\Contexts',

	/*
	|--------------------------------------------------------------------------
	| Directory Structure
	|--------------------------------------------------------------------------
	|
	| Define qué carpetas se deben crear al generar un dominio.
	| Puedes activar/desactivar cualquier carpeta según las necesidades
	| de tu proyecto.
	|
	*/
	'structure' => [

		'application' => [
			'use_cases' => true,
			'dtos' => true,
			// Descomenta si usas CQRS
			// 'commands' => true,
			// 'handlers' => true,
			// 'queries'  => true,
		],

		'domain' => [
			'entities' => true,
			'value_objects' => true,
			'enums' => true,   // solo se crea si hay enums
			'contracts' => true,
			'exceptions' => true,
		],

		'infrastructure' => [
			'http' => [
				'controllers' => true,
				'requests' => true,
				'resources' => true,
				'routes' => true,
			],
			'persistence' => [
				'mappers' => true,
				'repositories' => true,
			],
		],
	],
];