<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer;

use CraftCms\Cms\Plugin\PluginSettings;

class Settings extends PluginSettings
{
    public string $defaultProfile = '';
    public array $profiles = [];
    public string $apiKey = '';
    public bool $autoPurge = false;
    public bool $purgeElementAction = true;
}
