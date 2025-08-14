## Overview
WP Framework follows the PSR‑4 autoloading standard. It builds on the module autoloader formerly used in WP‑Scaffold. Instead of extending a base `Module` class, you now implement the `ModuleInterface` and use the provided `Module` trait.

## Example

```php
namespace YourNamespace;

use TenupFramework\ModuleInterface;
use TenupFramework\Module;

class YourModule implements ModuleInterface {
    use Module;

    public function can_register(): bool {
        return true;
    }

    public function register(): void {
        // Register hooks and filters here.
    }
}
```

## Key Points

* Follow PSR‑4 naming and directory conventions.
* Use the `Module` trait for a basic implementation of `ModuleInterface`.
* Keep module registration lean—only attach what’s necessary.