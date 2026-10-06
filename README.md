# Snapshot-based version history and restore for Filament resources

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elvin-qulizade/filament-versions.svg?style=flat-square)](https://packagist.org/packages/elvin-qulizade/filament-versions)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/elvin-qulizade/filament-versions/tests.yml?branch=5.x&label=tests&style=flat-square)](https://github.com/elvin-qulizade/filament-versions/actions?query=workflow%3Atests+branch%3A5.x)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/elvin-qulizade/filament-versions/fix-code-style.yml?branch=5.x&label=code%20style&style=flat-square)](https://github.com/elvin-qulizade/filament-versions/actions?query=workflow%3Afix-code-style+branch%3A5.x)
[![Total Downloads](https://img.shields.io/packagist/dt/elvin-qulizade/filament-versions.svg?style=flat-square)](https://packagist.org/packages/elvin-qulizade/filament-versions)



Adds WordPress-style version history to any Eloquent model used in a Filament v4 or v5 resource: every save is snapshotted, a "History" tab shows the timeline with a field-level diff, and any past version can be restored with one click.

## Installation

You can install the package via composer:

```bash
composer require elvin-qulizade/filament-versions
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels follow the instructions in the [Filament Docs](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme) first.

After setting up a custom theme add the plugin's views to your theme css file or your app's css file if using the standalone packages.

```css
@source '../../../../vendor/elvin-qulizade/filament-versions/resources/**/*.blade.php';
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-versions-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-versions-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-versions-views"
```

This is the contents of the published config file:

```php
return [
    // Attributes never stored in a version snapshot, on top of any
    // model-level $versionExcept property.
    'excluded_attributes' => [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ],

    // Maximum number of versions kept per model instance. Older versions
    // are pruned automatically after each save, and via `versions:prune`.
    'max_versions_per_model' => 50,

    // Optional callable(Version $version): bool to gate the "Restore" action.
    // Left null, restoring is allowed for anyone who can see the History tab.
    'authorize_restore' => null,

    // Global retroactive cleanup, on top of the automatic per-save pruning
    // above. Off by default since per-save pruning already keeps storage
    // bounded for new versions.
    'schedule' => [
        'enabled' => false,
        'cron' => '0 3 * * *',
    ],
];
```

## Usage

1. Add the trait and contract to any Eloquent model you want version history for:

```php
use ElvinQulizade\Versions\Concerns\HasVersions;
use ElvinQulizade\Versions\Contracts\Versionable;

class Post extends Model implements Versionable
{
    use HasVersions;
}
```

2. Register the History tab on the model's Filament resource:

```php
use ElvinQulizade\Versions\Filament\RelationManagers\VersionsRelationManager;

public static function getRelations(): array
{
    return [
        VersionsRelationManager::class,
    ];
}
```

That's it — every create/update is snapshotted automatically, the Edit page gets a "History" tab with a timeline, a field-level diff per version, and a one-click "Restore" action. To exclude fields from just one model in code, define `protected array $versionExcept = [...]` on it — or use the "Excluded fields" button on the History tab to manage it from the panel instead, per model class.

Select any two rows in the History table and use the "Compare" bulk action to diff them directly against each other, instead of only against the previous version.

Soft-deleted owner records keep working: their history and restore action remain reachable even while trashed.

Old versions beyond `max_versions_per_model` are pruned automatically after each save. To prune retroactively (e.g. after lowering the limit), run:

```bash
php artisan versions:prune
php artisan versions:prune --model="App\Models\Post" --keep=20
```

To run that automatically instead, set `schedule.enabled` to `true` in the config file (and `schedule.cron` if you want something other than daily at 3am).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Elvin-Qulizade](https://github.com/Elvin-Qulizade)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
