# Mailroom

A self-hosted email marketing platform built with Laravel 13: audiences, a drag & drop email builder, automations, A/B tests, landing pages, reports, a REST API and real sending through your own provider. It lives in this repository next to the Atlas package but is a separate application with its own `composer.json`.

## Features

| Area | What you get |
|------|--------------|
| **Audiences** | Multiple lists, custom fields (text, number, date, birthday, dropdown, phone, website), tags, search/filters, bulk actions, CSV import with field mapping (never resubscribes opt-outs), CSV export (formula-injection safe), star ratings, growth chart, notes, GDPR consent + per-contact JSON export |
| **Segments** | Saved filters on any field, tag, status, signup date, engagement (opened/clicked any campaign); match all/any; live size |
| **Signup** | Hosted form, embeddable HTML, pop-up script, landing pages, double opt-in, honeypot + rate limit, GDPR checkbox |
| **Email builder** | Drag & drop blocks (heading, text, button, image, columns, divider, spacer, social, footer), click-to-edit canvas, rich text, image library/uploads, design settings, saved templates, or raw HTML |
| **Campaigns** | Merge tags with fallbacks (`{{ first_name \| there }}`) incl. custom fields, preview text, target audience/segment/tag, test sends, schedule, send-time optimisation, duplicate, resend to non-openers, RSS-to-email |
| **Review** | Inbox preview, desktop/mobile/dark-mode/plain-text previews, preview as any contact, pre-send checklist (blocks errors) |
| **A/B testing** | Subject, from name, content or send time; 2-3 variants; test share; winner by opens/clicks after N hours, or manual |
| **Automations** | Triggers: subscribe, tag added, yearly date field (birthdays), manual from a segment; steps: email, wait, add/remove tag; journey view with per-email stats |
| **Delivery** | Any SMTP provider (presets for Mailgun, Postmark, SES, SendGrid, Brevo, Resend), encrypted credentials, rate limit, multipart HTML+text, RFC 8058 one-click unsubscribe, company address footer, "view in browser" link |
| **Deliverability** | Bounce/complaint webhooks (Postmark, Mailgun, SES, SendGrid, Resend, Brevo, generic), hard bounce / 3 soft bounces → cleaned, complaint → unsubscribed |
| **Reports** | Opens, clicks, unsubscribes, bounces, complaints, opens over time, top links, email clients, devices, per-variant results, cross-campaign comparison + CSV, public archive |
| **Team** | Owner/Admin/Manager/Author/Viewer roles, email invitations, password reset, profile |
| **API** | Bearer API keys (role-scoped): audiences, contact upsert/tags/delete, campaigns, reports, transactional send |

## Run it

```bash
cd mailroom
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan storage:link
php artisan serve           # http://localhost:8000, log in as admin@example.com / password
php artisan queue:work      # sends queued emails
php artisan schedule:work   # scheduled campaigns, A/B winners, automations, RSS, birthdays
```

Until you connect a provider under **Settings**, emails go to `storage/logs/laravel.log`. Set `APP_URL` to your public URL so tracking links and images work in real inboxes.

## API

Create a key under **Profile → API keys**, then:

```bash
curl -H "Authorization: Bearer mr_..." http://localhost:8000/api/v1/audiences
curl -X PUT -H "Authorization: Bearer mr_..." -H "Content-Type: application/json" \
  -d '{"email":"ann@example.com","first_name":"Ann","tags":["customer"]}' \
  http://localhost:8000/api/v1/audiences/1/contacts
```

All endpoints are listed on the profile page.

## Tests

```bash
vendor/bin/phpunit
vendor/bin/pint --test
```
