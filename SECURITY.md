# Security Policy

## Supported Versions

This project is infrastructure-level software intended for controlled
WordPress multisite environments. Only the **latest tagged release**
is supported with security updates.

| Version        | Supported |
|----------------|-----------|
| Latest release | ✔ Yes     |
| Older releases | ✖ No      |
| Unreleased     | ✖ No      |

Security fixes are **not backported** to older versions.  
Users are expected to upgrade to the latest release promptly.

---

## Reporting a Vulnerability

If you believe you have discovered a security vulnerability, please **do not**
open a public GitHub issue.

### How to Report

Send a detailed report to:

**Email:** support@starisian.com  
**Subject:** `Security Report – SparxStar SendGrid Runtime`

Please include:
- A clear description of the vulnerability
- Steps to reproduce (if applicable)
- Potential impact
- Any suggested mitigations (optional)

### What to Expect

- **Acknowledgement:** within 3 business days  
- **Assessment:** vulnerability will be reviewed and validated  
- **Resolution:** confirmed issues will be patched in the next release  
- **Disclosure:** coordinated disclosure may be requested before public discussion  

If a report is declined, a brief explanation will be provided.

---

## Scope

This policy applies only to:
- The SparxStar SendGrid Mail Runtime codebase
- Bundled infrastructure logic
- WP-CLI commands and admin diagnostics included in this repository

It does **not** cover:
- Third-party services (SendGrid)
- WordPress core
- Server or hosting configuration issues

---

## Responsible Disclosure

We appreciate responsible disclosure and ask that you give us reasonable time
to address confirmed vulnerabilities before any public release of details.
