<!-- Pull requests target the `staging` branch. Please read CONTRIBUTING.md first. -->

## What does this change?

<!-- A short summary of the change and why it's needed. -->

Fixes #

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / cleanup (no behaviour change)
- [ ] Documentation
- [ ] Breaking change (needs a note in CHANGELOG.md and readme.txt)

## How was it tested?

<!-- Environment (WordPress, PHP, theme, relevant plugins) and what you checked. -->

- [ ] `composer lint` passes
- [ ] Fresh activation with `WP_DEBUG` on — no notices in `debug.log`
- [ ] Library: allowed types upload, other types and a renamed script are refused
- [ ] Collections render (gallery, audio playlist, mixed); lightbox and player work
- [ ] `[smv_upload_form]` submits and files appear in Submissions
- [ ] Contact Form 7 (if touched): connected form stores files, other forms unchanged
- [ ] A page without shortcodes is unchanged with the plugin active
- [ ] Not applicable — documentation only

## Screenshots

<!-- For any UI change: before / after. -->

## Checklist

- [ ] Input is sanitised, output is escaped, admin actions check capability + nonce
- [ ] No new CSS/JS/queries on pages that don't use the plugin
- [ ] User-facing strings are translatable (`secure-media-vault`)
- [ ] `CHANGELOG.md` (Unreleased) and docs updated where behaviour changed
- [ ] Database schema change? `SMV_DB_VERSION` bumped
