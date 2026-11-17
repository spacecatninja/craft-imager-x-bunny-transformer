<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer\ElementActions;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use Illuminate\Support\Facades\Gate;
use SpaceCatNinja\BunnyTransformer\Services\BunnyService;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class BunnyPurgeElementAction extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return t('Purge from Bunny', category: 'imager-x-bunny-transformer');
    }

    /**
     * Purges selected image Assets from Bunny
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $user = currentUser();

        // Only act on images the current user can view
        $imagesToPurge = array_filter(
            $query->kind('image')->all(),
            static fn($asset) => $asset instanceof Asset && $user !== null && Gate::forUser($user)->check('view', $asset),
        );

        if (empty($imagesToPurge)) {
            $this->setMessage(t('No images to purge', category: 'imager-x-bunny-transformer'));
            return true;
        }

        try {
            foreach ($imagesToPurge as $imageToPurge) {
                app(BunnyService::class)->purgeAssetFromBunny($imageToPurge);
            }
        } catch (\Throwable $throwable) {
            $this->setMessage($throwable->getMessage());
            return false;
        }

        $numImagesToPurge = \count($imagesToPurge);

        if ($numImagesToPurge > 1) {
            $this->setMessage(t('Purging {count} images from Bunny...', [
                'count' => $numImagesToPurge,
            ], 'imager-x-bunny-transformer'));

            return true;
        }

        $this->setMessage(t('Purging image from Bunny...', category: 'imager-x-bunny-transformer'));

        return true;
    }
}
