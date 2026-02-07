import { renderHook, act, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import { 
  useTracking, 
  useSingleTracking, 
  useTrackingHistory, 
  useTrackingPreferences,
  useLazyShipment,
  usePrefetchShipments
} from '../useTracking';
import { trackingApi } from '../../services/trackingApi';

// Mock the tracking API
jest.mock('../../services/trackingApi');

const mockTrackingApi = trackingApi as jest.Mocked<typeof trackingApi>;

// Create a wrapper with QueryClientProvider
const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
  
  const Wrapper = ({ children }: { children: React.ReactNode }) => 
    React.createElement(QueryClientProvider, { client: queryClient }, children);
  
  return Wrapper;
};

describe('useTracking', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
  });

  it('should fetch shipments when trackShipments is called', async () => {
    const mockResponse = {
      success: true,
      data: [
        {
          id: '1',
          trackingNumber: 'TH1234567890',
          status: 'in_transit',
          events: [],
          exceptions: [],
          createdAt: new Date(),
          updatedAt: new Date(),
        },
      ],
      errors: [],
      meta: { total: 1, found: 1, notFound: 0 },
    };

    mockTrackingApi.trackShipments.mockResolvedValue(mockResponse);

    const { result } = renderHook(() => useTracking(), { wrapper: createWrapper() });

    await act(async () => {
      await result.current.trackShipments(['TH1234567890']);
    });

    await waitFor(() => {
      expect(result.current.shipments).toHaveLength(1);
      expect(result.current.shipments[0].trackingNumber).toBe('TH1234567890');
    });
  });

  it('should reset state when reset is called', async () => {
    const mockResponse = {
      success: true,
      data: [{ id: '1', trackingNumber: 'TH1234567890', events: [], exceptions: [], createdAt: new Date(), updatedAt: new Date() }],
      errors: [],
      meta: { total: 1, found: 1, notFound: 0 },
    };

    mockTrackingApi.trackShipments.mockResolvedValue(mockResponse);

    const { result } = renderHook(() => useTracking(), { wrapper: createWrapper() });

    await act(async () => {
      await result.current.trackShipments(['TH1234567890']);
    });

    await waitFor(() => {
      expect(result.current.shipments).toHaveLength(1);
    });

    act(() => {
      result.current.reset();
    });

    expect(result.current.shipments).toHaveLength(0);
  });
});

describe('useTrackingHistory', () => {
  beforeEach(() => {
    localStorage.clear();
    // Set preferences to enable history
    localStorage.setItem('tracking_preferences', JSON.stringify({ enableLocalCache: true, enableHistory: true }));
  });

  it('should return empty array when no history exists', () => {
    const { result } = renderHook(() => useTrackingHistory());
    expect(result.current.getHistory()).toEqual([]);
  });

  it('should add tracking numbers to history', () => {
    const { result } = renderHook(() => useTrackingHistory());

    act(() => {
      result.current.addToHistory(['TH1234567890', 'TH1234567891']);
    });

    const history = result.current.getHistory();
    expect(history).toContain('TH1234567890');
    expect(history).toContain('TH1234567891');
  });

  it('should not add duplicates to history', () => {
    const { result } = renderHook(() => useTrackingHistory());

    act(() => {
      result.current.addToHistory(['TH1234567890']);
      result.current.addToHistory(['TH1234567890']);
    });

    const history = result.current.getHistory();
    expect(history.filter(num => num === 'TH1234567890')).toHaveLength(1);
  });

  it('should limit history to 10 items', () => {
    const { result } = renderHook(() => useTrackingHistory());

    const manyNumbers = Array.from({ length: 15 }, (_, i) => `TH${String(i).padStart(10, '0')}`);
    
    act(() => {
      result.current.addToHistory(manyNumbers);
    });

    const history = result.current.getHistory();
    expect(history).toHaveLength(10);
  });

  it('should clear history', () => {
    const { result } = renderHook(() => useTrackingHistory());

    act(() => {
      result.current.addToHistory(['TH1234567890']);
    });

    expect(result.current.getHistory()).toHaveLength(1);

    act(() => {
      result.current.clearHistory();
    });

    expect(result.current.getHistory()).toHaveLength(0);
  });
});

describe('useTrackingPreferences', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('should return default preferences when none are set', () => {
    const { result } = renderHook(() => useTrackingPreferences());
    
    expect(result.current.preferences.enableLocalCache).toBe(true);
    expect(result.current.preferences.enableHistory).toBe(true);
  });

  it('should toggle local cache preference', () => {
    const { result } = renderHook(() => useTrackingPreferences());

    expect(result.current.preferences.enableLocalCache).toBe(true);

    act(() => {
      result.current.toggleLocalCache();
    });

    expect(result.current.preferences.enableLocalCache).toBe(false);
  });

  it('should toggle history preference', () => {
    const { result } = renderHook(() => useTrackingPreferences());

    expect(result.current.preferences.enableHistory).toBe(true);

    act(() => {
      result.current.toggleHistory();
    });

    expect(result.current.preferences.enableHistory).toBe(false);
  });

  it('should persist preferences to localStorage', () => {
    const { result } = renderHook(() => useTrackingPreferences());

    act(() => {
      result.current.updatePreferences({ enableLocalCache: false });
    });

    const stored = JSON.parse(localStorage.getItem('tracking_preferences') || '{}');
    expect(stored.enableLocalCache).toBe(false);
  });

  it('should clear local cache', () => {
    // Add some cached data
    localStorage.setItem('tracking_cache', JSON.stringify([{
      trackingNumbers: ['TH1234567890'],
      data: { success: true, data: [], errors: [], meta: { total: 0, found: 0, notFound: 0 } },
      timestamp: Date.now(),
    }]));

    const { result } = renderHook(() => useTrackingPreferences());

    act(() => {
      result.current.clearLocalCache();
    });

    expect(localStorage.getItem('tracking_cache')).toBeNull();
  });
});

describe('useSingleTracking', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
  });

  it('should fetch single shipment', async () => {
    const mockShipment = {
      id: '1',
      trackingNumber: 'TH1234567890',
      status: 'delivered',
      events: [],
      exceptions: [],
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    mockTrackingApi.trackSingleShipment.mockResolvedValue(mockShipment);

    const { result } = renderHook(
      () => useSingleTracking('TH1234567890'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.shipment?.trackingNumber).toBe('TH1234567890');
    });
  });

  it('should return null for non-existent shipment', async () => {
    mockTrackingApi.trackSingleShipment.mockResolvedValue(null);

    const { result } = renderHook(
      () => useSingleTracking('TH9999999999'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.shipment).toBeNull();
    });
  });

  it('should not fetch when disabled', async () => {
    const { result } = renderHook(
      () => useSingleTracking('TH1234567890', { enabled: false }),
      { wrapper: createWrapper() }
    );

    // Wait for any potential async operations
    await waitFor(() => {
      expect(mockTrackingApi.trackSingleShipment).not.toHaveBeenCalled();
    });
  });
});

describe('useLazyShipment', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
  });

  it('should not fetch until loadShipment is called', async () => {
    const { result } = renderHook(
      () => useLazyShipment('TH1234567890'),
      { wrapper: createWrapper() }
    );

    // Wait for any potential async operations
    await waitFor(() => {
      expect(mockTrackingApi.trackSingleShipment).not.toHaveBeenCalled();
    });
  });

  it('should fetch when loadShipment is called', async () => {
    const mockShipment = {
      id: '1',
      trackingNumber: 'TH1234567890',
      status: 'in_transit',
      events: [],
      exceptions: [],
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    mockTrackingApi.trackSingleShipment.mockResolvedValue(mockShipment);

    const { result } = renderHook(
      () => useLazyShipment('TH1234567890'),
      { wrapper: createWrapper() }
    );

    act(() => {
      result.current.loadShipment();
    });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.shipment?.trackingNumber).toBe('TH1234567890');
    });
  });
});

describe('usePrefetchShipments', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
  });

  it('should prefetch multiple shipments', async () => {
    const mockResponse = {
      success: true,
      data: [],
      errors: [],
      meta: { total: 0, found: 0, notFound: 0 },
    };

    mockTrackingApi.trackShipments.mockResolvedValue(mockResponse);

    const { result } = renderHook(
      () => usePrefetchShipments(),
      { wrapper: createWrapper() }
    );

    await act(async () => {
      await result.current.prefetch(['TH1234567890', 'TH1234567891']);
    });

    expect(mockTrackingApi.trackShipments).toHaveBeenCalledWith(['TH1234567890', 'TH1234567891']);
  });

  it('should prefetch single shipment', async () => {
    mockTrackingApi.trackSingleShipment.mockResolvedValue(null);

    const { result } = renderHook(
      () => usePrefetchShipments(),
      { wrapper: createWrapper() }
    );

    await act(async () => {
      await result.current.prefetchSingle('TH1234567890');
    });

    expect(mockTrackingApi.trackSingleShipment).toHaveBeenCalledWith('TH1234567890');
  });
});

describe('localStorage caching', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
  });

  it('should cache responses in localStorage when enabled', async () => {
    // Enable local cache
    localStorage.setItem('tracking_preferences', JSON.stringify({ enableLocalCache: true, enableHistory: true }));

    const mockResponse = {
      success: true,
      data: [{ id: '1', trackingNumber: 'TH1234567890', events: [], exceptions: [], createdAt: new Date(), updatedAt: new Date() }],
      errors: [],
      meta: { total: 1, found: 1, notFound: 0 },
    };

    mockTrackingApi.trackShipments.mockResolvedValue(mockResponse);

    const { result } = renderHook(() => useTracking(), { wrapper: createWrapper() });

    await act(async () => {
      await result.current.trackShipments(['TH1234567890']);
    });

    await waitFor(() => {
      expect(result.current.shipments).toHaveLength(1);
    });

    // Check localStorage - note: caching happens on successful response
    // The cache key is set after the query completes
    await waitFor(() => {
      const cached = localStorage.getItem('tracking_cache');
      expect(cached).not.toBeNull();
    }, { timeout: 3000 });
  });

  it('should not cache when local cache is disabled', async () => {
    // Disable local cache
    localStorage.setItem('tracking_preferences', JSON.stringify({ enableLocalCache: false, enableHistory: true }));

    const mockResponse = {
      success: true,
      data: [{ id: '1', trackingNumber: 'TH1234567890', events: [], exceptions: [], createdAt: new Date(), updatedAt: new Date() }],
      errors: [],
      meta: { total: 1, found: 1, notFound: 0 },
    };

    mockTrackingApi.trackShipments.mockResolvedValue(mockResponse);

    const { result } = renderHook(() => useTracking(), { wrapper: createWrapper() });

    await act(async () => {
      await result.current.trackShipments(['TH1234567890']);
    });

    await waitFor(() => {
      expect(result.current.shipments).toHaveLength(1);
    });

    // Check localStorage - should be null or empty
    const cached = localStorage.getItem('tracking_cache');
    expect(cached).toBeNull();
  });
});
