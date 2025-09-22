# BlockRegistrar Usage Guide

The `BlockRegistrar` class provides automatic block registration from `block.json` files. This guide shows how to use it in themes and plugins.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Theme Implementation](#theme-implementation)
- [Plugin Implementation](#plugin-implementation)
- [Multiple Directories](#multiple-directories)
- [Block Structure](#block-structure)
- [Advanced Features](#advanced-features)
- [Troubleshooting](#troubleshooting)

## Basic Usage

### 1. Extend BlockRegistrar

Create a class that extends `BlockRegistrar` and implement the required method:

```php
<?php
namespace YourProject;

use TenupFramework\BlockRegistrar;

class Blocks extends BlockRegistrar {

	/**
	 * Get the blocks directory paths.
	 *
	 * @return array<string> Array of paths to the blocks directories.
	 */
	public function get_blocks_directory(): array {
		return [ YOUR_BLOCKS_DIRECTORY ];
	}
}
```

### 2. Automatic Registration

The framework automatically discovers and registers your `Blocks` class through `ModuleInitialization`. No manual registration needed!

## Theme Implementation

### Directory Structure

```
your-theme/
├── src/
│   └── Blocks.php
├── blocks/
│   ├── example-block/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── index.js
│   │   ├── markup.php
│   │   └── save.js
│   └── hero-block/
│       ├── block.json
│       ├── edit.js
│       ├── index.js
│       ├── markup.php
│       └── save.js
└── functions.php
```

### Theme Blocks Class

```php
<?php
/**
 * Theme Blocks
 *
 * @package YourTheme
 */

declare(strict_types = 1);

namespace YourTheme;

use TenupFramework\BlockRegistrar;

/**
 * Theme blocks registration.
 */
class Blocks extends BlockRegistrar {

	/**
	 * Get the blocks directory paths.
	 *
	 * @return array<string> Array of paths to the blocks directories.
	 */
	public function get_blocks_directory(): array {
		return [
			get_template_directory() . '/blocks/',
		];
	}
}
```

### Block Definition (block.json)

```json
{
    "name": "your-theme/hero",
    "title": "Hero Block",
    "description": "A hero section block",
    "category": "layout",
    "icon": "cover-image",
    "keywords": ["hero", "banner", "header"],
    "supports": {
        "align": ["wide", "full"],
        "color": {
            "background": true,
            "text": true
        }
    },
    "attributes": {
        "title": {
            "type": "string",
            "default": "Welcome"
        },
        "subtitle": {
            "type": "string",
            "default": "Subtitle text"
        }
    },
    "editorScript": "file:./build/index.js",
    "editorStyle": "file:./build/editor.css",
    "style": "file:./build/style.css"
}
```

## Plugin Implementation

### Directory Structure

```
your-plugin/
├── src/
│   └── Blocks.php
├── blocks/
│   ├── contact-form/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── index.js
│   │   ├── markup.php
│   │   └── save.js
│   └── testimonials/
│       ├── block.json
│       ├── edit.js
│       ├── index.js
│       ├── markup.php
│       └── save.js
└── plugin.php
```

### Plugin Blocks Class

```php
<?php
/**
 * Plugin Blocks
 *
 * @package YourPlugin
 */

declare(strict_types = 1);

namespace YourPlugin;

use TenupFramework\BlockRegistrar;

/**
 * Plugin blocks registration.
 */
class Blocks extends BlockRegistrar {

	/**
	 * Get the blocks directory paths.
	 *
	 * @return array<string> Array of paths to the blocks directories.
	 */
	public function get_blocks_directory(): array {
		return [
			plugin_dir_path( __FILE__ ) . 'blocks/',
		];
	}
}
```

## Multiple Directories

You can register blocks from multiple directories:

```php
public function get_blocks_directory(): array {
	return [
		get_template_directory() . '/blocks/',           // Theme blocks
		get_template_directory() . '/custom-blocks/',    // Custom theme blocks
		plugin_dir_path( __FILE__ ) . 'vendor-blocks/', // Third-party blocks
	];
}
```

## Block Structure

### Required Files

Each block directory must contain:

- **`block.json`** - Block metadata (required)
- **`index.js`** - Block JavaScript (required)

### Standard Files (Recommended)

- **`edit.js`** - Editor component (recommended)
- **`save.js`** - Save component (recommended)
- **`markup.php`** - Server-side rendering (recommended for dynamic blocks)

### Optional Files

- **`style.css`** - Block styles
- **`editor.css`** - Editor-only styles

### Dynamic Blocks with Server-Side Rendering

For blocks that need server-side rendering, add a `markup.php` file:

```php
<?php
/**
 * Hero Block Markup
 *
 * @var array $attributes Block attributes
 * @var string $content Block content
 * @var object $block Block object
 */

$title    = $attributes['title'] ?? 'Default Title';
$subtitle = $attributes['subtitle'] ?? '';
?>

<div class="hero-block">
	<h1 class="hero-title"><?php echo esc_html( $title ); ?></h1>
	<?php if ( $subtitle ) : ?>
		<p class="hero-subtitle"><?php echo esc_html( $subtitle ); ?></p>
	<?php endif; ?>
	<div class="hero-content">
		<?php echo $content; ?>
	</div>
</div>
```

The `BlockRegistrar` automatically detects `markup.php` files and creates render callbacks.

## Advanced Features

### Conflict Detection

The `BlockRegistrar` automatically detects block name conflicts between themes and plugins:

```php
// Check if a block has conflicts
if ( \TenupFramework\BlockRegistrar::has_block_conflict( 'theme/hero' ) ) {
	$source = \TenupFramework\BlockRegistrar::get_block_source( 'theme/hero' );
	error_log( "Block 'theme/hero' already registered by: {$source}" );
}

// Get all registered blocks and their sources
$sources = \TenupFramework\BlockRegistrar::get_all_block_sources();
foreach ( $sources as $block_name => $source_class ) {
	echo "Block '{$block_name}' registered by: {$source_class}\n";
}
```

### Error Handling

The `BlockRegistrar` provides comprehensive error handling:

- **Invalid directories** - Skipped with error logging
- **Malformed JSON** - Skipped with error logging
- **Missing required fields** - Skipped with error logging
- **Block registration failures** - Logged with details

### Security Features

- **Path validation** - Prevents directory traversal attacks
- **JSON validation** - Ensures proper block metadata
- **File permission checks** - Verifies readable directories
- **Block name validation** - Enforces proper naming conventions

## Troubleshooting

### Common Issues

#### 1. Blocks Not Appearing

**Problem**: Blocks don't appear in the editor.

**Solutions**:
- Check that `block.json` exists and is valid JSON
- Verify the block name follows `namespace/name` format
- Ensure the directory path is correct
- Check WordPress error logs for registration errors

#### 2. Block Name Conflicts

**Problem**: "Block name conflict detected" error.

**Solutions**:
- Use unique block names (e.g., `theme/hero`, `plugin/form`)
- Check which class registered the conflicting block
- Consider using different namespaces

#### 3. Server-Side Rendering Not Working

**Problem**: Dynamic blocks don't render on the frontend.

**Solutions**:
- Ensure `markup.php` exists in the block directory
- Check that `markup.php` has proper PHP syntax
- Verify the block is registered as dynamic in `block.json`

#### 4. Styles Not Loading

**Problem**: Block styles don't appear.

**Solutions**:
- Check `style.css` path in `block.json`
- Ensure the CSS file exists
- Verify the `style` property is correctly set

### Debug Information

Enable WordPress debug logging to see detailed error messages:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check `/wp-content/debug.log` for `BlockRegistrar` error messages.

### Testing Your Implementation

```php
// Test that your blocks class is working
$blocks      = new YourTheme\Blocks();
$directories = $blocks->get_blocks_directory();

// Check if directories exist
foreach ( $directories as $dir ) {
	if ( ! file_exists( $dir ) ) {
		error_log( "Block directory does not exist: {$dir}" );
	}
}
```

## Best Practices

1. **Use descriptive block names** - `theme/hero` instead of `theme/block1`
2. **Organize blocks logically** - Group related blocks in subdirectories
3. **Include proper metadata** - Complete `block.json` with all required fields
4. **Test thoroughly** - Verify blocks work in both editor and frontend
5. **Handle errors gracefully** - Check error logs regularly
6. **Use consistent naming** - Follow your project's naming conventions

## Examples

### Complete Theme Example

```php
<?php
namespace MyTheme;

use TenupFramework\BlockRegistrar;

class Blocks extends BlockRegistrar {

	public function get_blocks_directory(): array {
		return [
			get_template_directory() . '/blocks/',
			get_template_directory() . '/custom-blocks/',
		];
	}
}
```

### Complete Plugin Example

```php
<?php
namespace MyPlugin;

use TenupFramework\BlockRegistrar;

class Blocks extends BlockRegistrar {

	public function get_blocks_directory(): array {
		return [
			plugin_dir_path( __FILE__ ) . 'blocks/',
		];
	}
}
```

The `BlockRegistrar` handles all the complexity of block registration, conflict detection, and error handling, allowing you to focus on building great blocks!
