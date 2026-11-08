# Support policy

This policy covers `class-atlas/laravel-flitt`. The [README](README.md#supported-versions) shows the versions supported
today; this page says how that list is chosen and when it changes.

## Which versions are supported

| Dependency | Versions     |
|------------|--------------|
| Laravel    | 13           |
| PHP        | 8.4, 8.5     |
| Saloon     | 4, from 4.5.1 |

- **Laravel:** every major that still receives security fixes from Laravel
  ([Laravel's support policy](https://laravel.com/docs/releases#support-policy)).
- **PHP:** every version that still receives security fixes from PHP
  ([supported versions](https://www.php.net/supported-versions.php)) and is accepted by a supported Laravel major.
- **Other dependencies** (Saloon, spatie/laravel-package-tools): their current major. Minimum versions are what the
  code needs and are set by hand. Automated updates widen a version range; they never raise its minimum.

## When versions are added

A new Laravel major or PHP version is supported **within two weeks of its release, in a minor release**.

## When versions are dropped

A Laravel major or PHP version is dropped **on the day its security support ends, never earlier, in a major release**.
Composer keeps an app that is still on the old version at the last release that fits it; the new major number tells
everyone else that the floor moved.

| Version    | Security support ends | Dropped                               |
|------------|-----------------------|---------------------------------------|
| Laravel 13 | 2028-03-17            | In the first major on or after that day |
| PHP 8.4    | 2028-12-31            | In the first major on or after that day |
| PHP 8.5    | 2029-12-31            | In the first major on or after that day |

## What "supported" means

- `composer.json` and the CI test matrix list exactly the same versions, and every combination is tested with both the
  lowest and the latest allowed dependencies.
- Bug and security fixes go into the latest release only. Older releases are not patched.
- The package follows Flitt's API as it behaves on Flitt's public test merchant, which a scheduled CI run checks every
  day.

## Getting help

Questions and bug reports go to the [issues](https://github.com/ClassAtlas/laravel-flitt/issues). Please include the
package, Laravel and PHP versions you use. Questions about your Flitt merchant account go to Flitt.

Security problems never go to a public issue: see [SECURITY.md](SECURITY.md).
