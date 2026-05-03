# UT Robinhood

A Drupal 11 module that imports your Robinhood stock orders as fieldable
`RobinhoodOrder` content entities on a cron schedule.

---

## Architecture

```
hook_cron()
  └─ Queues a job → ut_robinhood_import queue
        └─ RobinhoodImportWorker (QueueWorker)
              └─ RobinhoodOrderImporter::import()
                    └─ proc_open(rh_fetch_orders.py)   ← Python / robin_stocks
                          └─ JSON array on STDOUT
                    └─ upserts RobinhoodOrder entities
```

The Python bridge script is invoked via `proc_open()` (not `shell_exec`) so
that credentials are passed via **environment variables on the subprocess**,
never as command-line arguments visible in `ps` output.

---

## Requirements

- Drupal 11 (PHP 8.3+)
- Python 3.8+ with `robin_stocks` installed:
  ```bash
  pip install robin_stocks
  ```
- A Robinhood account
- Private files configured in Drupal (for the auth pickle cache)

---

## Installation

1. Place this module in `web/modules/custom/ut_robinhood/`.
2. Enable it:
   ```bash
   drush en ut_robinhood -y
   drush cr
   ```

---

## Credential Configuration

Credentials are **never stored in the Drupal config system**. Set them in
`settings.php` or as environment variables.

### Option A — settings.php

```php
// web/sites/default/settings.php
$settings['ut_robinhood_username']   = 'you@example.com';
$settings['ut_robinhood_password']   = 'your-password';
$settings['ut_robinhood_mfa_code']   = '';   // leave empty; MFA handled by pickle
$settings['ut_robinhood_python_bin'] = '/usr/bin/python3';
```

### Option B — Environment variables

```bash
export UT_ROBINHOOD_USERNAME="you@example.com"
export UT_ROBINHOOD_PASSWORD="your-password"
export UT_ROBINHOOD_MFA_CODE=""
export UT_ROBINHOOD_PYTHON_BIN="/usr/bin/python3"
```

> **MFA note:** The first run will trigger an MFA challenge (SMS or
> authenticator app). After you approve it, robin_stocks stores the session
> in a pickle file and subsequent runs reuse it without re-triggering MFA.
> The pickle file lives in `private://ut_robinhood/` by default.

---

## Module Settings

Visit **Admin → Configuration → Content authoring → UT Robinhood Settings**
(`/admin/config/content/ut-robinhood`) to configure:

| Setting | Default | Description |
|---|---|---|
| Cron interval | 1 hour | Minimum time between import runs |
| Allowed order states | `filled` | Which Robinhood states to import |
| Update existing | Yes | Re-sync mutable fields on re-import |
| Max orders per run | 0 (unlimited) | Throttle for large accounts |
| Bridge script path | *(module default)* | Override if you moved `rh_fetch_orders.py` |
| Pickle directory | `private://ut_robinhood/` | Override the session cache location |

---

## Entity Fields

The `RobinhoodOrder` entity ships with these base fields (all display-configurable
via Field UI at **Admin → Configuration → Content authoring → UT Robinhood Settings**):

| Field | Type | Source |
|---|---|---|
| `robinhood_order_id` | string | `id` — Robinhood's UUID (deduplication key) |
| `symbol` | string | Resolved from `instrument` URL |
| `order_side` | list (buy/sell) | `side` |
| `order_type` | list | `type` |
| `order_state` | list | `state` |
| `quantity` | decimal | `quantity` |
| `price` | decimal | `price` (limit/stop price) |
| `average_price` | decimal | `average_price` |
| `total_notional_value` | decimal | `total_notional.amount` |
| `fees` | decimal | `fees` |
| `time_in_force` | list | `time_in_force` |
| `created_at_robinhood` | datetime | `created_at` |
| `updated_at_robinhood` | datetime | `updated_at` |
| `instrument_id` | string | Extracted UUID from `instrument` URL |
| `trigger` | list | `trigger` |
| `extended_hours` | boolean | `extended_hours` |

Because this is a **fieldable** entity with `field_ui_base_route` set, you can
add any additional fields via **Manage fields** just like you would on a node type.

---

## Adding Custom Fields

1. Go to **Admin → Configuration → Content authoring → UT Robinhood Settings**.
2. Click the **Manage fields** tab.
3. Add any Drupal field type (text, number, entity reference, etc.).
4. Use **Manage display** to control how fields appear on the entity view page.

---

## Import Log

Each cron run writes a record to the `ut_robinhood_import_log` database table:

```sql
SELECT * FROM ut_robinhood_import_log ORDER BY imported DESC LIMIT 20;
```

---

## Running Manually

Force an immediate import outside cron:

```php
// In a custom controller, Drush command, or devel execute block:
\Drupal::service('ut_robinhood.order_importer')->import();
```

Or trigger via the queue directly:
```bash
drush queue:run ut_robinhood_import
```

---

## Uninstalling

```bash
drush pmu ut_robinhood -y
```

This removes the `robinhood_order` entity table, the `ut_robinhood_import_log`
table, and all module state variables. Entity data is permanently deleted.
