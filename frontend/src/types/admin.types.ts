// User and authentication types
export interface User {
  id: string;
  name: string;
  email: string;
  avatar?: string;
  provider?: string;
  isActive: boolean;
  roles: string[];
  permissions: string[];
  lastLoginAt?: string;
  createdAt: string;
}

export interface AuthResponse {
  success: boolean;
  data: {
    user: User;
    token: string;
    expiresAt?: string;
  };
  timestamp: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

// Shipment management types
export interface AdminShipment {
  id: string;
  trackingNumber: string;
  referenceNumber?: string;
  serviceType: string;
  currentStatus: string;
  estimatedDelivery?: string;
  originFacility?: Facility;
  destinationFacility?: Facility;
  currentLocation?: Facility;
  createdAt: string;
  updatedAt: string;
}

export interface AdminEvent {
  id: string;
  eventCode: string;
  eventTime: string;
  description?: string;
  facility?: {
    id: string;
    name: string;
    code: string;
  };
  location?: string;
  rawPayload?: Record<string, any>;
  createdAt: string;
}

export interface AdminSubscription {
  id: string;
  channel: string;
  contactValue: string;
  active: boolean;
  events: string[];
  createdAt: string;
}

export interface ShipmentSearchParams {
  trackingNumber?: string;
  referenceNumber?: string;
  phone?: string;
  email?: string;
  status?: string;
  serviceType?: string;
  facilityId?: string;
  dateFrom?: string;
  dateTo?: string;
  perPage?: number;
  page?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface ShipmentSearchResponse {
  success: boolean;
  data: {
    shipments: AdminShipment[];
    pagination: Pagination;
  };
  timestamp: string;
}

export interface ShipmentDetailResponse {
  success: boolean;
  data: {
    shipment: AdminShipment;
    events: AdminEvent[];
    subscriptions: AdminSubscription[];
  };
  timestamp: string;
}

// Facility types
export interface Facility {
  id: string;
  code: string;
  name: string;
  type: string;
  address?: string;
  city?: string;
  province?: string;
  postalCode?: string;
  country?: string;
  latitude?: number;
  longitude?: number;
  timezone?: string;
  isActive: boolean;
}

// Dashboard types
export interface SystemHealth {
  status: 'healthy' | 'degraded' | 'unhealthy';
  services: {
    database: ServiceHealth;
    redis: ServiceHealth;
    queue: ServiceHealth;
  };
  server: {
    phpVersion: string;
    laravelVersion: string;
    memoryUsage: string;
    uptime: string;
  };
}

export interface ServiceHealth {
  status: 'healthy' | 'unhealthy';
  latencyMs?: number;
  pendingJobs?: number;
  error?: string;
}

export interface DashboardStats {
  shipments: {
    total: number;
    period: number;
    byStatus: Record<string, number>;
  };
  events: {
    total: number;
    period: number;
    perHour: Record<string, number>;
  };
  subscriptions: {
    total: number;
    active: number;
    byChannel: Record<string, number>;
  };
  users: {
    total: number;
    active: number;
    loggedInToday: number;
  };
}

export interface SlaMetrics {
  onTimeDeliveryRate: number;
  exceptionRate: number;
  deliveredCount: number;
  onTimeCount: number;
  exceptionCount: number;
  period: string;
}

export interface QueueStatus {
  [queueName: string]: {
    size: number | null;
    status: 'normal' | 'moderate' | 'high' | 'unknown';
    error?: string;
  };
}

// Audit log types
export interface AuditLog {
  id: string;
  user?: {
    id: string;
    name: string;
    email: string;
  };
  action: string;
  entityType?: string;
  entityId?: string;
  oldValues?: Record<string, any>;
  newValues?: Record<string, any>;
  ipAddress?: string;
  metadata?: Record<string, any>;
  createdAt: string;
}

export interface AuditLogSearchParams {
  userId?: string;
  action?: string;
  entityType?: string;
  dateFrom?: string;
  dateTo?: string;
  perPage?: number;
  page?: number;
}

// Pagination
export interface Pagination {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
}

// API Response types
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  timestamp: string;
}

export interface ApiError {
  success: false;
  error: string;
  errorCode: string;
  timestamp: string;
}

// Role types
export type UserRole = 'admin' | 'ops' | 'cs' | 'readonly';

export interface Role {
  id: string;
  name: UserRole;
}

// Event management types
export interface CreateEventRequest {
  eventCode: string;
  eventTime: string;
  description?: string;
  facilityId?: string;
  location?: string;
  notes?: string;
}

export interface UpdateEventRequest {
  eventCode?: string;
  eventTime?: string;
  description?: string;
  facilityId?: string;
  location?: string;
  notes: string; // Required for audit trail
}
