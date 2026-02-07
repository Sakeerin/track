import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useCallback, useEffect, useMemo } from 'react';
import { trackingApi } from '../services/trackingApi';
import { Shipment, TrackingResponse } from '../types/tracking.types';

// Constants
const STORAGE_KEY_HISTORY = 'tracking_history';
const STORAGE_KEY_CACHE = 'tracking_cache';
const STORAGE_KEY_PREFERENCES = 'tracking_preferences';
const MAX_HISTORY_ITEMS = 10;
const MAX_CACHED_QUERIES = 5;
const CACHE_EXPIRY_MS = 5 * 60 * 1000; // 5 minutes

interface UseTrackingOptions {
  enabled?: boolean;
  staleTime?: number;
  cacheTime?: number;
}

interface UseTrackingResult {
  data: TrackingResponse | undefined;
  shipments: Shipment[];
  isLoading: boolean;
  isError: boolean;
  error: Error | null;
  trackShipments: (trackingNumbers: string[]) => Promise<void>;
  refetch: () => void;
  reset: () => void;
}

interface CachedQuery {
  trackingNumbers: string[];
  data: TrackingResponse;
  timestamp: number;
}

interface TrackingPreferences {
  enableLocalCache: boolean;
  enableHistory: boolean;
}

// Helper to get preferences from localStorage
const getPreferences = (): TrackingPreferences => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY_PREFERENCES);
    if (stored) {
      return JSON.parse(stored);
    }
  } catch {
    // Silently fail
  }
  return { enableLocalCache: true, enableHistory: true };
};

// Helper to set preferences in localStorage
const setPreferences = (prefs: Partial<TrackingPreferences>): void => {
  try {
    const current = getPreferences();
    localStorage.setItem(STORAGE_KEY_PREFERENCES, JSON.stringify({ ...current, ...prefs }));
  } catch {
    // Silently fail
  }
};

export const useTracking = (
  initialTrackingNumbers: string[] = [],
  options: UseTrackingOptions = {}
): UseTrackingResult => {
  const queryClient = useQueryClient();
  const [trackingNumbers, setTrackingNumbers] = useState<string[]>(initialTrackingNumbers);
  const preferences = useMemo(() => getPreferences(), []);

  // Check localStorage cache first
  const getCachedData = useCallback((numbers: string[]): TrackingResponse | null => {
    if (!preferences.enableLocalCache) return null;
    
    try {
      const stored = localStorage.getItem(STORAGE_KEY_CACHE);
      if (!stored) return null;
      
      const cachedQueries: CachedQuery[] = JSON.parse(stored);
      const sortedNumbers = [...numbers].sort();
      
      const cached = cachedQueries.find(q => {
        const cachedSorted = [...q.trackingNumbers].sort();
        return (
          cachedSorted.length === sortedNumbers.length &&
          cachedSorted.every((num, i) => num === sortedNumbers[i]) &&
          Date.now() - q.timestamp < CACHE_EXPIRY_MS
        );
      });
      
      return cached?.data || null;
    } catch {
      return null;
    }
  }, [preferences.enableLocalCache]);

  // Save to localStorage cache
  const setCachedData = useCallback((numbers: string[], data: TrackingResponse): void => {
    if (!preferences.enableLocalCache) return;
    
    try {
      const stored = localStorage.getItem(STORAGE_KEY_CACHE);
      let cachedQueries: CachedQuery[] = stored ? JSON.parse(stored) : [];
      
      // Remove expired entries
      cachedQueries = cachedQueries.filter(q => Date.now() - q.timestamp < CACHE_EXPIRY_MS);
      
      // Remove existing entry for same tracking numbers if exists
      const sortedNumbers = [...numbers].sort();
      cachedQueries = cachedQueries.filter(q => {
        const cachedSorted = [...q.trackingNumbers].sort();
        return !(
          cachedSorted.length === sortedNumbers.length &&
          cachedSorted.every((num, i) => num === sortedNumbers[i])
        );
      });
      
      // Add new entry
      cachedQueries.unshift({
        trackingNumbers: numbers,
        data,
        timestamp: Date.now(),
      });
      
      // Keep only MAX_CACHED_QUERIES entries
      cachedQueries = cachedQueries.slice(0, MAX_CACHED_QUERIES);
      
      localStorage.setItem(STORAGE_KEY_CACHE, JSON.stringify(cachedQueries));
    } catch {
      // Silently fail
    }
  }, [preferences.enableLocalCache]);

  const {
    data,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['tracking', trackingNumbers],
    queryFn: async () => {
      // Check localStorage cache first
      const cached = getCachedData(trackingNumbers);
      if (cached) {
        return cached;
      }
      
      const result = await trackingApi.trackShipments(trackingNumbers);
      
      // Save to localStorage cache
      setCachedData(trackingNumbers, result);
      
      return result;
    },
    enabled: trackingNumbers.length > 0 && (options.enabled !== false),
    staleTime: options.staleTime ?? 30000, // 30 seconds
    cacheTime: options.cacheTime ?? 300000, // 5 minutes
    retry: (failureCount, error: any) => {
      // Don't retry on client errors (4xx)
      if (error?.response?.status >= 400 && error?.response?.status < 500) {
        return false;
      }
      // Exponential backoff for retries
      return failureCount < 3;
    },
    retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 10000),
  });

  const mutation = useMutation({
    mutationFn: trackingApi.trackShipments,
    onSuccess: (data) => {
      // Update the query cache with the new data
      queryClient.setQueryData(['tracking', trackingNumbers], data);
      // Save to localStorage cache
      setCachedData(trackingNumbers, data);
    },
    retry: 2,
    retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 5000),
  });

  const trackShipments = useCallback(async (newTrackingNumbers: string[]) => {
    setTrackingNumbers(newTrackingNumbers);
    
    // Check React Query cache first
    const cachedData = queryClient.getQueryData(['tracking', newTrackingNumbers]);
    
    if (!cachedData) {
      // Check localStorage cache
      const localCached = getCachedData(newTrackingNumbers);
      if (localCached) {
        queryClient.setQueryData(['tracking', newTrackingNumbers], localCached);
        return;
      }
      
      // If no cached data, trigger the mutation
      await mutation.mutateAsync(newTrackingNumbers);
    } else {
      // If we have cached data, just refetch to ensure freshness
      queryClient.invalidateQueries(['tracking', newTrackingNumbers]);
    }
  }, [queryClient, mutation, getCachedData]);

  const reset = useCallback(() => {
    setTrackingNumbers([]);
    queryClient.removeQueries(['tracking']);
  }, [queryClient]);

  return {
    data,
    shipments: data?.data || [],
    isLoading: isLoading || mutation.isLoading,
    isError: isError || mutation.isError,
    error: (error || mutation.error) as Error | null,
    trackShipments,
    refetch,
    reset,
  };
};

interface UseSingleTrackingOptions {
  enabled?: boolean;
  staleTime?: number;
}

interface UseSingleTrackingResult {
  shipment: Shipment | null;
  isLoading: boolean;
  isError: boolean;
  error: Error | null;
  refetch: () => void;
}

export const useSingleTracking = (
  trackingNumber: string,
  options: UseSingleTrackingOptions = {}
): UseSingleTrackingResult => {
  const {
    data: shipment,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['tracking', 'single', trackingNumber],
    queryFn: () => trackingApi.trackSingleShipment(trackingNumber),
    enabled: Boolean(trackingNumber) && (options.enabled !== false),
    staleTime: options.staleTime ?? 30000, // 30 seconds
    retry: (failureCount, error: any) => {
      // Don't retry on 404 (not found)
      if (error?.response?.status === 404) {
        return false;
      }
      // Don't retry on other client errors (4xx)
      if (error?.response?.status >= 400 && error?.response?.status < 500) {
        return false;
      }
      return failureCount < 3;
    },
    retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 10000),
  });

  return {
    shipment: shipment || null,
    isLoading,
    isError,
    error: error as Error | null,
    refetch,
  };
};

// Hook for lazy loading individual shipments
interface UseLazyShipmentOptions {
  enabled?: boolean;
}

interface UseLazyShipmentResult {
  shipment: Shipment | null;
  isLoading: boolean;
  isError: boolean;
  error: Error | null;
  loadShipment: () => void;
  refetch: () => void;
}

export const useLazyShipment = (
  trackingNumber: string,
  options: UseLazyShipmentOptions = {}
): UseLazyShipmentResult => {
  const [shouldLoad, setShouldLoad] = useState(false);
  
  const {
    data: shipment,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['tracking', 'lazy', trackingNumber],
    queryFn: () => trackingApi.trackSingleShipment(trackingNumber),
    enabled: Boolean(trackingNumber) && shouldLoad && (options.enabled !== false),
    staleTime: 30000,
    retry: 3,
    retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 10000),
  });

  const loadShipment = useCallback(() => {
    setShouldLoad(true);
  }, []);

  return {
    shipment: shipment || null,
    isLoading,
    isError,
    error: error as Error | null,
    loadShipment,
    refetch,
  };
};

// Hook for managing tracking history in localStorage with opt-in
export const useTrackingHistory = () => {
  const preferences = useMemo(() => getPreferences(), []);

  const getHistory = useCallback((): string[] => {
    if (!preferences.enableHistory) return [];
    
    try {
      const stored = localStorage.getItem(STORAGE_KEY_HISTORY);
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  }, [preferences.enableHistory]);

  const addToHistory = useCallback((trackingNumbers: string[]) => {
    if (!preferences.enableHistory) return;
    
    try {
      const history = getHistory();
      const newHistory = [
        ...trackingNumbers,
        ...history.filter(num => !trackingNumbers.includes(num))
      ].slice(0, MAX_HISTORY_ITEMS);
      
      localStorage.setItem(STORAGE_KEY_HISTORY, JSON.stringify(newHistory));
    } catch {
      // Silently fail if localStorage is not available
    }
  }, [preferences.enableHistory, getHistory]);

  const clearHistory = useCallback(() => {
    try {
      localStorage.removeItem(STORAGE_KEY_HISTORY);
    } catch {
      // Silently fail if localStorage is not available
    }
  }, []);

  return {
    getHistory,
    addToHistory,
    clearHistory,
    isEnabled: preferences.enableHistory,
  };
};

// Hook for managing tracking preferences
export const useTrackingPreferences = () => {
  const [preferences, setPreferencesState] = useState<TrackingPreferences>(getPreferences);

  const updatePreferences = useCallback((updates: Partial<TrackingPreferences>) => {
    setPreferences(updates);
    setPreferencesState(prev => ({ ...prev, ...updates }));
  }, []);

  const toggleLocalCache = useCallback(() => {
    updatePreferences({ enableLocalCache: !preferences.enableLocalCache });
  }, [preferences.enableLocalCache, updatePreferences]);

  const toggleHistory = useCallback(() => {
    updatePreferences({ enableHistory: !preferences.enableHistory });
  }, [preferences.enableHistory, updatePreferences]);

  const clearLocalCache = useCallback(() => {
    try {
      localStorage.removeItem(STORAGE_KEY_CACHE);
    } catch {
      // Silently fail
    }
  }, []);

  return {
    preferences,
    updatePreferences,
    toggleLocalCache,
    toggleHistory,
    clearLocalCache,
  };
};

// Prefetch hook for warming up cache
export const usePrefetchShipments = () => {
  const queryClient = useQueryClient();

  const prefetch = useCallback(async (trackingNumbers: string[]) => {
    await queryClient.prefetchQuery({
      queryKey: ['tracking', trackingNumbers],
      queryFn: () => trackingApi.trackShipments(trackingNumbers),
      staleTime: 30000,
    });
  }, [queryClient]);

  const prefetchSingle = useCallback(async (trackingNumber: string) => {
    await queryClient.prefetchQuery({
      queryKey: ['tracking', 'single', trackingNumber],
      queryFn: () => trackingApi.trackSingleShipment(trackingNumber),
      staleTime: 30000,
    });
  }, [queryClient]);

  return { prefetch, prefetchSingle };
};
