# Domain Forge

Domain Forge is a Laravel package that allows you to easily generate domain modules following the hexagonal architecture.

## Installation

Run the following command to install the package:

```sh
composer require yntech/domain-forge
```

## Configuration

After installing the package, run the following command to configure the base structure:

```sh
php artisan domain-forge:install
```

Then, add the following configuration to the `composer.json` file in the `autoload.psr-4` section to ensure that all classes are loaded correctly:

```json
"autoload": {
    "psr-4": {
        "Src\\": "src/"
    }
}
```

After making this modification, run the following command to update the autoload configuration:

```sh
composer dump-autoload
```

## Domain Generation

To generate a new domain module, use the following command:

```sh
php artisan domain-forge:domain <domain>
```

Replace `<domain>` with the name of the domain you want to create.

### Generate Domain with Props

You can generate a domain with properties using the `--props` option.

Example:

```sh
php artisan domain-forge:domain <domain> --props=prop1:type1,prop2:type2
```

## Contributions

If you want to contribute to this project, please open an issue or submit a pull request in the official repository.

## License

This project is under the MIT license.

