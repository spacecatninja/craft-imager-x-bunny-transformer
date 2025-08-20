# Bunny.net transformer for Imager X

A plugin for using [Bunny.net Optimizer](https://bunny.net/) as a transformer in Imager X.   
Also, an example of [how to make a custom transformer for Imager X](https://imager-x.spacecat.ninja/extending.html#transformers).

## Requirements

This plugin requires Craft CMS 5.0.0 or later, [Imager X 5.0.0](https://github.com/spacecatninja/craft-imager-x/) or later,
and an account at [bunny.net](https://bunny.net/).
 
## Usage

Install and configure this transformer as described below. Then, in your [Imager X config](https://imager-x.spacecat.ninja/configuration.html), 
set the transformer to `bunny`, ie:

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
2. Install the plugin in the Craft Control Panel under Settings > Plugins, or from the command line via `./craft plugin/install imager-x-bunny-transformer`.


## Configuration

You can configure the transformer by creating a file in your config folder called
`imager-x-bunny-transformer.php`, and override settings as needed.

### profiles [array]
Default: `[]`  
Profiles are usually a one-to-one mapping to the [pull zones](https://support.bunny.net/hc/en-us/articles/207790269-How-to-create-your-first-Pull-Zone) you've created in Bunny.net.
You must the default profile to use using the `defaultProfile` config setting, and can override it 
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

Each profile takes four settings:

*hostname*: This is the Hostname for your zone.

*addPath*: Prepends a path to the asset's path. Can be useful if you have several volumes that you want to serve with 
one Bunny.net zone. If this setting is an array, the key should be the volume handle, and the value the path to add. See example above.

*useCloudSourcePath*: If enabled, Imager will prepend the Craft source path to the asset path, before adding it to the 
Bunny.net path. This makes it possible to have one Bunny zone pulling images from many Craft volumes when they are for instance 
on the same S3 bucket, but in different subfolder. This only works on volumes that implements a path 
setting (AWS S3 and GCS does, local volumes does not).  

*defaultParams*: An array of default parameters that you want passed to all of your Bunny transforms. Example:  
```php
'defaultParams' => [
    'quality' => 70,
    'saturation' => 110,
]
```
💡 Any default parameter added to a profile can be overridden per-transform, via the `transformerParams` transform parameter.

### defaultProfile [string]
Default: `''`  
Sets the default profile to use (see `profiles`). You can override profile at the transform level by setting it through the `transformParams` transform parameter. Example:

```
{% set transforms = craft.imagerx.transformImage(asset, 
    [{width: 800}, {width: 2000}], 
    { transformerParams: { profile: 'myotherprofile' } }
) %}
```


Price, license and support
---
The plugin is released under the MIT license. It requires Imager X, which is a commercial 
plugin [available in the Craft plugin store](https://plugins.craftcms.com/imager-x). If you 
need help, or found a bug, please post an issue in this repo, or in Imager X' repo (preferably). 
