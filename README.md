# Domain Forge

Domain Forge es un paquete para Laravel que permite generar módulos de dominio siguiendo la arquitectura hexagonal de manera sencilla.

## Instalación

Ejecuta el siguiente comando para instalar el paquete:

```sh
composer require yntech/domain-forge
```

## Configuración

Después de instalar el paquete, ejecuta el siguiente comando para configurar la estructura base:

```sh
php artisan domain-forge:install
```

Luego, añade la siguiente configuración en el archivo `composer.json` en la sección `autoload.psr-4` para asegurar que todas las clases sean cargadas correctamente:

```json
"autoload": {
    "psr-4": {
        "Src\\": "src/"
    }
}
```

Después de realizar esta modificación, ejecuta el siguiente comando para actualizar la configuración de autoload:

```sh
composer dump-autoload
```

## Generación de Dominios

Para generar un nuevo módulo de dominio, usa el siguiente comando:

```sh
php artisan domain-forge:domain <domain>
```

Reemplaza `<domain>` por el nombre del dominio que deseas crear.

## Contribuciones

Si deseas contribuir a este proyecto, por favor, abre un issue o envía un pull request en el repositorio oficial.

## Licencia

Este proyecto está bajo la licencia MIT.

