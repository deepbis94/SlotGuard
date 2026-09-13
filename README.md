# SlotGuard

[![CI](https://github.com/deepbis94/SlotGuard/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/deepbis94/SlotGuard/actions/workflows/ci.yml)

JSON API for booking appointments against a provider's calendar. A customer can reserve a service in a time window; the system **never** allows two confirmed bookings for the same service to overlap — including under concurrent requests.

This is an API-only Laravel app. There is no UI, auth, or email.

## Setup

### Docker (recommended)

Requires Docker and Docker Compose. PHP is **not** required on the host.

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
```

- API: [http://localhost:8000](http://localhost:8000)
- Health: [http://localhost:8000/up](http://localhost:8000/up)
- Adminer: [http://localhost:8080](http://localhost:8080)

The first `docker compose up` installs Composer dependencies inside the `app` container (the `vendor/` directory lives in a named volume). Subsequent starts reuse that volume.

### Adminer

Open [http://localhost:8080](http://localhost:8080) and connect with:

| Field    | Value      |
|----------|------------|
| **System** | **PostgreSQL** (not the MySQL default — that produces `db: Connection refused`) |
| Server   | `db`       |
| Username | `slotguard`|
| Password | `secret`   |
| Database | `slotguard`|

These match `.env.example` and `docker-compose.yml`.

A Compose plugin pre-selects **PostgreSQL** so Adminer does not try MySQL on the `db` hostname (that is what produces `db: Connection refused`). After pulling this change, recreate Adminer:

```bash
docker compose up -d adminer
```

From a client on the host (TablePlus, `psql`, Cursor), use server `127.0.0.1` instead of `db` — `db` is only a Docker DNS name.

### Tests

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

Or, if PHP 8.3+ and Composer are installed locally:

```bash
php artisan test
vendor/bin/pint --test
```

GitHub Actions (`.github/workflows/ci.yml`) runs both on every push and pull request: PHPUnit against in-memory SQLite, then Pint.

The PHPUnit suite uses **SQLite in-memory**. `phpunit.xml` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` with `force="true"`, and `Tests\TestCase::createApplication()` also pins the default connection to SQLite and purges any connection opened under Compose's `DB_CONNECTION=pgsql`. That keeps tests fast, zero-config, and isolated from the Docker Postgres volume. It is **not** the same engine as production PostgreSQL — see [Assumptions](#assumptions) and [Key technical decisions](#key-technical-decisions).

### Non-Docker path (optional)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Point `.env` at a local PostgreSQL database (or SQLite for a quick try), then:

```bash
php artisan migrate --seed
php artisan serve
```

When running outside Compose, change `DB_HOST` from `db` to `127.0.0.1`.

## API

No authentication. All write endpoints are open by design (scope cut).

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/services` | List bookable services |
| `GET` | `/api/services/{id}` | Service detail |
| `POST` | `/api/services/{id}/bookings` | Create a confirmed booking |
| `GET` | `/api/services/{id}/bookings` | List bookings (`?from=` & `?to=` ISO-8601) |
| `DELETE` | `/api/bookings/{id}` | Soft-cancel a booking |

### Create a booking

```http
POST /api/services/1/bookings
Content-Type: application/json

{
  "customer_name": "Ada Lovelace",
  "customer_email": "ada@example.com",
  "starts_at": "2026-09-15T10:00:00Z"
}
```

`ends_at` is computed as `starts_at + service.duration_minutes` and is not accepted from the client.

Success: `201` with a `data` object. Validation failures: `422` with field-level `errors`. Overlap: `409`.

### List bookings

`from` and `to` are optional. When present they select bookings whose interval overlaps `[from, to)`. Cancelled bookings are included so a calendar can show history.

### Cancel a booking

Soft-cancel only: `status` becomes `cancelled`. The row is not deleted (the PostgreSQL `EXCLUDE` constraint is partial on `status = 'confirmed'`, so cancelling frees the slot).

- Already cancelled → `422`
- Start time is less than 24 hours away → `409`

### Error envelope

```json
{
  "message": "Human-readable summary",
  "errors": {
    "starts_at": ["The start time must be in the future."]
  }
}
```

`errors` is an empty object for non-validation failures (409, 404).

## Assumptions

Every product decision that was not specified:

1. **No auth.** Deliberate scope cut. Anyone who can reach the API can book or cancel. Add tokens or signed customer links before exposing this beyond a trusted network.
2. **No mail / notifications.** Confirmations and reminders are out of scope.
3. **No frontend.** JSON API only. `GET /` returns a machine-readable index of endpoints.
4. **Single provider per service.** A `services` row *is* one provider's calendar. There is no `providers` table and no `provider_id`. Multi-provider support would add that column and lock/exclude on `(provider_id, …)` instead of `service_id`.
5. **Timezone is UTC.** `APP_TIMEZONE=UTC` and `BOOKING_TIMEZONE=UTC`. Incoming ISO-8601 values are converted to UTC. `daily_start_time` / `daily_end_time` are wall-clock times in that timezone. Send offsets (`2026-09-15T10:00:00Z` or `2026-09-15T15:30:00+05:30`); naive datetimes are interpreted as UTC.
6. **Cancellation window is 24 hours**, configurable via `BOOKING_CANCELLATION_WINDOW_HOURS` / `config/booking.php`. A booking may be cancelled when `now < starts_at - window`. Past bookings cannot be cancelled (they are inside the window). Double-cancel returns **422** (the request is invalid given current state). Slot contention stays **409**. 409 would also be defensible for double-cancel; 422 is the consistent choice here.
7. **SQLite for tests, PostgreSQL for Docker/dev/prod.** The GiST `EXCLUDE` constraint is created only when the migration driver is `pgsql`. SQLite tests rely on the application overlap check. `lockForUpdate()` is a no-op on SQLite (SQLite locks the whole database on write anyway).
8. **PHP 8.3+ / Laravel 13.** Latest stable Laravel requires PHP 8.3. The Docker image is `php:8.3-fpm`. The spec's "PHP 8.2+" is satisfied by 8.3.
9. **Overnight bookings are rejected.** If `ends_at` falls on a later calendar day than `starts_at` (in the booking timezone), the request is treated as exceeding daily hours (`422`).
10. **Start must be inside `[daily_start, daily_end)`.** A booking may *end* exactly at `daily_end_time` (half-open hours, matching slot math). Starting at closing time is rejected.
11. **List filter uses interval overlap**, not "starts_at between from and to".
12. **Integer primary keys.** No ULIDs/UUIDs.
13. **Create returns 201.** Cancel returns 200 with the updated booking.
14. **Seeded services:** Haircut (30 min, 09:00–17:00 UTC) and Consultation (60 min, 10:00–16:00 UTC).

## Key technical decisions

### Transaction + `lockForUpdate`

`BookingService::create()` runs inside a DB transaction. It locks the **service** row (`SELECT … FOR UPDATE`) *before* computing `ends_at`, checking hours, checking overlap, and inserting. Concurrent bookers for the same service serialize on that row lock; only one check+insert proceeds at a time. That caps throughput per service — acceptable while one service is one calendar. Multi-provider would lock/exclude on `provider_id` instead. Cancelled bookings for other customers do not hold the slot.

### PostgreSQL `EXCLUDE` constraint (the safety net)

The bookings migration enables `btree_gist` and adds:

```sql
ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
EXCLUDE USING gist (
    service_id WITH =,
    tstzrange(starts_at, ends_at, '[)') WITH &&
) WHERE (status = 'confirmed');
```

This is the definitive guarantee: even if the PHP overlap check were buggy or skipped, PostgreSQL will reject a conflicting confirmed range (`SQLSTATE 23P01`). `BookingService` maps that error to HTTP 409.

The constraint is **PostgreSQL-only**. The migration skips it when the driver is `sqlite`. That is why the test suite cannot claim DB-level exclusion; it claims application-level exclusion.

### Half-open intervals `[starts_at, ends_at)`

Two confirmed bookings overlap iff `A.starts_at < B.ends_at AND A.ends_at > B.starts_at`. A booking ending at 10:00 and one starting at 10:00 do **not** overlap. `tstzrange(..., '[)')` uses the same convention. The pure helper `App\Support\BookingInterval` encodes this and is unit-tested independently of the HTTP layer.

### SQLite tests vs Postgres dev

- Tests: in-memory SQLite, milliseconds, no Docker required for the suite itself (the suite still runs fine *inside* the app container).
- Dev/prod: PostgreSQL 15 with the GiST exclusion constraint.

A race that slips past PHP cannot persist on PostgreSQL. It could persist on SQLite if the application check were removed — that is an accepted test-fidelity trade-off, documented here.

### Concurrent-request testing

PHPUnit is single-threaded and the in-memory SQLite database is per-connection. `ConcurrentBookingTest` fires two identical requests back-to-back and asserts only one row is stored. That exercises the overlap detector, not two OS threads. True interleaving is handled by Postgres row locks + `EXCLUDE`. Unit tests on `BookingInterval` and `BookingService::hasConfirmedOverlap()` cover the interval math, including cancelled-booking exclusion.

## Local Compose services

| Service   | Image / build      | Port | Role |
|-----------|--------------------|------|------|
| `app`     | `Dockerfile` (PHP-FPM 8.3 + Composer + `pdo_pgsql` / `pgsql` / `pdo_sqlite` / `bcmath` / `mbstring` / `xml`) | 8000 | Laravel via `php artisan serve --host=0.0.0.0` |
| `db`      | `postgres:15`      | 5432 | Primary database, data in the `pgdata` volume |
| `adminer` | `adminer:4`        | 8080 | Web SQL UI |

`.env.example` defaults: `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE=slotguard`, `DB_USERNAME=slotguard`, `DB_PASSWORD=secret`.

## Limitations and what I'd improve with more time

- **Auth and tenancy.** Customer identity, provider accounts, and rate limiting.
- **Real parallel tests.** A Postgres-only group that opens two connections and contends on `lockForUpdate`, plus a test that a raw overlapping `INSERT` hits `23P01`. `ConcurrentBookingTest` is sequential by design (PHPUnit + in-memory SQLite).
- **Maximum lead time.** There is no upper bound on `starts_at`; a booking years ahead is accepted if it is in the future and inside daily hours.
- **Service admin API.** Services are created by the seeder/factories, not HTTP. On PostgreSQL, `CHECK` constraints require `duration_minutes > 0` and `daily_end_time > daily_start_time` so a bad row cannot be inserted even off-API. SQLite tests skip those CHECKs (same split as the GiST exclude).
- **Availability / slots endpoint.** `GET /api/services/{id}/slots?date=` would make clients simpler.
- **Idempotency keys** on `POST` so retries do not look like a second customer.
- **Per-service timezone** instead of a global `BOOKING_TIMEZONE`.
- **Audit log** of cancel/create for disputes.
- **Stronger email uniqueness / double-book-by-customer** rules (not required; a customer can hold two non-overlapping slots today).
- **Nginx + PHP-FPM** instead of `artisan serve` for a closer production process model. The spec allowed artisan's server; Compose uses that for a one-command start.
- **Queue + outbox** if notifications are added later, so mail cannot roll back a committed booking.
