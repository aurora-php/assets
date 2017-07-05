# Assets

This is a simple assets manager for the octris php framework to make it possible to install additional
modules containing Javascript, Css, Images etc. using composer. The assets manager acts as composer plugin
and hooks into installation and updating commands. It tries to install new assets, update existing assets
and remove no longer needed assets.

## Configuration

The assets manager needs configuration in the composer.json file of the main package as well as the
sub-package containing assets. This is done by defining an `octris/assets` namespace inside the `extra`
part of composer.json.

### Sub-package

Define as many sources as you want. The following example maps directories inside the sub-package to
source-keys:

    js => /libsjs
    css => /styles

The directories have to be defined as relative to the root directory of the sub-package.

    ...
    "extra": {
        "octris/assets": {
            "source": {
                "js": "libsjs",
                "css": "styles"
            }
        }
    },
    ...

### Main package

In the main package the source-keys can be mapped to (target) directories, in the main package.
The directories have to be defined as relative to the root directory of the main package. The
assets manager will create necessary directories when it runs.

    "extra": {
        "octris/assets": {
            "target": {
                "js": "vendor_assets/js",
                "css": "vendor_assets/css",
                "img": "host/vendor_assets"
            }
        }
    },

The asset source directories will be symlinked into the target directories.

As example, if the name of the sub-package would be `octris/assetsdemo`, the following symlinks
would be created in the main-package for the above configuration:

    MAIN_PKG_ROOT/vendor_assets/css/octris/assetsdemo -> MAIN_PKG_ROOT/vendor/octris/assetsdemo/styles
    MAIN_PKG_ROOT/vendor_assets/js/octris/assetsdemo -> MAIN_PKG_ROOT/vendor/octris/assetsdemo/libsjs

### Application configuration

To make it possible to manage assets with the template compiler of the octris php framework, additional
configuration might be required. Open the file `etc/global.php` of your octris web project and configure
the additional paths for the template engine:

```diff
    $tpl->addPostprocessor(
        new \Octris\Core\Tpl\Postprocess\CombineJs(
-           [ '/libsjs/' => OCTRIS_APP_BASE . '/libsjs/' ],
+           [
+               '/libsjs/' => OCTRIS_APP_BASE . '/libsjs/',
+               '/vendor_js/' => OCTRIS_APP_BASE . '/vendor_assets/js/'
+           ],
            OCTRIS_APP_BASE . '/host/libsjs/'
        )
    );
    $tpl->addPostprocessor(
        new \Octris\Core\Tpl\Postprocess\CombineCss(
-           [ '/styles/' => OCTRIS_APP_BASE . '/styles/' ],
+           [
+               '/styles/' => OCTRIS_APP_BASE . '/styles/' ,
+               '/vendor_css/' => OCTRIS_APP_BASE . '/vendor_assets/css/'
+           ],
            OCTRIS_APP_BASE . '/host/styles/'
        )
    );
```

The new assets can be accessed in the template by specifying the configured additional root paths.

Example:

    <script src="/vendor_js/octris/assetsdemo/foo.js"></script>

