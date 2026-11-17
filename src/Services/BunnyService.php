<?php
/**
 * Bunny.net transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\BunnyTransformer\Services;

use CraftCms\Cms\Asset\Elements\Asset;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SpaceCatNinja\BunnyTransformer\Data\BunnyProfile;
use SpaceCatNinja\BunnyTransformer\Settings;
use SpaceCatNinja\BunnyTransformer\Support\BunnyHelpers;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;

use function CraftCms\Cms\t;

#[Singleton]
class BunnyService
{
    /**
     * @var string
     */
    public const PURGE_ENDPOINT = 'https://api.bunny.net/purge';

    /**
     * Purging is possible if there's a `profiles` map, and at least one profile
     * has an API key set (either on the profile itself or at the top-level settings)
     * and is not excluded from purging.
     */
    public static function getCanPurge(): bool
    {
        $settings = app(Settings::class);
        $profilesArr = $settings->profiles;

        if (empty($profilesArr)) {
            return false;
        }

        $globalApiKey = $settings->apiKey;

        foreach ($profilesArr as $profileConfig) {
            $profile = new BunnyProfile($profileConfig);

            if ($profile->excludeFromPurge) {
                continue;
            }

            if ($profile->apiKey || $globalApiKey) {
                return true;
            }
        }

        return false;
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
            $response = Http::withHeaders(['AccessKey' => $apiKey])
                ->timeout(30)
                ->withQueryParameters(['url' => $url, 'async' => 'false'])
                ->send('POST', self::PURGE_ENDPOINT);

            if ($response->status() !== 200) {
                Log::error(t('An error occurred when trying to purge "{url}", status was "{httpStatus}" and response was "{response}"', [
                    'url' => $url,
                    'httpStatus' => $response->status(),
                    'response' => $response->body(),
                ], 'imager-x-bunny-transformer'));
            }
        } catch (\Throwable $throwable) {
            // We don't continue to throw this error, since it could be caused by a duplicated request.
            Log::error(t('An error was encountered while attempting to purge image "{url}". The error was: "{error}"', [
                'url' => $url,
                'error' => $throwable->getMessage(),
            ], 'imager-x-bunny-transformer'));
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
        $settings = app(Settings::class);
        $globalApiKey = $settings->apiKey;
        $profilesArr = $settings->profiles;

        if (empty($profilesArr)) {
            $msg = t('The `profiles` config setting is missing, or is not correctly set up.', category: 'imager-x-bunny-transformer');
            Log::error($msg);
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
                Log::error($throwable->getMessage());
                throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        }
    }
}
