# Chun Tian — Backend PHP (Hostinger)

> Minimal API (PHP 8.1 + SQLite) compatible Hostinger. Uses Stripe Checkout.
> This package only includes code; you must set environment variables and run Composer on Hostinger.

## Structure
```
public_html/
  (your existing front: index.html, fruits.html, ...)
  api/
    bootstrap.php
    checkout.php
    webhook.php
    orders.php
    slots.php
  storage/
    (writable directory; db.sqlite will be created here)
  composer.json
  .env            (create from .env.example)
```

## Quick start (Hostinger)
1. Upload `api/`, `storage/`, `composer.json`, `.env` (created from `.env.example`) to your Hostinger `public_html/`.
2. In hPanel → File Manager or SSH, run **Composer install** in `public_html/` to install Stripe:
   ```bash
   composer require stripe/stripe-php:^12
   ```
3. Make sure `public_html/storage` is **writable** (permissions 755 or 775 as needed).
4. Create the database:
   - Option A: Let the app create tables automatically on first request.
   - Option B: Run `init.sql` manually in a SQLite client (optional).
5. Configure a Stripe webhook endpoint to:
   `https://YOUR_DOMAIN/api/webhook.php` (events: `checkout.session.completed`).
6. Update your **frontend** to call:
   - `POST /api/checkout.php` to start payment (returns `session.url`).
   - `GET /api/orders.php?status=pending` (admin).
   - `POST /api/orders.php` with `{id, status}` (admin).

## Environment (.env)
Copy `.env.example` to `.env` and fill values:
```
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
ADMIN_TOKEN=choose_a_long_random_string
STORE_OPENING_JSON={"Mon":["09:00","19:00"],"Tue":["09:00","19:00"],"Wed":["09:00","19:00"],"Thu":["09:00","19:00"],"Fri":["09:00","19:00"],"Sat":["09:00","18:00"],"Sun":null}
STORE_SLOT_EVERY_MIN=30
STORE_COORDS_LAT=48.792716
STORE_COORDS_LON=2.359279
STORE_DELIVERY_KM=5
```
> Anything not set will fall back to these defaults.

## Endpoints

### POST /api/checkout.php
Create order (pending) and Stripe Checkout Session.
**Body JSON**
```json
{
  "customer":{"name":"...", "email":"...", "phone":"..."},
  "mode":"cc|livraison",
  "slot":"2025-09-20T10:00:00.000Z",
  "address":"optional if livraison",
  "cart":[{"id":"banane","qty":2},{"id":"tomate","qty":1}]
}
```
**Response**
```json
{ "ok": true, "url": "https://checkout.stripe.com/...", "order_id":"ord_abc123" }
```

### POST /api/webhook.php
Stripe webhook (verifies signature). Marks order `paid` and stores payment intent.

### GET /api/orders.php?status=pending
Bearer admin token required. Returns list of orders with that status.

### POST /api/orders.php
Bearer admin token required. Change order status.
**Body**
```json
{ "id": "ord_abc123", "status": "accepted" }
```

### GET /api/slots.php?date=YYYY-MM-DD
Return available slots for given date, taking opening hours and optional capacity into account.
**Response**
```json
{ "ok": true, "slots": [{"iso":"2025-09-20T10:00:00.000Z","available":true}] }
```

## Notes
- Prices are recalculated on server from the `products` table.
- If you don't want server products yet, you can seed a minimal set directly in the DB using the admin tool later.
- Do NOT expose `.env` publicly.