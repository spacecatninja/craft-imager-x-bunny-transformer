<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer\Transformers;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Support\Url;
use SpaceCatNinja\BunnyTransformer\Data\BunnyTransformedImageModel;
use SpaceCatNinja\BunnyTransformer\Settings;
use SpaceCatNinja\BunnyTransformer\Support\BunnyHelpers;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Transformers\TransformerInterface;

class Bunny implements TransformerInterface
{

    public static array $validParams = [
        'width',
        'height',
        'aspect_ratio',
        'quality',
        'sharpen',
        'blur',
        'crop',
        'crop_gravity',
        'flip',
        'flop',
        'brightness',
        'saturation',
        'hue',
        'contrast',
        'auto_optimize',
        'sepia',
        'class',
        'format',
    ];

    /**
     * @param Asset|string $image
     * @param array $transforms
     *
     * @return array|null
     *
     * @throws ImagerException
     */
    public function transform(Asset|string $image, array $transforms): ?array
    {
        $transformedImages = [];

        foreach ($transforms as $transform) {
            $transformedImages[] = $this->getTransformedImage($image, $transform);
        }

        return $transformedImages;
    }

    /**
     * @param Asset|string $image
     * @param array $transform
     *
     * @return BunnyTransformedImageModel
     * @throws ImagerException
     */
    private function getTransformedImage(Asset|string $image, array $transform): BunnyTransformedImageModel
    {
        $settings = app(Settings::class);
        $transformerParams = $transform['transformerParams'] ?? [];

        $profileName = $transformerParams['profile'] ?? $settings->defaultProfile;
        $profile = BunnyHelpers::getProfile($profileName);

        if ($profile === null) {
            throw new ImagerException("No profile with name `$profileName` exists.");
        }

        $path = BunnyHelpers::getImagePath($image, $profile);

        $url = (!str_starts_with($profile->hostname, 'http') ? 'https://' : '') . rtrim($profile->hostname, '/') . '/' . $path;

        // Get applicable params
        $params = [...$profile->defaultParams, ...$transform, ...$transformerParams];

        // get quality
        if (!isset($params['quality'])) {
            if (isset($params['format'])) {
                $params['quality'] = BunnyHelpers::getQualityFromExtension($params['format'], $params);
            } else {
                $params['quality'] = BunnyHelpers::getQualityFromExtension($image, $params);
            }
        }

        $mode = $params['mode'] ?? 'crop';

        // Bunny essentially only supports `fit` and `crop`
        if (isset($params['width'], $params['height']) && $mode === 'crop') {
            $params['crop'] = BunnyHelpers::getCropParamValue($image, $params);
        }

        // Prune params and fix bools
        $params = array_intersect_key($params, array_flip(self::$validParams));

        foreach ($params as $key => $val) {
            if (is_bool($val)) {
                $params[$key] = $val ? 'true' : 'false';
            }
        }

        // Create the final URL
        $url = Url::url($url, $params);

        return new BunnyTransformedImageModel($url, $image, $transform);
    }

}
