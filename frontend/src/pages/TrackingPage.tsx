import React, { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams } from 'react-router-dom';
import TrackingForm from '../components/tracking/TrackingForm';
import ShipmentCard from '../components/tracking/ShipmentCard';
import BulkView from '../components/tracking/BulkView';
import { SkeletonCard } from '../components/common/SkeletonLoader';
import { 
  useTracking, 
  useSingleTracking, 
  useTrackingHistory,
  useTrackingPreferences,
  usePrefetchShipments 
} from '../hooks/useTracking';

type ViewMode = 'cards' | 'table';

const TrackingPage: React.FC = () => {
  const { t } = useTranslation();
  const { trackingNumber } = useParams<{ trackingNumber?: string }>();
  const { addToHistory, getHistory, isEnabled: historyEnabled } = useTrackingHistory();
  const { preferences, toggleLocalCache, toggleHistory, clearLocalCache } = useTrackingPreferences();
  const { prefetch } = usePrefetchShipments();
  
  const [hasSubmitted, setHasSubmitted] = useState(false);
  const [viewMode, setViewMode] = useState<ViewMode>('cards');
  const [showSettings, setShowSettings] = useState(false);
  
  // For single tracking number from URL
  const singleTracking = useSingleTracking(trackingNumber || '', {
    enabled: Boolean(trackingNumber),
  });
  
  // For multiple tracking numbers from form
  const multiTracking = useTracking([], {
    enabled: hasSubmitted && !trackingNumber,
  });

  const handleTrackingSubmit = useCallback(async (trackingNumbers: string[]) => {
    setHasSubmitted(true);
    if (historyEnabled) {
      addToHistory(trackingNumbers);
    }
    await multiTracking.trackShipments(trackingNumbers);
  }, [historyEnabled, addToHistory, multiTracking]);

  // Prefetch on hover for history items
  const handleHistoryItemHover = useCallback((trackingNumbers: string[]) => {
    prefetch(trackingNumbers);
  }, [prefetch]);

  const isLoading = trackingNumber ? singleTracking.isLoading : multiTracking.isLoading;
  const isError = trackingNumber ? singleTracking.isError : multiTracking.isError;
  const error = trackingNumber ? singleTracking.error : multiTracking.error;
  const shipments = trackingNumber 
    ? (singleTracking.shipment ? [singleTracking.shipment] : [])
    : multiTracking.shipments;

  const showViewToggle = !trackingNumber && shipments.length > 1;
  const history = getHistory();

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="text-center mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          {t('tracking.title', 'Track Your Parcels')}
        </h1>
        <p className="text-lg text-gray-600 max-w-2xl mx-auto">
          {t('tracking.description', 'Enter up to 20 tracking numbers to get real-time updates on your shipments.')}
        </p>
      </div>
      
      {/* Show form only if not viewing single tracking number */}
      {!trackingNumber && (
        <div className="max-w-2xl mx-auto mb-8">
          <div className="bg-white rounded-lg shadow-md p-6">
            <TrackingForm
              onSubmit={handleTrackingSubmit}
              isLoading={isLoading}
              maxNumbers={20}
            />
            
            {/* Recent History */}
            {historyEnabled && history.length > 0 && (
              <div className="mt-4 pt-4 border-t border-gray-200">
                <h3 className="text-sm font-medium text-gray-700 mb-2">
                  {t('tracking.recentSearches', 'Recent Searches')}
                </h3>
                <div className="flex flex-wrap gap-2">
                  {history.slice(0, 5).map((num) => (
                    <button
                      key={num}
                      type="button"
                      onClick={() => handleTrackingSubmit([num])}
                      onMouseEnter={() => handleHistoryItemHover([num])}
                      className="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors"
                    >
                      {num}
                    </button>
                  ))}
                </div>
              </div>
            )}
            
            {/* Settings Toggle */}
            <div className="mt-4">
              <button
                type="button"
                onClick={() => setShowSettings(!showSettings)}
                className="text-sm text-gray-500 hover:text-gray-700 flex items-center"
              >
                <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {t('tracking.cacheSettings', 'Cache Settings')}
              </button>
              
              {showSettings && (
                <div className="mt-3 p-3 bg-gray-50 rounded-lg space-y-3">
                  <div className="flex items-center justify-between">
                    <label className="text-sm text-gray-700">
                      {t('tracking.enableLocalCache', 'Enable local caching')}
                    </label>
                    <button
                      type="button"
                      onClick={toggleLocalCache}
                      className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                        preferences.enableLocalCache ? 'bg-blue-600' : 'bg-gray-200'
                      }`}
                      role="switch"
                      aria-checked={preferences.enableLocalCache}
                    >
                      <span
                        className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                          preferences.enableLocalCache ? 'translate-x-6' : 'translate-x-1'
                        }`}
                      />
                    </button>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <label className="text-sm text-gray-700">
                      {t('tracking.enableHistory', 'Enable search history')}
                    </label>
                    <button
                      type="button"
                      onClick={toggleHistory}
                      className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                        preferences.enableHistory ? 'bg-blue-600' : 'bg-gray-200'
                      }`}
                      role="switch"
                      aria-checked={preferences.enableHistory}
                    >
                      <span
                        className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                          preferences.enableHistory ? 'translate-x-6' : 'translate-x-1'
                        }`}
                      />
                    </button>
                  </div>
                  
                  <button
                    type="button"
                    onClick={clearLocalCache}
                    className="text-sm text-red-600 hover:text-red-800"
                  >
                    {t('tracking.clearCache', 'Clear local cache')}
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
      
      {/* View Toggle */}
      {showViewToggle && (
        <div className="max-w-4xl mx-auto mb-6">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold text-gray-900">
              {t('tracking.results', 'Tracking Results')}
            </h2>
            <div className="flex items-center space-x-2">
              <span className="text-sm text-gray-500">
                {t('tracking.viewMode', 'View:')}
              </span>
              <div className="flex rounded-md shadow-sm" role="group">
                <button
                  type="button"
                  onClick={() => setViewMode('cards')}
                  className={`px-3 py-2 text-sm font-medium rounded-l-md border ${
                    viewMode === 'cards'
                      ? 'bg-blue-600 text-white border-blue-600'
                      : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                  } focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
                  aria-pressed={viewMode === 'cards'}
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                  <span className="ml-1">{t('tracking.cardView', 'Cards')}</span>
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('table')}
                  className={`px-3 py-2 text-sm font-medium rounded-r-md border-t border-r border-b ${
                    viewMode === 'table'
                      ? 'bg-blue-600 text-white border-blue-600'
                      : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                  } focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
                  aria-pressed={viewMode === 'table'}
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M3 14h18m-9-4v8m-7 0V4a1 1 0 011-1h3M3 20h18a1 1 0 001-1V4a1 1 0 00-1-1H3a1 1 0 00-1 1v16a1 1 0 001 1z" />
                  </svg>
                  <span className="ml-1">{t('tracking.tableView', 'Table')}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Loading state */}
      {isLoading && (
        <div className="max-w-4xl mx-auto">
          {!showViewToggle && (
            <div className="mb-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                {t('tracking.results', 'Tracking Results')}
              </h2>
            </div>
          )}
          <div className="space-y-4">
            {Array.from({ length: trackingNumber ? 1 : 3 }).map((_, index) => (
              <SkeletonCard key={index} />
            ))}
          </div>
        </div>
      )}
      
      {/* Error state */}
      {isError && error && (
        <div className="max-w-2xl mx-auto">
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <div className="flex items-start">
              <svg 
                className="w-5 h-5 text-red-400 mt-0.5 mr-3 flex-shrink-0" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path 
                  fillRule="evenodd" 
                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" 
                  clipRule="evenodd" 
                />
              </svg>
              <div>
                <h3 className="text-sm font-medium text-red-800">
                  {t('tracking.error', 'An error occurred while tracking your parcels')}
                </h3>
                <p className="mt-1 text-sm text-red-700">
                  {error.message}
                </p>
                <button
                  onClick={() => trackingNumber ? singleTracking.refetch() : multiTracking.refetch()}
                  className="mt-2 text-sm text-red-800 underline hover:text-red-900"
                >
                  {t('common.retry', 'Retry')}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* No results */}
      {!isLoading && !isError && hasSubmitted && shipments.length === 0 && (
        <div className="max-w-2xl mx-auto">
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex items-start">
              <svg 
                className="w-5 h-5 text-yellow-400 mt-0.5 mr-3 flex-shrink-0" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path 
                  fillRule="evenodd" 
                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" 
                  clipRule="evenodd" 
                />
              </svg>
              <div>
                <h3 className="text-sm font-medium text-yellow-800">
                  {t('tracking.noResults', 'No tracking information found')}
                </h3>
                <p className="mt-1 text-sm text-yellow-700">
                  Please check your tracking numbers and try again.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Results */}
      {!isLoading && !isError && shipments.length > 0 && (
        <div className="max-w-7xl mx-auto">
          {!showViewToggle && (
            <div className="mb-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                {t('tracking.results', 'Tracking Results')}
              </h2>
              <p className="text-gray-600">
                {t('tracking.resultsCount', {
                  count: shipments.length,
                  defaultValue: `Found ${shipments.length} shipment(s)`,
                })}
              </p>
            </div>
          )}
          
          {viewMode === 'cards' ? (
            <div className="space-y-6">
              {shipments.map((shipment) => (
                <ShipmentCard 
                  key={shipment.id} 
                  shipment={shipment}
                  showTimeline={true}
                  showMap={false}
                />
              ))}
            </div>
          ) : (
            <BulkView 
              shipments={shipments}
              locale="en"
            />
          )}
        </div>
      )}
    </div>
  );
};

export default TrackingPage;
