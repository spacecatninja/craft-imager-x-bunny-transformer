# Bunny.net transformer for Imager X Changelog

## 3.0.0 - Unreleased

> [!IMPORTANT]
> Version 3 requires Craft CMS 6, Imager X 7 and PHP 8.5, the config file moves to `config/craft/`, and there are breaking changes for code that uses the plugin's classes. See [UPGRADE.md](UPGRADE.md).

### Added
- Added `BunnyHelpers::getExtension()` and `BunnyHelpers::prepFormat()`.

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

### Removed
- Removed the `BunnyTransformer::$plugin` property and the plugin's `bunny` component.

### Fixed
- Fixed `webpQuality`, `avifQuality` and `pngCompressionLevel` being ignored when a transform set `format`. The quality came from `jpegQuality`.
- Fixed format `jpg` being sent to Bunny as `jpg`. Bunny's `format` parameter takes `jpeg`.
- Fixed the quality for the source image's format not being found when its extension was uppercase, or its URL had a query string.
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
