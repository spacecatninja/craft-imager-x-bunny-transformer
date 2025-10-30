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
    /** @var string */
    public $hostname = '';
    /** @var array|string */
    public $addPath = [];
    /** @var bool */
    public $useCloudSourcePath = false;
    /** @var array */
    public $defaultParams = [];
}
