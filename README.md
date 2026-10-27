# Renewal Tracker

A small, self-hosted web app for tracking your clients' **domain and hosting expiry dates** — with automatic email reminders before anything expires.

Built with plain **PHP 8 + MySQL** (no framework, no Composer). Mobile-friendly, single admin login, and a browser-based installer — no config files to edit by hand.

![Status](https://img.shields.io/badge/PHP-8.0%2B-777BB4) ![Database](https://img.shields.io/badge/Database-MySQL-4479A1) ![License](https://img.shields.io/badge/License-MIT-green)

## Features

- 📋 One record per website: client name, phone, domain, domain expiry, hosting provider, hosting expiry, renewal fee, notes
- 🎨 Dashboard with colour-coded status — expired, expiring soon (30 days), or safe
- 📅 All dates shown as `dd-mm-yyyy`
- 🔍 Domain expiry auto-fill: enter a domain and its real expiry date is fetched automatically (RDAP)
- 🔁 One-click **Renew** buttons — add 1 year to a domain or hosting expiry
- ✉️ **Email reminders** 7, 3, 1 and 0 days before expiry (daily cron job, duplicate-safe)
- 📱 Fully responsive — the website list stays a compact 4-column table on phones
- 🧙 Installer wizard — database setup and admin creation from the browser, no file editing

## Requirements

- PHP 8.0+ with PDO MySQL
- MySQL / MariaDB database
- A cron job (or scheduled task) that can call a URL once a day
- Outgoing mail from PHP (`mail()`) for the reminders

## Installation

1. Create a MySQL database and a user with **ALL PRIVILEGES** on it (e.g. in cPanel → MySQL Databases).
2. Upload all files to your hosting (public folder of your domain or subdomain) and extract them.
3. Visit `https://your-domain/install.php`:
   - **Step 1:** enter the database host (usually `localhost`), name, user and password — the config file and tables are created automatically.
   - **Step 2:** create your admin username and password.
4. Log in, open **Settings**, and set the email address that should receive the reminders.
5. Add the daily cron job — Settings shows the exact command for your installation (`cron.php` with your secret key). Once a day is enough (for example at midnight).

Full walkthrough: [README-INSTALL.md](README-INSTALL.md)

## How reminders work

`cron.php?key=YOUR_SECRET` checks every record and emails you when a domain or hosting expiry is **7, 3, 1 or 0 days** away. Every sent reminder is logged, so the same reminder is never sent twice. Clicking **Renew** on a record moves that date 1 year forward and clears its old reminder logs.

## Security notes

- Real database credentials live only in `config.local.php`, which the installer creates on your server — it is **not** part of this repository (see `.gitignore`).
- `installed.lock` blocks the installer after setup. Delete both files only if you want to install again from scratch.
- Passwords are stored hashed (bcrypt); all forms use CSRF tokens and prepared statements.

## License

MIT — see [LICENSE](LICENSE).

---

Made by [Ibrahim Khalil](https://github.com/ibrahimduniya) · Live demo of the system in daily use: [renew.techsmartbd.com](https://renew.techsmartbd.com)
