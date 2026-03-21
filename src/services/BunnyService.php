<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2024 André Elvan
 */

namespace spacecatninja\bunnytransformer\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;

use spacecatninja\bunnytransformer\helpers\BunnyHelpers;
use spacecatninja\bunnytransformer\models\BunnyProfile;
use spacecatninja\bunnytransformer\models\Settings;
use spacecatninja\imagerx\exceptions\ImagerException;

class BunnyService extends Component
{
    /**
     * @var string
     */
    public const PURGE_ENDPOINT = 'https://api.bunny.net/purge';

    /**
     * @var bool|null If purging is enabled or not
     */
    protected static ?bool $canPurge = null;

    /**
     * Purging is possible if there's a `profiles` map, and at least one profile
     * has an API key set (either on the profile itself or at the top-level settings)
     * and is not excluded from purging.
     */
    public static function getCanPurge(): bool
    {
        if (self::$canPurge === null) {
            /** @var \spacecatninja\bunnytransformer\BunnyTransformer $plugin */
            $plugin = \spacecatninja\bunnytransformer\BunnyTransformer::$plugin;
            /** @var Settings $settings */
            $settings = $plugin->getSettings();

            $profilesArr = $settings->profiles;
            if (empty($profilesArr)) {
                self::$canPurge = false;
                return false;
            }

            $globalApiKey = $settings->apiKey;
            $hasPurgableProfile = false;

            foreach ($profilesArr as $profileConfig) {
                $profile = new BunnyProfile($profileConfig);
                if ($profile->excludeFromPurge) {
                    continue;
                }
                if ($profile->apiKey || $globalApiKey) {
                    $hasPurgableProfile = true;
                    break;
                }
            }

            self::$canPurge = $hasPurgableProfile;
        }

        return self::$canPurge;
    }

    /**
     * Purges a specific URL from Bunny CDN.
     *
     * @param string $url The full URL to the image you wish to purge
     * @param string $apiKey Bunny.net API key
     */
    public function purgeUrlFromBunny(string $url, string $apiKey): void
    {
        try {
            $endpoint = self::PURGE_ENDPOINT . '?' . http_build_query(['url' => $url, 'async' => 'false']);

            $curl = curl_init($endpoint);

            $opts = [
                CURLOPT_HTTPHEADER => ['AccessKey: ' . $apiKey],
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => '',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_RETURNTRANSFER => true,
            ];

            curl_setopt_array($curl, $opts);

            $response = curl_exec($curl);
            $curlErrorNo = curl_errno($curl);
            $curlError = curl_error($curl);
            $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($curlErrorNo !== 0) {
                $msg = Craft::t('imager-x-bunny-transformer', 'A cURL error "{curlErrorNo}" encountered while attempting to purge image "{url}". The error was: "{curlError}"', ['url' => $url, 'curlErrorNo' => $curlErrorNo, 'curlError' => $curlError]);
                Craft::error($msg, __METHOD__);
            }

            if ($httpStatus !== 200) {
                $msg = Craft::t('imager-x-bunny-transformer', 'An error occured when trying to purge "{url}", status was "{httpStatus}" and response was "{response}"', ['url' => $url, 'httpStatus' => $httpStatus, 'response' => $response]);
                Craft::error($msg, __METHOD__);
            }
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            // We don't continue to throw this error, since it could be caused by a duplicated request.
        }
    }

    /**
     * Purges all profiles for a given asset from Bunny CDN.
     *
     * @param Asset $asset The Asset you wish to purge
     * @throws ImagerException
     */
    public function purgeAssetFromBunny(Asset $asset): void
    {
        /** @var \spacecatninja\bunnytransformer\BunnyTransformer $plugin */
        $plugin = \spacecatninja\bunnytransformer\BunnyTransformer::$plugin;
        /** @var Settings $settings */
        $settings = $plugin->getSettings();

        $globalApiKey = $settings->apiKey;
        $profilesArr = $settings->profiles;

        if (empty($profilesArr)) {
            $msg = Craft::t('imager-x-bunny-transformer', 'The `profiles` config setting is missing, or is not correctly set up.');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        foreach ($profilesArr as $profileConfig) {
            $profile = new BunnyProfile($profileConfig);

            if ($profile->excludeFromPurge) {
                continue;
            }

            $apiKey = $profile->apiKey ?: $globalApiKey;
            if (!$apiKey) {
                continue;
            }

            try {
                $path = BunnyHelpers::getImagePath($asset, $profile);
                $baseUrl = (!str_starts_with($profile->hostname, 'http') ? 'https://' : '') . rtrim($profile->hostname, '/') . '/' . $path;

                $this->purgeUrlFromBunny($baseUrl, $apiKey);
            } catch (\Throwable $throwable) {
                Craft::error($throwable->getMessage(), __METHOD__);
                throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        }
    }
}
