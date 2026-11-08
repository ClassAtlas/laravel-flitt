# Changelog

All notable changes to `class-atlas/laravel-flitt` are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `config/flitt.php` with the merchant's id and keys, timeouts and logging, published with the `flitt-config` tag.
- `Credentials` for one merchant, kept out of dumps, JSON, logs, stack traces and serialized payloads.
- `Flitt::withCredentials()` for apps that hold several merchants.
- The `CredentialsResolver` contract, and a default that knows the merchant in `config/flitt.php`.
