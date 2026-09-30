# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- Code cleaned up to the WordPress Coding Standards; removed leftover code from earlier versions.
- Repository documentation: README, contributing guide, security policy, issue and pull-request templates, CI and build script.

## [1.7.0]

- Storage is chosen per source: Library, Upload form and Contact Form 7 each have their own Local / Local + Dropbox / Dropbox only setting, and each CF7 form can override it. Local folder remains the default everywhere.
- Submissions show where each file is stored, with a Retry button if a Dropbox copy failed.

## [1.6.1]

- The upload form builder and its guide now have their own "Upload form" tab; Submissions is a clean full-width inbox.

## [1.6.0]

- Storage limit: cap the total size of all uploads (default 50 GB, adjustable any time, 0 = no limit), with usage meter, warnings and a paused upload form when full.

## [1.5.0]

- Only JPG, JPEG, PNG, MP3 and WAV are accepted anywhere. Documents, video and other formats were removed, along with the "Video playlist" and "Download list" collection types. Collection types are now Image gallery, Audio playlist and Mixed (images + audio).

## [1.4.0]

- Contact Form 7 is now opt-in per form, from a new "Media Vault" tab in the CF7 form editor. All other forms stay untouched (1.3.0's "all forms" default is removed; forms selected in 1.3.0 are carried over).
- Connected forms: files are no longer attached to the email; it carries a link to the submission in Media Vault instead (both options per form). The visitor's acknowledgement email never gets the link or the files.
- Settings → Upload forms → Contact Form 7 shows which forms are connected, with links to edit them.

## [1.3.0]

- Contact Form 7 integration: files from existing CF7 forms are saved privately in the vault (and Dropbox) with all answers, per-form choice, [mediavault-link] mail tag and [mediavault collection:…] form tag. CF7 behaviour is unchanged.

## [1.2.1]

- Fix: on Pro Radio sites, 1.2.0 loaded the plugin's CSS/JS on every page. Now nothing is added to pages that don't use a Media Vault shortcode, on any theme. "On every page" is opt-in.
- Fix: the scripts could break a site's combined JavaScript file when an optimisation plugin concatenated them after a file without a trailing semicolon, stopping later scripts (e.g. theme/Elementor). Scripts are now safe to combine and minify (tested with the minifiers behind Autoptimize, WP Rocket, LiteSpeed Cache and W3 Total Cache) and can't throw into other code.
- On AJAX-navigation themes, galleries/players/forms bring their own assets via a small optimiser-safe loader.

## [1.2.0]

- Tested with WP Ghost Lite (Lite Mode, nginx).
- Pro Radio / AJAX page-loading themes: automatic detection, assets available on every page, components start on content loaded later, links and uploads shielded from AJAX navigation, "#noajax" on downloads.
- One sound at a time between Media Vault players and the site's other audio (optional).
- Elementor: no double lightbox, assets available in the editor preview.
- Front-end styles isolated from aggressive theme button/input styles; readable dropdowns on dark themes.
- Fix: form uploads sent from the browser went to the wrong address (a hidden "action" field shadowed the form's URL).

## [1.1.1]

- Compatibility hardening: guard against duplicate copies/name clashes, boot on init (no early-translation notices on WordPress 6.7+), no PHP warnings if the uploads folder is not writable (clear admin notice instead), colour fallbacks for older browsers, zero extra database queries on normal pages.

## [1.1.0]

- Custom form fields: text, long text, email, phone, link, number, date, dropdown, radio buttons, checkboxes and consent tick box, with required and half-width options.
- Form builder: "Add field" rows that write the shortcode for you.
- Step-by-step "How to add an upload form" guide on the Submissions page.
- Custom answers shown on each submission and in notification emails.

## [1.0.0]

- Initial release.
