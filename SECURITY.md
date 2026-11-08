# Security policy

## Reporting a vulnerability

**Please do not open a public issue.** Report it privately through
[GitHub's vulnerability reporting](https://github.com/ClassAtlas/laravel-flitt/security/advisories/new).

Include what you found, how to reproduce it, and the package version. Never include real merchant keys, card tokens or
payers' data: Flitt's public test merchant (`1549901`, key `test`) is enough to show any problem with this package.

You will get an answer within a week. Once a fix is released, the advisory is published and you are credited, unless
you prefer not to be.

## What counts

Anything that lets someone move money or learn something they should not, for example:

- a forged, altered or unsigned callback, browser return or API answer that the package accepts;
- a merchant's key, a signature, a card token or a payer's data reaching a log, an exception message or a dump;
- a request sent to Flitt with parameters other than the ones the caller set.

## Supported versions

Only the latest release receives security fixes. See [SUPPORT.md](SUPPORT.md).
