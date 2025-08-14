**Overview**  
WP Framework now manages asset loading using the `GetAssetInfo` trait. Any class that registers assets must use this trait to retrieve asset details—such as dependencies and version—from a corresponding `.asset.php` file. This system also allows a fallback version when the asset file is unavailable.

**Setup**  
Within your asset-registering class, include the `GetAssetInfo` trait and initialize the asset variables by calling the `setup_asset_vars` method. You must pass in the distribution directory path and a fallback version number. For example:

```php
$this->setup_asset_vars(
    dist_path: TENUP_PLUGIN_PATH . 'dist/',
    fallback_version: TENUP_PLUGIN_VERSION
);
```

This sets the base path for assets and ensures that a fallback version is used when the corresponding `.asset.php` file cannot be found.

## Enqueuing Assets
Once asset variables are set up, you can enqueue your scripts by using the `get_asset_info()` method provided by the trait. This method dynamically retrieves the appropriate asset information (dependencies and version) from the `.asset.php` file. For example, to enqueue an admin script:

```php
wp_enqueue_script(
    'tenup_plugin_admin',
    TENUP_PLUGIN_URL . 'dist/js/admin.js',
    $this->get_asset_info( 'admin', 'dependencies' ),
    $this->get_asset_info( 'admin', 'version' ),
    true
);
```

* **Dependencies:** `$this->get_asset_info( 'admin', 'dependencies' )` retrieves an array of script dependencies.
* **Version:** `$this->get_asset_info( 'admin', 'version' )` retrieves the asset version for cache busting.

## Key Points

* **Trait Usage:** Use the `GetAssetInfo` trait in any class that registers assets.
* **Asset Setup:** Call `setup_asset_vars()` with the correct dist directory and fallback version.
* **Dynamic Retrieval:** Enqueue assets using `get_asset_info()` to dynamically load dependencies and version info from the `.asset.php` file.