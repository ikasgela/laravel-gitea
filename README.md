# Gitea API integration for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ikasgela/laravel-gitea.svg?style=flat-square)](https://packagist.org/packages/ikasgela/laravel-gitea)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ikasgela/laravel-gitea/run-tests?label=tests)](https://github.com/ikasgela/laravel-gitea/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ikasgela/laravel-gitea/Check%20&%20fix%20styling?label=code%20style)](https://github.com/ikasgela/laravel-gitea/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ikasgela/laravel-gitea.svg?style=flat-square)](https://packagist.org/packages/ikasgela/laravel-gitea)

Gitea API integration for Laravel.

## Installation

You can install the package via composer:

```bash
composer require ikasgela/laravel-gitea
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Ikasgela\Gitea\GiteaServiceProvider" --tag="laravel-gitea-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$laravel-gitea = new Ikasgela\Gitea();
echo $laravel-gitea->echoPhrase('Hello, Ikasgela!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ion Jaureguialzo Sarasola](https://github.com/ijaureguialzo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
