import React from 'react';
import { useTranslation } from 'react-i18next';

const TrackingPage: React.FC = () => {
  const { t } = useTranslation();

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="text-center">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">
          {t('tracking.title', 'Track Your Parcels')}
        </h1>
        
        <div className="max-w-md mx-auto">
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-gray-600 mb-4">
              {t('tracking.description', 'Enter up to 20 tracking numbers to get real-time updates on your shipments.')}
            </p>
            
            <div className="space-y-4">
              <textarea
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                rows={4}
                placeholder={t('tracking.placeholder', 'Enter tracking numbers (one per line)')}
              />
              
              <button className="w-full btn-primary">
                {t('tracking.submit', 'Track Parcels')}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TrackingPage;