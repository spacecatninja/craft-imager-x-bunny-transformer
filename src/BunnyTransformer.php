<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer;

use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\RegisterElementActionsEvent;
use craft\events\ReplaceAssetEvent;
use craft\services\Assets;
use craft\services\Elements;

use spacecatninja\bunnytransformer\elementactions\BunnyPurgeElementAction;
use spacecatninja\bunnytransformer\models\Settings;
use spacecatninja\bunnytransformer\services\BunnyService;
use spacecatninja\bunnytransformer\transformers\Bunny;
use spacecatninja\imagerx\services\ImagerService;

use yii\base\Event;

/**
 * @property BunnyService $bunny
 */
class BunnyTransformer extends Plugin
{
    // Static Properties
    // =========================================================================

    public static BunnyTransformer $plugin;

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'bunny' => BunnyService::class,
        ]);

        // Register transformer with Imager
        Event::on(\spacecatninja\imagerx\ImagerX::class,
            \spacecatninja\imagerx\ImagerX::EVENT_REGISTER_TRANSFORMERS,
            static function (\spacecatninja\imagerx\events\RegisterTransformersEvent $event) {
                $event->transformers['bunny'] = Bunny::class;
            }
        );

        /** @var Settings $settings */
        $settings = $this->getSettings();

        // Register element action for purging from the asset index
        if ($settings->purgeElementAction && BunnyService::getCanPurge()) {
            Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS,
                static function(RegisterElementActionsEvent $event) {
                    $event->actions[] = BunnyPurgeElementAction::class;
                }
            );
        }

        // Event listeners for auto-purging
        if ($settings->autoPurge && BunnyService::getCanPurge()) {
            Event::on(Assets::class, Assets::EVENT_AFTER_REPLACE_ASSET,
                static function(ReplaceAssetEvent $event) {
                    if ($event->asset->kind === 'image') {
                        BunnyTransformer::$plugin->bunny->purgeAssetFromBunny($event->asset);
                    }
                }
            );

            $imagerConfig = ImagerService::getConfig();

            if ($imagerConfig->removeTransformsOnAssetFileops) {
                Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT,
                    static function(\craft\events\ElementEvent $event) {
                        if ($event->element instanceof Asset) {
                            BunnyTransformer::$plugin->bunny->purgeAssetFromBunny($event->element);
                        }
                    }
                );

                Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT,
                    static function(\craft\events\ElementEvent $event) {
                        /** @var Element $element */
                        $element = $event->element;

                        if ($element instanceof Asset && $element->getScenario() === Asset::SCENARIO_FILEOPS) {
                            BunnyTransformer::$plugin->bunny->purgeAssetFromBunny($element);
                        }
                    }
                );
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

}
