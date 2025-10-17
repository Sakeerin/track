import React from 'react';
import { useTranslation } from 'react-i18next';
import Header from './Header';
import Footer from './Footer';

interface LayoutProps {
  children: React.ReactNode;
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
  const { i18n } = useTranslation();

  return (
    <div className={`min-h-screen bg-gray-50 ${i18n.language === 'th' ? 'font-thai' : 'font-sans'}`}>
      <Header />
      <main className="flex-1">
        {children}
      </main>
      <Footer />
    </div>
  );
};

export default Layout;