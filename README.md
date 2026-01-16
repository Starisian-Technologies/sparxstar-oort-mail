
<img width="1280" height="640" alt="sparxstar-sendgrid-runtime" src="https://github.com/user-attachments/assets/07ffd676-0cc0-4f8c-b807-4aefb7f5a350" />

SPARXSTAR SendGrid Mail Runtime
===============================

**Infrastructure-level SPARXSTAR SendGrid transport for WordPress multisite**

* * * * *

Overview
--------

**SPARXSTAR SendGrid Runtime** is a **network-wide MU-plugin** that provides a deterministic, SendGrid-backed email transport layer for WordPress multisite environments using the official SendGrid mail PHP SDK.

It is designed as **shared infrastructure**, not a product, and is always loaded when present.

This runtime safely intercepts `wp_mail()` and routes email delivery through the SendGrid API while preserving WordPress compatibility and fallback behavior.

[![CodeQL](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/github-code-scanning/codeql)  [![Copilot code review](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer)  [![Copilot coding agent](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/copilot-swe-agent/copilot/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/copilot-swe-agent/copilot)  [![Release](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/release.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime/actions/workflows/release.yml)

* * * * *

Design Goals
------------

- Deterministic, network-wide email delivery

- No SMTP configuration

- No plugin activation lifecycle

- Safe interception of `wp_mail()`

- Composer-optional dependency loading

- Multisite-first architecture

- Minimal surface area and predictable behavior

* * * * *

Key Features
------------

- Intercepts `wp_mail()` using `pre_wp_mail` (best practice)

- Sends mail via SendGrid REST API (no SMTP)

- Optional Composer autoload support

- Network admin health diagnostics

- CC-TLD-aware sender domain resolution

- Full attachment support (local files & dynamic content)

- Filterable sender identity

- WP-CLI test command

- Zero database writes

- Zero background processes

* * * * *

Requirements
------------

| Component     | Requirement            |
|--------------|------------------------|
| WordPress    | 6.8+                   |
| PHP          | 8.2+                   |
| Environment  | Multisite recommended  |
| SendGrid API | Required               |

* * * * *

Installation
------------

### Via Composer (Recommended)

```bash
composer require starisian/sparxstar-sendgrid-runtime
```

### Manual Installation

This runtime **must be installed as an MU-plugin**.

Copy `sparxstar-sendgrid-runtime.php` to:
`/wp-content/mu-plugins/sparxstar-sendgrid-runtime.php`

No activation step is required or supported.

* * * * *

Configuration
-------------

### SendGrid API Key

The SendGrid API key **must** be provided via environment variable:

`SENDGRID_API_KEY=your_api_key_here`

This runtime does **not** support storing credentials in the database or wp-config.php by design.

* * * * *

Runtime Behavior
----------------

### Mail Interception

- Hooks into `pre_wp_mail`

- Validates recipients, subject, and message

- Falls back to native WordPress mail if input is malformed

- Sends via SendGrid only when all conditions are met

### Sender Resolution

The sender email defaults to:

`support@{resolved-domain}`

Domain resolution is:

- CC-TLD aware (`.com.gm`, `.co.za`, `.co.uk`, etc.)

- Reduced to registrable base domain

- Falls back to `sparxstar.com`

* * * * *

Filters
-------

### Override From Email

`add_filter('sparxstar_sendgrid/from_email', function ($email) {
    return 'noreply@example.com';
});`

### Override From Name

`add_filter('sparxstar_sendgrid/from_name', function ($name) {
    return 'My Network';
});`

* * * * *

Actions
-------

### Before Send

`do_action('sparxstar_sendgrid/before_send', $recipients, $subject);`

### After Send

`do_action('sparxstar_sendgrid/after_send', $status_code);`

* * * * *

Network Admin Health Page
-------------------------

Available under:

`Network Admin → Sparxstar Mail`

Displays:

- API key presence

- Resolved sender domain

- Active sender identity

No configuration is performed in the UI.

* * * * *

WP-CLI
------

### Test Email

`wp sparxstar sendgrid test you@example.com`

Outputs success or failure and sends a test email through SendGrid.

* * * * *

Logging
-------

All runtime issues are logged via `error_log()` with consistent prefixes:

`[SPARXSTAR SendGrid WARN]
[SPARXSTAR SendGrid ERROR]`

No logs are stored in the database.

* * * * *

Development
-----------

This project adheres to strict coding standards (WordPress Extra + PSR compatible rules).

### Available Commands

- **Lint Code**: `composer run lint`
- **Auto-fix Code**: `composer run fix` (Fixes indentation, spacing, array syntax)
- **Static Analysis**: `composer run analyze` (PHPStan)

The codebase is strictly typed and verified against PHP 8.2+ compatibility.

* * * * *

What This Plugin Is Not
-----------------------

- ❌ A marketing email tool

- ❌ A UI-based mail manager

- ❌ An SMTP replacement plugin

- ❌ A per-site configuration plugin

- ❌ A standalone product

This is **infrastructure**.

* * * * *

Security Model
--------------

- Credentials loaded only from environment

- No credential storage

- No database writes

- No background jobs

- No cron usage

* * * * *

License
-------

MIT License\
Copyright © 2025--2026 Starisian Technologies.

SPARXSTAR and Starisian Technologies are trademarks of Starisian Technologies
SendGrid is a trademark of Twillio. SPARXSTAR is in no way affiliated with SendGrid or Twillio.

* * * * *

Related Infrastructure
----------------------

- SPARXSTAR 2FA Enforcement

* * * * *

Maintainer
----------

**Starisian Technologies**\
Support: <support@starisian.com>\
Website: <https://starisian.com>
