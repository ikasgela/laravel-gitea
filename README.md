# Gitea API integration for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ikasgela/laravel-gitea.svg?style=flat-square)](https://packagist.org/packages/ikasgela/laravel-gitea)
[![Total Downloads](https://img.shields.io/packagist/dt/ikasgela/laravel-gitea.svg?style=flat-square)](https://packagist.org/packages/ikasgela/laravel-gitea)

Gitea API integration for Laravel.

## Installation

You can install the package via composer:

```bash
composer require ikasgela/laravel-gitea
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Ikasgela\Gitea\GiteaServiceProvider"
```

This is the contents of the published config file:

```php
return [
    'url' => env('GITEA_URL'),
    'token' => env('GITEA_TOKEN'),
];
```

## Usage

```php
$repository = GiteaClient::repo('user/repository');
echo $repository['http_url_to_repo'];
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

Apache License 2.0. Please see [License File](LICENSE) for more information.
