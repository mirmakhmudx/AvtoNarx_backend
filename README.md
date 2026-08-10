<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="260" alt="Laravel">
</p>

<h1 align="center">AvtoNarx — Backend</h1>

<p align="center">
  O'zbekiston bozoridagi avtomobillar uchun rasmiy va bozor narxlarini yig'ib,<br>
  tozalab va ochiq API orqali taqdim etuvchi Laravel backend xizmati.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis">
  <img src="https://img.shields.io/github/actions/workflow/status/mirmakhmudx/AvtoNarx_backend/ci.yml?branch=main&label=CI" alt="CI">
  <img src="https://img.shields.io/badge/license-MIT-blue" alt="License">
</p>

---

## Loyiha haqida

**AvtoNarx** — turli manbalardan (rasmiy diler saytlari, e'lon platformalari) avtomobil narxlarini avtomatik yig'ib, normallashtirib, statistik jihatdan tozalab, ishonchli **rasmiy narx** va **bozor (mediana) narxi** ko'rinishida tashqi dunyoga ochiq API orqali taqdim etuvchi tizim.

Loyiha to'liq texnik topshiriq (TZ) asosida qurilgan va quyidagi asosiy oqimni amalga oshiradi:
cat > README.md << 'EOF'
<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="260" alt="Laravel">
</p>

<h1 align="center">AvtoNarx — Backend</h1>

<p align="center">
  A Laravel backend service that collects, normalizes, and cleans official and market prices<br>
  for cars in the Uzbekistan market, and exposes them through a public API.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis">
  <img src="https://img.shields.io/github/actions/workflow/status/mirmakhmudx/AvtoNarx_backend/ci.yml?branch=main&label=CI" alt="CI">
  <img src="https://img.shields.io/badge/license-MIT-blue" alt="License">
</p>

---

## About the project

**AvtoNarx** ingests car listing and offer data from multiple sources (official dealer sites, classifieds platforms), normalizes it, statistically cleans it, and serves two trustworthy price signals through a public API: an **official price** and a **market (median) price**.

The project is built directly from a formal technical specification (TZ) and implements the following pipeline:

Parser (PHP, HTTP + DOM crawling)
│ gzip + Idempotency-Key + content_hash
▼
Ingestion API (Sanctum token, rate limiting, validation)
│
▼
Queues (Horizon: ingestion / statistics / parser / default)
│
├─▶ Catalog matching (brand/model/alias)
├─▶ Currency conversion (UZS)
├─▶ Lifecycle (incremental + snapshot)
│
▼
Statistics (median, IQR, global bounds)
│
▼
Public API ── Redis cache + ETag/304
│
▼
Admin panel (Filament) ── moderation, audit log, MFA


## Key features

- **Ingestion API** — token-authenticated endpoint for parser clients, with gzip support and duplicate protection via `content_hash` / `Idempotency-Key`.
- **Two price models**:
  - *Official price* (`official_offers`) — never published until manually moderated;
  - *Market price* (`market_price_statistics`) — a median-based aggregate cleaned with global price bounds and IQR outlier filtering.
- **Public API** — brands, models, prices; Redis caching, `ETag` / `Last-Modified`, `304 Not Modified`, per-IP rate limiting (120 requests/minute).
- **Parser** (PHP, built on `spekulatius/phpscraper`) — pluggable adapter architecture for multiple sources (OLX.uz, Uzum Avto, etc.), automatic brand/model discovery, and an unmatched-candidates review queue.
- **Admin panel** ([Filament](https://filamentphp.com)) — sources, parser clients, batches/errors, unmatched candidates, official price moderation, audit log, 2FA (TOTP).
- **Queues** — [Laravel Horizon](https://laravel.com/docs/horizon) with dedicated supervisors (`ingestion`, `statistics`, `parser`, `default`) and exponential backoff retries.
- **Observability** — Sentry (error tracking), `/metrics` (Prometheus format), structured JSON logs, `/health/live` and `/health/ready` endpoints.
- **Quality gates** — Pint (code style), Larastan/PHPStan (static analysis), Pest (unit/feature/contract tests), OpenAPI validation — all enforced in CI.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Database | PostgreSQL 16 |
| Cache / Queue / Locks | Redis 7 |
| Queue management | Laravel Horizon |
| Authentication | Laravel Sanctum (parser tokens), Laravel Fortify (admin 2FA) |
| Admin panel | Filament 3 |
| Parser | spekulatius/phpscraper, Symfony DomCrawler |
| Error tracking | Sentry |
| Static analysis | Larastan (PHPStan) |
| Code style | Laravel Pint |
| Testing | Pest (unit, feature, contract) |
| Containerization | Docker Compose (app, nginx, postgres, redis, queue, scheduler) |
| CI/CD | GitHub Actions (Pint → PHPStan → Pest → OpenAPI → dependency audit → deploy) |

## Running locally (Docker)

```bash
git clone https://github.com/mirmakhmudx/AvtoNarx_backend.git
cd AvtoNarx_backend

cp .env.example .env
# In .env, make sure the following are set correctly:
#   DB_HOST=postgres, REDIS_HOST=redis  (matching the docker-compose service names)
#   APP_KEY — generated automatically by the command below

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The application will be available at:

| Service | URL |
|---|---|
| API / App | http://localhost:8080 |
| Admin panel | http://localhost:8080/admin |
| Horizon (queue monitoring) | http://localhost:8080/horizon |
| Metrics | http://localhost:8080/metrics |
| Health check | http://localhost:8080/up |

### Useful commands

```bash
# Container status
docker compose ps

# Follow logs live
docker compose logs -f queue

# Run the test suite
docker compose exec app php artisan test

# Code style and static analysis
docker compose exec app vendor/bin/pint
docker compose exec app composer analyse

# Manually trigger the parser (e.g. for OLX.uz)
docker compose exec app php artisan tinker --execute="App\Jobs\RunParserSourceJob::dispatch('olx_uz');"
```

## API documentation

The full OpenAPI specification lives at [`docs/openapi.yaml`](docs/openapi.yaml).

**Public endpoints** — unauthenticated, cached:

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/brands` | List of active brands |
| GET | `/api/v1/brands/{slug}` | A single brand |
| GET | `/api/v1/brands/{slug}/models?year=&page=` | Models for a brand (with prices) |
| GET | `/api/v1/models/{id}/prices?year=&region=` | Detailed price history for a model |
| GET | `/api/v1/filters` | Available filter values (year, region) |

**Ingestion endpoints** — require a Sanctum token:

| Method | Path | Description |
|---|---|---|
| POST | `/api/v1/ingestion/market-listings/batches` | Submit a batch of market listings |
| POST | `/api/v1/ingestion/official-offers/batches` | Submit a batch of official offers |
| GET | `/api/v1/ingestion/batches/{batchId}` | Batch status |
| GET | `/api/v1/ingestion/batches/{batchId}/errors` | Batch errors |
| POST | `/api/v1/ingestion/heartbeat` | Parser client heartbeat |
| GET | `/api/v1/ingestion/catalog` | Brand/model reference data |

**Admin endpoints** — protected by Sanctum + role checks (`/api/v1/admin/...`): brands, models, official price moderation, unmatched candidates.

## Project structure

app/
├── Console/Commands/ # Artisan commands (parser, cleanup, discovery)
├── Filament/ # Admin panel resources and pages
├── Http/Controllers/Api/V1/
│ ├── Public/ # Public API
│ ├── Parser/ # Ingestion API
│ └── Admin/ # Admin API
├── Jobs/ # Queued jobs (parser, statistics, lifecycle)
├── Models/ # Eloquent models
├── Services/
│ ├── Parser/ # Adapter architecture, extraction, discovery
│ ├── PriceStatistics/ # Median/IQR calculation
│ ├── PublicApi/ # Caching, price formatting
│ └── OfficialOffers/ # Official price moderation
config/
├── market_statistics.php # Global price bounds, IQR settings
├── public_api.php # Public API cache/pagination settings
docs/
└── openapi.yaml # Full API specification
tests/
├── Unit/
├── Feature/
└── ...


## Testing

```bash
php artisan test                 # full suite
php artisan test --filter=Public # public API tests only
composer analyse                 # Larastan/PHPStan
vendor/bin/pint --test           # code style check (no changes made)
```

CI (`.github/workflows/ci.yml`) runs on every push/PR: **Pint → PHPStan → Pest → OpenAPI validation → dependency audit**. The deploy pipeline (`.github/workflows/deploy.yml`) ships to staging/production after merges to `main`.

## License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
