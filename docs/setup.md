# Setup Guide

## Prerequisites

- Docker and Docker Compose
- Node.js 18+ (for local development)
- PHP 8.2+ (for local development)
- Composer (for local development)

## Quick Start with Docker

1. Clone the repository
2. Copy environment files:
   ```bash
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```

3. Start the development environment:
   ```bash
   docker-compose up -d
   ```

4. Install dependencies and setup database:
   ```bash
   # Backend setup
   docker-compose exec backend composer install
   docker-compose exec backend php artisan key:generate
   docker-compose exec backend php artisan migrate --seed

   # Frontend setup
   docker-compose exec frontend npm install
   ```

5. Access the applications:
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8000
   - Admin Console: http://localhost:3000/admin
   - Horizon Dashboard: http://localhost:8000/horizon
   - MinIO Console: http://localhost:9001

## Local Development Setup

### Backend (Laravel)

1. Navigate to backend directory:
   ```bash
   cd backend
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Copy and configure environment:
   ```bash
   cp .env.example .env
   # Edit .env file with your database credentials
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Run migrations:
   ```bash
   php artisan migrate --seed
   ```

6. Start the development server:
   ```bash
   php artisan serve
   ```

7. Start queue workers:
   ```bash
   php artisan horizon
   ```

### Frontend (React)

1. Navigate to frontend directory:
   ```bash
   cd frontend
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Copy and configure environment:
   ```bash
   cp .env.example .env
   ```

4. Start the development server:
   ```bash
   npm start
   ```

## Testing

### Backend Tests
```bash
cd backend
php artisan test
```

### Frontend Tests
```bash
cd frontend
npm test
```

## Production Deployment

1. Prepare TLS certificate files:
   - Place `fullchain.pem` and `privkey.pem` in `docker/nginx/ssl/`

2. Configure production variables in your shell or `.env` file:
   ```bash
   cp .env.prod.example .env.prod
   # Edit .env.prod with real secrets and alert destinations
   ```
   - Required: `POSTGRES_PASSWORD`, `GRAFANA_ADMIN_PASSWORD`
   - Alerting: `ALERT_SLACK_WEBHOOK_URL`, `ALERT_INCIDENT_WEBHOOK_URL`, SMTP vars

3. Deploy with rolling update script:
   ```bash
   docker compose --env-file .env.prod -f docker-compose.prod.yml up -d postgres redis
   ENV_FILE=.env.prod COMPOSE_FILE=docker-compose.prod.yml sh docker/deploy.sh
   ```

4. Or start full production stack directly:
   ```bash
   docker compose --env-file .env.prod -f docker-compose.prod.yml up -d
   ```

5. Run migration/seed manually if needed:
   ```bash
   docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force
   docker compose -f docker-compose.prod.yml run --rm backend php artisan db:seed --force
   ```

## Backup and Restore

- Daily full backup is run by `db-backup` service using `docker/backup.sh`
- WAL archive is generated every 15 minutes by PostgreSQL (`archive_timeout=900`)

Restore procedure:

```bash
docker compose -f docker-compose.prod.yml run --rm \
  -e BACKUP_FILE=/backups/full/<backup-file>.dump \
  db-backup sh /scripts/restore.sh
```

Monthly restore verification:

```bash
docker compose -f docker-compose.prod.yml run --rm \
  -e BACKUP_FILE=/backups/full/<backup-file>.dump \
  db-backup sh /scripts/verify-backup.sh
```

## Monitoring

- Nginx health endpoint: `https://<host>/healthz`
- Tracking health endpoint: `https://<host>/api/tracking/health`
- Event ingestion health endpoint: `https://<host>/api/events/health`
- Prometheus: `http://<host>:9090`
- Alertmanager: `http://<host>:9093`
- Grafana: `http://<host>:3001`

## Troubleshooting

### Common Issues

1. **Database connection errors**: Ensure PostgreSQL is running and credentials are correct
2. **Redis connection errors**: Ensure Redis is running and accessible
3. **Permission errors**: Check file permissions for storage and cache directories
4. **Port conflicts**: Ensure ports 3000, 8000, 5432, 6379, 9092 are available

### Logs

- Laravel logs: `backend/storage/logs/laravel.log`
- Horizon logs: Available in Horizon dashboard
- Docker logs: `docker-compose logs [service_name]`
