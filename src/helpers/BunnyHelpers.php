<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\helpers;

use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\FileHelper;

use craft\volumes\Local;
use spacecatninja\bunnytransformer\BunnyTransformer;
use spacecatninja\bunnytransformer\models\BunnyProfile;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\services\ImagerService;

use yii\base\InvalidConfigException;

class BunnyHelpers
{
    /**
     * @param string $name
     * @return BunnyProfile|null
     */
    public static function getProfile(string $name): ?BunnyProfile
    {
        $settings = BunnyTransformer::$plugin->getSettings();

        if ($settings && isset($settings->profiles[$name])) {
            return new BunnyProfile($settings->profiles[$name]);
        }

        return null;
    }

    /**
     * @param Asset|string $image
     * @param BunnyProfile $profile
     * @return string
     * @throws ImagerException
     */
    public static function getImagePath($image, BunnyProfile $profile): string
    {
        if (is_string($image)) {
            // assume this is a direct path inside the pull zone
            return ltrim($image, '/');
        }

        try {
            $volume = $image->getVolume();
        } catch (InvalidConfigException $invalidConfigException) {
            \Craft::error($invalidConfigException->getMessage(), __METHOD__);
            throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
        }

        $urlSegments = [];

        // Add cloud source path if applicable
        if ($profile->useCloudSourcePath) {
            try {
                if (isset($volume->subfolder) && \get_class($volume) !== 'craft\volumes\Local') {
                    $urlSegments[] = App::parseEnv($volume->subfolder);
                }
            } catch (\Throwable $e) {

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
        $urlSegments[] = $image->path;

        return FileHelper::normalizePath(implode('/', $urlSegments), '/');
    }

    /**
     * Gets the quality setting based on the extension.
     *
     * @param Asset|string $image
     * @param array|null   $transform
     *
     * @return string
     */
    public static function getQualityFromExtension($image, array $transform = null): string
    {
        /** @var ConfigModel $settings */
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
     * @param $image
     * @param array $params
     * @return string
     * @throws InvalidConfigException
     */
    public static function getCropParamValue($image, array $params): string
    {
        $imageWidth = 0;
        $imageHeight = 0;

        // Attempt to get width and height from the file on disk
        $imageInfo = self::getImageSize($image);
        if (!empty($imageInfo)) {
            [$imageWidth, $imageHeight] = $imageInfo;
        } else if ($image instanceof Asset) {
            // Fall back to width and height from the database for assets
            $imageWidth = $image->width ?? 0;
            $imageHeight = $image->height ?? 0;
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

    /**
     * @param $image
     * @return array|null
     * @throws InvalidConfigException
     */
    private static function getImageSize($image): ?array
    {
        if ($image instanceof Asset && $image->getVolume() instanceof Local) {
            $imagePath = App::parseEnv(rtrim($image->getVolume()->path ?? '', '/') . DIRECTORY_SEPARATOR . rtrim($image->path, '/'));
        } else if (is_string($image)) {
            $imagePath = App::parseEnv('@webroot/'.ltrim($image, '/'));
        } else {
            return null;
        }
        $imageInfo = @getimagesize($imagePath);
        if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return null;
        }

        return $imageInfo;
    }

}
