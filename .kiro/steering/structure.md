# Project Structure & Organization

## Root Directory Layout

```
parcel-tracking/
├── backend/                 # Laravel API application
├── frontend/               # React TypeScript application
├── docker/                 # Docker configuration files
├── docs/                   # Project documentation
└── .kiro/                  # Kiro configuration and specs
```

## Backend Structure (Laravel)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/        # API controllers (tracking, ingestion)
│   │   │   └── Admin/      # Admin console controllers
│   │   ├── Middleware/     # Custom middleware (rate limiting, auth)
│   │   └── Requests/       # Form request validation classes
│   ├── Models/             # Eloquent models (Shipment, Event, Facility)
│   ├── Services/           # Business logic services
│   │   ├── Tracking/       # Tracking and shipment services
│   │   ├── Ingestion/      # Event processing services
│   │   ├── Notification/   # Multi-channel notification services
│   │   └── ETA/           # ETA calculation engine
│   ├── Jobs/              # Queue jobs for async processing
│   └── Events/            # Laravel events and listeners
├── database/
│   ├── migrations/        # Database schema migrations
│   ├── seeders/          # Data seeders (facilities, test data)
│   └── factories/        # Model factories for testing
├── routes/
│   ├── api.php           # Public API routes
│   └── admin.php         # Admin console routes
└── tests/
    ├── Feature/          # Integration and API tests
    └── Unit/             # Unit tests for services and models
```

## Frontend Structure (React)

```
frontend/
├── src/
│   ├── components/
│   │   ├── tracking/     # Public tracking interface components
│   │   ├── admin/        # Admin console components
│   │   ├── common/       # Shared UI components
│   │   └── layout/       # Layout and navigation components
│   ├── hooks/            # Custom React hooks
│   ├── services/         # API client and external services
│   ├── utils/            # Utility functions and helpers
│   ├── types/            # TypeScript type definitions
│   ├── i18n/             # Internationalization files (Thai/English)
│   └── styles/           # Tailwind CSS and custom styles
├── public/               # Static assets
└── tests/
    ├── components/       # Component unit tests
    ├── integration/      # Integration tests
    └── e2e/             # End-to-end tests
```

## Key Architectural Patterns

### Backend Patterns
- **Repository Pattern**: Data access abstraction for models
- **Service Layer**: Business logic separation from controllers
- **Event-Driven Architecture**: Kafka events for real-time processing
- **CQRS**: Separate read/write operations for performance
- **Circuit Breaker**: External API failure handling

### Frontend Patterns
- **Component Composition**: Reusable UI components with props
- **Custom Hooks**: Shared logic extraction (useTracking, useNotifications)
- **Error Boundaries**: Graceful error handling and fallbacks
- **Lazy Loading**: Code splitting and performance optimization

## File Naming Conventions

### Backend (Laravel)
- Controllers: `TrackingController.php`, `EventIngestionController.php`
- Models: `Shipment.php`, `Event.php`, `Facility.php`
- Services: `TrackingService.php`, `ETACalculationService.php`
- Jobs: `ProcessEventJob.php`, `SendNotificationJob.php`
- Tests: `TrackingControllerTest.php`, `ShipmentModelTest.php`

### Frontend (React)
- Components: `TrackingForm.tsx`, `ShipmentCard.tsx`, `Timeline.tsx`
- Hooks: `useTracking.ts`, `useNotifications.ts`, `useAuth.ts`
- Services: `trackingApi.ts`, `notificationService.ts`
- Types: `shipment.types.ts`, `api.types.ts`
- Tests: `TrackingForm.test.tsx`, `useTracking.test.ts`

## Configuration Management

### Environment-Specific Config
- `.env.local` - Local development
- `.env.staging` - Staging environment  
- `.env.production` - Production environment

### Key Configuration Areas
- Database connections and credentials
- Redis cache and session configuration
- Kafka broker settings and topics
- External API keys (SMS, email, LINE)
- Rate limiting and security settings
- Notification channel configurations