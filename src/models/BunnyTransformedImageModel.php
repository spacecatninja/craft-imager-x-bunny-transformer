<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\models;

use craft\elements\Asset;
use spacecatninja\bunnytransformer\helpers\BunnyHelpers;
use spacecatninja\imagerx\models\BaseTransformedImageModel;
use spacecatninja\imagerx\models\TransformedImageInterface;

class BunnyTransformedImageModel extends BaseTransformedImageModel implements TransformedImageInterface
{
    /**
     * BunnyTransformedImageModel constructor.
     *
     * @param string|null       $imageUrl
     * @param Asset|string|null $source
     * @param array             $transform
     * @param bool              $upscale   Whether Bunny may enlarge the image, which it does when its `upscaling`
     *                                     param is `resampling`. Without it, sizes are calculated as if the image is
     *                                     never enlarged, like Bunny does by default.
     */
    public function __construct(?string $imageUrl = null, Asset|string|null $source = null, array $transform = [], bool $upscale = false)
    {
        if ($imageUrl !== null) {
            $this->url = $imageUrl;
        }

        $mode = $transform['mode'] ?? 'crop';

        if (isset($transform['width'], $transform['height'])) {
            $this->width = (int)$transform['width'];
            $this->height = (int)$transform['height'];

            [$sourceWidth, $sourceHeight] = $source !== null ? $this->getSourceImageDimensions($source) : [0, 0];

            if ($sourceWidth !== 0 && $sourceHeight !== 0) {
                $transformW = (int)$transform['width'];
                $transformH = (int)$transform['height'];

                if ($mode === 'crop') {
                    // The crop area is fitted inside the size, and only enlarged with upscaling
                    [$cropWidth, $cropHeight] = BunnyHelpers::getCropSize($sourceWidth, $sourceHeight, $transformW, $transformH);
                    [$this->width, $this->height] = BunnyHelpers::getFitSize($cropWidth, $cropHeight, $transformW, $transformH, $upscale);
                } else {
                    // Bunny fits the image inside the size in every other mode, and only enlarges it with upscaling
                    [$this->width, $this->height] = BunnyHelpers::getFitSize($sourceWidth, $sourceHeight, $transformW, $transformH, $upscale);
                }
            }
        } else if (isset($transform['width']) || isset($transform['height'])) {
            if ($source !== null) {
                [$sourceWidth, $sourceHeight] = $this->getSourceImageDimensions($source);
                [$w, $h] = $this->calculateTargetSize($transform, $sourceWidth ?: 1, $sourceHeight ?: 1);

                // Bunny only enlarges the image with upscaling
                if (!$upscale && $sourceWidth !== 0 && $sourceHeight !== 0 && ($w > $sourceWidth || $h > $sourceHeight)) {
                    [$w, $h] = [$sourceWidth, $sourceHeight];
                }

                $this->width = $w;
                $this->height = $h;
            }
        } else {
            // Neither is set, image is not resized. Just get dimensions and return.
            [$sourceWidth, $sourceHeight] = $this->getSourceImageDimensions($source);
    
            $this->width = $sourceWidth;
            $this->height = $sourceHeight;
        }
    }

    /**
     * @param Asset|string $source
     *
     * @return array
     */
    protected function getSourceImageDimensions(Asset|string $source): array
    {
        return BunnyHelpers::getSourceSize($source);
    }

    /**
     * @param array $transform
     * @param int   $sourceWidth
     * @param int   $sourceHeight
     *
     * @return array
     */
    protected function calculateTargetSize(array $transform, int $sourceWidth, int $sourceHeight): array
    {
        $ratio = $sourceWidth / $sourceHeight;

        $w = $transform['width'] ?? null;
        $h = $transform['height'] ?? null;

        if ($w) {
            return [$w, BunnyHelpers::round($w / $ratio)];
        }
        if ($h) {
            return [BunnyHelpers::round($h * $ratio), $h];
        }

        return [0, 0];
    }
}
