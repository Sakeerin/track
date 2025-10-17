# Technology Stack & Build System

## Backend Stack

- **Framework**: Laravel 10 with PHP 8.2
- **Database**: PostgreSQL 15 (primary), Redis 7 (cache/sessions)
- **Authentication**: Laravel Sanctum with OAuth2/OIDC (Google/Microsoft)
- **Queue Management**: Laravel Horizon with Redis
- **Search**: Laravel Scout
- **Permissions**: Spatie Laravel Permission for RBAC
- **Event Streaming**: Apache Kafka for real-time event processing

## Frontend Stack

- **Framework**: React 18 with TypeScript
- **Styling**: Tailwind CSS for responsive design
- **State Management**: React Query for API caching and state
- **Routing**: React Router
- **Forms**: React Hook Form
- **Internationalization**: i18next (Thai/English)
- **Maps**: Leaflet for location visualization

## Infrastructure

- **Containerization**: Docker for development and production
- **Reverse Proxy**: Nginx with SSL termination
- **Object Storage**: MinIO/S3 for file storage
- **Monitoring**: Application health checks and performance monitoring
- **Backup**: Daily PostgreSQL backups with 15-minute WAL

## Common Commands

### Development Setup
```bash
# Backend setup
composer install
php artisan migrate --seed
php artisan horizon:install
php artisan serve

# Frontend setup
npm install
npm run dev

# Docker development
docker-compose up -d
```

### Testing
```bash
# Backend tests
php artisan test
php artisan test --coverage

# Frontend tests
npm run test
npm run test:e2e
```

### Production Deployment
```bash
# Build and deploy
docker build -t parcel-tracking .
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm run build
```

### Queue Management
```bash
# Start queue workers
php artisan horizon
php artisan queue:work --queue=events,notifications

# Monitor queues
php artisan horizon:status
php artisan queue:monitor
```

## Performance Considerations

- Redis caching with 30-second TTL for shipment data
- Database indexing on tracking_number, event_time, and status fields
- API rate limiting: 100 requests/minute for public endpoints
- Lazy loading for shipment events and timeline data
- CDN integration for static assets and map tiles