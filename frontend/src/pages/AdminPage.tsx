import React from 'react';
import { useTranslation } from 'react-i18next';

const AdminPage: React.FC = () => {
  const { t } = useTranslation();

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="text-center">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">
          {t('nav.admin', 'Admin Console')}
        </h1>
        
        <div className="bg-white rounded-lg shadow-md p-6">
          <p className="text-gray-600">
            Admin console functionality will be implemented in future tasks.
          </p>
        </div>
      </div>
    </div>
  );
};

export default AdminPage;