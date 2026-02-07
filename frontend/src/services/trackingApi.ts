import axios, { AxiosError, AxiosInstance } from 'axios';
import { TrackingRequest, TrackingResponse, Shipment } from '../types/tracking.types';

const API_BASE_URL = process.env.REACT_APP_API_URL || '/api';

// Retry configuration
const MAX_RETRIES = 3;
const INITIAL_RETRY_DELAY = 1000;
const MAX_RETRY_DELAY = 10000;

// Create axios instance with optimized configuration
const createApiClient = (): AxiosInstance => {
  const client = axios.create({
    baseURL: API_BASE_URL,
    timeout: 30000,
    headers: {
      'Content-Type': 'application/json',
    },
  });

  // Request interceptor for adding auth tokens if needed
  client.interceptors.request.use(
    (config) => {
      // Add auth token if available
      const token = localStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      
      // Add request timestamp for tracking
      config.metadata = { startTime: Date.now() };
      
      return config;
    },
    (error) => Promise.reject(error)
  );

  // Response interceptor for error handling
  client.interceptors.response.use(
    (response) => {
      // Log response time for performance monitoring
      if (response.config.metadata) {
        const duration = Date.now() - (response.config.metadata as any).startTime;
        if (duration > 5000) {
          console.warn(`Slow API response: ${response.config.url} took ${duration}ms`);
        }
      }
      return response;
    },
    (error: AxiosError) => {
      if (error.response?.status === 429) {
        throw new RateLimitError('Rate limit exceeded. Please try again later.');
      }
      if (error.response?.status === 503) {
        throw new ServiceUnavailableError('Service temporarily unavailable. Please try again later.');
      }
      if (error.response && error.response.status >= 500) {
        throw new ServerError('Server error. Please try again later.');
      }
      if (error.code === 'ECONNABORTED') {
        throw new TimeoutError('Request timeout. Please try again.');
      }
      if (error.code === 'ERR_NETWORK') {
        throw new NetworkError('Network error. Please check your connection.');
      }
      return Promise.reject(error);
    }
  );

  return client;
};

// Custom error classes for specific handling
export class RateLimitError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'RateLimitError';
  }
}

export class ServiceUnavailableError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ServiceUnavailableError';
  }
}

export class ServerError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ServerError';
  }
}

export class TimeoutError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'TimeoutError';
  }
}

export class NetworkError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'NetworkError';
  }
}

// Retry helper with exponential backoff
const sleep = (ms: number): Promise<void> => new Promise(resolve => setTimeout(resolve, ms));

const calculateRetryDelay = (attemptIndex: number): number => {
  const delay = Math.min(INITIAL_RETRY_DELAY * Math.pow(2, attemptIndex), MAX_RETRY_DELAY);
  // Add jitter to prevent thundering herd
  return delay + Math.random() * 1000;
};

const shouldRetry = (error: Error, attemptIndex: number): boolean => {
  // Don't retry if we've exceeded max retries
  if (attemptIndex >= MAX_RETRIES) return false;
  
  // Don't retry on rate limit - let the user wait
  if (error instanceof RateLimitError) return false;
  
  // Retry on network errors, timeouts, and server errors
  if (error instanceof NetworkError) return true;
  if (error instanceof TimeoutError) return true;
  if (error instanceof ServerError) return true;
  if (error instanceof ServiceUnavailableError) return true;
  
  // Check for axios errors with retryable status codes
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    // Don't retry on 4xx errors (except 408 timeout and 429 rate limit which is handled above)
    if (status && status >= 400 && status < 500 && status !== 408) {
      return false;
    }
    // Retry on 5xx errors
    if (status && status >= 500) {
      return true;
    }
  }
  
  return false;
};

const retryRequest = async <T>(
  requestFn: () => Promise<T>,
  attemptIndex = 0
): Promise<T> => {
  try {
    return await requestFn();
  } catch (error) {
    if (shouldRetry(error as Error, attemptIndex)) {
      const delay = calculateRetryDelay(attemptIndex);
      console.log(`Retrying request in ${delay}ms (attempt ${attemptIndex + 1}/${MAX_RETRIES})`);
      await sleep(delay);
      return retryRequest(requestFn, attemptIndex + 1);
    }
    throw error;
  }
};

const apiClient = createApiClient();

// Convert date strings to Date objects
const transformShipment = (shipment: any): Shipment => ({
  ...shipment,
  estimatedDelivery: shipment.estimatedDelivery ? new Date(shipment.estimatedDelivery) : undefined,
  createdAt: new Date(shipment.createdAt),
  updatedAt: new Date(shipment.updatedAt),
  events: shipment.events.map((event: any) => ({
    ...event,
    eventTime: new Date(event.eventTime),
  })),
  exceptions: shipment.exceptions.map((exception: any) => ({
    ...exception,
    createdAt: new Date(exception.createdAt),
    resolvedAt: exception.resolvedAt ? new Date(exception.resolvedAt) : undefined,
  })),
});

export const trackingApi = {
  /**
   * Track multiple shipments with retry logic
   */
  async trackShipments(trackingNumbers: string[]): Promise<TrackingResponse> {
    const request: TrackingRequest = { trackingNumbers };
    
    const response = await retryRequest(async () => {
      return await apiClient.post<TrackingResponse>('/tracking', request);
    });
    
    // Transform dates
    response.data.data = response.data.data.map(transformShipment);
    
    return response.data;
  },

  /**
   * Track a single shipment (for SEO pages) with retry logic
   */
  async trackSingleShipment(trackingNumber: string): Promise<Shipment | null> {
    try {
      const response = await retryRequest(async () => {
        return await apiClient.get<{ success: boolean; data: Shipment }>(`/tracking/${trackingNumber}`);
      });
      
      if (!response.data.success || !response.data.data) {
        return null;
      }

      return transformShipment(response.data.data);
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        return null;
      }
      throw error;
    }
  },

  /**
   * Batch track shipments in chunks for large requests
   */
  async trackShipmentsBatched(
    trackingNumbers: string[],
    chunkSize = 20,
    onProgress?: (completed: number, total: number) => void
  ): Promise<TrackingResponse> {
    const chunks: string[][] = [];
    for (let i = 0; i < trackingNumbers.length; i += chunkSize) {
      chunks.push(trackingNumbers.slice(i, i + chunkSize));
    }

    const results: Shipment[] = [];
    const errors: TrackingResponse['errors'] = [];
    let found = 0;
    let notFound = 0;

    for (let i = 0; i < chunks.length; i++) {
      const chunk = chunks[i];
      const response = await this.trackShipments(chunk);
      
      results.push(...response.data);
      errors.push(...response.errors);
      found += response.meta.found;
      notFound += response.meta.notFound;

      if (onProgress) {
        onProgress(i + 1, chunks.length);
      }
    }

    return {
      success: errors.length === 0,
      data: results,
      errors,
      meta: {
        total: trackingNumbers.length,
        found,
        notFound,
      },
    };
  },

  /**
   * Prefetch shipment data (for cache warming)
   */
  async prefetchShipment(trackingNumber: string): Promise<void> {
    try {
      await this.trackSingleShipment(trackingNumber);
    } catch {
      // Silently fail for prefetch
    }
  },

  /**
   * Prefetch multiple shipments
   */
  async prefetchShipments(trackingNumbers: string[]): Promise<void> {
    try {
      await this.trackShipments(trackingNumbers);
    } catch {
      // Silently fail for prefetch
    }
  },
};

// Extend axios config type to include metadata
declare module 'axios' {
  export interface AxiosRequestConfig {
    metadata?: {
      startTime: number;
    };
  }
}

export default trackingApi;
