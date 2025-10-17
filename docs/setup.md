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

1. Build the applications:
   ```bash
   # Frontend
   cd frontend && npm run build

   # Backend
   cd backend && composer install --optimize-autoloader --no-dev
   ```

2. Configure production environment variables

3. Run database migrations:
   ```bash
   php artisan migrate --force
   ```

4. Cache configuration:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. Start queue workers and scheduler

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