# scafera/scaffold

Composer plugin that scaffolds framework-owned files into Scafera projects.

> **Provides:** Composer plugin that copies framework-owned files (entry point, kernel bootstrap, `.gitignore`, config examples) into Scafera projects on `composer install`/`update`. Files are always overwritten (except `initial-files`, copied once) to keep framework and project in sync.
>
> **Depends on:** Composer 2+ (`composer-plugin-api ^2.0`). Activates automatically for any installed package that declares `extra.scafera-scaffold`.
>
> **Extension points:**
> - `composer.json` → `extra.scafera-scaffold.files` — logical keys mapped to source paths in the declaring package; always overwritten on install
> - `extra.scafera-scaffold.initial-files` — literal target paths; copied once, never overwritten (intended for user-editable configs)
> - `extra.scafera-scaffold.target-map` — architecture packages remap logical keys to project-specific paths (e.g. `index.php` → `public/index.php`)
> - `extra.scafera-scaffold.file-mapping` — project-level opt-out (`"public/index.php": false` to disable a scaffolded file)
> - Convention — package-side scaffold sources live under `support/scaffold/` at the package root
>
> **Not responsible for:** Code generation (use `scafera make:controller`, `make:service`, `make:command` from the architecture package — e.g. `scafera/layered`) · application scaffolding (only framework-owned files are copied) · modifying user-authored code · conflict resolution beyond last-package-wins for duplicate logical keys.

## How It Works

Scafera packages declare which files they provide. The plugin collects these declarations from all installed packages and copies the files into the project.

```
composer install
  → plugin reads declarations from installed packages
  → copies files to project
  → creates directories as needed
```

## Package Declaration

Packages declare scaffold files in their `composer.json` under `extra.scafera-scaffold`.

### `files` — Always Overwritten

Use a logical key (not a path) to identify each file. The key decouples the file identity from its target location.

```json
{
    "extra": {
        "scafera-scaffold": {
            "files": {
                "index.php": "support/scaffold/public/index.php"
            }
        }
    }
}
```

The logical key is used as the default target path. To place the file elsewhere, an architecture package provides a `target-map` (see below).

### `initial-files` — Created Once

Files that are only copied if the target does not already exist. Useful for configuration files that the developer is expected to modify.

```json
{
    "extra": {
        "scafera-scaffold": {
            "initial-files": {
                "config/config.yaml": "support/scaffold/config/config.example.yaml"
            }
        }
    }
}
```

Unlike `files`, initial files use literal target paths (not logical keys).

### `target-map` — Control Placement

Architecture packages can remap where a logical key is placed without duplicating the source file.

```json
{
    "extra": {
        "scafera-scaffold": {
            "target-map": {
                "index.php": "public/index.php"
            }
        }
    }
}
```

This tells the plugin: place the file identified by `index.php` at `public/index.php`. The source remains in the kernel — no duplication.

A different architecture package could remap the same key:

```json
{
    "target-map": {
        "index.php": "web/index.php"
    }
}
```

## Project-Level Overrides

Projects can disable specific scaffolded files via `file-mapping`:

```json
{
    "extra": {
        "scafera-scaffold": {
            "file-mapping": {
                "public/index.php": false
            }
        }
    }
}
```

Disabled files are skipped during scaffolding with a console message.

## File Placement Convention

Packages that provide scaffold files should place them under `support/scaffold/` at the package root:

```
my-package/
├── src/
├── support/
│   └── scaffold/
│       └── public/
│           └── index.php
└── composer.json
```

## Conflict Resolution

If multiple packages declare the same logical key, the last package processed wins. Package order is determined by Composer's dependency resolution.

## Console Output

The plugin logs every action during scaffolding:

```
Scafera: scaffolding project files...
  .gitignore → .gitignore (from scafera/layered)
  config/config.example.yaml → config/config.example.yaml (from scafera/kernel)
  index.php → public/index.php (from scafera/kernel)
  Skipped: .gitignore (already exists)
Scafera: 3 file(s) scaffolded.
```

## Requirements

- PHP >= 8.4

## License

MIT
