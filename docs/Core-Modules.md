# Core Modules

WP Framework provides several Core modules that handle common WordPress functionality modifications. These modules are designed to be explicitly opted into by consuming applications (themes/plugins) rather than being automatically initialized.

## Available Core Modules

### HeadOverrides

The `HeadOverrides` module removes unwanted WordPress head elements that are typically not needed in production sites.

**Location**: `TenupFramework\Core\HeadOverrides`

**Functionality**:
- Removes WordPress generator meta tag (`wp_generator`)
- Removes Windows Live Writer manifest link (`wlwmanifest_link`)
- Removes Really Simple Discovery service endpoint link (`rsd_link`)

**Usage**:
```php
// Recommended: Array-based approach
$core_modules = [ \TenupFramework\Core\HeadOverrides::class ];
foreach ( $core_modules as $module_class ) {
    $module = new $module_class();
    if ( $module->can_register() ) {
        $module->register();
    }
}
```

### Emoji

The `Emoji` module disables WordPress core emoji functionality, which can improve performance by removing unnecessary scripts and styles.

**Location**: `TenupFramework\Core\Emoji`

**Functionality**:
- Removes emoji detection scripts from `wp_head` and admin
- Removes emoji-related styles from front-end and back-end
- Removes emoji-to-static-img conversion from feeds and email
- Disables TinyMCE emoji plugin
- Removes emoji CDN from DNS prefetching hints

**Usage**:
```php
// Recommended: Array-based approach
$core_modules = [ \TenupFramework\Core\Emoji::class ];
foreach ( $core_modules as $module_class ) {
    $module = new $module_class();
    if ( $module->can_register() ) {
        $module->register();
    }
}
```

## Core Module Characteristics

All Core modules follow these patterns:

### Module Interface Compliance
- Implement `TenupFramework\ModuleInterface`
- Use the `TenupFramework\Module` trait
- Provide `load_order()`, `can_register()`, and `register()` methods

### Load Order
Core modules typically use a load order of `5`, ensuring they initialize early in the module lifecycle.

### Explicit Opt-In
Core modules are **not automatically initialized** by the framework. Consuming applications must explicitly instantiate and register them.

## Integration Patterns

### Recommended: Array-Based Approach
The cleanest way to initialize Core modules is using an array and foreach loop:

```php
// Define the Core modules you want to use
$core_modules = [
    \TenupFramework\Core\HeadOverrides::class,
    \TenupFramework\Core\Emoji::class,
];

// Initialize each module
foreach ( $core_modules as $module_class ) {
    $module = new $module_class();
    if ( $module->can_register() ) {
        $module->register();
    }
}
```

### Manual Instantiation (Alternative)
```php
// Initialize specific Core modules individually
$head_overrides = new \TenupFramework\Core\HeadOverrides();
if ( $head_overrides->can_register() ) {
    $head_overrides->register();
}

$emoji = new \TenupFramework\Core\Emoji();
if ( $emoji->can_register() ) {
    $emoji->register();
}
```

### Configuration-Based Approach (Future Enhancement)
If using the configuration-based initialization pattern:

```php
// Using ModuleInitialization::init_specific_modules()
ModuleInitialization::instance()->init_specific_modules([
    \TenupFramework\Core\HeadOverrides::class,
    \TenupFramework\Core\Emoji::class,
]);
```

## Best Practices

### When to Use Core Modules
- **HeadOverrides**: Use when you want to remove WordPress generator meta and other unnecessary head elements
- **Emoji**: Use when you want to disable WordPress emoji functionality for performance reasons

### Conditional Loading
Consider loading Core modules conditionally based on your application's needs:

```php
// Build array of Core modules based on conditions
$core_modules = [ \TenupFramework\Core\HeadOverrides::class ];

// Only load emoji module in production
if ( wp_get_environment_type() === 'production' ) {
    $core_modules[] = \TenupFramework\Core\Emoji::class;
}

// Initialize all selected modules
foreach ( $core_modules as $module_class ) {
    $module = new $module_class();
    if ( $module->can_register() ) {
        $module->register();
    }
}
```

### Testing
Core modules include comprehensive test suites that verify:
- Interface implementation
- Method existence and callability
- Source code verification of WordPress function calls
- Functional behavior testing

## Migration from Scaffold

If migrating from the WP Scaffold plugin's Core modules:

1. **Remove** the old Core module classes from your scaffold
2. **Add explicit opt-in** to the framework's Core modules
3. **Test** that functionality works as expected
4. **Verify** no regressions in your application

## Future Enhancements

### Framework Helper Method
A future enhancement could add a helper method to the `ModuleInitialization` class:

```php
// Potential future API
ModuleInitialization::init_core_modules([
    \TenupFramework\Core\HeadOverrides::class,
    \TenupFramework\Core\Emoji::class,
]);
```

This would internally handle the instantiation and registration logic, making the array-based approach even cleaner.

## Future Core Modules

The framework is designed to accommodate additional Core modules as needed. New modules should follow the same patterns:
- Implement `ModuleInterface`
- Use the `Module` trait
- Provide comprehensive test coverage
- Require explicit opt-in from consuming applications
