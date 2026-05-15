# Security Policy

## Supported versions

Only the latest minor release on the `main` branch receives security fixes during the pre-release phase.

| Version | Supported |
| ------- | --------- |
| 0.1.x   | Yes       |
| < 0.1   | No        |

Older releases will be added to this table once a stable line ships.

## Reporting a vulnerability

**Please do not open public GitHub issues for security vulnerabilities.**

Use one of these private channels:

- **GitHub Security Advisories** (preferred): open a private advisory at <https://github.com/dlabsit/m2-xml-feed/security/advisories/new>. This keeps the discussion confidential until a fix is shipped.
- **Email**: `security@dlabsit.nl`. If the issue is sensitive, request a PGP key in your first message.

Please include:

- A clear description of the vulnerability and its impact.
- Steps to reproduce, ideally with a minimal Magento configuration.
- Affected versions and Magento edition (Open Source / Commerce).
- Any proof-of-concept code or feed input that triggers the issue.

## Response process

- We aim to acknowledge new reports within **3 working days**.
- A first assessment with severity and a target fix window is provided within **10 working days**.
- Fixes are released as a patch version. Reporters are credited in the release notes and the advisory unless they ask to remain anonymous.

## Scope

In scope:

- Code in this repository (`dlabsit/module-xml-feed`).
- Generated feed output that could be used to attack downstream consumers (XML injection, malformed CDATA escapes, etc.).
- Admin controllers, frontend controllers, and CLI command surface introduced by this module.

Out of scope (please report upstream instead):

- Vulnerabilities in Magento core or third-party modules.
- Server / hosting misconfiguration on installs that use this module.
- Social engineering of merchants or Dlabsit staff.

## Hardening notes for integrators

- Run the module behind the same authentication and rate-limiting controls you apply to any Magento frontend route.
- Restrict access to `pub/media/xmlfeed/` if the feed should not be public.
- Keep PHP, Magento, and your dependencies up to date; security patches in upstream components are not duplicated here.
