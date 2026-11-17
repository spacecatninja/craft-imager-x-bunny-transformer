<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Events\AssetReplaced;
use CraftCms\Cms\Element\Events\ElementActionsResolving;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Support\Facades\Event;
use SpaceCatNinja\BunnyTransformer\ElementActions\BunnyPurgeElementAction;
use SpaceCatNinja\BunnyTransformer\Listeners\PurgeFromBunny;
use SpaceCatNinja\BunnyTransformer\Services\BunnyService;
use SpaceCatNinja\BunnyTransformer\Transformers\Bunny;
use SpaceCatNinja\ImagerX\Transformers\Transformers;

class BunnyTransformer extends Plugin
{
    // Public Methods
    // =========================================================================

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Settings::class, fn() => $this->getSettings());
    }

    public function boot(Transformers $transformers): void
    {
        // Register transformer with Imager X
        $transformers->register('bunny', Bunny::class);

        $settings = app(Settings::class);

        // Register element action for purging from the asset index
        if ($settings->purgeElementAction && BunnyService::getCanPurge()) {
            Event::listen(static function(ElementActionsResolving $event): void {
                if (is_a($event->elementType, Asset::class, true)) {
                    $event->actions[] = BunnyPurgeElementAction::class;
                }
            });
        }

        // Event listeners for auto-purging
        if ($settings->autoPurge && BunnyService::getCanPurge()) {
            $this->registerAutoPurgeListeners();
        }
    }

    // Protected Methods
    // =========================================================================

    protected static function createSettings(): ?PluginSettings
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    private function registerAutoPurgeListeners(): void
    {
        Event::listen(AssetReplaced::class, [PurgeFromBunny::class, 'handleAssetReplaced']);
        Event::listen(ElementDeleted::class, [PurgeFromBunny::class, 'handleElementDeleted']);
        Event::listen(ElementSaving::class, [PurgeFromBunny::class, 'handleElementSaving']);
    }
}
