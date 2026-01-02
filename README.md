SPARXSTAR Oort Mail
===================

**SPARXSTAR Oort Mail** is a **must-use (MU) WordPress plugin** that enforces reliable, domain-correct outbound email delivery across a WordPress multisite network using the SendGrid API.

It is designed for **infrastructure-level email transport**, not per-site configuration, and is intended to load early and run consistently across the network.

* * * * *

What This Plugin Is
-------------------

-   A **network mail transport layer**

-   Installed as a **MU plugin**

-   Alias-domain aware (Mercator-friendly)

-   API-based (SendGrid, not SMTP)

-   Headless (no UI, no settings pages)

This plugin exists to solve **domain alignment, DKIM compliance, and delivery reliability** in multisite environments.

* * * * *

What This Plugin Is *Not*
-------------------------

-   Not a UI mail plugin

-   Not configurable per site

-   Not optional once deployed

-   Not a replacement for `wp_mail()` calls in code

It operates **below** application logic.

* * * * *

Installation (Required: MU Plugin)
----------------------------------

This plugin **must be installed as a MU plugin**.

`wp-content/mu-plugins/sparxstar-oort-mail.php`

-   Do **not** install in `/wp-content/plugins/`

-   Do **not** rely on activation hooks

-   Do **not** network-activate

MU placement ensures:

-   Early loading

-   Cannot be accidentally disabled

-   Consistent mail policy across the network

* * * * *

Requirements
------------

-   WordPress **6.8+**

-   PHP **8.2+**

-   SendGrid account

-   Verified SendGrid sending domain(s)

-   `SENDGRID_API_KEY` available via environment variables

-   Composer dependencies bundled at release time

* * * * *

Configuration
-------------

### Environment Variable (Required)

`SENDGRID_API_KEY=your_sendgrid_api_key`

The plugin **will not** read API keys from:

-   The database

-   wp-config constants

-   Admin settings

This is intentional.

* * * * *

How Mail Delivery Works
-----------------------

-   WordPress calls `wp_mail()`

-   The plugin intercepts mail via `pre_wp_mail`

-   Mail is sent via the SendGrid API

-   PHPMailer is bypassed

-   If SendGrid fails, WordPress may fall back

This is a **supported and safe interception point**.

* * * * *

Sender Domain Resolution
------------------------

The plugin dynamically resolves the sender domain at send time using:

1.  Alias domain (Mercator)

2.  Subdomain reduction to registrable base

3.  ccTLD-aware handling, including:

    -   `.com.gm`

    -   `.org.gm`

    -   `.co.za`

    -   `.org.za`

4.  Final fallback: `sparxstar.com`

This ensures outbound mail aligns with **verified SendGrid domains**.

* * * * *

Example
-------

If the site URL is:

`https://legal.clientname.com`

Outbound email will be sent as:

`From: support@clientname.com`

If the site URL is:

`https://clientname.co.za`

Outbound email will be sent as:

`From: support@clientname.co.za`

* * * * *

Composer & Dependencies
-----------------------

-   The SendGrid PHP SDK is **MIT-licensed**

-   Dependencies are **bundled in release builds**

-   Composer is **not required at runtime**

The repository may include Composer metadata for build automation only.

* * * * *

Licensing
---------

This plugin is **proprietary commercial software**.

-   Redistribution, modification, or resale is prohibited without permission

-   The bundled SendGrid SDK remains licensed under the **MIT License**

-   See `LICENSE.txt` for full terms

* * * * *

Support
-------

**Starisian Technologies**\
Email: support@starisian.com\
Website: <https://starisian.com>

* * * * *

Status
------

-   Current version: **0.5.0**

-   Intended for production multisite use

-   Part of the SPARXSTAR infrastructure stack

© 2023--2025 Starisian Technologies. All Rights Reserved.

* * * * *

STARISIAN TECHNOLOGIES -- CONFIDENTIAL

NOTICE: All information contained herein is, and remains, the property of Starisian Technologies and its suppliers, if any. The intellectual and technical concepts contained herein are proprietary to Starisian Technologies and its suppliers and may be protected by U.S. and international copyright, trade secret, and patent laws, including patents in process.

Unauthorized reproduction, redistribution, transmission, or disclosure of any part of this repository is strictly prohibited without prior written consent from Starisian Technologies.
