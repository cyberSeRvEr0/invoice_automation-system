# Invoice Automation System

A lightweight PHP invoice automation tool. Create invoices, email them to clients, track payment status, and send automatic overdue reminders.

## Features

- Create invoices with multiple line items
- Auto-generate unique invoice IDs (e.g. INV-2026-5154EE)
- Email invoices to clients instantly or send later from a draft
- Client receives a payment page with amount, due date, and payment instructions
- Printable invoice page (client saves as PDF via browser)
- Automatic overdue reminders (configurable days)
- Track revenue, outstanding, and overdue amounts
- Client management (add, edit, delete)
- Multi-currency support
- Configurable tax rate
- SQLite (default, zero setup) or MySQL
- First registered user becomes admin
- Logout functionality

## Client Experience Flow

When you send an invoice to a client, they receive an email with a "View Invoice" button. Clicking it takes them through this flow:

1. Email arrives with a "View Invoice" button
2. Button opens the payment page (pay.php) showing the amount, due date, and your payment methods
3. From the payment page, they can click "View Full Invoice" to see the detailed line items
4. They can click "Print / Save as PDF" to download a copy

If the invoice becomes overdue and you send a reminder, the client receives the same flow with an overdue notice.

## Requirements

- PHP 7.4 or higher
- SQLite3 (default) or MySQL 5.7+
- PHP extensions: PDO, cURL
- SMTP access (Gmail, Proton, Yahoo, or your hosting provider)

## Installation

1. Upload all files to your web server (public_html)
2. Copy .env.example to .env and fill in your SMTP credentials
3. Open install.php in your browser
4. Wait for "Installation Successful"
5. Delete install.php from your server immediately
6. Visit login.php — the first account you create becomes the admin

## Configuration

### SMTP (Email)

Edit .env in the project root:

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=you@gmail.com
SMTP_PASS=your_app_password
SMTP_FROM=you@gmail.com
SMTP_FROM_NAME=My Company Invoices

Gmail: Go to myaccount.google.com/apppasswords and generate an App Password. Do NOT use your regular Gmail password.

Proton: Go to Proton Mail > Settings > Mail > App passwords.

Yahoo: Go to Yahoo Account Security then App Passwords.

cPanel / Custom Hosting: Use your hosting provider's SMTP details. Port is usually 465 (SSL) or 587 (STARTTLS).

### Database (Optional — MySQL instead of SQLite)

Edit config/config.php:
- Set $db_type = 'mysql'
- Fill in $db_host, $db_name, $db_user, $db_pass
- Create the database in phpMyAdmin first
- Run install.php after changing the type

### Company Settings

Configure via the admin panel: Settings tab. Set your company name, email, phone, address, currency symbol, tax rate, invoice prefix, reminder days, and website base URL.

The Website Base URL is critical — it is used to generate the clickable links in your client emails. Set it to your full domain path, for example:

https://yoursite.com/invoice-automation

Without this set, the "View Invoice" button in your emails will not appear.

## Usage

1. Add a client — go to Admin > Clients, enter name, email, phone, company, address
2. Create an invoice — go to Admin > Invoices, select client, add line items
3. Send or save as draft — check "Send email to client immediately" or leave unchecked
4. Send a draft later — click the orange Send button on any DRAFT invoice
5. Track payments — Dashboard shows total, paid, pending, overdue, revenue
6. Send reminders — Dashboard > Send Reminders button (sends to overdue invoices on configured days)
7. Mark as paid — Dashboard or Invoices page > Mark Paid button
8. Client views invoice — they click the link in their email, see the payment page, then the full invoice

## Overdue Reminders

When an invoice passes its due date and you click "Send Reminders" on the dashboard:

- The system checks how many days past due each SENT invoice is
- If that number matches one of your configured reminder days (default: 3, 7, 14), a reminder email is sent
- The invoice status changes from SENT to OVERDUE (shown in red)
- You can configure the reminder days in Settings (e.g. 1, 3, 7, 14, 30)

The Send Reminders button is manual. For fully automated daily checks, set up a cron job on your server.

## File Structure

config/config.php — Main configuration (database, SMTP, settings)

admin/dashboard.php — Stats, recent invoices, send reminders
admin/clients.php — Client management (add, edit, delete)
admin/invoices.php — Create and manage invoices
admin/settings.php — Company info, base URL, and SMTP configuration
admin/logout.php — Session destroy and redirect to login
admin/style.css — Admin panel styles

public/view.php — Full printable invoice (client-facing)
public/pay.php — Payment page with amount, due date, and payment methods

includes/functions.php — Shared helpers (totals, email, reminders)
includes/phpmailer/PHPMailer.php — Email library
includes/phpmailer/SMTP.php — SMTP transport
includes/phpmailer/Exception.php — Error handling

install.php — Database installer (DELETE AFTER USE)
login.php — Login and registration (first user becomes admin)
.env.example — SMTP credentials template
README.md — This file

## Security Notes

- Delete install.php after first run
- Set display_errors = Off in php.ini for production
- Block direct access to .env via .htaccess (Require all denied)
- Use HTTPS/SSL on your server

## License

MIT   