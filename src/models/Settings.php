<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\models;

use craft\base\Model;

class Settings extends Model
{
    /** @var string */
    public $defaultProfile = '';
    /** @var array */
    public $profiles = [];
}
