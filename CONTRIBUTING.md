# Contributing to Secure Media Vault

Thanks for helping improve Secure Media Vault. This guide explains how to report problems, suggest features and submit changes.

## Ground rules

- Be respectful and constructive. Assume good intent, focus on the problem, not the person.
- One topic per issue, one change per pull request.
- **Never post security vulnerabilities publicly.** Follow [SECURITY.md](SECURITY.md).
- Don't include passwords, API keys, Dropbox secrets or personal data (visitor names, emails, IPs) in issues, screenshots or logs.

## Reporting a bug

1. **Search first.** Check [open and closed issues](https://github.com/blacdev/media-vault/issues?q=is%3Aissue) to avoid duplicates. If you find one, add your details there.
2. **Confirm it's this plugin.** Deactivate Secure Media Vault, clear all caches (caching plugin, Elementor → Tools → Regenerate CSS, host cache, Cloudflare) and check whether the problem disappears.
3. **Open a [Bug report](https://github.com/blacdev/media-vault/issues/new?template=bug_report.yml)** and fill in every field:
   - Plugin version, WordPress version, PHP version, theme, and relevant plugins (Tools → Site Health → Info → *Copy site info*, then keep only what's relevant).
   - Exact steps to reproduce, starting from a fresh page load.
   - What you expected and what happened instead.
   - Browser console errors (F12 → Console) and PHP errors (`wp-content/debug.log` with `WP_DEBUG_LOG` on).
   - Screenshots if the problem is visual.

A good bug report can be reproduced by someone else in five minutes.

## Suggesting a feature

Open a [Feature request](https://github.com/blacdev/media-vault/issues/new?template=feature_request.yml). Describe:

- **The problem**, not only the solution ("I need contributors' files to go to a different Dropbox folder" is more useful than "add a setting").
- Who it helps and how often it comes up.
- Any alternatives you considered.

Features that add weight to every page, widen the accepted file types, or loosen security defaults need a strong case.

## Submitting a pull request

### Branches

| Branch | Purpose |
|---|---|
| `staging` | Integration branch. **All pull requests target `staging`.** |
| `feature/<short-name>` | New functionality, e.g. `feature/cf7-folder-per-form` |
| `fix/<short-name>` | Bug fixes, e.g. `fix/playlist-autoplay-safari` |
| `docs/<short-name>` | Documentation only |

Releases are tagged from `staging` (`v1.8.0`, …) after testing.

### Workflow

1. For anything bigger than a small fix, **open or comment on an issue first** so the approach can be agreed before you write code.
2. Fork the repository and create your branch from `staging`:
   ```bash
   git checkout staging && git pull
   git checkout -b fix/short-description
   ```
3. Make your change. Keep it focused; don't reformat unrelated code.
4. Run the checks:
   ```bash
   composer install
   composer lint        # must pass
   ```
5. Test it manually (see the checklist below).
6. Update documentation when behaviour changes: `README.md`, `readme.txt` (end-user docs), and `CHANGELOG.md` under **Unreleased**.
7. Push and open a pull request **against `staging`**. Fill in the template, link the issue (`Fixes #123`), and add screenshots for UI changes.

### Commit messages

Write the summary in the imperative mood, 72 characters max, optionally prefixed with the area:

```text
Forms: validate URL custom fields on the server
Fix playlist not advancing after the last track when loop is on
Docs: explain Dropbox-only storage for Contact Form 7
```

Add a body when the *why* isn't obvious.

### Coding standards

- **PHP** follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) (`phpcs.xml.dist`) and must run on **PHP 7.4 – 8.4** and **WordPress 6.0+**. `composer lint` checks both.
- **Prefix** everything global with `smv_` / `SMV_` (functions, classes, options, hooks, transients).
- **Security is not optional:**
  - check `current_user_can( SMV_Plugin::capability() )` *and* a nonce for every admin action;
  - sanitise all input (`sanitize_*`, `absint`, allow-lists) and escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`);
  - use `$wpdb->prepare()` for every query with variables;
  - every stored file must go through `SMV_Storage` (allow-list + content check + storage limit).
- **Front-end footprint:** don't add CSS, JS or queries to pages that don't use the plugin's shortcodes. Front-end code must work with AJAX page-loading themes, combined/minified assets, and Elementor.
- **JavaScript:** no build step and no dependencies. Keep the files ES5-compatible and safe to concatenate (they start with `void function`). Front-end code goes in `assets/js/frontend.js`, admin code in `assets/js/admin.js`.
- **CSS:** scope front-end styles under `.smv` and admin styles under `.smv-admin`.
- **Strings:** translatable with the `secure-media-vault` text domain, with a `translators:` comment for placeholders.
- **Database changes:** update the schema in `SMV_Installer` and bump `SMV_DB_VERSION`.

### Manual test checklist

Tick what applies in the pull request:

- [ ] Fresh install and activation on WordPress 6.0+ with `WP_DEBUG` on: no notices in `debug.log`.
- [ ] Upgrade from the previous release keeps settings, files, collections and submissions.
- [ ] Library: JPG, JPEG, PNG, MP3, WAV upload; other types and a renamed script are refused.
- [ ] A gallery, audio playlist and mixed collection render; the lightbox and playlist work.
- [ ] `[smv_upload_form]` submits as a visitor and as a logged-in user; files appear in Submissions.
- [ ] Contact Form 7 (if touched): a connected form stores files, unconnected forms are unchanged.
- [ ] A page **without** shortcodes has identical HTML with the plugin active and inactive.
- [ ] Deactivating the plugin leaves the site working.

## Reviews and merging

- A maintainer reviews every pull request. Expect questions: they're about the code, not you.
- CI (PHP lint on 7.4–8.4 and coding standards) must pass.
- Pull requests are squash-merged into `staging` with a clean summary line.

## Releasing (maintainers)

1. Bump the version in `secure-media-vault.php` (header and `SMV_VERSION`) and `readme.txt` (`Stable tag`).
2. Move **Unreleased** entries in `CHANGELOG.md` under the new version; mirror them in `readme.txt`.
3. `composer build`, test the zip on a clean site.
4. Tag `vX.Y.Z` on `staging` and attach `dist/secure-media-vault.zip` to the GitHub release.

## License

By contributing, you agree that your contributions are licensed under the [GPL-2.0-or-later](LICENSE) license.
