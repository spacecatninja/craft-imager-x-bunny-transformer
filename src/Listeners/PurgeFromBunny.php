<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer\Listeners;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Events\AssetReplaced;
use CraftCms\Cms\Asset\Validation\AssetRules;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementSaving;
use SpaceCatNinja\BunnyTransformer\Services\BunnyService;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Services\ImagerService;

/**
 * Purges images from Bunny.net when their files change: when an image is replaced, and when an asset is deleted,
 * moved or renamed while Imager X's `removeTransformsOnAssetFileops` setting is on.
 *
 * Registered when the `autoPurge` setting is on and purging is possible.
 */
class PurgeFromBunny
{
    public function __construct(
        private readonly BunnyService $bunny,
    ) {
    }

    /**
     * @throws ImagerException
     */
    public function handleAssetReplaced(AssetReplaced $event): void
    {
        if ($event->asset->kind === 'image') {
            $this->bunny->purgeAssetFromBunny($event->asset);
        }
    }

    /**
     * @throws ImagerException
     */
    public function handleElementDeleted(ElementDeleted $event): void
    {
        // Imager X's config is read here rather than when listening, since this plugin may boot before Imager X has bound its settings
        if ($event->element instanceof Asset && ImagerService::getConfig()->removeTransformsOnAssetFileops) {
            $this->bunny->purgeAssetFromBunny($event->element);
        }
    }

    /**
     * Purges an asset that's about to be moved or renamed, while its URL still has the old path.
     *
     * @throws ImagerException
     */
    public function handleElementSaving(ElementSaving $event): void
    {
        $element = $event->element;

        if (!$element instanceof Asset || $event->isNew || !ImagerService::getConfig()->removeTransformsOnAssetFileops) {
            return;
        }

        // Craft only uses the file operations scenario when the filename changes, a plain move uses the move scenario
        $isRelocation = \in_array($element->ruleset->getScenario(), [AssetRules::SCENARIO_FILEOPS, AssetRules::SCENARIO_MOVE], true)
            || ($element->newFolderId !== null && $element->newFolderId !== (int)$element->folderId)
            || ($element->newFilename !== null && $element->newFilename !== $element->getFilename())
            || $element->newLocation !== null;

        if ($isRelocation) {
            $this->bunny->purgeAssetFromBunny($element);
        }
    }
}
