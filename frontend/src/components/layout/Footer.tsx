import React from 'react';
import { useTranslation } from 'react-i18next';

const Footer: React.FC = () => {
  const { t } = useTranslation();

  return (
    <footer className="bg-white border-t border-gray-200">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="flex flex-col md:flex-row justify-between items-center">
          <div className="text-sm text-gray-500 mb-4 md:mb-0">
            {t('footer.copyright')}
          </div>
          
          <div className="flex space-x-6 text-sm">
            <a href="/privacy" className="text-gray-500 hover:text-gray-900">
              {t('footer.privacy')}
            </a>
            <a href="/terms" className="text-gray-500 hover:text-gray-900">
              {t('footer.terms')}
            </a>
            <a href="/contact" className="text-gray-500 hover:text-gray-900">
              {t('footer.contact')}
            </a>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;