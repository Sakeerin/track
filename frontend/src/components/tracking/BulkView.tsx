import React, { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { enUS, th } from 'date-fns/locale';
import { Shipment, ShipmentStatus } from '../../types/tracking.types';

interface BulkViewProps {
  shipments: Shipment[];
  locale?: 'th' | 'en';
  onExportCSV?: () => void;
  className?: string;
}

type SortField = 'trackingNumber' | 'status' | 'estimatedDelivery' | 'updatedAt';
type SortDirection = 'asc' | 'desc';

interface SortConfig {
  field: SortField;
  direction: SortDirection;
}

const BulkView: React.FC<BulkViewProps> = ({
  shipments,
  locale = 'en',
  onExportCSV,
  className = '',
}) => {
  const { t, i18n } = useTranslation();
  
  const [sortConfig, setSortConfig] = useState<SortConfig>({
    field: 'updatedAt',
    direction: 'desc',
  });
  const [statusFilter, setStatusFilter] = useState<ShipmentStatus | 'all'>('all');
  const [searchQuery, setSearchQuery] = useState('');

  const dateLocale = locale === 'th' ? th : enUS;
  const currentLang = i18n.language || locale;

  // Get location name based on locale
  const getLocationName = (location: any): string => {
    if (!location) return '';
    
    if (currentLang === 'th' && location.nameTh) {
      return location.nameTh;
    }
    
    return location.nameEn || location.name || '';
  };

  // Format date based on locale
  const formatDate = (date: Date | undefined): string => {
    if (!date) return '-';
    
    try {
      return format(date, 'PPp', { locale: dateLocale });
    } catch {
      return date.toLocaleString();
    }
  };

  // Get status badge styling
  const getStatusBadgeClass = (status: ShipmentStatus): string => {
    const baseClass = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium';
    
    switch (status) {
      case 'delivered':
        return `${baseClass} bg-green-100 text-green-800`;
      case 'out_for_delivery':
        return `${baseClass} bg-blue-100 text-blue-800`;
      case 'exception':
        return `${baseClass} bg-red-100 text-red-800`;
      case 'returned':
      case 'cancelled':
        return `${baseClass} bg-gray-100 text-gray-800`;
      default:
        return `${baseClass} bg-yellow-100 text-yellow-800`;
    }
  };

  // Filter and sort shipments
  const filteredAndSortedShipments = useMemo(() => {
    let filtered = shipments;

    // Apply status filter
    if (statusFilter !== 'all') {
      filtered = filtered.filter(shipment => shipment.status === statusFilter);
    }

    // Apply search filter
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(shipment =>
        shipment.trackingNumber.toLowerCase().includes(query) ||
        shipment.referenceNumber?.toLowerCase().includes(query) ||
        getLocationName(shipment.origin).toLowerCase().includes(query) ||
        getLocationName(shipment.destination).toLowerCase().includes(query) ||
        getLocationName(shipment.currentLocation).toLowerCase().includes(query)
      );
    }

    // Apply sorting
    const sorted = [...filtered].sort((a, b) => {
      let aValue: any;
      let bValue: any;

      switch (sortConfig.field) {
        case 'trackingNumber':
          aValue = a.trackingNumber;
          bValue = b.trackingNumber;
          break;
        case 'status':
          aValue = a.status;
          bValue = b.status;
          break;
        case 'estimatedDelivery':
          aValue = a.estimatedDelivery?.getTime() || 0;
          bValue = b.estimatedDelivery?.getTime() || 0;
          break;
        case 'updatedAt':
          aValue = a.updatedAt.getTime();
          bValue = b.updatedAt.getTime();
          break;
        default:
          return 0;
      }

      if (aValue < bValue) {
        return sortConfig.direction === 'asc' ? -1 : 1;
      }
      if (aValue > bValue) {
        return sortConfig.direction === 'asc' ? 1 : -1;
      }
      return 0;
    });

    return sorted;
  }, [shipments, statusFilter, searchQuery, sortConfig, currentLang]);

  // Handle sort
  const handleSort = (field: SortField) => {
    setSortConfig(prev => ({
      field,
      direction: prev.field === field && prev.direction === 'asc' ? 'desc' : 'asc',
    }));
  };

  // Get sort icon
  const getSortIcon = (field: SortField): React.ReactNode => {
    if (sortConfig.field !== field) {
      return (
        <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
        </svg>
      );
    }

    return sortConfig.direction === 'asc' ? (
      <svg className="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
      </svg>
    ) : (
      <svg className="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
      </svg>
    );
  };

  // Get unique statuses for filter
  const availableStatuses = useMemo(() => {
    const statuses = Array.from(new Set(shipments.map(s => s.status)));
    return statuses.sort();
  }, [shipments]);

  // Export to CSV
  const handleExportCSV = () => {
    if (onExportCSV) {
      onExportCSV();
      return;
    }

    // Default CSV export implementation
    const headers = [
      t('bulkView.headers.trackingNumber', 'Tracking Number'),
      t('bulkView.headers.status', 'Status'),
      t('bulkView.headers.serviceType', 'Service Type'),
      t('bulkView.headers.origin', 'Origin'),
      t('bulkView.headers.destination', 'Destination'),
      t('bulkView.headers.currentLocation', 'Current Location'),
      t('bulkView.headers.estimatedDelivery', 'Estimated Delivery'),
      t('bulkView.headers.lastUpdate', 'Last Update'),
    ];

    const csvData = filteredAndSortedShipments.map(shipment => [
      shipment.trackingNumber,
      t(`status.${shipment.status}`, shipment.status),
      shipment.serviceType,
      getLocationName(shipment.origin),
      getLocationName(shipment.destination),
      getLocationName(shipment.currentLocation),
      shipment.estimatedDelivery ? formatDate(shipment.estimatedDelivery) : '',
      formatDate(shipment.updatedAt),
    ]);

    const csvContent = [headers, ...csvData]
      .map(row => row.map(cell => `"${cell}"`).join(','))
      .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `shipments-${format(new Date(), 'yyyy-MM-dd')}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  if (shipments.length === 0) {
    return (
      <div className={`text-center py-8 ${className}`}>
        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-2.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 009.586 13H7" />
        </svg>
        <h3 className="mt-2 text-sm font-medium text-gray-900">
          {t('bulkView.noShipments', 'No shipments found')}
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          {t('bulkView.noShipmentsDescription', 'Try adjusting your search or filter criteria.')}
        </p>
      </div>
    );
  }

  return (
    <div className={`bulk-view ${className}`}>
      {/* Controls */}
      <div className="mb-6 space-y-4 sm:space-y-0 sm:flex sm:items-center sm:justify-between">
        <div className="flex-1 min-w-0">
          <div className="flex flex-col sm:flex-row gap-4">
            {/* Search */}
            <div className="flex-1 max-w-lg">
              <label htmlFor="search" className="sr-only">
                {t('bulkView.search', 'Search shipments')}
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <input
                  id="search"
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder={t('bulkView.searchPlaceholder', 'Search by tracking number, reference, or location...')}
                />
              </div>
            </div>

            {/* Status Filter */}
            <div className="min-w-0">
              <label htmlFor="status-filter" className="sr-only">
                {t('bulkView.filterByStatus', 'Filter by status')}
              </label>
              <select
                id="status-filter"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as ShipmentStatus | 'all')}
                className="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md"
              >
                <option value="all">{t('bulkView.allStatuses', 'All Statuses')}</option>
                {availableStatuses.map(status => (
                  <option key={status} value={status}>
                    {t(`status.${status}`, status)}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Export Button */}
        <div className="flex-shrink-0">
          <button
            onClick={handleExportCSV}
            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <svg className="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            {t('bulkView.exportCSV', 'Export CSV')}
          </button>
        </div>
      </div>

      {/* Results Summary */}
      <div className="mb-4 text-sm text-gray-600">
        {t('bulkView.showingResults', {
          count: filteredAndSortedShipments.length,
          total: shipments.length,
          defaultValue: `Showing ${filteredAndSortedShipments.length} of ${shipments.length} shipments`,
        })}
      </div>

      {/* Table */}
      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th
                  scope="col"
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('trackingNumber')}
                >
                  <div className="flex items-center space-x-1">
                    <span>{t('bulkView.headers.trackingNumber', 'Tracking Number')}</span>
                    {getSortIcon('trackingNumber')}
                  </div>
                </th>
                <th
                  scope="col"
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('status')}
                >
                  <div className="flex items-center space-x-1">
                    <span>{t('bulkView.headers.status', 'Status')}</span>
                    {getSortIcon('status')}
                  </div>
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {t('bulkView.headers.serviceType', 'Service')}
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {t('bulkView.headers.route', 'Route')}
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {t('bulkView.headers.currentLocation', 'Current Location')}
                </th>
                <th
                  scope="col"
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('estimatedDelivery')}
                >
                  <div className="flex items-center space-x-1">
                    <span>{t('bulkView.headers.eta', 'ETA')}</span>
                    {getSortIcon('estimatedDelivery')}
                  </div>
                </th>
                <th
                  scope="col"
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('updatedAt')}
                >
                  <div className="flex items-center space-x-1">
                    <span>{t('bulkView.headers.lastUpdate', 'Last Update')}</span>
                    {getSortIcon('updatedAt')}
                  </div>
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredAndSortedShipments.map((shipment) => (
                <tr key={shipment.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">
                      {shipment.trackingNumber}
                    </div>
                    {shipment.referenceNumber && (
                      <div className="text-sm text-gray-500">
                        {t('shipment.reference', 'Ref')}: {shipment.referenceNumber}
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={getStatusBadgeClass(shipment.status)}>
                      {t(`status.${shipment.status}`, shipment.status)}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {shipment.serviceType}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div>
                      {getLocationName(shipment.origin) || '-'}
                    </div>
                    <div className="text-xs text-gray-400">↓</div>
                    <div>
                      {getLocationName(shipment.destination) || '-'}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {getLocationName(shipment.currentLocation) || '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(shipment.estimatedDelivery)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(shipment.updatedAt)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default BulkView;