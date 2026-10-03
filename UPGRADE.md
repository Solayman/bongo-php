# Upgrading the Bongo PHP Framework

```bash
php bongo upgrade --check     # installed vs latest version, and what changed
php bongo upgrade             # download, verify, back up, replace the framework files
php bongo migrate             # then run new migrations
php bongo test                # and your tests
php bongo upgrade --rollback  # if something is wrong: put the previous version back
```

An upgrade replaces only the framework: `system/`, `bongo`, `public/index.php` and `config/bootstrap.php`. Your `app/`, `routes/`, `resources/`, `config/*.php` settings, `database/` and `storage/` are never touched, so **don't edit files inside `system/`**. Put your changes in `app/` instead.

New settings in a release are listed after the upgrade (for example `New settings in config/mail.php: …`). Copy the ones you want from the release's `config/` files.

Without internet access, download `bongo-framework-X.Y.Z.json.gz` and its `.sha256` from the release page and run `php bongo upgrade --from=bongo-framework-X.Y.Z.json.gz`. For a private repository, set `UPGRADE_TOKEN` in `.env` to a GitHub token that can read it.

Versions follow semantic versioning: a change in the **first** number (1.x → 2.0) can need changes in your code, and those are described below.

## Changes per version

### 0.9.0
First versioned release.
