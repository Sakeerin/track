import React from 'react';
import { useTranslation } from 'react-i18next';

const LanguageSwitcher: React.FC = () => {
  const { i18n, t } = useTranslation();

  const toggleLanguage = () => {
    const newLang = i18n.language === 'en' ? 'th' : 'en';
    i18n.changeLanguage(newLang);
    document.documentElement.lang = newLang;
  };

  return (
    <button
      onClick={toggleLanguage}
      className="flex items-center px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-900 rounded-md border border-gray-300 hover:border-gray-400 transition-colors"
      aria-label={t('common.language')}
    >
      <span className="mr-2">🌐</span>
      {i18n.language === 'en' ? 'ไทย' : 'EN'}
    </button>
  );
};

export default LanguageSwitcher;