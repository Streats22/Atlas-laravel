# Mailroom

A self-hosted email marketing app built with Laravel 13. It lives in this repository next to the Atlas package but is a separate application with its own `composer.json`.

## Phase 1: what works now

| Area | Features |
|------|----------|
| Audiences | Multiple lists, default sender, double opt-in on/off |
| Contacts | Add/edit, status (subscribed, pending, unsubscribed, cleaned), tags, search and filters, bulk tag/untag/unsubscribe/delete, CSV import (`,` or `;`, header aliases, never resubscribes opt-outs), CSV export (formula-injection safe) |
| Segments | Saved filters on email, name, status, source, signup date, tag, "opened/clicked any campaign"; match all/any; live size and preview |
| Signup forms | Hosted form, embeddable HTML snippet, honeypot, rate limit, double opt-in confirmation email |
| Campaigns | 3 starter templates, HTML editor with live desktop/mobile preview, merge tags with fallbacks (`{{ first_name \| there }}`), preview text, target by audience/segment/tag, test sends, send now, schedule, duplicate |
| Delivery | Queued jobs per recipient, retries, skip contacts who unsubscribed after queuing, RFC 8058 one-click `List-Unsubscribe` |
| Reports | Recipients, unique opens/clicks, open and click rate, unsubscribes, failures, opens per hour, top links, recent activity, dashboard totals |
| Tracking | Open pixel, click redirects (only to links stored for that campaign, so no open redirect) |

## Run it

```bash
cd mailroom
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan serve           # http://localhost:8000, log in as admin@example.com / password
php artisan queue:work      # sends the queued emails
php artisan schedule:work   # sends scheduled campaigns
```

Mail goes to `storage/logs/laravel.log` by default (`MAIL_MAILER=log`). Set SMTP, SES, Postmark or another Laravel mailer in `.env` to send for real.

## Tests

```bash
vendor/bin/phpunit
vendor/bin/pint --test
```

## Roadmap (premium features, next phases)

1. **Drag & drop email builder**: blocks, image uploads, saved templates.
2. **Automations**: welcome series, tag-triggered journeys, date-based mails (birthdays).
3. **A/B testing**: subject/content/send-time variants with automatic winner.
4. **Advanced reporting**: geo and device data, comparison across campaigns, revenue.
5. **Landing pages, multi-user teams with roles, send-time optimisation, bounce/complaint webhooks.**
