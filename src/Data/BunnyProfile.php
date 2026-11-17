<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer\Data;

use CraftCms\Cms\Component\Component;

/**
 * The config of one Bunny.net profile, from the `profiles` setting.
 */
class BunnyProfile extends Component
{
    public string $hostname = '';
    public array|string $addPath = [];
    public bool $useCloudSourcePath = false;
    public array $defaultParams = [];
    public string $apiKey = '';
    public bool $excludeFromPurge = false;
}
