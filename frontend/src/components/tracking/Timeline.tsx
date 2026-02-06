import React from 'react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { enUS, th } from 'date-fns/locale';
import { TrackingEvent } from '../../types/tracking.types';

interface TimelineProps {
  events: TrackingEvent[];
  locale?: 'th' | 'en';
  showUTC?: boolean;
  className?: string;
}

const Timeline: React.FC<TimelineProps> = ({
  events,
  locale = 'en',
  showUTC = false,
  className = '',
}) => {
  const { t, i18n } = useTranslation();
  
  const dateLocale = locale === 'th' ? th : enUS;
  const currentLang = i18n.language || locale;

  // Format date based on locale and UTC preference
  const formatDate = (date: Date): string => {
    try {
      const formatStr = 'PPp'; // e.g., "Jan 1, 2024 at 2:30 PM"
      return format(showUTC ? new Date(date.getTime() + date.getTimezoneOffset() * 60000) : date, formatStr, { 
        locale: dateLocale 
      });
    } catch {
      return date.toLocaleString();
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
  const getEventDescription = (event: TrackingEvent): string => {
    if (currentLang === 'th' && event.descriptionTh) {
      return event.descriptionTh;
    }
    
    return event.descriptionEn || event.description || '';
  };

  // Get event icon based on event code
  const getEventIcon = (eventCode: string): React.ReactNode => {
    const iconClass = "w-4 h-4";
    
    switch (eventCode.toLowerCase()) {
      case 'created':
      case 'order_created':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
          </svg>
        );
      
      case 'picked_up':
      case 'pickup':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.414 7H14z" />
          </svg>
        );
      
      case 'in_transit':
      case 'departed':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clipRule="evenodd" />
          </svg>
        );
      
      case 'at_hub':
      case 'arrived':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" />
          </svg>
        );
      
      case 'out_for_delivery':
      case 'delivery':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.414 7H14z" />
          </svg>
        );
      
      case 'delivered':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
          </svg>
        );
      
      case 'exception':
      case 'failed':
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
        );
      
      default:
        return (
          <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clipRule="evenodd" />
          </svg>
        );
    }
  };

  // Get event color based on event code
  const getEventColor = (eventCode: string): string => {
    switch (eventCode.toLowerCase()) {
      case 'delivered':
        return 'text-green-600 bg-green-100';
      case 'out_for_delivery':
        return 'text-blue-600 bg-blue-100';
      case 'exception':
      case 'failed':
        return 'text-red-600 bg-red-100';
      case 'returned':
      case 'cancelled':
        return 'text-gray-600 bg-gray-100';
      default:
        return 'text-yellow-600 bg-yellow-100';
    }
  };

  if (!events || events.length === 0) {
    return (
      <div className={`text-center py-4 text-gray-500 ${className}`}>
        <p>{t('timeline.noEvents', 'No tracking events available')}</p>
      </div>
    );
  }

  return (
    <div className={`timeline ${className}`}>
      <div className="flow-root">
        <ul className="-mb-8" role="list">
          {events.map((event, eventIdx) => (
            <li key={event.id}>
              <div className="relative pb-8">
                {eventIdx !== events.length - 1 && (
                  <span 
                    className="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" 
                    aria-hidden="true" 
                  />
                )}
                
                <div className="relative flex space-x-3">
                  {/* Event Icon */}
                  <div>
                    <span className={`
                      h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white
                      ${getEventColor(event.eventCode)}
                    `}>
                      {getEventIcon(event.eventCode)}
                    </span>
                  </div>
                  
                  {/* Event Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <p className="text-sm font-medium text-gray-900">
                          {getEventDescription(event)}
                        </p>
                        
                        {/* Location and Facility */}
                        <div className="mt-1 text-sm text-gray-600">
                          {event.facility && (
                            <p>{getLocationName(event.facility)}</p>
                          )}
                          {event.location && event.location !== event.facility && (
                            <p>{getLocationName(event.location)}</p>
                          )}
                        </div>
                        
                        {/* Remarks */}
                        {event.remarks && (
                          <p className="mt-1 text-sm text-gray-500 italic">
                            {event.remarks}
                          </p>
                        )}
                      </div>
                      
                      {/* Timestamp */}
                      <div className="text-right flex-shrink-0 ml-4">
                        <p className="text-sm text-gray-900">
                          {formatDate(event.eventTime)}
                        </p>
                        {showUTC && (
                          <p className="text-xs text-gray-500 mt-1">
                            UTC
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
};

export default Timeline;