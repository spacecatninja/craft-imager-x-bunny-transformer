# Upgrading to Bunny.net transformer 3 (Craft 6)

This guide lists what changed in version 3 of the Bunny.net transformer for Imager X, and what you need to update in your project and custom code. It applies when upgrading from version 2 (Craft 5 and Imager X 5 or 6).

Templates that transform with Bunny through Imager X (`craft.imagerx.transformImage()` with the `bunny` transformer) aren't affected. Projects need to move and convert the config file. The class changes only affect custom code that uses the plugin's classes directly, for example to purge images.

## Requirements

- Craft CMS 6.0.0 or later
- Imager X 7.0.0-beta.2 or later, Pro edition
- PHP 8.5 or later
- The plugin doesn't need the Yii 2 adapter (`craftcms/yii2-adapter`).

## Upgrade steps

1. Upgrade your project to Craft 6, and Imager X to version 7.
2. Update the plugin: `composer require spacecatninja/imager-x-bunny-transformer:^3.0.0`.
3. Move your config file to `config/craft/imager-x-bunny-transformer.php`, and convert it (see below).
4. Review the breaking changes below, and update your code.

## Breaking changes

### The config file

Craft 6 reads plugin config files from `config/craft/`, as plain Laravel config. Move `config/imager-x-bunny-transformer.php` to `config/craft/imager-x-bunny-transformer.php`, return a flat array instead of the multi-environment `'*'` format, and use `env()` instead of `craft\helpers\App::env()`. The settings themselves are unchanged.

Before:

```php
use craft\helpers\App;

return [
    '*' => [
        'defaultProfile' => 'default',
        'apiKey' => App::env('BUNNY_API_KEY'),
        'autoPurge' => true,
        'profiles' => [
            'default' => [
                'hostname' => 'my-zone.b-cdn.net',
            ],
        ],
    ],
];
```

After:

```php
return [
    'defaultProfile' => 'default',
    'apiKey' => env('BUNNY_API_KEY', ''),
    'autoPurge' => true,
    'profiles' => [
        'default' => [
            'hostname' => 'my-zone.b-cdn.net',
        ],
    ],
];
```

Config files are loaded before Laravel knows the environment, so use `env()` for per-environment values, not `app()->environment()`. For example: `'hostname' => env('APP_ENV') === 'production' ? 'my-zone.b-cdn.net' : 'my-staging-zone.b-cdn.net'`.

### The cloud source path

With a profile's `useCloudSourcePath` setting on, the path of an asset in the pull zone starts with the asset's path in cloud storage. On Craft 5 that was the filesystem's subfolder. Craft 6 volumes use Laravel filesystem disks, so it's now the `root` of the volume's disk, followed by the volume's subpath. Version 2 left out the volume's subpath.

If your S3 or GCS disk's `root` matches the subfolder your Craft 5 filesystem had, and the volume has no subpath, the URLs are unchanged. If you added the volume's subpath with `addPath` to work around it, remove it from `addPath`.

### Transform URLs

Some transform URLs change, so Bunny processes those images again the first time they're requested:

- A transform larger than the source image now sends Bunny's `upscaling` parameter, from Imager X's `allowUpscale` setting: `resampling` when it's on (Imager X's default), and `off` when it's off. Bunny only enlarges images with `resampling`, and version 2 didn't send it, so these images now get enlarged while `allowUpscale` is on. Set `allowUpscale` to `false` to keep images no larger than their source.
- When the transformer can't read the source image's size (a path that isn't in the webroot, or an external URL), every transform with a size sends `upscaling=resampling` while `allowUpscale` is on, and crop transforms send `aspect_ratio` instead of an empty `crop`, so they're cropped around the center.
- A transform with `format: 'jpg'` now sends `format=jpeg`, the value Bunny documents.
- A transform with `format` set to `webp`, `avif` or `png` now gets its quality from `webpQuality`, `avifQuality` or `pngCompressionLevel`. Version 2 always used `jpegQuality` when `format` was set. A `quality` in a profile's `defaultParams` or the transform's `transformerParams` still wins.
- Assets with `useCloudSourcePath`, see above.

### Namespace and classes

The plugin moved from `spacecatninja\bunnytransformer` to the `SpaceCatNinja\BunnyTransformer` namespace, with Laravel-style folders.

| Before | After |
| --- | --- |
| `spacecatninja\bunnytransformer\BunnyTransformer` | `SpaceCatNinja\BunnyTransformer\BunnyTransformer` |
| `spacecatninja\bunnytransformer\models\Settings` | `SpaceCatNinja\BunnyTransformer\Settings` |
| `spacecatninja\bunnytransformer\models\BunnyProfile` | `SpaceCatNinja\BunnyTransformer\Data\BunnyProfile` |
| `spacecatninja\bunnytransformer\models\BunnyTransformedImageModel` | `SpaceCatNinja\BunnyTransformer\Data\BunnyTransformedImageModel` |
| `spacecatninja\bunnytransformer\services\BunnyService` | `SpaceCatNinja\BunnyTransformer\Services\BunnyService` |
| `spacecatninja\bunnytransformer\helpers\BunnyHelpers` | `SpaceCatNinja\BunnyTransformer\Support\BunnyHelpers` |
| `spacecatninja\bunnytransformer\transformers\Bunny` | `SpaceCatNinja\BunnyTransformer\Transformers\Bunny` |
| `spacecatninja\bunnytransformer\elementactions\BunnyPurgeElementAction` | `SpaceCatNinja\BunnyTransformer\ElementActions\BunnyPurgeElementAction` |

`BunnyTransformedImageModel`'s constructor takes a fourth argument, `$upscale`, for whether Bunny may enlarge the image. Without it, sizes are calculated as if the image is never enlarged, like Bunny does by default. Images transformed with any mode but `crop` are sized to fit inside the transform's size.

`BunnyTransformedImageModel` extends Imager X 7's `SpaceCatNinja\ImagerX\Data\BaseTransformedImageModel`, and `BunnyProfile` extends Craft 6's `CraftCms\Cms\Component\Component`. The `Bunny` transformer and `BunnyService` no longer extend `craft\base\Component`, and the classes throw Imager X 7's `SpaceCatNinja\ImagerX\Exceptions\ImagerException`.

### The service

`BunnyTransformer::$plugin` and the `bunny` service component on the plugin were removed. `BunnyService` is a container singleton, so inject it, or resolve it with `app()`. Its methods are unchanged.

Before:

```php
use spacecatninja\bunnytransformer\BunnyTransformer;

BunnyTransformer::$plugin->bunny->purgeAssetFromBunny($asset);
```

After:

```php
use SpaceCatNinja\BunnyTransformer\Services\BunnyService;

app(BunnyService::class)->purgeAssetFromBunny($asset);
```

Read the plugin settings with `app(SpaceCatNinja\BunnyTransformer\Settings::class)` instead of `BunnyTransformer::$plugin->getSettings()`.

### Helpers

`BunnyHelpers::getQualityFromExtension()` now takes a file extension or format, and returns an integer. Use `BunnyHelpers::getExtension()` to get the extension of an asset, path or URL.

Before:

```php
$quality = BunnyHelpers::getQualityFromExtension($asset, $transform);
```

After:

```php
$quality = BunnyHelpers::getQualityFromExtension(BunnyHelpers::getExtension($asset), $transform);
```

`BunnyHelpers::prepFormat()` returns the value of Bunny's `format` parameter for a format.

### Auto-purging

The auto-purge event handlers are now the `SpaceCatNinja\BunnyTransformer\Listeners\PurgeFromBunny` listener, registered for Craft 6's `AssetReplaced`, `ElementDeleted` and `ElementSaving` events. When Imager X's `removeTransformsOnAssetFileops` setting is on, an asset that's moved without being renamed is now purged too. Version 2 only purged when the filename changed.

### Element action

The "Purge from Bunny" element action now only purges the selected images that the current user can view.
