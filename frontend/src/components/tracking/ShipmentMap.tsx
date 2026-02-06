import React, { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Shipment, TrackingEvent } from '../../types/tracking.types';

// Fix for default markers in Leaflet with webpack
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
  iconUrl: require('leaflet/dist/images/marker-icon.png'),
  shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
});

interface ShipmentMapProps {
  shipment: Shipment;
  locale?: 'th' | 'en';
  className?: string;
  height?: string;
}

const ShipmentMap: React.FC<ShipmentMapProps> = ({
  shipment,
  locale = 'en',
  className = '',
  height = '400px',
}) => {
  const { t, i18n } = useTranslation();
  const mapRef = useRef<HTMLDivElement>(null);
  const mapInstanceRef = useRef<L.Map | null>(null);
  
  const currentLang = i18n.language || locale;

  // Get location name based on locale
  const getLocationName = (location: any): string => {
    if (!location) return '';
    
    if (currentLang === 'th' && location.nameTh) {
      return location.nameTh;
    }
    
    return location.nameEn || location.name || '';
  };

  // Get description based on locale
  const getEventDescription = (event: TrackingEvent): string => {
    if (currentLang === 'th' && event.descriptionTh) {
      return event.descriptionTh;
    }
    
    return event.descriptionEn || event.description || '';
  };

  // Create custom icons
  const createIcon = (color: string, isLarge: boolean = false) => {
    const size = isLarge ? 32 : 24;
    return L.divIcon({
      className: 'custom-div-icon',
      html: `
        <div style="
          background-color: ${color};
          width: ${size}px;
          height: ${size}px;
          border-radius: 50%;
          border: 3px solid white;
          box-shadow: 0 2px 4px rgba(0,0,0,0.3);
          display: flex;
          align-items: center;
          justify-content: center;
        ">
          <div style="
            width: ${size - 12}px;
            height: ${size - 12}px;
            background-color: white;
            border-radius: 50%;
          "></div>
        </div>
      `,
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
    });
  };

  const originIcon = createIcon('#10B981'); // Green
  const destinationIcon = createIcon('#EF4444'); // Red
  const currentLocationIcon = createIcon('#3B82F6', true); // Blue, larger
  const eventIcon = createIcon('#F59E0B'); // Yellow

  useEffect(() => {
    if (!mapRef.current) return;

    // Initialize map
    const map = L.map(mapRef.current, {
      zoomControl: true,
      scrollWheelZoom: true,
    });

    mapInstanceRef.current = map;

    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 18,
    }).addTo(map);

    // Collect all locations with coordinates
    const locations: Array<{ lat: number; lng: number; type: string; data: any }> = [];

    // Add origin
    if (shipment.origin?.latitude && shipment.origin?.longitude) {
      locations.push({
        lat: shipment.origin.latitude,
        lng: shipment.origin.longitude,
        type: 'origin',
        data: shipment.origin,
      });
    }

    // Add destination
    if (shipment.destination?.latitude && shipment.destination?.longitude) {
      locations.push({
        lat: shipment.destination.latitude,
        lng: shipment.destination.longitude,
        type: 'destination',
        data: shipment.destination,
      });
    }

    // Add current location
    if (shipment.currentLocation?.latitude && shipment.currentLocation?.longitude) {
      locations.push({
        lat: shipment.currentLocation.latitude,
        lng: shipment.currentLocation.longitude,
        type: 'current',
        data: shipment.currentLocation,
      });
    }

    // Add event locations
    shipment.events.forEach(event => {
      if (event.location?.latitude && event.location?.longitude) {
        locations.push({
          lat: event.location.latitude,
          lng: event.location.longitude,
          type: 'event',
          data: event,
        });
      } else if (event.facility?.latitude && event.facility?.longitude) {
        locations.push({
          lat: event.facility.latitude,
          lng: event.facility.longitude,
          type: 'event',
          data: event,
        });
      }
    });

    // Add markers
    const markers: L.Marker[] = [];

    locations.forEach(location => {
      let icon: L.DivIcon;
      let popupContent: string;

      switch (location.type) {
        case 'origin':
          icon = originIcon;
          popupContent = `
            <div class="p-2">
              <div class="font-semibold text-green-800">${t('shipment.origin', 'Origin')}</div>
              <div class="text-sm">${getLocationName(location.data)}</div>
            </div>
          `;
          break;
        case 'destination':
          icon = destinationIcon;
          popupContent = `
            <div class="p-2">
              <div class="font-semibold text-red-800">${t('shipment.destination', 'Destination')}</div>
              <div class="text-sm">${getLocationName(location.data)}</div>
            </div>
          `;
          break;
        case 'current':
          icon = currentLocationIcon;
          popupContent = `
            <div class="p-2">
              <div class="font-semibold text-blue-800">${t('shipment.currentLocation', 'Current Location')}</div>
              <div class="text-sm">${getLocationName(location.data)}</div>
            </div>
          `;
          break;
        case 'event':
          icon = eventIcon;
          const event = location.data as TrackingEvent;
          popupContent = `
            <div class="p-2">
              <div class="font-semibold text-yellow-800">${getEventDescription(event)}</div>
              <div class="text-xs text-gray-600">${event.eventTime.toLocaleString()}</div>
              ${event.facility ? `<div class="text-sm">${getLocationName(event.facility)}</div>` : ''}
              ${event.remarks ? `<div class="text-xs text-gray-500 mt-1">${event.remarks}</div>` : ''}
            </div>
          `;
          break;
        default:
          return;
      }

      const marker = L.marker([location.lat, location.lng], { icon })
        .bindPopup(popupContent)
        .addTo(map);
      
      markers.push(marker);
    });

    // Draw route line if we have origin and destination
    if (shipment.origin?.latitude && shipment.origin?.longitude && 
        shipment.destination?.latitude && shipment.destination?.longitude) {
      
      const routePoints: L.LatLng[] = [
        L.latLng(shipment.origin.latitude, shipment.origin.longitude),
      ];

      // Add event locations in chronological order
      const eventsWithLocation = shipment.events
        .filter(event => 
          (event.location?.latitude && event.location?.longitude) ||
          (event.facility?.latitude && event.facility?.longitude)
        )
        .sort((a, b) => a.eventTime.getTime() - b.eventTime.getTime());

      eventsWithLocation.forEach(event => {
        const location = event.location || event.facility;
        if (location?.latitude && location?.longitude) {
          routePoints.push(L.latLng(location.latitude, location.longitude));
        }
      });

      routePoints.push(L.latLng(shipment.destination.latitude, shipment.destination.longitude));

      // Draw polyline
      L.polyline(routePoints, {
        color: '#3B82F6',
        weight: 3,
        opacity: 0.7,
        dashArray: '10, 5',
      }).addTo(map);
    }

    // Fit map to show all markers
    if (markers.length > 0) {
      const group = new L.FeatureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.1));
    } else {
      // Default to Thailand if no coordinates
      map.setView([13.7563, 100.5018], 6);
    }

    // Cleanup function
    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove();
        mapInstanceRef.current = null;
      }
    };
  }, [shipment, currentLang, t]);

  // Check if we have any location data to show
  const hasLocationData = 
    (shipment.origin?.latitude && shipment.origin?.longitude) ||
    (shipment.destination?.latitude && shipment.destination?.longitude) ||
    (shipment.currentLocation?.latitude && shipment.currentLocation?.longitude) ||
    shipment.events.some(event => 
      (event.location?.latitude && event.location?.longitude) ||
      (event.facility?.latitude && event.facility?.longitude)
    );

  if (!hasLocationData) {
    return (
      <div className={`bg-gray-100 rounded-lg p-8 text-center ${className}`} style={{ height }}>
        <svg className="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          {t('map.noLocationData', 'No Location Data Available')}
        </h3>
        <p className="text-gray-600">
          {t('map.noLocationDataDescription', 'Location information is not available for this shipment.')}
        </p>
      </div>
    );
  }

  return (
    <div className={`shipment-map ${className}`}>
      <div className="mb-4">
        <h4 className="text-lg font-medium text-gray-900 mb-2">
          {t('map.title', 'Shipment Route')}
        </h4>
        <div className="flex flex-wrap gap-4 text-sm">
          <div className="flex items-center">
            <div className="w-4 h-4 bg-green-500 rounded-full mr-2"></div>
            <span>{t('shipment.origin', 'Origin')}</span>
          </div>
          <div className="flex items-center">
            <div className="w-4 h-4 bg-red-500 rounded-full mr-2"></div>
            <span>{t('shipment.destination', 'Destination')}</span>
          </div>
          <div className="flex items-center">
            <div className="w-5 h-5 bg-blue-500 rounded-full mr-2"></div>
            <span>{t('shipment.currentLocation', 'Current Location')}</span>
          </div>
          <div className="flex items-center">
            <div className="w-4 h-4 bg-yellow-500 rounded-full mr-2"></div>
            <span>{t('map.events', 'Events')}</span>
          </div>
        </div>
      </div>
      
      <div 
        ref={mapRef} 
        className="rounded-lg border border-gray-300 overflow-hidden"
        style={{ height }}
      />
      
      <div className="mt-2 text-xs text-gray-500 text-center">
        {t('map.attribution', 'Map data © OpenStreetMap contributors')}
      </div>
    </div>
  );
};

export default ShipmentMap;