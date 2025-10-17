# Requirements Document

## Introduction

This document outlines the requirements for a comprehensive parcel tracking system similar to Thailand Post and Flash Express. The system will provide public self-service tracking capabilities for 1-20 parcels per query, with real-time status updates, event timelines, ETA predictions, and optional notifications. Additionally, it includes an administrative console for operations staff to monitor, investigate, and manage shipments.

The system serves multiple user types: public users (shippers/consignees) checking shipment status, contact center staff assisting customers, operations/hub staff investigating exceptions, and system administrators managing configuration and users.

## Requirements

### Requirement 1

**User Story:** As a public user, I want to track multiple parcels simultaneously using their tracking numbers, so that I can monitor the status of all my shipments in one place.

#### Acceptance Criteria

1. WHEN a user enters 1-20 tracking numbers (separated by newlines, commas, or spaces) THEN the system SHALL validate each number format and remove duplicates
2. WHEN tracking numbers exceed the configured limit (default 20) THEN the system SHALL display an error message and reject the request
3. WHEN a user submits valid tracking numbers THEN the system SHALL display results in individual cards showing status badge, tracking number, service type, origin/destination, and ETA
4. WHEN displaying results THEN the system SHALL show a timeline in reverse chronological order with timestamp (local & UTC toggle), facility/location, event code with description, and remarks
5. IF location data is available THEN the system SHALL optionally display a map with last known location and route polyline
6. WHEN displaying shipment progress THEN the system SHALL show a progress bar with milestones (Picked up → In transit → At hub → Out for delivery → Delivered)
7. IF there are exceptions (address issues, customs hold) THEN the system SHALL display an exception banner with guidance

### Requirement 2

**User Story:** As a public user, I want to view tracking results in different formats and export data, so that I can analyze and share shipment information efficiently.

#### Acceptance Criteria

1. WHEN viewing multiple shipments THEN the system SHALL provide a bulk table view with columns for tracking number, last event, time, current location, ETA, and exceptions
2. WHEN in bulk view THEN the system SHALL allow sorting and filtering by status
3. WHEN a user requests export THEN the system SHALL generate a CSV file with all tracking data
4. WHEN the interface loads THEN the system SHALL support Thai/English language switching with proper number/date formats and translated event names
5. WHEN using the interface THEN the system SHALL meet WCAG 2.1 AA accessibility standards with keyboard navigation and ARIA labels for timeline elements

### Requirement 3

**User Story:** As a public user, I want responsive performance and error handling, so that I can reliably access tracking information even under poor network conditions.

#### Acceptance Criteria

1. WHEN typing tracking numbers THEN the system SHALL provide debounced validation with skeleton loaders during fetch
2. WHEN individual shipment requests fail THEN the system SHALL implement per-shipment lazy loading with retry on transient failures
3. WHEN a user opts in THEN the system SHALL cache the last 10 queries in localStorage
4. WHEN tracking numbers are invalid, not found, temporarily unavailable, or rate-limited THEN the system SHALL display appropriate error states
5. WHEN some tracking IDs fail but others succeed THEN the system SHALL show partial success results

### Requirement 4

**User Story:** As a public user, I want to subscribe to notifications and share tracking links, so that I can stay updated on shipment progress and share information with others.

#### Acceptance Criteria

1. WHEN a user clicks "Track this parcel" THEN the system SHALL display a subscription modal for email/phone/LINE notifications with consent checkbox
2. WHEN a user requests a share link THEN the system SHALL generate a public URL (e.g., /track/TH1234567890)
3. WHEN accessing a shared tracking link THEN the system SHALL render a server-side public detail page with proper SEO meta tags
4. WHEN users need help THEN the system SHALL provide an FAQ section explaining statuses and delivery times
5. WHEN contacting support THEN the system SHALL provide a contact form that auto-attaches the current tracking number

### Requirement 5

**User Story:** As an operations staff member, I want to search and manage shipments through an administrative console, so that I can investigate exceptions and maintain data accuracy.

#### Acceptance Criteria

1. WHEN accessing the admin console THEN the system SHALL require secure authentication and role-based access control
2. WHEN searching for shipments THEN the system SHALL support queries by tracking number, reference number, phone, order ID, date range, and facility
3. WHEN viewing shipment details THEN the system SHALL display events timeline, raw payloads, audit log, and subscription list
4. WHEN authorized users perform manual actions THEN the system SHALL allow appending/correcting events, marking delivery, adding remarks, and triggering ETA recalculation
5. WHEN system issues occur THEN the system SHALL provide reprocess/replay functionality for failed messages

### Requirement 6

**User Story:** As a system administrator, I want monitoring dashboards and configuration management, so that I can ensure system health and manage operational parameters.

#### Acceptance Criteria

1. WHEN accessing dashboards THEN the system SHALL display network status (events/min, queue lag), exception counts, and SLA breaches
2. WHEN managing configuration THEN the system SHALL provide interfaces for event code dictionary, facility geodata, service types, statuses, and ETA rules
3. WHEN managing integrations THEN the system SHALL allow configuration of API keys, rate limits, and webhook destinations
4. WHEN managing access THEN the system SHALL provide user and role management with RBAC capabilities
5. WHEN system changes occur THEN the system SHALL maintain audit trails for all administrative actions

### Requirement 7

**User Story:** As a system integrator, I want robust event ingestion from multiple sources, so that tracking data remains accurate and up-to-date across all channels.

#### Acceptance Criteria

1. WHEN receiving events THEN the system SHALL support REST webhooks, SFTP batch CSV, handheld scan API, and partner pull protocols
2. WHEN processing events THEN the system SHALL ensure idempotency using eventId + trackingNo + timestamp uniqueness with deduplication
3. WHEN validating events THEN the system SHALL verify schema, timestamp sanity, facility existence, and signed HMAC for partner authentication
4. WHEN normalizing data THEN the system SHALL map partner codes to canonical codes and translate text between Thai/English
5. WHEN geocoding THEN the system SHALL convert facility names to lat/lng coordinates with optional reverse-geocoding for ad-hoc locations
6. WHEN events arrive out-of-order THEN the system SHALL store events as received but compute current_status from max(eventTime)
7. WHEN processing fails THEN the system SHALL use dead-letter queue with replay capability and failure reasons

### Requirement 8

**User Story:** As a business stakeholder, I want accurate ETA predictions and automated notifications, so that customers receive timely updates about their shipments.

#### Acceptance Criteria

1. WHEN calculating ETA THEN the system SHALL use deterministic rules by lane/service (e.g., Bangkok→Chiang Mai, Standard = 2–3 days)
2. WHEN adjusting ETA THEN the system SHALL consider day-of-week, cut-off time, holidays, and hub congestion
3. WHEN certain events occur (pickup scan, arrival at destination hub, out-for-delivery) THEN the system SHALL recompute ETA automatically
4. WHEN shipment status changes THEN the system SHALL trigger notifications for Created, PickedUp, InTransit, AtHub, OutForDelivery, DeliveryAttempted, Delivered, ExceptionRaised/Resolved, Customs, and Returned events
5. WHEN sending notifications THEN the system SHALL support Email, SMS, LINE, and Webhook channels with throttling (max 1 per 2h unless critical)
6. WHEN managing notifications THEN the system SHALL provide Thai/English templates with variables, preview capability, and unsubscribe/consent management
7. WHEN delivering notifications THEN the system SHALL implement delivery receipts and retries with exponential backoff

### Requirement 9

**User Story:** As a security-conscious organization, I want robust security and access controls, so that sensitive tracking data remains protected while maintaining system availability.

#### Acceptance Criteria

1. WHEN accessing public APIs THEN the system SHALL implement read-only endpoints with API key authentication, per-IP rate limiting, and reCAPTCHA for web access
2. WHEN accessing admin APIs THEN the system SHALL require OAuth2/OIDC authentication (Google/Microsoft) with role-based access control
3. WHEN storing data THEN the system SHALL encrypt PII (phone/email) at rest, use TLS 1.2+, and maintain comprehensive audit trails
4. WHEN system operates THEN the system SHALL maintain 99.9% monthly availability SLO for public GET tracking endpoints
5. WHEN backing up data THEN the system SHALL perform daily full backups with 15-minute WAL, testing restore procedures monthly
6. WHEN upstream services fail THEN the system SHALL gracefully degrade by serving cached latest status information