<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\elementactions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use spacecatninja\bunnytransformer\BunnyTransformer as Plugin;

class BunnyPurgeElementAction extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('imager-x-bunny-transformer', 'Purge from Bunny');
    }

    /**
     * Purges selected image Assets from Bunny
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $imagesToPurge = $query->kind('image')->all();

        if (empty($imagesToPurge)) {
            $this->setMessage(Craft::t('imager-x-bunny-transformer', 'No images to purge'));
            return true;
        }

        $bunnyPlugin = Plugin::$plugin;

        try {
            foreach ($imagesToPurge as $imageToPurge) {
                $bunnyPlugin->bunny->purgeAssetFromBunny($imageToPurge);
            }
        } catch (\Throwable $throwable) {
            $this->setMessage($throwable->getMessage());
            return false;
        }

        $numImagesToPurge = is_countable($imagesToPurge) ? \count($imagesToPurge) : 0;
        if ($numImagesToPurge > 1) {
            $this->setMessage(Craft::t('imager-x-bunny-transformer', 'Purging {count} images from Bunny...', [
                'count' => $numImagesToPurge,
            ]));
            return true;
        }

        $this->setMessage(Craft::t('imager-x-bunny-transformer', 'Purging image from Bunny...'));
        return true;
    }
}
