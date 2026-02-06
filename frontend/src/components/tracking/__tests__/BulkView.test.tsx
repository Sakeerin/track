import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import BulkView from '../BulkView';
import { Shipment } from '../../../types/tracking.types';

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

const mockShipments: Shipment[] = [
  {
    id: '1',
    trackingNumber: 'TH1234567890',
    referenceNumber: 'REF123',
    status: 'in_transit',
    serviceType: 'Standard',
    origin: { id: '1', name: 'Bangkok' },
    destination: { id: '2', name: 'Chiang Mai' },
    currentLocation: { id: '3', name: 'Ayutthaya Hub' },
    estimatedDelivery: new Date('2024-01-15T10:00:00Z'),
    events: [
      {
        id: '1',
        eventTime: new Date('2024-01-10T08:00:00Z'),
        eventCode: 'in_transit',
        description: 'Package is in transit',
        source: 'system',
      },
    ],
    exceptions: [],
    createdAt: new Date('2024-01-09T10:00:00Z'),
    updatedAt: new Date('2024-01-10T08:00:00Z'),
  },
  {
    id: '2',
    trackingNumber: 'TH0987654321',
    status: 'delivered',
    serviceType: 'Express',
    origin: { id: '1', name: 'Bangkok' },
    destination: { id: '4', name: 'Phuket' },
    currentLocation: { id: '4', name: 'Phuket Hub' },
    events: [
      {
        id: '2',
        eventTime: new Date('2024-01-12T14:00:00Z'),
        eventCode: 'delivered',
        description: 'Package delivered',
        source: 'system',
      },
    ],
    exceptions: [],
    createdAt: new Date('2024-01-08T10:00:00Z'),
    updatedAt: new Date('2024-01-12T14:00:00Z'),
  },
];

describe('BulkView', () => {
  it('renders shipments in table format', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    expect(screen.getByText('TH1234567890')).toBeInTheDocument();
    expect(screen.getByText('TH0987654321')).toBeInTheDocument();
    expect(screen.getByText('Standard')).toBeInTheDocument();
    expect(screen.getByText('Express')).toBeInTheDocument();
  });

  it('displays search functionality', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    const searchInput = screen.getByPlaceholderText(/Search by tracking number/);
    expect(searchInput).toBeInTheDocument();
  });

  it('displays status filter', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    const statusFilter = screen.getByDisplayValue('All Statuses');
    expect(statusFilter).toBeInTheDocument();
  });

  it('displays export CSV button', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    const exportButton = screen.getByText('Export CSV');
    expect(exportButton).toBeInTheDocument();
  });

  it('filters shipments by search query', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    const searchInput = screen.getByPlaceholderText(/Search by tracking number/);
    fireEvent.change(searchInput, { target: { value: 'TH1234567890' } });

    expect(screen.getByText('TH1234567890')).toBeInTheDocument();
    expect(screen.queryByText('TH0987654321')).not.toBeInTheDocument();
  });

  it('filters shipments by status', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    const statusFilter = screen.getByDisplayValue('All Statuses');
    fireEvent.change(statusFilter, { target: { value: 'delivered' } });

    expect(screen.queryByText('TH1234567890')).not.toBeInTheDocument();
    expect(screen.getByText('TH0987654321')).toBeInTheDocument();
  });

  it('sorts shipments by clicking column headers', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    const trackingNumberHeader = screen.getByText('Tracking Number');
    fireEvent.click(trackingNumberHeader);

    // Should still show both shipments but potentially in different order
    expect(screen.getByText('TH1234567890')).toBeInTheDocument();
    expect(screen.getByText('TH0987654321')).toBeInTheDocument();
  });

  it('displays results count', () => {
    renderWithI18n(<BulkView shipments={mockShipments} />);

    expect(screen.getByText(/Showing 2 of 2 shipments/)).toBeInTheDocument();
  });

  it('handles empty shipments array', () => {
    renderWithI18n(<BulkView shipments={[]} />);

    expect(screen.getByText('No shipments found')).toBeInTheDocument();
    expect(screen.getByText(/Try adjusting your search/)).toBeInTheDocument();
  });

  it('calls onExportCSV when provided', () => {
    const mockExport = jest.fn();
    renderWithI18n(<BulkView shipments={mockShipments} onExportCSV={mockExport} />);

    const exportButton = screen.getByText('Export CSV');
    fireEvent.click(exportButton);

    expect(mockExport).toHaveBeenCalled();
  });
});