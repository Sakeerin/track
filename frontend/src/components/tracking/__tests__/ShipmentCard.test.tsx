import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import ShipmentCard from '../ShipmentCard';
import { Shipment } from '../../../types/tracking.types';

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

const mockShipment: Shipment = {
  id: '1',
  trackingNumber: 'TH1234567890',
  referenceNumber: 'REF123',
  status: 'in_transit',
  serviceType: 'Standard',
  origin: {
    id: '1',
    name: 'Bangkok',
    nameEn: 'Bangkok',
    nameTh: 'กรุงเทพฯ',
  },
  destination: {
    id: '2',
    name: 'Chiang Mai',
    nameEn: 'Chiang Mai',
    nameTh: 'เชียงใหม่',
  },
  currentLocation: {
    id: '3',
    name: 'Ayutthaya Hub',
    nameEn: 'Ayutthaya Hub',
    nameTh: 'ศูนย์คัดแยกอยุธยา',
  },
  estimatedDelivery: new Date('2024-01-15T10:00:00Z'),
  events: [
    {
      id: '1',
      eventTime: new Date('2024-01-10T08:00:00Z'),
      eventCode: 'in_transit',
      description: 'Package is in transit',
      descriptionEn: 'Package is in transit',
      descriptionTh: 'พัสดุอยู่ระหว่างขนส่ง',
      source: 'system',
    },
    {
      id: '2',
      eventTime: new Date('2024-01-09T14:00:00Z'),
      eventCode: 'picked_up',
      description: 'Package picked up',
      descriptionEn: 'Package picked up',
      descriptionTh: 'รับพัสดุแล้ว',
      source: 'system',
    },
  ],
  exceptions: [],
  createdAt: new Date('2024-01-09T10:00:00Z'),
  updatedAt: new Date('2024-01-10T08:00:00Z'),
};

describe('ShipmentCard', () => {
  beforeEach(() => {
    Object.assign(navigator, {
      clipboard: {
        writeText: jest.fn().mockResolvedValue(undefined),
      },
    });
  });

  it('renders shipment information correctly', () => {
    renderWithI18n(<ShipmentCard shipment={mockShipment} />);

    expect(screen.getByText('TH1234567890')).toBeInTheDocument();
    expect(screen.getByText('Standard')).toBeInTheDocument();
    expect(screen.getByText(/REF123/)).toBeInTheDocument();
    expect(screen.getByText('Bangkok')).toBeInTheDocument();
    expect(screen.getByText('Chiang Mai')).toBeInTheDocument();
  });

  it('displays status badge with correct styling', () => {
    renderWithI18n(<ShipmentCard shipment={mockShipment} />);

    const statusBadge = screen
      .getAllByText('In Transit')
      .find((node) => node.className.includes('bg-yellow-100'));

    expect(statusBadge).toBeTruthy();
    expect(statusBadge).toHaveClass('bg-yellow-100', 'text-yellow-800');
  });

  it('shows latest event information', () => {
    renderWithI18n(<ShipmentCard shipment={mockShipment} />);

    expect(screen.getByText('Package is in transit')).toBeInTheDocument();
  });

  it('toggles timeline visibility', () => {
    renderWithI18n(<ShipmentCard shipment={mockShipment} />);

    const toggleButton = screen.getByText(/Show Full Timeline/);
    expect(toggleButton).toBeInTheDocument();

    fireEvent.click(toggleButton);
    expect(screen.getByText(/Hide Timeline/)).toBeInTheDocument();
  });

  it('displays progress bar', () => {
    renderWithI18n(<ShipmentCard shipment={mockShipment} />);

    expect(screen.getByText(/Shipment Progress/)).toBeInTheDocument();
  });

  it('handles shipment with exceptions', () => {
    const shipmentWithException: Shipment = {
      ...mockShipment,
      status: 'exception',
      exceptions: [
        {
          id: '1',
          type: 'address_issue',
          message: 'Address not found',
          messageEn: 'Address not found',
          messageTh: 'ไม่พบที่อยู่',
          severity: 'high',
          resolved: false,
          createdAt: new Date('2024-01-10T09:00:00Z'),
        },
      ],
    };

    renderWithI18n(<ShipmentCard shipment={shipmentWithException} />);

    expect(screen.getByText(/Shipment Issue/)).toBeInTheDocument();
    expect(screen.getByText('Address not found')).toBeInTheDocument();
  });

  it('displays delivered status correctly', () => {
    const deliveredShipment: Shipment = {
      ...mockShipment,
      status: 'delivered',
    };

    renderWithI18n(<ShipmentCard shipment={deliveredShipment} />);

    const statusBadge = screen
      .getAllByText('Delivered')
      .find((node) => node.className.includes('bg-green-100'));

    expect(statusBadge).toBeTruthy();
    expect(statusBadge).toHaveClass('bg-green-100', 'text-green-800');
  });

  it('handles empty events array', () => {
    const shipmentWithoutEvents: Shipment = {
      ...mockShipment,
      events: [],
    };

    renderWithI18n(<ShipmentCard shipment={shipmentWithoutEvents} />);

    expect(screen.getByText('TH1234567890')).toBeInTheDocument();
    expect(screen.queryByText(/Latest Update/)).not.toBeInTheDocument();
  });

  it('copies share link for a shipment', async () => {
    renderWithI18n(<ShipmentCard shipment={mockShipment} />);

    fireEvent.click(screen.getByText('Share'));

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      expect.stringContaining('/track/TH1234567890')
    );
  });
});
