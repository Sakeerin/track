# Parcel Tracking System

A comprehensive parcel tracking system providing real-time shipment visibility for public users and operational management tools for staff.

## Project Structure

- `backend/` - Laravel API application
- `frontend/` - React TypeScript application  
- `docker/` - Docker configuration files
- `docs/` - Project documentation

## Quick Start

### Development Setup

1. Clone the repository
2. Copy environment files:
   ```bash
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```
3. Start development environment:
   ```bash
   docker-compose up -d
   ```
4. Install dependencies and run migrations:
   ```bash
   cd backend && composer install && php artisan migrate --seed
   cd ../frontend && npm install
   ```

### Running the Application

- Backend API: http://localhost:8000
- Frontend App: http://localhost:3000
- Admin Console: http://localhost:3000/admin

## Technology Stack

- **Backend**: Laravel 10 with PHP 8.2
- **Frontend**: React 18 with TypeScript
- **Database**: PostgreSQL 15, Redis 7
- **Infrastructure**: Docker, Nginx, Apache Kafka

## Documentation

See the `docs/` directory for detailed documentation.