# Bunny.net transformer for Imager X Changelog

## 2.2.0 - Unreleased

> [!NOTE]
> Some transform URLs change, so Bunny processes those images again the first time they're requested: transforms with `format` set to `webp`, `avif` or `png`, crop transforms of images whose size can't be read, transforms with only a `height` or no size, and some crops, whose crop area is a pixel smaller.

### Added
- Added `BunnyHelpers::getExtension()`, `BunnyHelpers::getQualityFromFormat()`, `BunnyHelpers::getSourceSize()`, `BunnyHelpers::getCropSize()`, `BunnyHelpers::getFitSize()`, `BunnyHelpers::round()` and `BunnyHelpers::getAspectRatioValue()`.
- Added support for Bunny's `upscaling` parameter in `transformerParams` and a profile's `defaultParams`. Bunny only enlarges images when it's `resampling`.

### Changed
- `BunnyTransformedImageModel`'s constructor takes an optional `$upscale` argument, for whether Bunny may enlarge the image.

### Fixed
- Fixed `webpQuality`, `avifQuality` and `pngCompressionLevel` being ignored when a transform set `format`, and the quality for the source image's format not being found when its extension was uppercase, or its URL had a query string.
- Fixed the reported size of transforms larger than the source image. Bunny doesn't enlarge images unless its `upscaling` parameter is `resampling`, so they're now reported at the size Bunny makes.
- Fixed the size of images transformed with modes `letterbox`, `stretch` and `croponly`. Bunny fits them inside the size like mode `fit`, but the full size was reported.
- Fixed crop transforms of images whose size couldn't be read not being cropped. An empty `crop` parameter was sent, and they're now cropped around the center with Bunny's `aspect_ratio` parameter.
- Fixed transforms with only a `height`, or no size, being resized to the pull zone's max width when Bunny's Smart Image Optimization was on, which also made their size depend on the device. The transformer now sends the width when it can read the source image's size.
- Fixed crop transforms sometimes being a pixel narrower or shorter than the transform's size. The crop area is now rounded down, and sizes are rounded like Bunny rounds them.

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
