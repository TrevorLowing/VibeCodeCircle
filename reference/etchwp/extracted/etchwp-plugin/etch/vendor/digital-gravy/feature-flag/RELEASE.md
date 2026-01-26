# Release Process

This document outlines the steps to build and publish a new release of this library.

## Prerequisites

- PHP installed (v8.2 recommended)
- Composer installed
- Git installed
- Repository cloned locally

## Using git tags

To create a new release, create a new git tag with the desired version number.

```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

The workflow will:

1. Run all linting and testing checks
1. Create a GitHub release with auto-generated release notes
1. Allow Packagist to automatically update the package version

## Version Tag Format

- Specific versions: `v1.0.0`, `v1.0.1`, etc.

## Notes

- This library is published to Packagist, so you can install it using Composer.
- The GitHub Actions workflow will automatically create a release and update the package version on Packagist.
