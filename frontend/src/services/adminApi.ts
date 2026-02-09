import axios, { AxiosError, AxiosInstance } from 'axios';
import {
  User,
  AuthResponse,
  LoginCredentials,
  ShipmentSearchParams,
  ShipmentSearchResponse,
  ShipmentDetailResponse,
  SystemHealth,
  DashboardStats,
  SlaMetrics,
  QueueStatus,
  AuditLog,
  AuditLogSearchParams,
  Pagination,
  ApiResponse,
  Role,
  Facility,
  CreateEventRequest,
  UpdateEventRequest,
} from '../types/admin.types';

const API_BASE_URL = process.env.REACT_APP_API_URL || '/api';

// Create axios instance
const createAdminClient = (): AxiosInstance => {
  const client = axios.create({
    baseURL: API_BASE_URL,
    timeout: 30000,
    headers: {
      'Content-Type': 'application/json',
    },
  });

  // Request interceptor
  client.interceptors.request.use(
    (config) => {
      const token = localStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    },
    (error) => Promise.reject(error)
  );

  // Response interceptor
  client.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
      if (error.response?.status === 401) {
        // Clear token and redirect to login
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.location.href = '/admin/login';
      }
      return Promise.reject(error);
    }
  );

  return client;
};

const adminClient = createAdminClient();

// Authentication API
export const authApi = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await adminClient.post<AuthResponse>('/auth/login', credentials);
    return response.data;
  },

  async getUser(): Promise<ApiResponse<User>> {
    const response = await adminClient.get<ApiResponse<User>>('/auth/user');
    return response.data;
  },

  async logout(): Promise<void> {
    await adminClient.post('/auth/logout');
  },

  async logoutAll(): Promise<void> {
    await adminClient.post('/auth/logout-all');
  },

  async refreshToken(): Promise<{ token: string; expiresAt?: string }> {
    const response = await adminClient.post<ApiResponse<{ token: string; expiresAt?: string }>>('/auth/refresh');
    return response.data.data;
  },

  getOAuthUrl(provider: 'google' | 'microsoft'): string {
    return `${API_BASE_URL}/auth/oauth/${provider}`;
  },
};

// Shipment management API
export const shipmentApi = {
  async search(params: ShipmentSearchParams): Promise<ShipmentSearchResponse> {
    const response = await adminClient.get<ShipmentSearchResponse>('/admin/shipments', { params });
    return response.data;
  },

  async getDetails(id: string, includeRaw = false): Promise<ShipmentDetailResponse> {
    const response = await adminClient.get<ShipmentDetailResponse>(`/admin/shipments/${id}`, {
      params: { include_raw: includeRaw },
    });
    return response.data;
  },

  async addEvent(shipmentId: string, event: CreateEventRequest): Promise<ApiResponse<{ event: any }>> {
    const response = await adminClient.post<ApiResponse<{ event: any }>>(
      `/admin/shipments/${shipmentId}/events`,
      event
    );
    return response.data;
  },

  async updateEvent(
    shipmentId: string,
    eventId: string,
    data: UpdateEventRequest
  ): Promise<ApiResponse<{ event: any }>> {
    const response = await adminClient.put<ApiResponse<{ event: any }>>(
      `/admin/shipments/${shipmentId}/events/${eventId}`,
      data
    );
    return response.data;
  },

  async deleteEvent(shipmentId: string, eventId: string, notes: string): Promise<ApiResponse<void>> {
    const response = await adminClient.delete<ApiResponse<void>>(
      `/admin/shipments/${shipmentId}/events/${eventId}`,
      { data: { notes } }
    );
    return response.data;
  },

  async export(params: {
    trackingNumbers?: string[];
    status?: string;
    dateFrom?: string;
    dateTo?: string;
  }): Promise<any[]> {
    const response = await adminClient.post<ApiResponse<any[]>>('/admin/shipments/export', params);
    return response.data.data;
  },
};

// User management API
export const userApi = {
  async list(params?: {
    search?: string;
    role?: string;
    isActive?: boolean;
    perPage?: number;
    sortBy?: string;
    sortOrder?: 'asc' | 'desc';
  }): Promise<{ users: User[]; pagination: Pagination }> {
    const response = await adminClient.get<ApiResponse<{ users: User[]; pagination: Pagination }>>(
      '/admin/users',
      { params }
    );
    return response.data.data;
  },

  async get(id: string): Promise<User> {
    const response = await adminClient.get<ApiResponse<User>>(`/admin/users/${id}`);
    return response.data.data;
  },

  async create(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    isActive?: boolean;
  }): Promise<User> {
    const response = await adminClient.post<ApiResponse<User>>('/admin/users', data);
    return response.data.data;
  },

  async update(
    id: string,
    data: {
      name?: string;
      email?: string;
      password?: string;
      password_confirmation?: string;
      isActive?: boolean;
    }
  ): Promise<User> {
    const response = await adminClient.put<ApiResponse<User>>(`/admin/users/${id}`, data);
    return response.data.data;
  },

  async updateRoles(id: string, roles: string[]): Promise<User> {
    const response = await adminClient.put<ApiResponse<User>>(`/admin/users/${id}/roles`, { roles });
    return response.data.data;
  },

  async toggleActive(id: string): Promise<User> {
    const response = await adminClient.post<ApiResponse<User>>(`/admin/users/${id}/toggle-active`);
    return response.data.data;
  },

  async delete(id: string): Promise<void> {
    await adminClient.delete(`/admin/users/${id}`);
  },

  async getRoles(): Promise<Role[]> {
    const response = await adminClient.get<ApiResponse<Role[]>>('/admin/users/roles');
    return response.data.data;
  },
};

// Dashboard API
export const dashboardApi = {
  async getHealth(): Promise<SystemHealth> {
    const response = await adminClient.get<ApiResponse<SystemHealth>>('/admin/dashboard/health');
    return response.data.data;
  },

  async getStats(period: 'today' | 'week' | 'month' = 'today'): Promise<DashboardStats> {
    const response = await adminClient.get<ApiResponse<DashboardStats>>('/admin/dashboard/stats', {
      params: { period },
    });
    return response.data.data;
  },

  async getEventMetrics(): Promise<any> {
    const response = await adminClient.get<ApiResponse<any>>('/admin/dashboard/events');
    return response.data.data;
  },

  async getSlaMetrics(): Promise<SlaMetrics> {
    const response = await adminClient.get<ApiResponse<SlaMetrics>>('/admin/dashboard/sla');
    return response.data.data;
  },

  async getQueueStatus(): Promise<QueueStatus> {
    const response = await adminClient.get<ApiResponse<QueueStatus>>('/admin/dashboard/queues');
    return response.data.data;
  },

  async getAuditLogs(params: AuditLogSearchParams): Promise<{ logs: AuditLog[]; pagination: Pagination }> {
    const response = await adminClient.get<ApiResponse<{ logs: AuditLog[]; pagination: Pagination }>>(
      '/admin/audit-logs',
      { params }
    );
    return response.data.data;
  },
};

// Configuration API
export const configApi = {
  async getFacilities(params?: {
    search?: string;
    type?: string;
    isActive?: boolean;
    perPage?: number;
  }): Promise<{ facilities: Facility[]; pagination: Pagination }> {
    const response = await adminClient.get<ApiResponse<{ facilities: Facility[]; pagination: Pagination }>>(
      '/admin/config/facilities',
      { params }
    );
    return response.data.data;
  },

  async createFacility(data: Partial<Facility>): Promise<Facility> {
    const response = await adminClient.post<ApiResponse<Facility>>('/admin/config/facilities', data);
    return response.data.data;
  },

  async updateFacility(id: string, data: Partial<Facility>): Promise<Facility> {
    const response = await adminClient.put<ApiResponse<Facility>>(`/admin/config/facilities/${id}`, data);
    return response.data.data;
  },

  async getEventCodes(): Promise<Record<string, any>> {
    const response = await adminClient.get<ApiResponse<Record<string, any>>>('/admin/config/event-codes');
    return response.data.data;
  },

  async getEtaRules(): Promise<{ rules: any[]; lanes: any[] }> {
    const response = await adminClient.get<ApiResponse<{ rules: any[]; lanes: any[] }>>('/admin/config/eta-rules');
    return response.data.data;
  },

  async getSystemConfig(): Promise<Record<string, any>> {
    const response = await adminClient.get<ApiResponse<Record<string, any>>>('/admin/config/system');
    return response.data.data;
  },
};

export default {
  auth: authApi,
  shipments: shipmentApi,
  users: userApi,
  dashboard: dashboardApi,
  config: configApi,
};
