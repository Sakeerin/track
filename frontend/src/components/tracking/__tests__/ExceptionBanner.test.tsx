import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import ExceptionBanner from '../ExceptionBanner';
import { ShipmentException } from '../../../types/tracking.types';

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

const mockException: ShipmentException = {
  id: '1',
  type: 'address_issue',
  message: 'Address not found',
  messageEn: 'Address not found',
  messageTh: 'ไม่พบที่อยู่',
  severity: 'high',
  resolved: false,
  guidance: 'Please contact the recipient to verify the delivery address.',
  guidanceEn: 'Please contact the recipient to verify the delivery address.',
  guidanceTh: 'กรุณาติดต่อผู้รับเพื่อยืนยันที่อยู่จัดส่ง',
  createdAt: new Date('2024-01-10T09:00:00Z'),
};

describe('ExceptionBanner', () => {
  it('renders exception message', () => {
    renderWithI18n(<ExceptionBanner exception={mockException} />);

    expect(screen.getByText('Address not found')).toBeInTheDocument();
    expect(screen.getByText(/Shipment Issue/)).toBeInTheDocument();
  });

  it('shows severity indicator for high severity', () => {
    renderWithI18n(<ExceptionBanner exception={mockException} />);

    const banner = screen.getByRole('alert');
    expect(banner).toHaveClass('border-red-200', 'bg-red-50');
  });

  it('shows severity indicator for medium severity', () => {
    const mediumException = { ...mockException, severity: 'medium' as const };
    renderWithI18n(<ExceptionBanner exception={mediumException} />);

    const banner = screen.getByRole('alert');
    expect(banner).toHaveClass('border-yellow-200', 'bg-yellow-50');
  });

  it('shows severity indicator for low severity', () => {
    const lowException = { ...mockException, severity: 'low' as const };
    renderWithI18n(<ExceptionBanner exception={lowException} />);

    const banner = screen.getByRole('alert');
    expect(banner).toHaveClass('border-blue-200', 'bg-blue-50');
  });

  it('toggles guidance visibility', () => {
    renderWithI18n(<ExceptionBanner exception={mockException} />);

    expect(screen.queryByText(/Please contact the recipient/)).not.toBeInTheDocument();

    const showMoreButton = screen.getByText(/Show More/);
    fireEvent.click(showMoreButton);

    expect(screen.getByText(/Please contact the recipient/)).toBeInTheDocument();
    expect(screen.getByText(/Show Less/)).toBeInTheDocument();
  });

  it('shows contact support button', () => {
    renderWithI18n(<ExceptionBanner exception={mockException} />);

    expect(screen.getByText(/Contact Support/)).toBeInTheDocument();
  });

  it('handles resolved exceptions', () => {
    const resolvedException = { ...mockException, resolved: true };
    renderWithI18n(<ExceptionBanner exception={resolvedException} />);

    const banner = screen.getByRole('alert');
    expect(banner).toHaveClass('border-green-200', 'bg-green-50');
  });

  it('handles exception without guidance', () => {
    const exceptionWithoutGuidance = { 
      ...mockException, 
      guidance: undefined,
      guidanceEn: undefined,
      guidanceTh: undefined
    };
    renderWithI18n(<ExceptionBanner exception={exceptionWithoutGuidance} />);

    expect(screen.queryByText(/Show More/)).not.toBeInTheDocument();
  });

  it('has proper accessibility attributes', () => {
    renderWithI18n(<ExceptionBanner exception={mockException} />);

    const banner = screen.getByRole('alert');
    expect(banner).toHaveAttribute('aria-live', 'polite');
  });

  it('applies custom className', () => {
    renderWithI18n(<ExceptionBanner exception={mockException} className="custom-class" />);

    const banner = screen.getByRole('alert');
    expect(banner).toHaveClass('custom-class');
  });
});