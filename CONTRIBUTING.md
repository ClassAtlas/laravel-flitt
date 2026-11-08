# Contributing

Thank you for helping. Bug reports and pull requests are welcome; for anything larger than a fix, open an issue first so
we can agree on the shape before you write it.

## Running the checks

```bash
composer update
composer test        # Pest, offline, from the committed fixtures
composer analyse     # Larastan, level 9
composer format      # Pint
```

CI runs the tests on every supported PHP version with the lowest and the latest allowed dependencies, plus Larastan,
Pint and `composer validate --strict`. A pull request needs `tests-passed` and `static-analysis` green.

## The live suite

The tests answer from recorded fixtures by default, and a test whose fixture is missing fails. Two switches send them to
Flitt's **public test merchant** instead (`1549901`, payment key `test`, credit key `testcredit`, on `pay.flitt.com`).
Those credentials are public and hard-coded; there is no `.env`. Both modes refuse any other merchant.

```bash
FLITT_LIVE=1 composer test -- --group=live     # the live suite against the test merchant; nothing is written
FLITT_RECORD=1 composer test -- --group=live   # the same, and each answer is saved as its fixture
```

Each run sends new order ids, because Flitt refuses an order id it has already seen with other parameters. Fixtures
live in `tests/Fixtures/Saloon`, one file per answer. Before an answer is saved it is scrubbed, then signed again with
the public key: the caller's IP address becomes `203.0.113.10`, emails move to `example.com`, an open-banking payer's
IBAN and name are replaced, and the run's order ids become the fixed ids the default run sends. A guard test fails on
any fixture with another merchant id, an IP address outside `203.0.113.0/24`, a card that is not one of Flitt's
published test cards, or an email outside `example.com`. Read the fixture diff before you commit it.

The live suite also runs every day in CI (the `Live` workflow), and on a pull request labelled `live`. It is never a
required check: Flitt's test merchant can be down without anything being wrong here.

## Re-capturing callbacks

Callbacks and browser returns cannot be recorded by the suite: Flitt posts them to a public URL, after a payer acts on
the payment page. They are captured by hand when Flitt changes them:

1. Expose a local listener through a tunnel, and create checkouts on the test merchant with `server_callback_url`,
   `response_url` and `cancel_url` pointing at it.
2. Pay with Flitt's [test cards](https://docs.flitt.com/api/testing/), including a declined one and a 3-D Secure one.
3. Save each raw body with its content type, scrub it the same way as above, and sign it again with the public key.
4. Add it to `tests/Fixtures/callbacks.json`, run the tests (the guard reads it too), and read the diff.

## Pull requests

- One change per pull request, with a test that fails without it.
- Add a line under "Unreleased" in [CHANGELOG.md](CHANGELOG.md) for anything a user would notice.
- Never commit a real merchant's id, key, signature, card token or payer data.

## Releases

Releases are made by the maintainer only.

1. Move the "Unreleased" entries of `CHANGELOG.md` to a new version section, in a pull request.
2. Check the release gate on the merged commit: the test matrix, static analysis and Pint green, and a live run started
   by hand (Actions → Live → Run workflow) green.
3. Tag it, annotated, on `main`, and push the tag:

   ```bash
   git tag -a v1.2.0 -m "v1.2.0"
   git push origin v1.2.0
   ```

4. Publish the GitHub release from that tag with the changelog section as its notes. A `-beta.N` version is marked
   pre-release. Releases are immutable: a mistake is fixed by the next version, never by moving a tag.
