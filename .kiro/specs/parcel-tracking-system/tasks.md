# Implementation Plan

- [x] 1. Set up project structure and core infrastructure

  - Initialize Laravel backend project with required packages (Sanctum, Horizon, Scout)
  - Initialize React frontend project with TypeScript, Tailwind CSS, and required dependencies
  - Configure Docker development environment with PostgreSQL, Redis, and Kafka
  - Set up basic CI/CD pipeline configuration
  - _Requirements: 9.4, 9.5_

- [x] 2. Implement core data models and database schema

  - Create Laravel migrations for shipments, events, facilities, and subscriptions tables
  - Implement Eloquent models with relationships and proper casting
  - Set up database seeders for facilities and test data
  - Configure database indexing strategy for performance
  - _Requirements: 7.6, 8.1_

- [x] 2.1 Create shipment and event models

  - Implement Shipment model with status management and relationships
  - Implement Event model with event ordering and deduplication logic
  - Add model factories for testing data generation
  - _Requirements: 7.6, 8.1_

- [x] 2.2 Write unit tests for data models

  - Create unit tests for model relationships and scopes
  - Test event deduplication and ordering logic
  - Validate model casting and attribute handling
  - _Requirements: 7.6_

- [x] 3. Build event ingestion system


  - Implement webhook endpoint for receiving scan events from handhelds and partners
  - Create SFTP batch processing for CSV file uploads
  - Build event validation and normalization service
  - Implement idempotency checking using eventId + trackingNo + timestamp
  - Set up Kafka producer for event streaming
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.7_

- [x] 3.1 Create event ingestion API endpoints

  - Build REST webhook controller with HMAC signature validation
  - Implement batch CSV upload endpoint with file validation
  - Add partner API pull mechanism with configurable schedules
  - Create event queuing system with dead letter queue for failures
  - _Requirements: 7.1, 7.2, 7.7_

- [x] 3.2 Implement event processing pipeline

  - Build event normalization service to map partner codes to canonical codes
  - Implement geocoding service for facility location resolution
  - Create event ordering logic that handles out-of-order events
  - Add current status computation based on latest event timestamp
  - _Requirements: 7.4, 7.5, 7.6_

- [x] 3.3 Write integration tests for event ingestion

  - Test webhook endpoint with various partner formats
  - Validate HMAC signature verification
  - Test batch processing with malformed CSV files
  - Verify event deduplication and ordering
  - _Requirements: 7.1, 7.2, 7.6_

- [ ] 4. Develop ETA calculation engine

  - Implement deterministic ETA rules based on lane and service type
  - Build dynamic adjustment logic for holidays, cut-off times, and congestion
  - Create ETA recalculation triggers for specific events
  - Add configuration interface for ETA rules management
  - _Requirements: 8.1, 8.2, 8.3_

- [ ] 4.1 Create ETA service with rule engine

  - Build lane-based ETA calculation (origin-destination pairs)
  - Implement service type modifiers (standard, express, economy)
  - Add day-of-week and holiday adjustments
  - Create ETA recalculation job for event triggers
  - _Requirements: 8.1, 8.2, 8.3_

- [ ] 4.2 Write unit tests for ETA calculations

  - Test deterministic rules with various lane/service combinations
  - Validate holiday and weekend adjustments
  - Test ETA recalculation triggers
  - Verify configuration rule application
  - _Requirements: 8.1, 8.2, 8.3_

- [ ] 5. Build public tracking API

  - Create tracking controller with rate limiting and validation
  - Implement shipment data service with Redis caching
  - Build bulk tracking endpoint for multiple shipments
  - Add single shipment endpoint for SEO-friendly pages
  - Implement partial success handling for failed lookups
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 3.1, 3.2, 3.5, 9.1_

- [ ] 5.1 Create tracking endpoints with caching

  - Build multi-shipment tracking API with input validation
  - Implement Redis caching layer for shipment data
  - Add rate limiting per IP address with configurable limits
  - Create single shipment API for public sharing
  - Handle partial failures with appropriate error responses
  - _Requirements: 1.1, 1.2, 1.3, 3.1, 3.5, 9.1_

- [ ] 5.2 Implement tracking data formatting

  - Build shipment response formatter with timeline data
  - Add progress milestone calculation (pickup → delivery)
  - Implement exception detection and banner generation
  - Create location data formatting for map integration
  - _Requirements: 1.4, 1.5, 1.6, 1.7_

- [ ] 5.3 Write API integration tests

  - Test multi-shipment tracking with various scenarios
  - Validate rate limiting and error responses
  - Test caching behavior and cache invalidation
  - Verify partial success response formatting
  - _Requirements: 1.1, 1.2, 3.5, 9.1_

- [ ] 6. Develop notification system

  - Implement notification service with multiple channels (email, SMS, LINE, webhook)
  - Build subscription management with consent tracking
  - Create notification templates with Thai/English support
  - Add throttling and delivery receipt tracking
  - Implement unsubscribe mechanism with token-based links
  - _Requirements: 4.1, 8.4, 8.5, 8.6, 8.7_

- [ ] 6.1 Create notification channels and templates

  - Build email notification service with template rendering
  - Implement SMS gateway integration with delivery tracking
  - Add LINE messaging API integration
  - Create webhook notification system for external integrations
  - Build template management with variable substitution
  - _Requirements: 8.5, 8.6, 8.7_

- [ ] 6.2 Implement subscription management

  - Build subscription API with consent tracking
  - Create notification preference management
  - Implement throttling logic (max 1 per 2h unless critical)
  - Add unsubscribe token generation and validation
  - Build subscription analytics and delivery tracking
  - _Requirements: 4.1, 8.6, 8.7_

- [ ] 6.3 Write notification system tests

  - Test notification delivery across all channels
  - Validate template rendering with Thai/English content
  - Test throttling and delivery receipt handling
  - Verify subscription consent and unsubscribe flows
  - _Requirements: 4.1, 8.5, 8.6, 8.7_

- [ ] 7. Build React frontend tracking interface

  - Create tracking form component with validation and debouncing
  - Implement shipment card display with timeline and progress bar
  - Build bulk table view with sorting, filtering, and CSV export
  - Add map integration using Leaflet for location visualization
  - Implement internationalization with Thai/English switching
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 7.1 Create tracking form and validation

  - Build multi-input tracking form with format validation
  - Implement debounced input validation and duplicate removal
  - Add configurable limit enforcement (default 20 tracking numbers)
  - Create loading states with skeleton loaders
  - Add error handling for invalid formats and API failures
  - _Requirements: 1.1, 1.2, 3.1, 3.4_

- [ ] 7.2 Implement shipment display components

  - Build shipment card component with status badges and progress bars
  - Create timeline component with reverse chronological event display
  - Add UTC/local time toggle for timestamp display
  - Implement exception banner with guidance messages
  - Build milestone progress indicator (pickup → delivery)
  - _Requirements: 1.3, 1.4, 1.6, 1.7_

- [ ] 7.3 Create bulk view and data export

  - Build table view with sortable columns (tracking no., status, ETA, etc.)
  - Implement filtering by shipment status
  - Add CSV export functionality for tracking data
  - Create responsive design for mobile and desktop
  - Add accessibility features (WCAG 2.1 AA compliance)
  - _Requirements: 2.1, 2.2, 2.3, 2.5_

- [ ] 7.4 Add map integration and internationalization

  - Integrate Leaflet maps for location visualization
  - Display last known location and route polylines
  - Implement Thai/English language switching with i18next
  - Add proper number and date formatting for each locale
  - Create translated event descriptions and status messages
  - _Requirements: 1.5, 2.4_

- [ ] 7.5 Write frontend component tests

  - Test tracking form validation and submission
  - Validate shipment card rendering with various data states
  - Test bulk view sorting, filtering, and export functionality
  - Verify internationalization and accessibility features
  - _Requirements: 1.1, 1.2, 2.1, 2.2, 2.4, 2.5_

- [ ] 8. Implement caching and performance optimization

  - Set up Redis caching for shipment data with TTL management
  - Implement localStorage caching for user's last 10 queries (opt-in)
  - Add per-shipment lazy loading with retry mechanisms
  - Create cache invalidation strategy for real-time updates
  - Optimize database queries with proper indexing
  - _Requirements: 3.2, 3.3, 9.4_

- [ ] 8.1 Configure Redis caching layer

  - Set up Redis cache for latest shipment status (30-second TTL)
  - Implement cache-aside pattern for event timeline data
  - Add cache warming for frequently accessed shipments
  - Create cache invalidation on event updates
  - Build cache metrics and monitoring
  - _Requirements: 3.2, 9.4_

- [ ] 8.2 Implement frontend performance features

  - Add localStorage caching for recent queries with user consent
  - Implement per-shipment lazy loading with React Query
  - Add retry logic for transient API failures
  - Create skeleton loading states for better UX
  - Optimize bundle size with code splitting
  - _Requirements: 3.2, 3.3, 3.4_

- [ ] 8.3 Write performance tests

  - Test cache hit rates and TTL behavior
  - Validate localStorage functionality and data persistence
  - Test retry mechanisms under various failure scenarios
  - Measure API response times and frontend rendering performance
  - _Requirements: 3.2, 3.3_

- [ ] 9. Build admin console and authentication

  - Implement OAuth2/OIDC authentication with Google/Microsoft
  - Create role-based access control (RBAC) system
  - Build shipment search interface with multiple filter options
  - Implement manual event management for operations staff
  - Create monitoring dashboards for system health
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4, 6.5, 9.2_

- [ ] 9.1 Set up authentication and RBAC

  - Configure Laravel Sanctum with OAuth2/OIDC providers
  - Implement role-based permissions (admin, ops, cs, readonly)
  - Create user management interface with role assignment
  - Add API middleware for permission checking
  - Build audit logging for administrative actions
  - _Requirements: 5.1, 6.5, 9.2_

- [ ] 9.2 Create admin search and management interface

  - Build advanced search with filters (tracking no., phone, date range, facility)
  - Implement shipment detail view with events timeline and raw payloads
  - Create manual event addition/correction interface
  - Add bulk operations for shipment management
  - Build subscription management for customer notifications
  - _Requirements: 5.2, 5.3, 5.4_

- [ ] 9.3 Implement monitoring dashboards

  - Create system health dashboard (events/min, queue lag, SLA metrics)
  - Build exception monitoring with alert thresholds
  - Add configuration management interface (event codes, facilities, ETA rules)
  - Implement API key and rate limit management
  - Create user activity and audit trail views
  - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [ ] 9.4 Write admin console tests

  - Test authentication flows and permission enforcement
  - Validate search functionality with various filter combinations
  - Test manual event operations and audit logging
  - Verify dashboard data accuracy and real-time updates
  - _Requirements: 5.1, 5.2, 6.1, 9.2_

- [ ] 10. Add sharing and SEO features

  - Create public tracking page with server-side rendering
  - Implement share link generation (/track/TH1234567890)
  - Add SEO meta tags and structured data for tracking pages
  - Build FAQ section with status explanations
  - Create contact form with auto-attached tracking numbers
  - _Requirements: 4.2, 4.3, 4.4, 4.5_

- [ ] 10.1 Implement public sharing and SEO

  - Build server-side rendered tracking page for SEO
  - Create share link generation with tracking number validation
  - Add Open Graph and Twitter Card meta tags
  - Implement structured data markup for search engines
  - Build sitemap generation for public tracking pages
  - _Requirements: 4.2, 4.3_

- [ ] 10.2 Create help and support features

  - Build FAQ section with searchable content
  - Create contact form with tracking number auto-attachment
  - Add status explanation tooltips and help text
  - Implement delivery time estimates by service type
  - Build customer support ticket integration
  - _Requirements: 4.4, 4.5_

- [ ] 10.3 Write SEO and sharing tests

  - Test server-side rendering and meta tag generation
  - Validate share link functionality and tracking number parsing
  - Test FAQ search and contact form submission
  - Verify structured data markup and sitemap generation
  - _Requirements: 4.2, 4.3, 4.4, 4.5_

- [ ] 11. Implement security and data protection

  - Add PII encryption for phone numbers and email addresses
  - Implement comprehensive audit logging for all operations
  - Set up rate limiting with reCAPTCHA for public endpoints
  - Create data retention policies and cleanup jobs
  - Add security headers and CSRF protection
  - _Requirements: 9.1, 9.2, 9.3_

- [ ] 11.1 Configure data protection and encryption

  - Implement PII field encryption using Laravel's built-in encryption
  - Set up audit logging for all database changes
  - Create data retention policies with automated cleanup
  - Add GDPR compliance features (data export, deletion)
  - Implement secure session management
  - _Requirements: 9.3_

- [ ] 11.2 Add security middleware and protection

  - Configure rate limiting with different tiers for public/admin APIs
  - Add reCAPTCHA integration for public tracking form
  - Implement CSRF protection and security headers
  - Set up API key authentication with usage tracking
  - Create IP whitelisting for admin access
  - _Requirements: 9.1, 9.2_

- [ ] 11.3 Write security tests

  - Test PII encryption and decryption functionality
  - Validate rate limiting and reCAPTCHA integration
  - Test audit logging and data retention policies
  - Verify CSRF protection and security header configuration
  - _Requirements: 9.1, 9.2, 9.3_

- [ ] 12. Set up deployment and monitoring

  - Configure production deployment with Docker containers
  - Set up database backup and restore procedures
  - Implement application monitoring with health checks
  - Create log aggregation and error tracking
  - Add performance monitoring and alerting
  - _Requirements: 9.4, 9.5, 9.6_

- [ ] 12.1 Configure production deployment

  - Set up Docker containers for Laravel and React applications
  - Configure Nginx reverse proxy with SSL termination
  - Implement database migration and seeding for production
  - Set up environment-specific configuration management
  - Create deployment scripts with zero-downtime deployment
  - _Requirements: 9.4, 9.5_

- [ ] 12.2 Implement monitoring and backup systems

  - Configure automated daily database backups with 15-minute WAL
  - Set up application health checks and uptime monitoring
  - Implement log aggregation with structured logging
  - Add error tracking and alerting for critical issues
  - Create performance monitoring dashboards
  - _Requirements: 9.5, 9.6_

- [ ] 12.3 Write deployment and monitoring tests
  - Test backup and restore procedures
  - Validate health check endpoints and monitoring alerts
  - Test graceful degradation under various failure scenarios
  - Verify log aggregation and error tracking functionality
  - _Requirements: 9.5, 9.6_
