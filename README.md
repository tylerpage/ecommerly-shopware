# Ecommerly Shopware Connector

The Shopware 6 half of an Ecommerly connection: a plugin that answers **read-only**
capability requests over the signed-request protocol from PRD §6.3 — the same protocol
`ecommerly-magento-module` speaks, so the SaaS side needed no new transport and no
platform-specific dispatch path to support it.

Supports Shopware **6.6 and 6.7** from one codebase. Self-hosted only; Shopware Cloud
requires the App system, which is a different trust model (see "Why a plugin" below).

## What it does

Three endpoints, all POST, all rejecting anything unsigned:

| Path | Purpose |
|---|---|
| `/ecommerly/manifest/` | Module version, Shopware version, edition, granted scopes, supported capabilities |
| `/ecommerly/health/` | Whether this store can be dispatched into right now |
| `/ecommerly/capability/execute` | Runs one named capability |

Capabilities implemented: `get_store_context`, `search_products`, `get_product` — the same
three the Magento module has, deliberately. The rest of the release-one set is the same
proven pattern applied again, once both connectors widen together.

**The trailing slashes are load-bearing.** The request path is part of the signed canonical
string, and Symfony would 301 a signed POST to the unslashed form, turning it into a GET
that fails for no visible reason.

## Install and pair

```bash
# Development: a path repository pointing at this checkout
composer config repositories.ecommerly-connector path ../ecommerly-shopware-plugin
composer require ecommerly/shopware-connector

bin/console plugin:refresh
bin/console plugin:install --activate EcommerlyConnector
bin/console cache:clear
```

Set the Ecommerly URL in **Settings → Extensions → Ecommerly**, then pair with a token
issued by Ecommerly:

```bash
bin/console ecommerly:pair <one-time-token>
bin/console ecommerly:status
bin/console ecommerly:disconnect
```

Re-running `ecommerly:pair` on a paired store rotates the credential and keeps the outgoing
secret valid for a short overlap, so a request already in flight still verifies.

## Why a plugin, and why no administration module

A plugin mirrors the Magento module exactly: the store exposes signed endpoints and
Ecommerly calls in. An **App** would invert that — Shopware calls out to an
Ecommerly-hosted backend that reads the store over the Admin API with OAuth — which is the
only option on Shopware Cloud but a different protocol from Magento's. The capability
handlers under `src/Capability/` depend on DAL repositories and a `Result` value object,
never on anything HTTP-shaped, so an App backend could reuse them verbatim if Cloud support
is ever wanted.

There is **no administration module**, on purpose. The administration build is the one part
of a plugin that cannot be written once for 6.6 and 6.7 — the Vue 2 compatibility layer is
gone and the asset pipeline moved from Webpack to Vite. Settings therefore live in
`config.xml`, which core renders itself, and the interactive actions are console commands.
That is what keeps one release serving both lines.

## Security model

Requests authenticate by HMAC-SHA256 over a canonical string, not by a Shopware credential:

```
HMAC-SHA256( UPPER(method) \n path \n sha256(rawBody) \n timestamp \n nonce \n connectionId , secret )
```

`SignatureValidator::computeSignature()` must stay **byte-for-byte identical** to
Ecommerly's `App\Services\Capabilities\Connectors\RequestSigner::sign()`. Drift is a
protocol break against live stores, not a local bug — which is why
`SignatureValidatorTest` pins the same cross-repo reference vector Ecommerly's
`RequestSignerTest` pins rather than re-deriving the formula and agreeing with itself.

`EcommerlyRouteScope` is a route scope of its own. Shopware's
`ApiAuthenticationListener::validateRequest()` only demands a bearer token when the matched
route's scope implements `ApiContextRouteScopeDependant`; this scope deliberately does not,
so these endpoints are never OAuth-checked and never appear in the Store API surface.
Borrowing the `api` scope would demand an admin token nobody has.

Rejected: expired timestamps, reused nonces, tampered bodies, wrong connection ids, revoked
or disabled connections, and capabilities outside the granted scopes. Every rejection is
logged with its error code and answered with the same shape, so a caller learns nothing
from the difference between a wrong connection id and a wrong secret. No response carries
secrets, stack traces, filesystem paths or database detail.

The connection secret is encrypted at rest with AES-256-GCM under a key derived from the
installation's `APP_SECRET` — Shopware's `SystemConfigService` has no encrypted-value type
of its own, and a shared HMAC secret in a plaintext database row ends up in dumps, support
exports and staging clones.

## Tests

Plain PHPUnit, no Shopware test kernel — matching the convention in this shop's other
plugins.

```bash
# Installed under custom/plugins (the shop's vendor/autoload.php is found automatically)
../../../vendor/bin/phpunit

# From a bare checkout, pointing at any Shopware install for its classes
SHOPWARE_ROOT=/path/to/shopware phpunit
```

## Verification status

**Verified:** 35 unit tests pass against Shopware 6.6.10.18's own classes, covering the
cross-repo signature reference vector, the six replay/forgery cases (expired timestamp,
reused nonce, tampered body, wrong connection id, revoked connection, capability outside
granted scopes), the rotation overlap, secret encryption including tamper rejection, cursor
bounds, and capability routing. Every Shopware API this plugin calls was checked to exist
in 6.6, and `EcommerlyRouteScope` was confirmed at runtime to allow `/ecommerly/*`, reject
`/api/*`, and not implement `ApiContextRouteScopeDependant`.

**Not yet verified — do this before trusting it:**

- Never installed into a running Shopware. The service wiring, route registration and
  capability handlers have not executed against a real DAL.
- No end-to-end pairing run against a live Ecommerly instance.
- Never run on 6.7. The single-codebase claim rests on reasoning about two known traps
  (the administration build, and `AbstractRouteScope::$allowedPaths` becoming natively
  typed — see `EcommerlyRouteScope::__construct()`), not on a 6.7 test run.
- Commercial edition detection is untested against a real Commercial install, because none
  was available. `EditionDetector` accepts either known bundle name and reports
  `active_bundles` in the manifest so a wrong guess is diagnosable from the field rather
  than invisible.

## Known gaps

- `product_id` and the Magento-shaped `website_id`/`store_view_id` arrive as integers in
  Ecommerly's shared capability schemas, and Shopware identifies everything by UUID. Those
  report `unavailable` with a code saying why rather than guessing at a storefront. The fix
  is neutral aliases (`sales_channel_id`, `language_id`, `order_number`) in the shared
  schemas on the Ecommerly side; the Magento names stay valid.
- Pairing does not negotiate scopes yet, so a connection arrives with an empty scope list,
  which means "nothing was narrowed". Same gap as the Magento side.
- `search_products` uses a DAL contains-match rather than Shopware's scored product search,
  which needs a sales-channel context this connection does not have. Predictable and
  explainable beats relevance-ranked when the result gets cited as evidence.
