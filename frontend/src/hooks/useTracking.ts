import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useCallback } from 'react';
import { trackingApi } from '../services/trackingApi';
import { Shipment, TrackingResponse } from '../types/tracking.types';

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

export const useTracking = (
  initialTrackingNumbers: string[] = [],
  options: UseTrackingOptions = {}
): UseTrackingResult => {
  const queryClient = useQueryClient();
  const [trackingNumbers, setTrackingNumbers] = useState<string[]>(initialTrackingNumbers);

  const {
    data,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['tracking', trackingNumbers],
    queryFn: () => trackingApi.trackShipments(trackingNumbers),
    enabled: trackingNumbers.length > 0 && (options.enabled !== false),
    staleTime: options.staleTime ?? 30000, // 30 seconds
    cacheTime: options.cacheTime ?? 300000, // 5 minutes
    retry: (failureCount, error: any) => {
      // Don't retry on client errors (4xx)
      if (error?.response?.status >= 400 && error?.response?.status < 500) {
        return false;
      }
      return failureCount < 3;
    },
  });

  const mutation = useMutation({
    mutationFn: trackingApi.trackShipments,
    onSuccess: (data) => {
      // Update the query cache with the new data
      queryClient.setQueryData(['tracking', trackingNumbers], data);
    },
  });

  const trackShipments = useCallback(async (newTrackingNumbers: string[]) => {
    setTrackingNumbers(newTrackingNumbers);
    
    // Check if we have cached data for these tracking numbers
    const cachedData = queryClient.getQueryData(['tracking', newTrackingNumbers]);
    
    if (!cachedData) {
      // If no cached data, trigger the mutation
      await mutation.mutateAsync(newTrackingNumbers);
    } else {
      // If we have cached data, just refetch to ensure freshness
      queryClient.invalidateQueries(['tracking', newTrackingNumbers]);
    }
  }, [queryClient, mutation]);

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
  });

  return {
    shipment: shipment || null,
    isLoading,
    isError,
    error: error as Error | null,
    refetch,
  };
};

// Hook for managing tracking history in localStorage
export const useTrackingHistory = () => {
  const STORAGE_KEY = 'tracking_history';
  const MAX_HISTORY_ITEMS = 10;

  const getHistory = useCallback((): string[] => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  }, []);

  const addToHistory = useCallback((trackingNumbers: string[]) => {
    try {
      const history = getHistory();
      const newHistory = [
        ...trackingNumbers,
        ...history.filter(num => !trackingNumbers.includes(num))
      ].slice(0, MAX_HISTORY_ITEMS);
      
      localStorage.setItem(STORAGE_KEY, JSON.stringify(newHistory));
    } catch {
      // Silently fail if localStorage is not available
    }
  }, [getHistory]);

  const clearHistory = useCallback(() => {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // Silently fail if localStorage is not available
    }
  }, []);

  return {
    getHistory,
    addToHistory,
    clearHistory,
  };
};