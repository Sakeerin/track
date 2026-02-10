import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { format, formatDistanceToNow } from 'date-fns';
import { enUS, th } from 'date-fns/locale';
import toast from 'react-hot-toast';
import { Shipment, ShipmentStatus } from '../../types/tracking.types';
import Timeline from './Timeline';
import ProgressBar from './ProgressBar';
import ExceptionBanner from './ExceptionBanner';
import ShipmentMap from './ShipmentMap';

interface ShipmentCardProps {
  shipment: Shipment;
  showTimeline?: boolean;
  showMap?: boolean;
  locale?: 'th' | 'en';
  className?: string;
}

const ShipmentCard: React.FC<ShipmentCardProps> = ({
  shipment,
  showTimeline = true,
  showMap = false,
  locale = 'en',
  className = '',
}) => {
  const { t, i18n } = useTranslation();
  const [isExpanded, setIsExpanded] = useState(false);
  const [showUTC, setShowUTC] = useState(false);

  const dateLocale = locale === 'th' ? th : enUS;
  const currentLang = i18n.language || locale;

  // Get status badge styling
  const getStatusBadgeClass = (status: ShipmentStatus): string => {
    const baseClass = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';
    
    switch (status) {
      case 'delivered':
        return `${baseClass} bg-green-100 text-green-800`;
      case 'out_for_delivery':
        return `${baseClass} bg-blue-100 text-blue-800`;
      case 'exception':
        return `${baseClass} bg-red-100 text-red-800`;
      case 'returned':
        return `${baseClass} bg-gray-100 text-gray-800`;
      case 'cancelled':
        return `${baseClass} bg-gray-100 text-gray-800`;
      default:
        return `${baseClass} bg-yellow-100 text-yellow-800`;
    }
  };

  // Format date based on locale and UTC preference
  const formatDate = (date: Date, formatStr: string = 'PPp'): string => {
    try {
      return format(date, formatStr, { locale: dateLocale });
    } catch {
      return date.toLocaleString();
    }
  };

  // Get relative time
  const getRelativeTime = (date: Date): string => {
    try {
      return formatDistanceToNow(date, { 
        addSuffix: true, 
        locale: dateLocale 
      });
    } catch {
      return '';
    }
  };

  // Get location name based on locale
  const getLocationName = (location: any): string => {
    if (!location) return '';
    
    if (currentLang === 'th' && location.nameTh) {
      return location.nameTh;
    }
    
    return location.nameEn || location.name || '';
  };

  // Get description based on locale
  const getEventDescription = (event: any): string => {
    if (currentLang === 'th' && event.descriptionTh) {
      return event.descriptionTh;
    }
    
    return event.descriptionEn || event.description || '';
  };

  const hasExceptions = shipment.exceptions && shipment.exceptions.length > 0;
  const unresolvedExceptions = shipment.exceptions?.filter(ex => !ex.resolved) || [];
  const latestEvent = shipment.events[0];

  const handleShareLink = async (): Promise<void> => {
    const url = `${window.location.origin}/track/${shipment.trackingNumber}`;

    try {
      await navigator.clipboard.writeText(url);
      toast.success(t('shipment.shareCopied', 'Share link copied'));
    } catch {
      toast.error(t('shipment.shareCopyFailed', 'Unable to copy share link'));
    }
  };

  return (
    <div className={`bg-white rounded-lg shadow-md overflow-hidden ${className}`}>
      {/* Exception Banner */}
      {hasExceptions && unresolvedExceptions.length > 0 && (
        <ExceptionBanner 
          exceptions={unresolvedExceptions} 
          locale={locale}
        />
      )}

      <div className="p-6">
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex-1">
            <h3 className="text-lg font-semibold text-gray-900 mb-1">
              {shipment.trackingNumber}
            </h3>
            <div className="flex items-center space-x-4 text-sm text-gray-600">
              <span>{shipment.serviceType}</span>
              {shipment.referenceNumber && (
                <span>
                  {t('shipment.reference', 'Ref')}: {shipment.referenceNumber}
                </span>
              )}
            </div>
          </div>
          
          <div className="flex items-center space-x-2">
            <span className={getStatusBadgeClass(shipment.status)}>
              {t(`status.${shipment.status}`, shipment.status)}
            </span>
            <button
              type="button"
              onClick={handleShareLink}
              className="text-xs px-2.5 py-1 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              {t('shipment.share', 'Share')}
            </button>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="mb-4">
          <ProgressBar 
            status={shipment.status}
            events={shipment.events}
            locale={locale}
          />
        </div>

        {/* Shipment Details Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          {shipment.origin && (
            <div>
              <dt className="text-sm font-medium text-gray-500 mb-1">
                {t('shipment.origin', 'Origin')}
              </dt>
              <dd className="text-sm text-gray-900">
                {getLocationName(shipment.origin)}
              </dd>
            </div>
          )}
          
          {shipment.destination && (
            <div>
              <dt className="text-sm font-medium text-gray-500 mb-1">
                {t('shipment.destination', 'Destination')}
              </dt>
              <dd className="text-sm text-gray-900">
                {getLocationName(shipment.destination)}
              </dd>
            </div>
          )}
          
          {shipment.estimatedDelivery && (
            <div>
              <dt className="text-sm font-medium text-gray-500 mb-1">
                {t('shipment.estimatedDelivery', 'Estimated Delivery')}
              </dt>
              <dd className="text-sm text-gray-900">
                {formatDate(shipment.estimatedDelivery, 'PPp')}
              </dd>
            </div>
          )}
          
          {shipment.currentLocation && (
            <div>
              <dt className="text-sm font-medium text-gray-500 mb-1">
                {t('shipment.currentLocation', 'Current Location')}
              </dt>
              <dd className="text-sm text-gray-900">
                {getLocationName(shipment.currentLocation)}
              </dd>
            </div>
          )}
        </div>

        {/* Latest Event Summary */}
        {latestEvent && (
          <div className="border-t border-gray-200 pt-4 mb-4">
            <h4 className="text-sm font-medium text-gray-900 mb-2">
              {t('shipment.latestUpdate', 'Latest Update')}
            </h4>
            <div className="text-sm text-gray-600">
              <p className="mb-1">{getEventDescription(latestEvent)}</p>
              <div className="flex items-center justify-between">
                <p className="text-xs text-gray-500">
                  {showUTC 
                    ? `${formatDate(latestEvent.eventTime)} UTC`
                    : formatDate(latestEvent.eventTime)
                  }
                  {latestEvent.facility && ` • ${getLocationName(latestEvent.facility)}`}
                </p>
                <p className="text-xs text-gray-500">
                  {getRelativeTime(latestEvent.eventTime)}
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Timeline Toggle */}
        {showTimeline && shipment.events.length > 1 && (
          <div className="border-t border-gray-200 pt-4">
            <div className="flex items-center justify-between mb-3">
              <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:underline"
                aria-expanded={isExpanded}
                aria-controls={`timeline-${shipment.id}`}
              >
                <span>
                  {isExpanded 
                    ? t('shipment.hideTimeline', 'Hide Timeline')
                    : t('shipment.showTimeline', 'Show Full Timeline')
                  }
                </span>
                <svg 
                  className={`ml-1 h-4 w-4 transform transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                  fill="none" 
                  viewBox="0 0 24 24" 
                  stroke="currentColor"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              
              {isExpanded && (
                <button
                  onClick={() => setShowUTC(!showUTC)}
                  className="text-xs text-gray-500 hover:text-gray-700 focus:outline-none focus:underline"
                >
                  {showUTC 
                    ? t('shipment.showLocalTime', 'Show Local Time')
                    : t('shipment.showUTC', 'Show UTC')
                  }
                </button>
              )}
            </div>
            
            {isExpanded && (
              <div id={`timeline-${shipment.id}`}>
                <Timeline 
                  events={shipment.events}
                  locale={locale}
                  showUTC={showUTC}
                />
              </div>
            )}
          </div>
        )}

        {/* Map */}
        {showMap && (
          <div className="border-t border-gray-200 pt-4 mt-4">
            <ShipmentMap 
              shipment={shipment}
              locale={locale}
              height="300px"
            />
          </div>
        )}
      </div>
    </div>
  );
};

export default ShipmentCard;
