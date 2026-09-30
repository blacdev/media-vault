# Security policy

Secure Media Vault handles uploads from the public, so security reports are taken seriously and handled privately.

## Supported versions

Only the latest release receives security fixes. Please update before reporting.

## Reporting a vulnerability

**Do not open a public issue, discussion or pull request for a vulnerability.**

Report it privately through GitHub:

1. Go to the repository's **Security** tab → **Report a vulnerability** ([direct link](https://github.com/blacdev/media-vault/security/advisories/new)).
2. Include:
   - the affected version;
   - the type of issue (e.g. file upload bypass, XSS, CSRF, access to private submissions, SQL injection);
   - step-by-step reproduction, including a minimal proof of concept;
   - the impact you believe it has and any conditions required (logged-in role, server type, settings).

Please don't access, modify or delete other people's data, and don't run automated scanners against sites you don't own.

## What to expect

- Acknowledgement within **3 working days**.
- An initial assessment within **7 working days**.
- A fix released as soon as practical, then a published advisory crediting you (unless you prefer to stay anonymous).

## Scope

In scope: the plugin's own code in this repository.

Out of scope: vulnerabilities in WordPress core, other plugins or themes, server misconfiguration (for example nginx not applying the folder rules shown under *Settings → Security & status*), and issues requiring an administrator account to attack the same site.
