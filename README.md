# Lightroom Publish Service

A TYPO3 extension (`lightroom_publish_service`) that lets you publish photos directly from **Adobe Lightroom Classic** into TYPO3, using Lightroom's built-in "Publish Services" feature (the same mechanism used to publish to Flickr, SmugMug, etc.).

This package ships **two halves**:

1. **The TYPO3 extension** — a token-authenticated JSON API plus a backend module for managing API tokens and browsing published photos/collections.
2. **The Lightroom Classic plugin** — Lua source under [`Resources/Private/Plugin/typo3_publish_service.lrplugin/`](Resources/Private/Plugin/typo3_publish_service.lrplugin/), which talks to that API from within Lightroom's Publish Services panel.

## Features

- **Upload** — new photos in a Lightroom Published Collection are uploaded on first publish.
- **Update** — republishing an already-published photo replaces the file and/or metadata.
- **Delete** — removing a photo from a Published Collection deletes it in TYPO3 too.
- **Metadata sync** — title, caption, keywords, GPS coordinates + altitude, star rating, color label, creator, copyright, and capture date.
- **Collections** — Lightroom Published Collections and Collection Sets map to nestable Collection records in TYPO3, with rename/reparent/delete kept in sync.
- Photos are stored in a **dedicated FAL storage** (`fileadmin/lightroom_publish_service/`), isolated from editor-managed site media.
- Compatible with **TYPO3 v13.4 LTS** and **v14.3 LTS**.

## Quick install

### 1. Install the extension

```bash
composer require rpflamm/lightroom-publish-service
```

If you develop this extension from source rather than a released package, add it as a
[Composer path repository](https://getcomposer.org/doc/05-repositories.md#path) instead. Running
under DDEV? Prefix any `composer`/`vendor/bin/typo3` command below with `ddev`
(e.g. `ddev composer require ...`, `ddev exec vendor/bin/typo3 ...`).

### 2. Set up the database and FAL storage

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 upgrade:run lightroomPublishServiceCreateStorage
```

### 3. Create an API token

Open the **Lightroom Publish Service** backend module (under **Web**) → **API tokens** → **Create new token**. Copy the token immediately — it's shown only once.

### 4. Install and configure the Lightroom plugin

In Lightroom Classic: **File → Plug-in Manager → Add**, then select the `typo3_publish_service.lrplugin` folder. Enter your TYPO3 site's URL and the API token in its settings, then click **Test Connection**.

See the [Lightroom plugin README](Resources/Private/Plugin/typo3_publish_service.lrplugin/README.md) for detailed publishing instructions.

## Documentation

Full documentation (installation, configuration, editor/administrator manuals, and the complete JSON API reference) is available under [`Documentation/`](Documentation/Index.rst).

## Testing

```bash
vendor/bin/phpunit -c Build/UnitTests.xml
vendor/bin/phpunit -c Build/FunctionalTests.xml
```

## Known issues

- **JPEG/sRGB only** — the Lightroom plugin currently restricts export to JPEG files in the sRGB color space. RAW/TIFF/original-file publishing is not yet supported.
- **One TYPO3 site per Lightroom install** — the server URL and API token are configured once, globally, in the plugin's Plugin Manager settings rather than per Publish Service connection. Publishing to multiple TYPO3 sites from the same Lightroom catalog isn't supported yet.
- **No per-token scoping** — any valid API token has full access to all collections and photos; there's no way to restrict a token to a subset of collections.
- **`sys_file_reference` written directly** — the extension writes file-reference rows via raw SQL rather than TYPO3's `DataHandler` (there's no backend user session in the API's request context). This keeps the reference index consistent for normal use, but bulk/manual reference-index maintenance operations (e.g. `referenceindex:update`) should be run after any direct database manipulation of these tables.
- **No comments/ratings sync back from TYPO3** — the API is one-directional (Lightroom → TYPO3); TYPO3-side edits to photo metadata are not pulled back into Lightroom.
- **No automated Lua tests** — the Lightroom Classic SDK has no test runner, so the plugin side (upload/update/delete, metadata sync, collection lifecycle) can only be verified manually in Lightroom itself, not via CI.

## Development notes

This project was developed with the assistance of AI coding agents; all generated code has been reviewed by a human before being committed.

## License

GPL-2.0-or-later
