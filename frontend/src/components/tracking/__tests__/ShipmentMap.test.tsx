import React from 'react';
import { render, screen } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import ShipmentMap from '../ShipmentMap';
import { Shipment } from '../../../types/tracking.types';

// Mock Leaflet
jest.mock('leaflet', () => ({
  map: jest.fn(() => ({
    setView: jest.fn(),
    remove: jest.fn(),
    fitBounds: jest.fn(),
  })),
  tileLayer: jest.fn(() => ({
    addTo: jest.fn(),
  })),
  marker: jest.fn(() => ({
    bindPopup: jest.fn().mockReturnThis(),
    addTo: jest.fn().mockReturnThis(),
  })),
  polyline: jest.fn(() => ({
    addTo: jest.fn(),
  })),
  latLng: jest.fn((lat, lng) => ({ lat, lng })),
  FeatureGroup: jest.fn(() => ({
    getBounds: jest.fn(() => ({
      pad: jest.fn(() => 'mockBounds'),
    })),
  })),
  divIcon: jest.fn(),
  Icon: {
    Default: {
      prototype: {},
      mergeOptions: jest.fn(),
    },
  },
}));

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

const mockShipmentWithLocation: Shipment = {
  id: '1',
  trackingNumber: 'TH1234567890',
  status: 'in_transit',
  serviceType: 'Standard',
  origin: {
    id: '1',
    name: 'Bangkok',
    nameEn: 'Bangkok',
    nameTh: 'กรุงเทพฯ',
    latitude: 13.7563,
    longitude: 100.5018,
  },
  destination: {
    id: '2',
    name: 'Chiang Mai',
    nameEn: 'Chiang Mai',
    nameTh: 'เชียงใหม่',
    latitude: 18.7883,
    longitude: 98.9853,
  },
  currentLocation: {
    id: '3',
    name: 'Ayutthaya Hub',
    nameEn: 'Ayutthaya Hub',
    nameTh: 'ศูนย์คัดแยกอยุธยา',
    latitude: 14.3692,
    longitude: 100.5877,
  },
  events: [
    {
      id: '1',
      eventTime: new Date('2024-01-10T08:00:00Z'),
      eventCode: 'in_transit',
      description: 'Package is in transit',
      source: 'system',
      facility: {
        id: '3',
        name: 'Ayutthaya Hub',
        nameEn: 'Ayutthaya Hub',
        nameTh: 'ศูนย์คัดแยกอยุธยา',
        latitude: 14.3692,
        longitude: 100.5877,
      },
    },
  ],
  exceptions: [],
  createdAt: new Date('2024-01-09T10:00:00Z'),
  updatedAt: new Date('2024-01-10T08:00:00Z'),
};

const mockShipmentWithoutLocation: Shipment = {
  id: '2',
  trackingNumber: 'TH0987654321',
  status: 'created',
  serviceType: 'Express',
  origin: { id: '1', name: 'Bangkok' },
  destination: { id: '2', name: 'Chiang Mai' },
  events: [],
  exceptions: [],
  createdAt: new Date('2024-01-09T10:00:00Z'),
  updatedAt: new Date('2024-01-09T10:00:00Z'),
};

describe('ShipmentMap', () => {
  it('renders map with location data', () => {
    renderWithI18n(<ShipmentMap shipment={mockShipmentWithLocation} />);

    expect(screen.getByText(/Shipment Route/)).toBeInTheDocument();
    expect(screen.getByText('Origin')).toBeInTheDocument();
    expect(screen.getByText('Destination')).toBeInTheDocument();
    expect(screen.getByText('Current Location')).toBeInTheDocument();
    expect(screen.getByText('Events')).toBeInTheDocument();
  });

  it('shows no location data message when coordinates are missing', () => {
    renderWithI18n(<ShipmentMap shipment={mockShipmentWithoutLocation} />);

    expect(screen.getByText(/No Location Data Available/)).toBeInTheDocument();
    expect(screen.getByText(/Location information is not available/)).toBeInTheDocument();
  });

  it('displays map attribution', () => {
    renderWithI18n(<ShipmentMap shipment={mockShipmentWithLocation} />);

    expect(screen.getByText(/Map data © OpenStreetMap contributors/)).toBeInTheDocument();
  });

  it('applies custom className and height', () => {
    renderWithI18n(
      <ShipmentMap 
        shipment={mockShipmentWithLocation} 
        className="custom-class" 
        height="300px" 
      />
    );

    const mapContainer = screen.getByText(/Shipment Route/).closest('.shipment-map');
    expect(mapContainer).toHaveClass('custom-class');
  });

  it('handles different locales', () => {
    renderWithI18n(<ShipmentMap shipment={mockShipmentWithLocation} locale="th" />);

    expect(screen.getByText(/Shipment Route/)).toBeInTheDocument();
  });

  it('renders legend with color indicators', () => {
    renderWithI18n(<ShipmentMap shipment={mockShipmentWithLocation} />);

    const legend = screen.getByText('Origin').closest('div');
    expect(legend).toBeInTheDocument();
    
    expect(screen.getByText('Origin')).toBeInTheDocument();
    expect(screen.getByText('Destination')).toBeInTheDocument();
    expect(screen.getByText('Current Location')).toBeInTheDocument();
    expect(screen.getByText('Events')).toBeInTheDocument();
  });

  it('has proper accessibility structure', () => {
    renderWithI18n(<ShipmentMap shipment={mockShipmentWithLocation} />);

    const mapTitle = screen.getByText(/Shipment Route/);
    expect(mapTitle.tagName).toBe('H4');
  });
});