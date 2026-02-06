import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import Timeline from '../Timeline';
import { TrackingEvent } from '../../../types/tracking.types';

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

const mockEvents: TrackingEvent[] = [
  {
    id: '1',
    eventTime: new Date('2024-01-10T08:00:00Z'),
    eventCode: 'in_transit',
    description: 'Package is in transit',
    descriptionEn: 'Package is in transit',
    descriptionTh: 'พัสดุอยู่ระหว่างขนส่ง',
    source: 'system',
    facility: {
      id: '1',
      name: 'Bangkok Hub',
      nameEn: 'Bangkok Hub',
      nameTh: 'ศูนย์คัดแยกกรุงเทพฯ',
    },
  },
  {
    id: '2',
    eventTime: new Date('2024-01-09T14:00:00Z'),
    eventCode: 'picked_up',
    description: 'Package picked up',
    descriptionEn: 'Package picked up',
    descriptionTh: 'รับพัสดุแล้ว',
    source: 'system',
    remarks: 'Picked up from sender',
  },
];

describe('Timeline', () => {
  it('renders events in reverse chronological order', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    const eventElements = screen.getAllByText(/Package/);
    expect(eventElements[0]).toHaveTextContent('Package is in transit');
    expect(eventElements[1]).toHaveTextContent('Package picked up');
  });

  it('displays event times correctly', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    expect(screen.getByText(/Jan 10, 2024/)).toBeInTheDocument();
    expect(screen.getByText(/Jan 9, 2024/)).toBeInTheDocument();
  });

  it('shows facility information when available', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    expect(screen.getByText('Bangkok Hub')).toBeInTheDocument();
  });

  it('shows remarks when available', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    expect(screen.getByText('Picked up from sender')).toBeInTheDocument();
  });

  it('toggles between UTC and local time', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    const toggleButton = screen.getByText(/Show UTC/);
    fireEvent.click(toggleButton);

    expect(screen.getByText(/Show Local Time/)).toBeInTheDocument();
  });

  it('handles empty events array', () => {
    renderWithI18n(<Timeline events={[]} />);

    expect(screen.getByText(/No tracking events available/)).toBeInTheDocument();
  });

  it('displays event icons correctly', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    const timelineItems = screen.getAllByRole('listitem');
    expect(timelineItems).toHaveLength(2);
  });

  it('has proper accessibility attributes', () => {
    renderWithI18n(<Timeline events={mockEvents} />);

    const timeline = screen.getByRole('list');
    expect(timeline).toHaveAttribute('aria-label', 'Shipment timeline');
  });
});