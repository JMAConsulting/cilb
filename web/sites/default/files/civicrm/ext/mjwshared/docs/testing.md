# Testing

This extension has two separate, independent test suites:

- **PHPUnit** (`tests/phpunit/`) - tests the PHP code (traits, API4 actions) against a real, booted CiviCRM install.
- **Node** (`tests/js/`) - tests pure-JS logic in `js/` that has no CiviCRM/browser dependencies, using Node's built-in test runner.

They are run with different tools and have no dependency on each other.

## PHPUnit

### Prerequisites

These tests require a working CiviCRM installation with [`cv`](https://github.com/civicrm/cv) available on your `PATH`, and are run through CiviCRM's normal `Civi\Test` bootstrap (`tests/phpunit/bootstrap.php` calls `cv php:boot` to boot a real CiviCRM, then `Civi\Test\CiviTestListener` handles test-DB setup/teardown per test). Running raw `phpunit`/`phpunit9` without a booted site and `cv` on `PATH` will fail.

If you're developing with [civicrm-buildkit](https://docs.civicrm.org/dev/en/latest/tools/civibuild/), buildkit's own tools already provide a pinned, wrapped `phpunit9` and `cv` - use those instead of any globally-installed version:

```bash
# From this extension's root directory
export PATH="/path/to/buildkit/bin:$PATH"

# Whole suite
CIVICRM_UF=UnitTests phpunit9 tests/phpunit

# A single file
CIVICRM_UF=UnitTests phpunit9 tests/phpunit/Civi/Api4/Membership/LinkRecurMJWTest.php

# A single test method
CIVICRM_UF=UnitTests phpunit9 --filter testLinkRecur tests/phpunit/Civi/Api4/Membership/LinkRecurMJWTest.php
```

### What's covered

| Test class | File | What it tests |
|---|---|---|
| `CRM_Core_Payment_MJWTraitTest` | `tests/phpunit/CRM/Core/Payment/MJWTraitTest.php` | `CRM_Core_Payment_MJWTrait` helpers shared by payment processor implementations (eg. `getEmail()`). |
| `UpdateAmountOnRecurMJWTest` | `tests/phpunit/Civi/Api4/ContributionRecur/UpdateAmountOnRecurMJWTest.php` | The `ContributionRecur.UpdateAmountOnRecurMJW` API4 action: no-op when the amount is unchanged, increasing a recurring contribution's amount, and rejecting a change to a zero amount. |
| `LinkRecurMJWTest` | `tests/phpunit/Civi/Api4/Membership/LinkRecurMJWTest.php` | Linking/unlinking a `Membership` to a `ContributionRecur` and that related entities (line items, contribution) are updated correctly in both directions. |
| `GetDefaultPriceFieldValueMJWTest` | `tests/phpunit/Civi/Api4/PriceFieldValue/GetDefaultPriceFieldValueMJWTest.php` | The `PriceFieldValue.GetDefaultPriceFieldValueMJW` API4 action returns line item defaults matching those for both a `Contribution` and a `Membership`. |

## Node

### Prerequisites

Node >= 18 (for the built-in `node:test` runner). No npm install is required - the tests use only Node's built-in modules (`node:test`, `node:assert`, `node:vm`, `node:fs`, `node:path`), no third-party dependencies.

```bash
npm test
# or directly:
node --test
```

### What's covered

| Test file | What it tests |
|---|---|
| `tests/js/crmPaymentRoundMoney.test.js` | Loads the real `js/crm.payment.js` in a sandboxed `vm` context (with minimal `document`/jQuery/`CRM` stubs - just enough for the file's top-level registration code to run without throwing) and exercises the resulting `CRM.payment.roundMoney()` against the exact rounding-boundary case from [stripe#510](https://lab.civicrm.org/extensions/stripe/-/work_items/510) (eg. `$20` + `7.625%` tax = `$21.525` exactly, which naive `Number.prototype.toFixed()` rounds the wrong way) plus other classic JS floating-point rounding edge cases. |

This approach - loading the actual shipped file in a sandbox rather than re-implementing/copy-pasting the logic under test - means the test exercises exactly what ships to the browser, so it catches accidental regressions or typos in the real file.
