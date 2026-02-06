import axios from 'axios';
import { TrackingRequest, TrackingResponse, Shipment } from '../types/tracking.types';

const API_BASE_URL = process.env.REACT_APP_API_URL || '/api';

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor for adding auth tokens if needed
apiClient.interceptors.request.use(
  (config) => {
    // Add auth token if available
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 429) {
      throw new Error('Rate limit exceeded. Please try again later.');
    }
    if (error.response?.status >= 500) {
      throw new Error('Server error. Please try again later.');
    }
    if (error.code === 'ECONNABORTED') {
      throw new Error('Request timeout. Please try again.');
    }
    return Promise.reject(error);
  }
);

export const trackingApi = {
  /**
   * Track multiple shipments
   */
  async trackShipments(trackingNumbers: string[]): Promise<TrackingResponse> {
    const request: TrackingRequest = { trackingNumbers };
    const response = await apiClient.post<TrackingResponse>('/tracking', request);
    
    // Convert date strings to Date objects
    response.data.data = response.data.data.map(shipment => ({
      ...shipment,
      estimatedDelivery: shipment.estimatedDelivery ? new Date(shipment.estimatedDelivery) : undefined,
      createdAt: new Date(shipment.createdAt),
      updatedAt: new Date(shipment.updatedAt),
      events: shipment.events.map(event => ({
        ...event,
        eventTime: new Date(event.eventTime),
      })),
      exceptions: shipment.exceptions.map(exception => ({
        ...exception,
        createdAt: new Date(exception.createdAt),
        resolvedAt: exception.resolvedAt ? new Date(exception.resolvedAt) : undefined,
      })),
    }));
    
    return response.data;
  },

  /**
   * Track a single shipment (for SEO pages)
   */
  async trackSingleShipment(trackingNumber: string): Promise<Shipment | null> {
    try {
      const response = await apiClient.get<{ success: boolean; data: Shipment }>(`/tracking/${trackingNumber}`);
      
      if (!response.data.success || !response.data.data) {
        return null;
      }

      const shipment = response.data.data;
      
      // Convert date strings to Date objects
      return {
        ...shipment,
        estimatedDelivery: shipment.estimatedDelivery ? new Date(shipment.estimatedDelivery) : undefined,
        createdAt: new Date(shipment.createdAt),
        updatedAt: new Date(shipment.updatedAt),
        events: shipment.events.map(event => ({
          ...event,
          eventTime: new Date(event.eventTime),
        })),
        exceptions: shipment.exceptions.map(exception => ({
          ...exception,
          createdAt: new Date(exception.createdAt),
          resolvedAt: exception.resolvedAt ? new Date(exception.resolvedAt) : undefined,
        })),
      };
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        return null;
      }
      throw error;
    }
  },
};

export default trackingApi;