Netgen Ibexa Import/Export
==========================

[![Downloads](https://img.shields.io/packagist/dt/netgen/ibexa-import-export.svg)](https://packagist.org/packages/netgen/ibexa-import-export)
[![Latest stable](https://img.shields.io/packagist/v/netgen/ibexa-import-export.svg)](https://packagist.org/packages/netgen/ibexa-import-export)
[![Ibexa](https://img.shields.io/badge/Ibexa-%E2%89%A5%204.6-orange.svg)](https://www.ibexa.co)

Netgen’s Import/Export module for Ibexa DXP adds an administration UI for exporting content to
Kaliop/Tano migrations and importing those migrations back into another environment.

The module is primarily intended for content transfer workflows (e.g. moving content/configuration
between environments) and is designed to work inside Ibexa Admin UI.

Installation & license
----------------------

Install the package with:

```bash
composer require netgen/ibexa-import-export
```

Licensed under [GPLv2](LICENSE)

Import/Export module
--------------------

After installation, the module appears in Ibexa Admin UI under the **Admin** menu
as **Import/Export**.

The UI provides:

* **Export**
  * Export a single content item or a subtree into a Kaliop/Tano migration YAML.
  * The generated migration is saved into the configured migrations directory and can be downloaded.
* **Import**
  * Upload a migration YAML and execute it.
  * The import preview checks the file and shows:
    * global errors (blocking)
    * skipped content items
    * skipped fields (per field)

Configuration
-------------

You can configure the bundle under `netgen_ibexa_import_export`:

```yaml
netgen_ibexa_import_export:
    storage_path: 'public/var/site/storage'
    migrations_path: 'var/cache/migrations'
    # Optional. If null/empty, PHP binary is auto-detected via Symfony PhpExecutableFinder.
    php_binary_path: ~
```

#### `storage_path`

Used for export/import storage handling.

#### `migrations_path`

Directory (relative to project root) where generated/uploaded migration files are stored.

#### `php_binary_path`

Path to the PHP binary used to run console commands (e.g. `../bin/console kaliop:migration:*`).

If not configured (null/empty), the bundle will auto-detect the PHP executable.

Permissions
-----------

Access to the Import/Export module is **restricted by Ibexa policies**.

To access the module, the user’s role must include the following policy:

* Module: `import_export`
* Function: `access`

Users without this policy:

* will **not** see the Import/Export menu item, and
* will receive an **Access Denied** exception if they try to access module routes directly.

This bundle registers the policy definition automatically (so it appears in the Role editor).

Logging
-------

This bundle logs on a dedicated Monolog channel: `netgen_ibexa_import_export`.

Note: this assumes your application uses `symfony/monolog-bundle` (standard in most Symfony apps).
If you don’t use Monolog, the bundle falls back to a `NullLogger` (logs are dropped).

### Route bundle logs to a separate file

In your application, configure a handler for the channel in `config/packages/monolog.yaml`:

```yaml
monolog:
    handlers:
        netgen_ibexa_import_export:
            type: stream
            path: '%kernel.logs_dir%/netgen_ibexa_import_export.%kernel.environment%.log'
            level: info
            channels: ['netgen_ibexa_import_export']
```

After that, bundle logs will be written to e.g. `var/log/netgen_ibexa_import_export.dev.log`.

If you do not configure a dedicated handler for the channel, logs will either:

* end up in your default handlers (e.g. `var/log/dev.log`) if those handlers accept this channel, or
* be ignored if none of your handlers are configured to handle this channel.
