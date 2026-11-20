<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\transformers;

use craft\base\Component;
use craft\elements\Asset;

use craft\helpers\UrlHelper;
use spacecatninja\bunnytransformer\BunnyTransformer;
use spacecatninja\bunnytransformer\helpers\BunnyHelpers;
use spacecatninja\bunnytransformer\models\BunnyTransformedImageModel;
use spacecatninja\bunnytransformer\models\Settings;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\transformers\TransformerInterface;
use spacecatninja\imagerx\exceptions\ImagerException;

class Bunny extends Component implements TransformerInterface
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
        'upscaling',
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
        /** @var Settings $settings */
        $settings = BunnyTransformer::$plugin->getSettings();
        $config = ImagerService::getConfig();
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
        
        // Get quality, from the format the image is transformed to, or the image's own
        if (!isset($params['quality'])) {
            $params['quality'] = BunnyHelpers::getQualityFromFormat($params['format'] ?? BunnyHelpers::getExtension($image), $params);
        }

        $mode = $params['mode'] ?? 'crop';
        [$sourceWidth, $sourceHeight] = BunnyHelpers::getSourceSize($image);
        $hasSourceSize = $sourceWidth !== 0 && $sourceHeight !== 0;

        // Bunny essentially only supports `crop`. Every other mode fits the image inside the size, like `fit`.
        if (isset($params['width'], $params['height']) && $mode === 'crop') {
            if ($hasSourceSize) {
                $params['crop'] = BunnyHelpers::getCropParamValue($image, $params);
            } elseif (!isset($params['aspect_ratio'])) {
                // Without the source's size, the crop area can't be calculated, so crop to the ratio around the center
                $params['aspect_ratio'] = BunnyHelpers::getAspectRatioValue($params['width'], $params['height']);
            }
        }

        // Bunny only enlarges images when `upscaling` is `resampling`, which can be set in transformerParams
        $upscale = ($params['upscaling'] ?? 'off') === 'resampling';

        // Without a width, Bunny resizes images to the pull zone's max width when Smart Image Optimization is on, by
        // default 1600 on desktop and 800 on mobile, so send the width the image is expected to have
        if (!isset($params['width']) && $hasSourceSize) {
            $params['width'] = isset($params['height']) ? (int)ceil($params['height'] * $sourceWidth / $sourceHeight) : $sourceWidth;
        }

        // Prune params and fix bools
        $params = array_intersect_key($params, array_flip(self::$validParams));
        
        foreach ($params as $key => $val) {
            if (is_bool($val)) {
                $params[$key] = $val ? 'true' : 'false';
            }
        }
        
        // Create the final URL
        $url = UrlHelper::url($url, $params);
        
        return new BunnyTransformedImageModel($url, $image, $transform, $upscale);
    }
    
}
