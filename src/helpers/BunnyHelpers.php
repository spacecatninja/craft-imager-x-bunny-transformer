<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\helpers;

use craft\elements\Asset;
use craft\fs\Local;
use craft\helpers\App;
use spacecatninja\bunnytransformer\BunnyTransformer;
use spacecatninja\bunnytransformer\models\BunnyProfile;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\FileHelper;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\services\ImagerService;
use yii\base\InvalidConfigException;

class BunnyHelpers
{
    /**
     * @param string $name
     *
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
     * @param \craft\elements\Asset|string                        $image
     * @param \spacecatninja\bunnytransformer\models\BunnyProfile $profile
     *
     * @return string
     * @throws \spacecatninja\imagerx\exceptions\ImagerException
     */
    public static function getImagePath(Asset|string $image, BunnyProfile $profile): string
    {
        if (is_string($image)) {
            // assume this is a direct path inside the pul zone
            return ltrim($image, '/');
        }

        try {
            $volume = $image->getVolume();
            $fs = $image->getVolume()->getFs();
        } catch (InvalidConfigException $invalidConfigException) {
            \Craft::error($invalidConfigException->getMessage(), __METHOD__);
            throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
        }

        $urlSegments = [];

        // Add cloud source path if applicable
        if ($profile->useCloudSourcePath) {
            try {
                if (property_exists($fs, 'subfolder') && $fs->subfolder !== '' && $fs::class !== Local::class) {
                    $urlSegments[] = App::parseEnv($fs->subfolder);
                }
            } catch (\Throwable) {

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
     * @param \craft\elements\Asset|string $image
     * @param array|null                   $transform
     *
     * @return string
     */
    public static function getQualityFromExtension(Asset|string $image, array $transform = null): string
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
     * @param \craft\elements\Asset|string $image
     * @param array                        $params
     *
     * @return string
     */
    public static function getCropParamValue(Asset|string $image, array $params): string
    {
        $imageWidth = 0;
        $imageHeight = 0;

        if (is_string($image)) {
            $imageInfo = @getimagesize(App::parseEnv('@webroot/'.ltrim($image, '/')));

            if (\is_array($imageInfo) && $imageInfo[0] !== '' && $imageInfo[1] !== '') {
                [$imageWidth, $imageHeight] = $imageInfo;
            }
        } else {
            $imageWidth = $image->width;
            $imageHeight = $image->height;
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

            $cropX = 0;
            $cropY = floor(($imageHeight - $cropHeight) * ($top / 100));
        } else {
            $cropWidth = ceil($imageHeight / ($params['height'] / $params['width']));
            $cropHeight = $imageHeight;

            $cropX = floor((($imageWidth - $cropWidth) * ($left / 100)));
            $cropY = 0;
        }

        return "$cropWidth,$cropHeight,$cropX,$cropY";
    }

}
