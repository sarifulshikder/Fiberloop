# Fiberloop

> AI-assisted ISP billing and subscriber management platform for fiber/FTTH internet providers

## About Fiberloop

Fiberloop is a comprehensive ISP billing and network management platform built with Laravel 13, designed to scale to 100,000+ subscribers. It provides:

- **Customer Management**: Full subscriber lifecycle, KYC, service addresses
- **Billing & Invoicing**: Automated billing cycles, pro-rated charges, late fees, receipts
- **Payments**: Multiple gateway support (Stripe, SSLCommerz, manual), receipts, reconciliation
- **Network Authentication**: FreeRADIUS integration for PPPoE/Hotspot/Static IP authentication
- **Package Management**: Flexible packages with speed tiers, FUP policies, add-ons
- **Reseller Management**: Multi-level reseller hierarchies with commission tracking
- **Network Device Management**: MikroTik RouterOS, OLTs, switches via SNMP
- **Support**: Ticketing system, field technician workflows
- **Customer Portal**: Self-service app (Flutter) for usage, payments, support
- **Admin Panel**: Filament v5 admin interface
- **Analytics**: AI-assisted churn prediction, network insights

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.4+) |
| Admin Panel | Filament v5 |
| Database | PostgreSQL 18 (app + FreeRADIUS) |
| Cache/Queue | Redis 7 |
| Queue Runtime | Laravel Horizon |
| Realtime | Laravel Reverb |
| Network AAA | FreeRADIUS 3.2.x |
| Customer App | Flutter |
| AI/ML | Laravel AI SDK, Python/FastAPI microservice |
| Testing | Pest PHP |
| Infrastructure | Docker Compose |

## Getting Started

### Prerequisites

- Docker & Docker Compose
- Git

### Quick Start

```bash
# Clone the repository
git clone <repository-url> fiberloop
cd fiberloop

# Copy environment file
cp .env.example .env

# Start the containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install

# Generate app key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate --force

# Seed the database (creates admin user)
docker-compose exec app php artisan db:seed --force

# Run queue workers
docker-compose up -d queue

# Access the admin panel at http://localhost/admin
# Login: admin@fiberloop.local / password
```

### Manual Installation (without Docker)

See [DOCKER.md](DOCKER.md) for production deployment options.

## Development

### Running Tests

```bash
docker-compose exec app composer test
```

### Code Style

```bash
docker-compose exec app composer pint
```

### Commands

| Command | Description |
|---|---|
| `docker-compose up -d` | Start all services |
| `docker-compose down` | Stop all services |
| `docker-compose exec app php artisan ...` | Run Artisan commands |
| `docker-compose exec app composer ...` | Run Composer commands |
| `make <target>` | See Makefile for shortcuts |

## Project Structure

```
app/
├── Actions/           # Business logic actions
├── Enums/            # PHP enums
├── Filament/         # Filament admin resources
├── Http/
│   ├── Controllers/
│   └── Requests/     # Form requests
├── Jobs/             # Queued jobs
├── Models/           # Eloquent models
├── Providers/
└── Services/         # External integrations (RADIUS, MikroTik, payments)

database/
├── migrations/
├── seeders/
└── factories/

config/               # Configuration files
docker/               # Docker configurations
routes/               # Application routes
storage/              # File storage
tests/                # Pest tests
```

## Architecture Decisions

- **Multi-tenant by design**: Every business table has `tenant_id` for future SaaS support
- **Money as integers**: All currency amounts stored as `bigint` in smallest unit (poysha = BDT × 100)
- **Soft deletes**: Financial data (customers, invoices, payments) is never hard-deleted
- **Queue everything**: All money-moving and network-provisioning actions run as queued jobs
- **RBAC**: Role-based access control via spatie/laravel-permission
- **Audit logging**: All financial mutations logged via spatie/laravel-activitylog

## Contributing

- Follow PSR-12 code style
- Write tests for new features
- Use Actions for business logic, keep controllers thin
- All migrations must have working `down()` methods
- Update PROGRESS.md and ROADMAP.md with changes

## License

MIT License. See [LICENSE](LICENSE) for details.

## Documentation

- [AGENTS.md](AGENTS.md) - Agent instructions and architecture decisions
- [ROADMAP.md](ROADMAP.md) - Phase-by-phase build plan
- [PROGRESS.md](PROGRESS.md) - Current build status
