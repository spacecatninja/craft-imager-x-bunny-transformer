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
     * Gets the quality setting based on the extension.
     *
     * @param Asset|string $image
     * @param array|null   $transform
     *
     * @return string
     */
    public static function getQualityFromExtension(Asset|string $image, ?array $transform = null): string
    {
        $config = ImagerService::getConfig();

        if (is_string($image)) {
            $ext = pathinfo($image, PATHINFO_EXTENSION);
        } else {
            $ext = $image->getExtension();
        }

        switch ($ext) {
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
     * Creates the crop parameter string
     *
     * @param Asset|string $image
     * @param array        $params
     *
     * @return string
     */
    public static function getCropParamValue(Asset|string $image, array $params): string
    {
        $imageWidth = 0;
        $imageHeight = 0;

        if (is_string($image)) {
            $imageInfo = @getimagesize(Env::parse('@webroot/'.ltrim($image, '/')));

            if (\is_array($imageInfo) && $imageInfo[0] !== '' && $imageInfo[1] !== '') {
                [$imageWidth, $imageHeight] = $imageInfo;
            }
        } else {
            $imageWidth = (int)$image->getWidth();
            $imageHeight = (int)$image->getHeight();
        }

        if ($imageWidth === 0 || $imageHeight === 0) {
            return '';
        }

        $transformRatio = $params['width'] / $params['height'];
        $assetRatio = $imageWidth / $imageHeight;

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

        if ($transformRatio > $assetRatio) {
            $cropWidth = $imageWidth;
            $cropHeight = ceil($imageWidth / ($params['width'] / $params['height']));
        } else {
            $cropWidth = ceil($imageHeight / ($params['height'] / $params['width']));
            $cropHeight = $imageHeight;
        }

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
