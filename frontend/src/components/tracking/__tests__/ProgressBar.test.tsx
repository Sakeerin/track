import React from 'react';
import { render, screen } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import ProgressBar from '../ProgressBar';

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

describe('ProgressBar', () => {
  it('renders progress bar with correct percentage', () => {
    renderWithI18n(<ProgressBar status="in_transit" />);

    const progressBar = screen.getByRole('progressbar');
    expect(progressBar).toHaveAttribute('aria-valuenow', '50');
    expect(progressBar).toHaveAttribute('aria-valuemin', '0');
    expect(progressBar).toHaveAttribute('aria-valuemax', '100');
  });

  it('displays correct progress for different statuses', () => {
    const { rerender } = renderWithI18n(<ProgressBar status="created" />);
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '10');

    rerender(
      <I18nextProvider i18n={i18n}>
        <ProgressBar status="picked_up" />
      </I18nextProvider>
    );
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '25');

    rerender(
      <I18nextProvider i18n={i18n}>
        <ProgressBar status="delivered" />
      </I18nextProvider>
    );
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100');
  });

  it('shows milestone labels', () => {
    renderWithI18n(<ProgressBar status="in_transit" />);

    expect(screen.getByText('Order Created')).toBeInTheDocument();
    expect(screen.getByText('Picked Up')).toBeInTheDocument();
    expect(screen.getByText('In Transit')).toBeInTheDocument();
    expect(screen.getByText('Out for Delivery')).toBeInTheDocument();
    expect(screen.getByText('Delivered')).toBeInTheDocument();
  });

  it('highlights active milestone', () => {
    renderWithI18n(<ProgressBar status="in_transit" />);

    const activeMilestone = screen.getByText('In Transit').closest('div');
    expect(activeMilestone).toHaveClass('text-blue-600');
  });

  it('handles exception status', () => {
    renderWithI18n(<ProgressBar status="exception" />);

    const progressBar = screen.getByRole('progressbar');
    expect(progressBar).toHaveClass('bg-red-200');
  });

  it('handles returned status', () => {
    renderWithI18n(<ProgressBar status="returned" />);

    const progressBar = screen.getByRole('progressbar');
    expect(progressBar).toHaveClass('bg-orange-200');
  });

  it('has proper accessibility attributes', () => {
    renderWithI18n(<ProgressBar status="in_transit" />);

    const progressBar = screen.getByRole('progressbar');
    expect(progressBar).toHaveAttribute('aria-label');
  });

  it('applies custom className', () => {
    renderWithI18n(<ProgressBar status="in_transit" className="custom-class" />);

    const container = screen.getByRole('progressbar').closest('.progress-bar');
    expect(container).toHaveClass('custom-class');
  });
});