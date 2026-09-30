=== Secure Media Vault ===
Contributors: blacdev
Tags: uploads, dropbox, gallery, audio playlist, file upload form
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure uploads to a folder you control, optional Dropbox storage, reusable media collections via shortcodes, and protected front-end upload forms with your own custom fields.

== Description ==

Secure Media Vault adds a "Media Vault" menu to your WordPress admin with five sections:

* **Library** – drag-and-drop uploads into your own protected folder (wp-content/uploads/<your folder>).
* **Collections** – build image galleries, audio playlists or mixed sets (plus video playlists and download lists when those formats are enabled).
  Each one gets a shortcode such as `[smv_collection id="3"]`. Edit the collection and every page using it updates.
* **Upload form** – build your upload form and copy its `[smv_upload_form]` shortcode (with a step-by-step guide).
* **Submissions** – files people send you through the upload form (or connected Contact Form 7 forms), with their answers.
  Submitted files stay private; only administrators can download them.
* **Settings** – folder, file types, size limits, form rules, Dropbox connection and a security check.

Files can be stored locally, locally with a Dropbox backup, or in Dropbox only.

== Installation ==

1. In WordPress go to Plugins → Add New → Upload Plugin, choose `secure-media-vault.zip`, click Install Now, then Activate.
2. Go to Media Vault → Settings and set your folder name, allowed file types and limits. Click Save settings.
3. (Optional) Connect Dropbox – see "Dropbox" below.
4. Go to Media Vault → Security & status (in Settings) and click "Run check" to confirm your private folder is protected.

Updating: upload the new zip the same way; WordPress will offer to replace the current version. Your files, collections and submissions are kept.

== Accepted file types ==

Always available: **JPG, JPEG, PNG** (images) and **MP3, WAV** (audio).

Optional formats – off until you enable them in Settings → Storage → File formats:

* Images: GIF, WebP, AVIF
* Audio: M4A, OGG, FLAC, AAC
* Video: MP4, M4V, WebM, MOV
* Documents: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, ZIP

How it works:

1. Enable a format under **File formats** and save. It then appears as a choice under **Allowed file types** (Library, on the Storage tab) and **File types accepted by forms** (Upload forms tab).
2. Select it where you want to accept it and save. Only then can files of that format be uploaded there.

Enabling a video format adds the **Video playlist** collection type and the Video filter; enabling a document format adds the **Download list** type and the Documents filter. Turning a format off again hides its files and stops new uploads, without deleting anything.

Scriptable formats (PHP, HTML, SVG, JavaScript, programs) are never supported, and every file's contents are checked against its extension.

== Installing on a live site safely ==

The plugin is designed to stay out of the way of the rest of your site:

* It adds nothing to pages that don't use its shortcodes – no CSS, no JavaScript, no database queries. (Tested: public pages are byte-for-byte identical with the plugin on or off.)
* Its admin CSS/JS load only on Media Vault screens.
* It never touches the WordPress Media Library, your theme, other plugins' data, or your site's main .htaccess. Its .htaccess rules live only inside its own upload folder.
* It uses its own prefixed database tables (wp_smv_*) and options (smv_*).
* If the upload folder can't be written, it shows an admin notice instead of breaking anything.
* Tested alongside WooCommerce, Elementor, Yoast SEO and Contact Form 7, with the Twenty Twenty-Five, Twenty Twenty-One and Astra themes, and with an AJAX page-loading radio theme like Pro Radio; compatible with PHP 7.4–8.4 and WordPress 6.0+.

Recommended steps anyway (good practice for any new plugin):

1. Take a backup (most hosts have one-click backups; or use a backup plugin).
2. If your host offers a staging site, install there first and click through your key pages.
3. Install and activate. Visit your homepage and a few important pages to confirm they look the same.
4. Settings → Security & status → "Run check".

To remove it: deactivate (pages that used its shortcodes will show the raw [smv_…] text until you remove the shortcodes), then delete. Your files are always kept.

== Pro Radio and other AJAX page-loading themes ==

Pro Radio (and similar music/radio themes) load pages with AJAX so the radio keeps playing while visitors browse.

* Pages that don't use a Media Vault shortcode are never touched – nothing is added to them, with or without Pro Radio. (Tested: an Elementor homepage is byte-for-byte identical with the plugin on or off, including with an optimisation plugin combining CSS/JS.)
* On pages that do use a shortcode, each gallery, playlist and form carries a tiny loader for its own style and script, so it keeps working when the theme brings it in with AJAX. It's marked so optimisation plugins (Autoptimize, LiteSpeed Cache, WP Rocket, Cloudflare) leave it alone.
* Gallery lightbox links, download buttons and form uploads are shielded from the theme's AJAX link handling ("#noajax").
* One sound at a time: when a Media Vault player starts, the site's other audio (such as the theme's radio player) is paused, and the other way round. Turn it off under Settings → Storage → Theme compatibility.

If a gallery or form only works after a full page refresh (some AJAX loaders don't run scripts in new content), choose "On every page" under Theme compatibility, then check your site's layout and clear your optimisation plugin's cache. Pro Radio's own per-page "disable ajax" option also works.

For developers: `window.smvInit( element )` re-scans an element, and a `smv:play` event fires on document when a Media Vault player starts.

== Elementor ==

* Use the Shortcode widget (or paste the shortcode into a Text Editor widget). Both render normally, including in the Elementor editor preview.
* Elementor's built-in image lightbox is switched off for Media Vault galleries automatically, so only one lightbox opens. Elementor's lightbox keeps working everywhere else.
* Content inside Elementor tabs, accordions and popups works too.

== Quick start ==

1. Media Vault → Library → "Upload files", then drop your images (JPG, PNG) or audio (MP3, WAV).
2. Media Vault → Collections → "New collection". Name it, pick a type (e.g. Image gallery), click "Add media", select files, drag to reorder, click "Create collection". The shortcode is copied for you.
3. Edit any page, add a Shortcode block, paste the shortcode, publish.
4. Later, add or remove files in the collection and click Save – every page using that shortcode updates automatically.

== Library ==

* Drag files onto the page (or click "Upload files"). Several files upload in parallel with progress bars.
* Filter by type, search by name, click a file to edit its title and caption, copy its URL, or delete it.
* "Select" lets you delete several files at once.
* Deleting a file also removes it from every collection and from Dropbox.
* Images get an optimised preview (800px) that galleries use for fast loading; the lightbox opens the full image.

== Collections (media shortcodes) ==

Types:

* **Image gallery** – responsive grid with a lightbox (keyboard, swipe, captions).
* **Audio playlist** – player with a track list; can continue to the next track and loop.
* **Video playlist** – player with a video list (available when a video format is enabled).
* **Download list** – tidy list of files with a Download button (available when a document format is enabled).
* **Mixed media** – each item rendered according to its type.

Place a collection anywhere with its id or its slug:

`[smv_collection id="1"]`
`[smv_collection slug="summer-tour"]`

Per-page overrides (optional):

* `columns="4"` – gallery columns (1–8)
* `captions="yes|no"`
* `lightbox="yes|no"`
* `autoplay="yes|no"` – continue to next track
* `loop="yes|no"`
* `class="my-class"` – extra CSS class on the wrapper

Example: `[smv_collection id="1" columns="4" captions="no"]`

== Upload forms ==

= How to add a form to your site (step by step) =

These steps are also shown on the Media Vault → Upload form tab, next to the builder.

1. **Build it.** Go to Media Vault → Upload form and use the form builder. Choose the standard fields (name, email, message) and which are required, tick the file types to accept, set the maximum number of files, and click "Add field" for any extra questions.
2. **Copy it.** The grey shortcode box at the bottom of the builder updates as you go. Click it to copy.
3. **Paste it.** Edit any page or post. In the block editor add a "Shortcode" block (type /shortcode), paste, and Publish/Update. In the classic editor or a page builder's shortcode/text widget, paste it where the form should appear.
4. **Choose who can use it.** Media Vault → Settings → Upload forms → "Who can upload":
   * *Logged-in users only* (default) – visitors see a "Log in" button instead of the form.
   * *Anyone* – protected by a hidden bot trap, a timing check and an hourly limit per visitor.
   Also set there: files per upload, max size per file, uploads per hour, accepted types, success message, and email notifications.
5. **Receive files.** Each upload appears under Media Vault → Submissions with the sender's answers. Files are private: click Download to get a copy, or "Move to library" to use a file in a collection. "Delete" removes the submission and its private files.

The simplest form is just:

`[smv_upload_form]`

= All form options =

* `label` – internal name shown in Submissions so you can tell forms apart. Visitors never see it.
* `title` – heading shown above the form.
* `description` – intro text under the heading.
* `fields` – standard fields to show: any of `name,email,message` (default: all three). `fields=""` shows none.
* `required` – which standard fields are mandatory (default: `name,email`).
* `custom` – your own extra fields (see next section).
* `types` – file types for this form, e.g. `types="mp3,wav"`. Can only narrow the types enabled in Settings.
* `max_files` – files per upload. Can only be lower than the Settings limit.
* `button` – submit button text.

Example:

`[smv_upload_form label="Demo submissions" title="Send us your demo" fields="name,email" types="mp3,wav" max_files="3" button="Send demo"]`

= Adding more input fields (custom fields) =

You can add as many extra fields as you need (up to 25 per form). The easiest way is the builder: click "Add field", type a label, choose a type, add choices if needed, and tick "Required" / "Half width". The builder writes the shortcode for you.

To write it by hand, add a `custom` attribute. Fields are separated by commas; each field is:

`Label[:type][(Choice 1|Choice 2)][:required][:half]`

* **Label** – what the visitor sees. Always first. Don't use commas, colons, brackets, quotes or `|` inside it.
* **type** – one of:
  * `text` – short text (default when no type is given)
  * `textarea` – long text
  * `email` – validated email address
  * `tel` – phone number (digits, spaces, + ( ) - .)
  * `url` – website link (http/https only)
  * `number` – numeric value
  * `date` – date picker
  * `select` – dropdown, pick one (needs choices)
  * `radio` – buttons, pick one (needs choices)
  * `checkbox` – with choices: pick several; without choices: a single tick box (e.g. consent)
* **(A|B|C)** – the choices for select, radio and checkbox, separated by `|`.
* **:required** – the visitor must fill it in / tick it / pick one.
* **:half** – half width, so two half fields sit side by side (they stack on phones).

Example:

`[smv_upload_form label="Bookings" title="Book a session" custom="Phone:tel:required:half, Company:half, Website:url, Genre:select(Rock|Pop|Jazz):required, Session length:radio(1 hour|2 hours|Full day):required, Extras:checkbox(Mixing|Mastering|Video), Preferred date:date, I agree to the terms:checkbox:required"]`

Order on the page: name, email, your custom fields, then message, then the file drop area.

Answers appear on each submission in Media Vault → Submissions and in the notification email. Every value is validated on the server: choices must be one of the ones you defined, emails/phones/links/numbers/dates must be valid, and required fields must be filled. The field definitions are signed, so visitors cannot add, remove or change fields.

= Using several forms =

Put different forms on different pages; give each a different `label` so you can tell submissions apart.

= Using your existing Contact Form 7 forms =

You decide per form, inside Contact Form 7. Forms you don't switch on stay exactly as they are.

1. Go to Contact → Contact Forms and edit the form that should accept files (it needs a [file] field, e.g. `[file* your-file limit:20mb filetypes:jpg|jpeg|png|mp3|wav]`).
2. Open its **Media Vault** tab and tick **"Save files sent through this form to Media Vault"**. Click Save.

For that form:

* Uploaded files are stored privately in Media Vault (random names, direct web access blocked) and copied to Dropbox if a Dropbox storage mode is on. The submission appears under Media Vault → Submissions with the sender's name, email, message and every other answer.
* The email you receive is a notification only: the files are not attached ("Don't attach the files to the email" – on by default) and it contains a link to the submission in Media Vault ("Add the Media Vault link automatically" – on by default). To place the link yourself, untick the automatic option and put `[mediavault-link]` in the Mail tab.
* The visitor's acknowledgement email (Mail (2)) never receives the link or the stored files.
* Contact Form 7 keeps its validation, spam protection and messages. If the vault can't store a file, the submission still goes through normally.
* Every file gets the vault's security checks. Scripts, HTML, SVG, programs and files whose contents don't match their extension are never stored; such files are left to Contact Form 7's normal handling.

Overview: Media Vault → Settings → Upload forms → Contact Form 7 lists your forms and shows which ones save to the vault. There's also a switch to turn the integration off for all forms.

Show a gallery or playlist inside a CF7 form: add `[mediavault collection:3]` (collection id or slug) to the Form tab.

Don't put `[smv_upload_form]` inside a CF7 form template: it's a complete form of its own and forms can't be nested. Use CF7's own [file] field.

= Other form plugins =

WPForms, Gravity Forms, Elementor Forms, Fluent Forms and others are not connected. Their uploads stay wherever that plugin stores them. You can place `[smv_upload_form]` on the same page as one of their forms.

= Things to know about forms =

* Page caching: exclude pages containing the form from your caching plugin. The form's security token expires after about a day, so an old cached copy would stop accepting uploads.
* Your server limits how much can be uploaded in one request (shown in Settings). Very large files may need the host's PHP `upload_max_filesize` and `post_max_size` raised.
* Rejected files are listed in the message the visitor sees; the rest of the submission is still saved.

== Dropbox ==

Where files are stored is chosen separately for each place files come from (Settings → Storage → "Where files are stored"):

* **Library** – files you upload yourself.
* **Upload form** – files sent through `[smv_upload_form]`.
* **Contact Form 7** – files from connected CF7 forms. Each CF7 form can override this in its Media Vault tab ("Where to store this form's files").

For each one choose:

* **Local folder** – files stay on your own server (fastest; the default everywhere).
* **Local + Dropbox backup** – kept on your server, with a copy in Dropbox.
* **Dropbox only** – saves server space. Public files are served from Dropbox links (gallery previews stay local); private submissions are downloaded straight from Dropbox when you click Download.

Example: Library and Upload form on "Local folder", Contact Form 7 on "Dropbox only" – only files sent through Contact Form 7 go to Dropbox. Changes apply to new files; files already stored stay where they are. Nothing is sent to Dropbox unless you connect it and choose a Dropbox option.

Connecting (Settings → Dropbox):

1. Create an app at https://www.dropbox.com/developers/apps/create – choose "Scoped access" and "App folder" (recommended) or "Full Dropbox".
2. On the app's Permissions tab enable `files.content.write`, `files.content.read`, `sharing.write`, `sharing.read`, `account_info.read`, then Submit.
3. On the app's Settings tab, add the Redirect URI shown in the plugin (click it to copy).
4. Paste the App key and App secret into the plugin, set the Dropbox folder, click Save settings.
5. Click "Connect Dropbox" and approve. Use "Test connection" to confirm.
6. On the Storage tab, choose where files from each place (Library, Upload form, Contact Form 7) are stored, and save.

If an upload to Dropbox fails, the file stays available locally and the Library shows a "Retry Dropbox sync" button on it.

== Settings reference ==

* **Folder name** – folder inside wp-content/uploads. Changing it only affects new uploads.
* **Storage limit** – total space all uploads may use (default 50 GB; 0 = no limit).
* **Maximum file size / Allowed file types** – rules for Library uploads.
* **Who can upload / Limits / File types accepted by forms** – rules for upload forms.
* **Success message / Email me when someone uploads / Notification email** – what happens after a form upload.
* **Uninstall** – optionally remove settings, collections and records when the plugin is deleted. Files are never deleted.
* **Security & status** – run the folder protection check; see the nginx rules and environment details.

== Security ==

* Every admin action requires an administrator and a valid security token (nonce).
* File types are on a strict allow-list and each file's contents are checked against its extension, so a script renamed to .jpg is rejected. SVG, HTML, PHP, JavaScript and executables are never accepted.
* Uploaded files are renamed; the folder has rules that block script execution.
* Form submissions are stored in a private folder with random names, direct web access is denied, and they are only downloadable by administrators through a signed link.
* Public forms use a signed configuration, a honeypot, a timing check and a per-visitor hourly limit. All field values are validated on the server.
* Dropbox uses OAuth 2 with PKCE; the app secret and tokens are encrypted (AES-256-GCM).

nginx: nginx ignores .htaccess files. If the Security & status check says the private folder is reachable, add the two rules shown there to your nginx server block.

Optional: define `SMV_ENCRYPTION_KEY` in wp-config.php to use a dedicated encryption key. Changing it (or your WordPress salts) means you'll need to re-enter the Dropbox secret and reconnect.

== Troubleshooting: my layout changed after activating ==

1. Deactivate Secure Media Vault. If the layout comes back, reactivate it and continue; otherwise the cause is elsewhere.
2. Clear every cache: your optimisation/caching plugin (e.g. "Purge all" in LiteSpeed Cache, "Delete cache" in Autoptimize/WP Rocket), Elementor → Tools → Regenerate CSS & Data, your host's cache and Cloudflare if used. Combined CSS/JS files built while an older version was active can keep the problem alive.
3. Settings → Storage → Theme compatibility should be "Automatic". With it, pages without a Media Vault shortcode are not touched at all.
4. Still wrong? Open the page, press F12 → Console, and note any red errors (especially ones mentioning secure-media-vault or a combined "autoptimize"/"min" file).

== Frequently Asked Questions ==

= Where are my files? =
In wp-content/uploads/<folder>/media (library) and wp-content/uploads/<folder>/private (form submissions), plus Dropbox if enabled.

= Can I change how collections look? =
Yes – each collection has display options, shortcodes accept overrides, and themes can restyle everything via CSS, e.g. `.smv { --smv-accent: #e11d48; }`.

= What happens if I delete the plugin? =
Dropbox credentials are always removed. Settings, collections and records are removed only if you enabled that under Settings → Storage → Uninstall. Your files are never deleted.

== Changelog ==

= 1.8.0 =
* Optional file formats are back – GIF, WebP, AVIF, M4A, OGG, FLAC, AAC, MP4, M4V, WebM, MOV, PDF, Word, Excel, PowerPoint, TXT, CSV and ZIP – but off by default. Enable them in Settings → Storage → File formats; only then can they be selected and uploaded.
* Video playlists and download lists return when a video or document format is enabled.

= 1.7.0 =
* Storage is chosen per source: Library, Upload form and Contact Form 7 each have their own Local / Local + Dropbox / Dropbox only setting, and each CF7 form can override it. Local folder remains the default everywhere.
* Submissions show where each file is stored, with a Retry button if a Dropbox copy failed.

= 1.6.1 =
* The upload form builder and its guide now have their own "Upload form" tab; Submissions is a clean full-width inbox.

= 1.6.0 =
* Storage limit: cap the total size of all uploads (default 50 GB, adjustable any time, 0 = no limit), with usage meter, warnings and a paused upload form when full.

= 1.5.0 =
* Only JPG, JPEG, PNG, MP3 and WAV are accepted anywhere. Documents, video and other formats were removed, along with the "Video playlist" and "Download list" collection types. Collection types are now Image gallery, Audio playlist and Mixed (images + audio).

= 1.4.0 =
* Contact Form 7 is now opt-in per form, from a new "Media Vault" tab in the CF7 form editor. All other forms stay untouched (1.3.0's "all forms" default is removed; forms selected in 1.3.0 are carried over).
* Connected forms: files are no longer attached to the email; it carries a link to the submission in Media Vault instead (both options per form). The visitor's acknowledgement email never gets the link or the files.
* Settings → Upload forms → Contact Form 7 shows which forms are connected, with links to edit them.

= 1.3.0 =
* Contact Form 7 integration: files from existing CF7 forms are saved privately in the vault (and Dropbox) with all answers, per-form choice, [mediavault-link] mail tag and [mediavault collection:…] form tag. CF7 behaviour is unchanged.

= 1.2.1 =
* Fix: on Pro Radio sites, 1.2.0 loaded the plugin's CSS/JS on every page. Now nothing is added to pages that don't use a Media Vault shortcode, on any theme. "On every page" is opt-in.
* Fix: the scripts could break a site's combined JavaScript file when an optimisation plugin concatenated them after a file without a trailing semicolon, stopping later scripts (e.g. theme/Elementor). Scripts are now safe to combine and minify (tested with the minifiers behind Autoptimize, WP Rocket, LiteSpeed Cache and W3 Total Cache) and can't throw into other code.
* On AJAX-navigation themes, galleries/players/forms bring their own assets via a small optimiser-safe loader.

= 1.2.0 =
* Tested with WP Ghost Lite (Lite Mode, nginx).
* Pro Radio / AJAX page-loading themes: automatic detection, assets available on every page, components start on content loaded later, links and uploads shielded from AJAX navigation, "#noajax" on downloads.
* One sound at a time between Media Vault players and the site's other audio (optional).
* Elementor: no double lightbox, assets available in the editor preview.
* Front-end styles isolated from aggressive theme button/input styles; readable dropdowns on dark themes.
* Fix: form uploads sent from the browser went to the wrong address (a hidden "action" field shadowed the form's URL).

= 1.1.1 =
* Compatibility hardening: guard against duplicate copies/name clashes, boot on init (no early-translation notices on WordPress 6.7+), no PHP warnings if the uploads folder is not writable (clear admin notice instead), colour fallbacks for older browsers, zero extra database queries on normal pages.

= 1.1.0 =
* Custom form fields: text, long text, email, phone, link, number, date, dropdown, radio buttons, checkboxes and consent tick box, with required and half-width options.
* Form builder: "Add field" rows that write the shortcode for you.
* Step-by-step "How to add an upload form" guide on the Submissions page.
* Custom answers shown on each submission and in notification emails.

= 1.0.0 =
* Initial release.
