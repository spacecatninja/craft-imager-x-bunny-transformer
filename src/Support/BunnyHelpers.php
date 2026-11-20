<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer\Support;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\File;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Log;
use SpaceCatNinja\BunnyTransformer\Data\BunnyProfile;
use SpaceCatNinja\BunnyTransformer\Settings;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Services\ImagerService;

class BunnyHelpers
{
    /**
     * @param string $name
     *
     * @return BunnyProfile|null
     */
    public static function getProfile(string $name): ?BunnyProfile
    {
        $settings = app(Settings::class);

        if (isset($settings->profiles[$name])) {
            return new BunnyProfile($settings->profiles[$name]);
        }

        return null;
    }

    /**
     * Returns the path of an image in the pull zone.
     *
     * @param Asset|string $image
     * @param BunnyProfile $profile
     *
     * @return string
     * @throws ImagerException
     */
    public static function getImagePath(Asset|string $image, BunnyProfile $profile): string
    {
        if (is_string($image)) {
            // assume this is a direct path inside the pull zone
            return ltrim($image, '/');
        }

        try {
            $volume = $image->getVolume();
            $disk = $volume->sourceDisk();
        } catch (\Throwable $throwable) {
            Log::error($throwable->getMessage());
            throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $urlSegments = [];

        // Add cloud source path if applicable: the disk's root, and the volume's subpath
        if ($profile->useCloudSourcePath && !$disk instanceof LocalFilesystemAdapter) {
            $cloudSourcePath = trim($disk->path(''), '/');

            if ($cloudSourcePath !== '') {
                $urlSegments[] = $cloudSourcePath;
            }
        }

        // Add addPath if applicable
        if (!empty($profile->addPath)) {
            if (\is_string($profile->addPath) && $profile->addPath !== '') {
                $urlSegments[] = $profile->addPath;
            } elseif (is_array($profile->addPath)) {
                if (isset($profile->addPath[$volume->handle])) {
                    $urlSegments[] = $profile->addPath[$volume->handle];
                }
            }
        }

        // Add file path
        $urlSegments[] = $image->getPath();

        return File::normalizePath(implode('/', $urlSegments), '/');
    }

    /**
     * Gets the file extension of an image, in lowercase.
     *
     * @param Asset|string $image The image asset, or a path or URL.
     *
     * @return string
     */
    public static function getExtension(Asset|string $image): string
    {
        if (is_string($image)) {
            return strtolower(pathinfo((string)parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION));
        }

        return strtolower($image->getExtension());
    }

    /**
     * Gets the quality setting based on the extension, or the format the image is transformed to.
     *
     * @param string     $ext
     * @param array|null $transform
     *
     * @return int
     */
    public static function getQualityFromExtension(string $ext, ?array $transform = null): int
    {
        $config = ImagerService::getConfig();

        switch (strtolower($ext)) {
            case 'png':
                $pngCompression = $config->getSetting('pngCompressionLevel', $transform);

                return max(100 - ($pngCompression * 10), 1);
            case 'webp':
                return $config->getSetting('webpQuality', $transform);
            case 'avif':
                return $config->getSetting('avifQuality', $transform);
            case 'jxl':
                return $config->getSetting('jxlQuality', $transform);
        }

        return $config->getSetting('jpegQuality', $transform);
    }

    /**
     * Returns the value of Bunny's `format` param for a format. Bunny calls JPEG `jpeg`, not `jpg`.
     *
     * @param string $format
     *
     * @return string
     */
    public static function prepFormat(string $format): string
    {
        $format = strtolower($format);

        return $format === 'jpg' ? 'jpeg' : $format;
    }

    /**
     * Returns the size of an image, or zeros if it can't be read. Paths are looked up in the webroot.
     *
     * @param Asset|string $image
     *
     * @return int[] The width and height.
     */
    public static function getSourceSize(Asset|string $image): array
    {
        if (is_string($image)) {
            $imageInfo = @getimagesize(Env::parse('@webroot/'.ltrim($image, '/')));

            if (\is_array($imageInfo) && $imageInfo[0] !== '' && $imageInfo[1] !== '') {
                return [(int)$imageInfo[0], (int)$imageInfo[1]];
            }

            return [0, 0];
        }

        return [(int)$image->getWidth(), (int)$image->getHeight()];
    }

    /**
     * Returns the size of the area that a crop transform cuts from the source image: as much of the image as fits
     * the transform's aspect ratio.
     *
     * The side that doesn't span the whole image is rounded down. Bunny resizes the area to fit inside the
     * transform's size, so the other side then gets the transform's exact size.
     *
     * @param int       $sourceWidth
     * @param int       $sourceHeight
     * @param int|float $width        The transform's width.
     * @param int|float $height       The transform's height.
     *
     * @return int[] The width and height.
     */
    public static function getCropSize(int $sourceWidth, int $sourceHeight, int|float $width, int|float $height): array
    {
        if ($width / $height > $sourceWidth / $sourceHeight) {
            return [$sourceWidth, max(1, (int)floor($sourceWidth / ($width / $height)))];
        }

        return [max(1, (int)floor($sourceHeight / ($height / $width))), $sourceHeight];
    }

    /**
     * Returns whether a transform asks for a larger image than the source. Bunny only enlarges images when its
     * `upscaling` param is `resampling`.
     *
     * Mode `crop` cuts the transform's aspect ratio from the source, and resizes that to the transform's size. Bunny
     * has no other modes, so every other mode fits the image inside the size.
     *
     * @param int   $sourceWidth
     * @param int   $sourceHeight
     * @param array $params       The transform's params, with `width`, `height` and `mode`.
     *
     * @return bool
     */
    public static function isEnlarging(int $sourceWidth, int $sourceHeight, array $params): bool
    {
        $width = (int)($params['width'] ?? 0);
        $height = (int)($params['height'] ?? 0);

        if ($sourceWidth === 0 || $sourceHeight === 0) {
            return false;
        }

        if ($width && $height) {
            if (($params['mode'] ?? 'crop') === 'crop') {
                [$cropWidth, $cropHeight] = self::getCropSize($sourceWidth, $sourceHeight, $width, $height);

                return $width > $cropWidth || $height > $cropHeight;
            }

            return $width > $sourceWidth && $height > $sourceHeight;
        }

        if ($width) {
            return $width > $sourceWidth;
        }

        return $height > $sourceHeight;
    }

    /**
     * Returns the size Bunny resizes an image or crop area to, when fitting it inside a size. Bunny scales by the
     * smaller of the two ratios, only enlarges with upscaling, and rounds halves to even.
     *
     * @param int       $width   The width of the image or crop area.
     * @param int       $height  The height of the image or crop area.
     * @param int|float $boxWidth
     * @param int|float $boxHeight
     * @param bool      $upscale
     *
     * @return int[] The width and height.
     */
    public static function getFitSize(int $width, int $height, int|float $boxWidth, int|float $boxHeight, bool $upscale): array
    {
        $scale = min($boxWidth / $width, $boxHeight / $height);

        if (!$upscale) {
            $scale = min($scale, 1);
        }

        return [self::round($width * $scale), self::round($height * $scale)];
    }

    /**
     * Rounds a size like Bunny does, halves to even.
     *
     * @param int|float $value
     *
     * @return int
     */
    public static function round(int|float $value): int
    {
        // Avoids float noise like 562.50000000001 deciding a half
        return (int)round(round($value, 6), 0, PHP_ROUND_HALF_EVEN);
    }

    /**
     * Returns the value of Bunny's `aspect_ratio` param for a size, as the smallest whole numbers, e.g. `3:2`.
     *
     * @param int|float $width
     * @param int|float $height
     *
     * @return string
     */
    public static function getAspectRatioValue(int|float $width, int|float $height): string
    {
        $width = (int)round($width);
        $height = (int)round($height);
        [$a, $b] = [$width, $height];

        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a > 0 ? ($width / $a) . ':' . ($height / $a) : "$width:$height";
    }

    /**
     * Creates the crop parameter string
     *
     * @param Asset|string $image
     * @param array        $params
     *
     * @return string
     */
    public static function getCropParamValue(Asset|string $image, array $params): string
    {
        [$imageWidth, $imageHeight] = self::getSourceSize($image);

        if ($imageWidth === 0 || $imageHeight === 0) {
            return '';
        }

        if (isset($params['position'])) {
            $focalPoint = explode(' ', $params['position']);
            $left = (float)($focalPoint[0] ?? 50);
            $top = (float)($focalPoint[1] ?? 50);
        } else if (is_string($image)) {
            $config = ImagerService::getConfig();
            $focalPoint = explode(' ', $config->position);
            $left = (float)($focalPoint[0] ?? 50);
            $top = (float)($focalPoint[1] ?? 50);
        } else {
            $left = $image->getFocalPoint()['x'] * 100;
            $top = $image->getFocalPoint()['y'] * 100;
        }

        [$cropWidth, $cropHeight] = self::getCropSize($imageWidth, $imageHeight, $params['width'], $params['height']);

        // Calculate absolute pixels for the original focal point x and y
        $focalX = floor($imageWidth * ($left / 100));
        $focalY = floor($imageHeight * ($top / 100));

        // Calculate absolute pixels for the crop origin x and y
        $cropX = floor($focalX - ($cropWidth  / 2));
        $cropY = floor($focalY - ($cropHeight / 2));

        // Clamp the crop origin x and y to image bounds
        $cropX = max(0, min($cropX, $imageWidth  - $cropWidth));
        $cropY = max(0, min($cropY, $imageHeight - $cropHeight));

        return "$cropWidth,$cropHeight,$cropX,$cropY";
    }

}
