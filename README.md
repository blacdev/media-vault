# Secure Media Vault

[![CI](https://github.com/blacdev/media-vault/actions/workflows/ci.yml/badge.svg?branch=staging)](https://github.com/blacdev/media-vault/actions/workflows/ci.yml)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP 7.4–8.4](https://img.shields.io/badge/PHP-7.4%E2%80%938.4-777bb4)
![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue)

A WordPress plugin for **secure image and audio uploads** to a folder you control (optionally Dropbox), with **reusable galleries and audio playlists** you place anywhere with a shortcode, a **protected upload form**, and optional **Contact Form 7** integration.

- [What it's for](#what-its-for)
- [Where it can be used](#where-it-can-be-used)
- [Installation](#installation)
- [How to use it](#how-to-use-it)
- [Security](#security)
- [Development](#development)
- [Issues and pull requests](#issues-and-pull-requests)
- [License](#license)

---

## What it's for

Secure Media Vault is for site owners who want full control over the images and audio on their site, and over files people send them, without depending on the WordPress Media Library:

- **Keep media in one protected place.** Files go to a folder you name inside `wp-content/uploads/`, which blocks script execution. Private files are stored with random names and can't be opened directly from the web.
- **Build once, use everywhere.** Put images or audio in a *collection* (image gallery, audio playlist, or both mixed) and place it on any page with a shortcode. Edit the collection and every page that uses it updates.
- **Receive files safely.** A drop-in upload form (with your own extra fields) or your existing Contact Form 7 forms deliver files to a private inbox that only administrators (and any users you choose under Settings → Access) can open.
- **Choose where files live.** For each source (your Library, the upload form, Contact Form 7) choose *Local folder*, *Local + Dropbox backup*, or *Dropbox only*.
- **Stay within limits.** Set a total storage cap (default 50 GB) and change it any time.

Always-available file types: **JPG, JPEG, PNG, MP3, WAV**. Optional formats (GIF, WebP, AVIF, M4A, OGG, FLAC, AAC, MP4, M4V, WebM, MOV, PDF, Office files, TXT, CSV, ZIP) are **off by default** and must be enabled in *Settings → Storage → File formats* before they can be selected or uploaded.

## Where it can be used

| | Supported |
|---|---|
| **WordPress** | 6.0 or newer (single site) |
| **PHP** | 7.4 – 8.4 |
| **Web servers** | Apache, LiteSpeed, nginx (nginx needs two extra rules, shown in the plugin), IIS |
| **Editors / builders** | Block editor (Shortcode block), Classic editor, Elementor (Shortcode and Text Editor widgets, editor preview, popups) |
| **Themes** | Any theme, including AJAX page-loading themes such as **Pro Radio** (galleries, players and forms keep working when pages load by AJAX) |
| **Forms** | Built-in `[smv_upload_form]`, and **Contact Form 7** (opt-in per form) |
| **Storage** | Local server folder, optional **Dropbox** (OAuth 2 with PKCE) |
| **Tested alongside** | WooCommerce, Elementor, Yoast SEO, Contact Form 7, WP Ghost (Hide My WP Ghost), Autoptimize; themes Twenty Twenty-Five, Twenty Twenty-One, Astra |

Not supported / not tested: WordPress multisite; form plugins other than Contact Form 7 (WPForms, Gravity Forms, Elementor Forms…); scriptable formats such as SVG, HTML or PHP (never allowed).

The plugin adds **nothing** to pages that don't use its shortcodes: no CSS, no JavaScript, no database queries.

## Installation

**From a release zip (recommended)**

1. Download `secure-media-vault.zip` from the [Releases](https://github.com/blacdev/media-vault/releases) page, or build it yourself (see [Building the zip](#building-the-zip)).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, **Install Now**, then **Activate**.
3. To update later, upload the new zip the same way and choose **Replace current with uploaded**. Files, collections and submissions are kept.

> Don't install GitHub's "Download ZIP" of the repository directly: the folder name would not match the plugin slug. Use a release zip or the build script.

**Before installing on a live site:** take a backup, and try it on a staging copy if your host offers one.

## How to use it

After activation you'll find a **Media Vault** menu with five sections: **Library**, **Collections**, **Upload form**, **Submissions** and **Settings**.

### 1. Upload media

**Media Vault → Library → Upload files.** Drag in JPG/PNG images or MP3/WAV audio (or any optional format you've enabled and allowed). Click a file to edit its title and caption, copy its URL, or delete it.

### 2. Create a collection and place it on a page

**Media Vault → Collections → New collection.** Pick a type, add media, drag to reorder, save. The shortcode is copied for you:

```text
[smv_collection id="1"]
[smv_collection slug="summer-tour"]
```

| Type | Output |
|---|---|
| Image gallery | Responsive grid with a lightbox (keyboard and swipe) |
| Audio playlist | Player with a track list, optional auto-advance and loop |
| Video playlist | Player with a video list (needs a video format enabled) |
| Download list | Files with Download buttons (needs a document format enabled) |
| Mixed media | Every item shown by its type, in the collection's order |

Per-page overrides: `columns="4"`, `captions="yes|no"`, `lightbox="yes|no"`, `autoplay="yes|no"`, `loop="yes|no"`, `class="my-class"`.

### 3. Accept uploads from visitors

**Media Vault → Upload form** has a builder that writes the shortcode for you:

```text
[smv_upload_form]
[smv_upload_form label="Demos" title="Send us your demo" types="mp3,wav" max_files="3"]
```

Add your own fields with `custom`. Fields are comma-separated; each is `Label[:type][(Choice|Choice)][:required][:half]`:

```text
[smv_upload_form custom="Phone:tel:required:half, Company:half, Genre:select(Rock|Pop|Jazz):required, I agree to the terms:checkbox:required"]
```

Types: `text`, `textarea`, `email`, `tel`, `url`, `number`, `date`, `select`, `radio`, `checkbox`.

Who may upload (logged-in users only, or anyone), size and count limits, the success message and email notifications are set in **Settings → Upload forms**. Uploads arrive privately in **Submissions**.

### 4. Use your existing Contact Form 7 forms (optional)

Edit a CF7 form, open its **Media Vault** tab, and tick **Save files sent through this form to Media Vault**. For that form only:

- files from its `[file]` fields are stored privately with all the answers;
- the notification email carries a link to the files instead of attachments (`[mediavault-link]` places it yourself);
- the visitor's acknowledgement email never gets the link or files.

Forms you don't switch on are not touched. `[mediavault collection:3]` shows a collection inside a CF7 form.

### 5. Enable more file formats (optional)

**Settings → Storage → File formats:** tick the extra formats you need (e.g. PDF, MP4) and save. They then appear under *Allowed file types* (Library) and on the *Upload forms* tab; select them there to accept uploads. Enabling video adds the *Video playlist* type; enabling documents adds the *Download list* type.

### 6. Storage, Dropbox and limits

- **Settings → Storage → Where files are stored:** choose *Local folder*, *Local + Dropbox backup* or *Dropbox only*, separately for Library, Upload form and Contact Form 7 (each CF7 form can override). Local folder is the default everywhere.
- **Settings → Dropbox:** create a Dropbox app, add the redirect URI shown, enter the app key and secret, click **Connect Dropbox**.
- **Settings → Storage → Storage limit:** total space for all uploads (default 50 GB, `0` = no limit). When it's reached, uploads are refused and the upload form shows a "not accepting files" message.
- **Settings → Security & status:** run the folder-protection check. On nginx, add the two rules it shows.

Full end-user documentation is in [`readme.txt`](readme.txt).

## Security

- Every admin action checks capability (`manage_options`, filterable via `smv_capability`) and a nonce.
- Strict allow-list: JPG, JPEG, PNG, MP3, WAV by default; other formats only after an admin enables them. Content sniffing rejects renamed scripts; SVG/HTML/PHP are never allowed.
- Uploads are renamed. The upload folder blocks script execution, and private submissions are denied direct web access and use random names.
- Public forms use a signed configuration, nonce, honeypot, timing check and per-visitor rate limit. All field values are validated server-side.
- Dropbox secret and tokens are encrypted at rest (AES-256-GCM). Optionally define `SMV_ENCRYPTION_KEY` in `wp-config.php`.

Found a vulnerability? Please **don't open a public issue**. See [SECURITY.md](SECURITY.md).

## Development

### Requirements

- PHP 7.4+ and [Composer](https://getcomposer.org/)
- A local WordPress site (e.g. [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), LocalWP, DDEV)
- `zip` and `rsync` for building the release zip

### Setup

```bash
git clone https://github.com/blacdev/media-vault.git
cd media-vault
composer install          # coding-standard tools
composer lint             # WordPress Coding Standards + PHP 7.4+ compatibility
composer lint:fix         # auto-fix formatting
```

Symlink or copy the folder into `wp-content/plugins/secure-media-vault` of your local site and activate it.

### Building the zip

```bash
composer build            # or: bash bin/build-zip.sh
```

This creates `dist/secure-media-vault.zip` containing only the plugin files (development files are listed in [`.distignore`](.distignore)).

### Project structure

```text
secure-media-vault.php        Plugin bootstrap (header, constants, class loading)
uninstall.php                 Cleanup on delete (data only if the user opted in; files never)
includes/
  class-smv-plugin.php        Boots all components on `init`
  class-smv-settings.php      Settings, defaults, sanitising, allowed file types
  class-smv-installer.php     Database tables and upgrades
  class-smv-storage.php       Validation, storage, folder protection, storage limit, Dropbox sync
  class-smv-files.php         File records
  class-smv-collections.php   Collections
  class-smv-shortcodes.php    [smv_collection] rendering and front-end asset loading
  class-smv-forms.php         [smv_upload_form] rendering and submission handling
  class-smv-cf7.php           Contact Form 7 integration
  class-smv-dropbox.php       Dropbox OAuth and API
  class-smv-download.php      Authenticated private downloads
  class-smv-ajax.php          Admin AJAX endpoints
  class-smv-admin.php         Admin menus, assets, notices
  class-smv-crypto.php        Encryption of stored secrets
admin/views/                  Admin screens
assets/css, assets/js         Admin and front-end styles/scripts (no build step, no dependencies)
```

## Issues and pull requests

Contributions are welcome. Please read **[CONTRIBUTING.md](CONTRIBUTING.md)** first. In short:

- **Bug?** [Open an issue](https://github.com/blacdev/media-vault/issues/new/choose) with the *Bug report* form: versions, steps to reproduce, expected vs. actual, and any console/PHP errors.
- **Idea?** Use the *Feature request* form and describe the problem you want solved.
- **Pull request?** Branch from `staging`, keep the change focused, run `composer lint`, test the checklist, and open the PR against `staging` using the template.
- **Security issue?** Report it privately. See [SECURITY.md](SECURITY.md).

## License

[GPL-2.0-or-later](LICENSE), the same license as WordPress.
