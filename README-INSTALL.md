# Renewal Tracker — Install Guide

A system to track your clients' domain and hosting expiry dates.
You get automatic email reminders **7 days, 3 days and 1 day before expiry, and on the expiry day itself**.

## What you need
- Any shared hosting with PHP 8.0+ and MySQL (cPanel is fine)
- A subdomain or folder (e.g. renew.yourdomain.com)

## Install (5 minutes) — no file editing needed

1. **Create a database:** cPanel → MySQL Databases →
   - Create a database (e.g. `user_renewal`)
   - Create a user, add it to the database, and grant **ALL PRIVILEGES**
   - Note down the database name, user and password

2. **Upload the files:** Upload all files from this zip into the subdomain's folder (public_html/renew, etc.).

3. **Open it in a browser:** Opening the site address brings up the **install page**.
   - Step 1: Enter the database name/user/password in the form → the config is written for you and the tables are created automatically.
   - Step 2: Create your login username/password. That's it — install done!

4. **Set your email:** After logging in, open the **Settings** page, enter your email address and save. Verify it with the "Send Test Mail" button.

5. **Turn on the cron (most important):** The Settings page shows a cron command made for your site.
   cPanel → **Cron Jobs** → "Once Per Day" → paste that command and click Add.
   Without this, no reminder emails will be sent.

## Using it
- **Add new:** One record per website — client name, phone, domain, domain expiry date, hosting, hosting expiry date, renewal fee, note.
  For the domain, leave the date empty or press "Fetch now" and the real expiry date will be pulled online (RDAP).
- **Dashboard:** Colour-coded statuses show at a glance what expires when (under 7 days = red).
- **After a renewal:** Press the matching "Renew ✓" button in the list and the date extends by 1 year.
- **Change reminder days:** In Settings, edit the "Remind how many days before" field with comma-separated values (for only 7 days before: `7`).

## To reinstall
Delete the `installed.lock` and `config.local.php` files from the server and open the site again — the install page will come back.
