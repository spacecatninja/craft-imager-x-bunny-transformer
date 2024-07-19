<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\models;

use craft\base\Model;

class BunnyProfile extends Model
{
    public string $hostname = '';
    public array|string $addPath = [];
    public bool $useCloudSourcePath = false;
}
