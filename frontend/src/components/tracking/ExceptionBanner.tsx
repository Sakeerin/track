import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Exception } from '../../types/tracking.types';

interface ExceptionBannerProps {
  exceptions: Exception[];
  locale?: 'th' | 'en';
  className?: string;
}

const ExceptionBanner: React.FC<ExceptionBannerProps> = ({
  exceptions,
  locale = 'en',
  className = '',
}) => {
  const { t, i18n } = useTranslation();
  const [isExpanded, setIsExpanded] = useState(false);
  
  const currentLang = i18n.language || locale;

  // Get message based on locale
  const getMessage = (exception: Exception): string => {
    if (currentLang === 'th' && exception.messageTh) {
      return exception.messageTh;
    }
    
    return exception.messageEn || exception.message || '';
  };

  // Get guidance based on locale
  const getGuidance = (exception: Exception): string => {
    if (currentLang === 'th' && exception.guidanceTh) {
      return exception.guidanceTh;
    }
    
    return exception.guidanceEn || exception.guidance || '';
  };

  // Get severity styling
  const getSeverityClass = (severity: Exception['severity']): string => {
    switch (severity) {
      case 'critical':
        return 'bg-red-50 border-red-200 text-red-800';
      case 'high':
        return 'bg-orange-50 border-orange-200 text-orange-800';
      case 'medium':
        return 'bg-yellow-50 border-yellow-200 text-yellow-800';
      case 'low':
        return 'bg-blue-50 border-blue-200 text-blue-800';
      default:
        return 'bg-gray-50 border-gray-200 text-gray-800';
    }
  };

  // Get severity icon
  const getSeverityIcon = (severity: Exception['severity']): React.ReactNode => {
    const iconClass = "w-5 h-5 flex-shrink-0";
    
    switch (severity) {
      case 'critical':
        return (
          <svg className={`${iconClass} text-red-500`} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
        );
      case 'high':
        return (
          <svg className={`${iconClass} text-orange-500`} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
        );
      case 'medium':
        return (
          <svg className={`${iconClass} text-yellow-500`} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
        );
      case 'low':
        return (
          <svg className={`${iconClass} text-blue-500`} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
        );
      default:
        return (
          <svg className={`${iconClass} text-gray-500`} fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
        );
    }
  };

  if (!exceptions || exceptions.length === 0) {
    return null;
  }

  // Sort exceptions by severity (critical first)
  const sortedExceptions = [...exceptions].sort((a, b) => {
    const severityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
    return severityOrder[a.severity] - severityOrder[b.severity];
  });

  const primaryException = sortedExceptions[0];
  const hasMultipleExceptions = sortedExceptions.length > 1;

  return (
    <div className={`exception-banner ${getSeverityClass(primaryException.severity)} border-l-4 ${className}`}>
      <div className="p-4">
        <div className="flex items-start">
          {getSeverityIcon(primaryException.severity)}
          
          <div className="ml-3 flex-1">
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <h3 className="text-sm font-medium">
                  {t('exception.title', 'Shipment Issue')}
                  {hasMultipleExceptions && (
                    <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white bg-opacity-50">
                      {sortedExceptions.length}
                    </span>
                  )}
                </h3>
                
                <div className="mt-1">
                  <p className="text-sm">
                    {getMessage(primaryException)}
                  </p>
                  
                  {getGuidance(primaryException) && (
                    <p className="mt-2 text-sm font-medium">
                      {t('exception.guidance', 'What to do')}: {getGuidance(primaryException)}
                    </p>
                  )}
                </div>
              </div>
              
              {hasMultipleExceptions && (
                <button
                  onClick={() => setIsExpanded(!isExpanded)}
                  className="ml-4 text-sm underline hover:no-underline focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 rounded"
                  aria-expanded={isExpanded}
                  aria-controls="additional-exceptions"
                >
                  {isExpanded 
                    ? t('exception.showLess', 'Show Less')
                    : t('exception.showMore', 'Show More')
                  }
                </button>
              )}
            </div>
            
            {/* Additional exceptions */}
            {hasMultipleExceptions && isExpanded && (
              <div id="additional-exceptions" className="mt-4 space-y-3">
                {sortedExceptions.slice(1).map((exception, index) => (
                  <div key={exception.id} className="border-t border-current border-opacity-20 pt-3">
                    <div className="flex items-start">
                      {getSeverityIcon(exception.severity)}
                      <div className="ml-3 flex-1">
                        <p className="text-sm">
                          {getMessage(exception)}
                        </p>
                        {getGuidance(exception) && (
                          <p className="mt-1 text-sm font-medium">
                            {t('exception.guidance', 'What to do')}: {getGuidance(exception)}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
            
            {/* Contact support link */}
            <div className="mt-3">
              <button className="text-sm underline hover:no-underline focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 rounded">
                {t('exception.contactSupport', 'Contact Support')}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ExceptionBanner;