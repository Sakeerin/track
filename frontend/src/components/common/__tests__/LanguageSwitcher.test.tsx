import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import LanguageSwitcher from '../LanguageSwitcher';

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

describe('LanguageSwitcher', () => {
  beforeEach(() => {
    // Reset language to English before each test
    i18n.changeLanguage('en');
  });

  it('renders with current language', () => {
    renderWithI18n(<LanguageSwitcher />);

    expect(screen.getByText('English')).toBeInTheDocument();
    expect(screen.getByRole('button')).toHaveAttribute('aria-expanded', 'false');
  });

  it('opens dropdown when clicked', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);

    expect(button).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByText('ไทย')).toBeInTheDocument();
  });

  it('changes language when option is selected', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);

    const thaiOption = screen.getByText('ไทย');
    fireEvent.click(thaiOption);

    expect(i18n.language).toBe('th');
  });

  it('closes dropdown after language selection', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);
    
    const thaiOption = screen.getByText('ไทย');
    fireEvent.click(thaiOption);

    expect(button).toHaveAttribute('aria-expanded', 'false');
  });

  it('closes dropdown when clicking outside', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);
    expect(button).toHaveAttribute('aria-expanded', 'true');

    fireEvent.mouseDown(document.body);
    expect(button).toHaveAttribute('aria-expanded', 'false');
  });

  it('closes dropdown on escape key', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);
    expect(button).toHaveAttribute('aria-expanded', 'true');

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(button).toHaveAttribute('aria-expanded', 'false');
  });

  it('shows current language as selected', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);

    const englishOption = screen.getByRole('menuitem', { name: /English/ });
    expect(englishOption).toHaveClass('bg-blue-50', 'text-blue-700');
  });

  it('displays language icons', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    const languageIcon = button.querySelector('svg');
    expect(languageIcon).toBeInTheDocument();
  });

  it('has proper accessibility attributes', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    expect(button).toHaveAttribute('aria-haspopup', 'true');
    expect(button).toHaveAttribute('aria-label');

    fireEvent.click(button);

    const menu = screen.getByRole('menu');
    expect(menu).toHaveAttribute('aria-orientation', 'vertical');

    const menuItems = screen.getAllByRole('menuitem');
    expect(menuItems).toHaveLength(2);
  });

  it('applies custom className', () => {
    renderWithI18n(<LanguageSwitcher className="custom-class" />);

    const container = screen.getByRole('button').closest('div');
    expect(container).toHaveClass('custom-class');
  });

  it('shows both native and English names for languages', () => {
    renderWithI18n(<LanguageSwitcher />);

    const button = screen.getByRole('button');
    fireEvent.click(button);

    expect(screen.getByText('English')).toBeInTheDocument();
    expect(screen.getByText('ไทย')).toBeInTheDocument();
    expect(screen.getByText('Thai')).toBeInTheDocument();
  });
});