# Simple SMTP

A very simple, lightweight and secure WordPress plugin to route all outgoing emails through **any** SMTP server — SiteGround, Gmail, Zoho, Mailgun, your web host, and more. If your provider gives you a **Host, Port, Username and Password**, this plugin works. No bloat — just the essentials.

## Features

- Routes all WordPress email through SMTP via the `phpmailer_init` hook
- Clean settings page under **Settings → Simple SMTP**
- SSL / TLS / None encryption support
- **Password encrypted at rest** using your site's security keys (never stored in plain text)
- Built-in **Send Test Email** tool
- Nonce-protected, capability-checked, fully sanitized/escaped
- Single file, zero external dependencies

## Installation

1. Download the latest release ZIP.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, install and activate.
3. Go to **Settings → Simple SMTP** and enter your details.

## Settings

Enter the values your email/SMTP provider gives you:

| Field       | Value                                          |
|-------------|------------------------------------------------|
| Host        | e.g. `smtp.yourprovider.com`                   |
| Port        | `465` (SSL) or `587` (TLS)                     |
| Encryption  | `SSL` (port 465) or `TLS` (port 587)          |
| Username    | usually your full email address                |
| Password    | your email/SMTP account password               |
| From Email  | match the username to avoid spam flags         |

Save, then send a test email to confirm it works.

### Common provider examples

| Provider   | Host                    | Port / Encryption |
|------------|-------------------------|-------------------|
| SiteGround | `mail.yourdomain.com`   | 465 / SSL         |
| Gmail      | `smtp.gmail.com`        | 465 / SSL (use an App Password) |
| Zoho       | `smtp.zoho.com`         | 465 / SSL         |
| Mailgun    | `smtp.mailgun.org`      | 587 / TLS         |

## Security

The SMTP password is encrypted with AES-256-CBC using a key derived from your
site's `AUTH_KEY` / `AUTH_SALT` before being stored in the database. The password
field is never echoed back into the page — leave it blank to keep the existing one.

## Author

**Abdullah Al Noman** — [instagram.com/noman_imtiaz11](https://instagram.com/noman_imtiaz11)

## License

GPL-2.0-or-later
