# PHP Feature Flag Manager

[![Build Status](https://github.com/Digital-Gravy/feature-flag/actions/workflows/ci.yml/badge.svg)](https://github.com/digitalgravy/feature-flag/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/digital-gravy/feature-flag/v)](https://packagist.org/packages/digital-gravy/feature-flag)
[![License](https://poser.pugx.org/digital-gravy/feature-flag/license)](https://packagist.org/packages/digital-gravy/feature-flag)

A flexible PHP library for managing feature flags with multiple storage backends.

## Installation

You can install the library via Composer:

```bash
composer require digital-gravy/feature-flag
```

## Features

- Simple on/off feature flag management
- Multiple storage backends:
  - JSON file
  - PHP Constants
  - Array-based storage
- Type-safe implementation
- Extensible storage interface
- Exception handling for invalid flags

## Usage

### Basic Usage

```php
use DigitalGravy\FeatureFlag\FeatureFlagStore;
use DigitalGravy\FeatureFlag\Storage\KeyedArray;

// Create a feature flag store with array storage
$flags = new KeyedArray([
    'dark_mode' => 'on',
    'beta_feature' => 'off'
]);

$store = new FeatureFlagStore($flags->get_flags());

// Check if a feature is enabled
if ($store->is_on('dark_mode')) {
    // Dark mode is enabled
}
```

### JSON File Storage

```php
use DigitalGravy\FeatureFlag\Storage\JsonFile;

$flags = new JsonFile('/path/to/flags.json');
$store = new FeatureFlagStore($flags->get_flags());
```

Example flags.json:

```json
{
  "dark_mode": "on",
  "beta_feature": "off"
}
```

### PHP Constants Storage

```php
use DigitalGravy\FeatureFlag\Storage\PHPConstant;

define('DARK_MODE', true);
define('BETA_FEATURE', 'off');

$flags = new PHPConstant(['DARK_MODE', 'BETA_FEATURE']);
$store = new FeatureFlagStore($flags->get_flags());
```

### Multiple Sources

The `FeatureFlagStore` constructor accepts an array of flags from different storage backends.
When multiple sources are provided, the flags are merged together, and the last source overrides the previous ones.

```php
$store = new FeatureFlagStore(
    $jsonFlags->get_flags(),
    $constantFlags->get_flags(),
    $arrayFlags->get_flags()
);
```

### Flag Rules

- Flag keys must contain only alphanumeric characters, underscores, and dashes
- Flag values must be either 'on' or 'off'
- Flag keys are case-insensitive and stored in lowercase

### Custom Storage Backend

You can implement your own storage backend by implementing the FlagStorageInterface:

```php
use DigitalGravy\FeatureFlag\Storage\FlagStorageInterface;

class CustomStorage implements FlagStorageInterface {
    public function get_flags(): array {
        // Return array of FeatureFlag objects
    }
}
```

### Error Handling

The library includes several exception types:

- `Invalid_Flag_Key`: Thrown when a flag key contains invalid characters
- `Invalid_Flag_Value`: Thrown when a flag value is not 'on' or 'off'
- `Flag_Key_Not_Found`: Thrown when attempting to check a non-existent flag
- `Not_A_Flag`: Thrown when invalid flag types are provided
- `FileNotFoundException`: Thrown when the JSON file does not exist
- `FileNotReadableException`: Thrown when the JSON file exists but is not readable

### License

GPLv3 - see LICENSE file for details
