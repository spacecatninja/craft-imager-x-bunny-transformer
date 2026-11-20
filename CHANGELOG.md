# Bunny.net transformer for Imager X Changelog

## 3.0.0-beta.1 - 2026-11-20

> [!IMPORTANT]
> Version 3 requires Craft CMS 6, Imager X 7 and PHP 8.5, the config file moves to `config/craft/`, and there are breaking changes for code that uses the plugin's classes. See [UPGRADE.md](UPGRADE.md).

### Added
- Added `BunnyHelpers::getExtension()`, `BunnyHelpers::prepFormat()`, `BunnyHelpers::getSourceSize()`, `BunnyHelpers::getCropSize()`, `BunnyHelpers::isEnlarging()`, `BunnyHelpers::getFitSize()`, `BunnyHelpers::round()` and `BunnyHelpers::getAspectRatioValue()`.
- Added support for Bunny's `upscaling` parameter in `transformerParams`.

### Changed
- The plugin now requires Craft CMS 6, Imager X 7.0.0-beta.2 or later, and PHP 8.5, and uses Craft 6's native plugin APIs. It doesn't need the Yii 2 adapter.
- The config file is now `config/craft/imager-x-bunny-transformer.php`, and is a plain Laravel config file.
- Classes moved to the `SpaceCatNinja\BunnyTransformer` namespace, and to Laravel-style folders.
- The transformer is now registered with Imager X's `Transformers` registry.
- `BunnyService` is now a container singleton instead of the plugin's `bunny` component, and purges with Laravel's HTTP client.
- The auto-purge event handlers are now the `PurgeFromBunny` listener, for Craft 6's events. When Imager X's `removeTransformsOnAssetFileops` setting is on, assets that are moved without being renamed are now purged too.
- With `useCloudSourcePath`, the cloud source path is now the `root` of the volume's filesystem disk followed by the volume's subpath. Version 2 used the filesystem's subfolder, and left out the volume's subpath.
- The "Purge from Bunny" element action now only purges the selected images that the current user can view.
- `BunnyHelpers::getQualityFromExtension()` now takes a file extension or format instead of an image, and returns an integer.
- Format `jpg` is now sent to Bunny as `jpeg`, the value Bunny documents.
- `BunnyTransformedImageModel`'s constructor takes an optional `$upscale` argument, for whether Bunny may enlarge the image.

### Removed
- Removed the `BunnyTransformer::$plugin` property and the plugin's `bunny` component.

### Fixed
- Fixed `webpQuality`, `avifQuality` and `pngCompressionLevel` being ignored when a transform set `format`. The quality came from `jpegQuality`.
- Fixed the quality for the source image's format not being found when its extension was uppercase, or its URL had a query string.
- Fixed transforms larger than the source image. Bunny only enlarges images when its `upscaling` parameter is `resampling`, and the transformer didn't send it, but reported the enlarged size. It now sends `upscaling` from `allowUpscale` when a transform is larger than the source, and reports the size Bunny makes.
- Fixed the size of images transformed with modes `letterbox`, `stretch` and `croponly`. Bunny fits them inside the size like mode `fit`, but the full size was reported.
- Fixed crop transforms of images whose size couldn't be read not being cropped. An empty `crop` parameter was sent, and they're now cropped around the center with Bunny's `aspect_ratio` parameter.
- Fixed transforms with only a `height`, or no size, being resized to the pull zone's max width when Bunny's Smart Image Optimization was on, which also made their size depend on the device. The transformer now sends the width when it can read the source image's size.
- Fixed crop transforms sometimes being a pixel narrower or shorter than the transform's size. The crop area is now rounded down, and sizes are rounded like Bunny rounds them.
- Fixed the README saying only moved assets need `removeTransformsOnAssetFileops` to be auto-purged. Deleted assets need it too.

## 2.1.0 - 2026-03-21

### Added
- Added purging of transforms via the Bunny API.

## 2.0.2 - 2025-09-01

### Fixed
- Fixed an issue that could result in a division by zero error.

## 2.0.1 - 2025-08-21

### Added
- Added `defaultParams` config setting to profiles

### Fixed
- Fixed a bug where the crop position could be miscalculated

## 2.0.0 - 2024-07-19

### Added
- Initial Craft 5 release
