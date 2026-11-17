# Bunny.net transformer for Imager X

A plugin for using [Bunny.net Optimizer](https://bunny.net/) as a transformer in Imager X.   
Also, an example of [how to make a custom transformer for Imager X](https://imager-x.spacecat.ninja/extending.html#transformers).

## Requirements

This plugin requires Craft CMS 6.0.0 or later, [Imager X 7.0.0](https://github.com/spacecatninja/craft-imager-x/) or later
(the Pro edition, like all custom transformers), PHP 8.5 or later, and an account at [bunny.net](https://bunny.net/).

Version 2.x of this plugin supports Craft CMS 5 and Imager X 5 and 6. See [UPGRADE.md](UPGRADE.md) when moving to Craft 6.
 
## Usage

Install and configure this transformer as described below. Then, in your [Imager X config](https://imager-x.spacecat.ninja/configuration.html), 
`config/craft/imager-x.php`, set the transformer to `bunny`, ie:

```
'transformer' => 'bunny',
``` 

Transforms are now by default transformed with bunny.net, test your configuration with a 
simple transform like this:

```
{% set transform = craft.imagerx.transformImage(asset, { width: 600 }) %}
<img src="{{ transform.url }}" width="600">
<p>URL is: {{ transform.url }}</p>
``` 

If this doesn't work, make sure you've configured a `defaultProfile`, have a profile with the correct name, and 
that the pull zone is set up to point to the location of your asset volume.


### Cave-ats, shortcomings, and tips

This transformer only supports a subset of what Imager X can do when using the default `craft` transformer. The 
Bunny.net API is very limited, and essentially just supports resizing using `crop` and `fit` resize modes. Width, height,
ratio, quality and format, is automatically converted from the standard Imager parameters, while the rest of the additional 
options can be passed directly to Bunny using the `transformerParams` transform parameter. Example:

```
{% set transforms = craft.imagerx.transformImage(asset, 
    [{width: 400}, {width: 600}, {width: 800}], 
    { ratio: 2/1, transformerParams: { saturation: 50 } }
) %}
```   

For more information, check out the [Bunny.net Optimizer documentation](https://docs.bunny.net/docs/stream-image-processing).


## Installation

To install the plugin, follow these instructions:

1. Install with composer via `composer require spacecatninja/imager-x-bunny-transformer` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings > Plugins, or from the command line via `php artisan craft:plugin:install imager-x-bunny-transformer`.


## Configuration

You can configure the transformer by creating a file called `imager-x-bunny-transformer.php` in your 
`config/craft/` folder, and override settings as needed. It's a plain Laravel config file, so use `env()` for 
environment-specific values.

```php
<?php

return [
    'defaultProfile' => 'default',
    'profiles' => [
        'default' => [
            'hostname' => env('BUNNY_HOSTNAME', 'my-zone.b-cdn.net'),
        ],
    ],
    'apiKey' => env('BUNNY_API_KEY', ''),
];
```

### profiles [array]
Default: `[]`  
Profiles are usually a one-to-one mapping to the [pull zones](https://support.bunny.net/hc/en-us/articles/207790269-How-to-create-your-first-Pull-Zone) you've created in Bunny.net.
You must set the default profile to use with the `defaultProfile` config setting, and can override it 
at the template level by setting `profile` in your `transformerParams`.

Example profile:

```
'profiles' => [
    'default' => [
        'hostname' => 'my-zone.b-cdn.net',
        'addPath' => [
            'images' => 'images',            
            'otherimages' => 'otherimages',            
            'moreimages' => 'more/images',            
        ],
        'useCloudSourcePath' => true,
        'defaultParams' => [
            'quality' => 70,
        ],
    ],
    'myotherzone' => [
        'hostname' => 'my-other-zone.b-cdn.net',
    ]
],
```

Each profile takes the following settings:

*hostname*: This is the Hostname for your zone.

*addPath*: Prepends a path to the asset's path. Can be useful if you have several volumes that you want to serve with
one Bunny.net zone. If this setting is an array, the key should be the volume handle, and the value the path to add. See example above.

*useCloudSourcePath*: If enabled, Imager will prepend the volume's path in its cloud storage to the asset path, before
adding it to the Bunny.net path. The path is the `root` of the volume's filesystem disk, followed by the volume's subpath.
This makes it possible to have one Bunny zone pulling images from many Craft volumes when they are for instance on the
same S3 bucket, but in different folders. This only applies to cloud disks (for instance S3 and GCS), not local disks.

*defaultParams*: An array of default parameters that you want passed to all of your Bunny transforms. Example:
```php
'defaultParams' => [
    'quality' => 70,
    'saturation' => 110,
]
```
💡 Any default parameter added to a profile can be overridden per-transform, via the `transformerParams` transform parameter.

*apiKey*: A Bunny.net API key for this specific profile, used for cache purging. If set, it takes precedence over
the top-level `apiKey` setting. See [Purging](#purging) below.

*excludeFromPurge*: Set to `true` to exclude this profile from cache purging. Default is `false`.

### defaultProfile [string]
Default: `''`  
Sets the default profile to use (see `profiles`). You can override profile at the transform level by setting it through the `transformerParams` transform parameter. Example:

```
{% set transforms = craft.imagerx.transformImage(asset,
    [{width: 800}, {width: 2000}],
    { transformerParams: { profile: 'myotherprofile' } }
) %}
```

### apiKey [string]
Default: `''`  
A global Bunny.net API key used for cache purging across all profiles. Can be overridden per-profile using the
`apiKey` profile setting. See [Purging](#purging) below.

### autoPurge [bool]
Default: `false`  
When enabled, assets are automatically purged from the Bunny CDN cache when they are replaced, deleted, moved or
renamed in Craft. Requires at least one profile to have a valid API key (either via the top-level `apiKey`
setting or the profile's own `apiKey`).

### purgeElementAction [bool]
Default: `true`  
When enabled, a "Purge from Bunny" action is added to the asset index element actions menu, allowing
editors to manually purge selected images from the Bunny CDN cache. Requires at least one profile to
have a valid API key.


## Purging

The plugin supports purging assets from the Bunny CDN cache in two ways:

**Automatic purging** — enabled via the `autoPurge` config setting. When active, assets are purged
automatically when replaced, deleted, moved or renamed in Craft (all but replacing require
[`removeTransformsOnAssetFileops`](https://imager-x.spacecat.ninja/configuration.html#removetransformsonassetfileops)
to be enabled in your Imager X config).

**Manual purging via element action** — enabled via the `purgeElementAction` config setting (on by default).
Adds a "Purge from Bunny" option to the actions menu in the asset index, letting editors selectively
purge images on demand. Only the selected images the user can view are purged.

To enable purging, you need a [Bunny.net API key](https://support.bunny.net/hc/en-us/articles/360012168840).
Set it globally via the top-level `apiKey` config setting, or per-profile using the `apiKey` profile setting.
Profiles can be excluded from purging individually using `excludeFromPurge`.

A minimal purge-enabled config looks like this:

```php
<?php

return [
    'defaultProfile' => 'default',
    'apiKey' => env('BUNNY_API_KEY', ''),
    'autoPurge' => true,
    'profiles' => [
        'default' => [
            'hostname' => 'my-zone.b-cdn.net',
        ],
    ],
];
```


Price, license and support
---
The plugin is released under the MIT license. It requires Imager X, which is a commercial
plugin [available in the Craft plugin store](https://plugins.craftcms.com/imager-x). If you
need help, or found a bug, please post an issue in this repo, or in Imager X' repo (preferably).
