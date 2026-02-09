import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { shipmentApi } from '../../services/adminApi';
import { ShipmentSearchParams, AdminShipment } from '../../types/admin.types';

const ShipmentsPage: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useState<ShipmentSearchParams>({
    perPage: 20,
    page: 1,
    sortBy: 'created_at',
    sortOrder: 'desc',
  });

  const { register, handleSubmit, reset } = useForm<ShipmentSearchParams>({
    defaultValues: searchParams,
  });

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin', 'shipments', searchParams],
    queryFn: () => shipmentApi.search(searchParams),
    keepPreviousData: true,
  });

  const onSubmit = (data: ShipmentSearchParams) => {
    setSearchParams({ ...searchParams, ...data, page: 1 });
  };

  const handleReset = () => {
    reset();
    setSearchParams({
      perPage: 20,
      page: 1,
      sortBy: 'created_at',
      sortOrder: 'desc',
    });
  };

  const handlePageChange = (page: number) => {
    setSearchParams({ ...searchParams, page });
  };

  const handleSort = (field: string) => {
    setSearchParams({
      ...searchParams,
      sortBy: field,
      sortOrder: searchParams.sortBy === field && searchParams.sortOrder === 'asc' ? 'desc' : 'asc',
    });
  };

  const getStatusBadgeColor = (status: string) => {
    const colors: Record<string, string> = {
      picked_up: 'bg-blue-100 text-blue-800',
      in_transit: 'bg-yellow-100 text-yellow-800',
      at_hub: 'bg-purple-100 text-purple-800',
      out_for_delivery: 'bg-indigo-100 text-indigo-800',
      delivered: 'bg-green-100 text-green-800',
      exception: 'bg-red-100 text-red-800',
      returned: 'bg-gray-100 text-gray-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  return (
    <div className="space-y-6">
      {/* Search Form */}
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">
          {t('admin.shipments.search', 'Search Shipments')}
        </h2>
        
        <form onSubmit={handleSubmit(onSubmit)} className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('admin.shipments.trackingNumber', 'Tracking Number')}
            </label>
            <input
              {...register('trackingNumber')}
              type="text"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="TH1234567890"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('admin.shipments.referenceNumber', 'Reference Number')}
            </label>
            <input
              {...register('referenceNumber')}
              type="text"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="ORDER-123"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('admin.shipments.phone', 'Phone')}
            </label>
            <input
              {...register('phone')}
              type="text"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="0812345678"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('admin.shipments.status', 'Status')}
            </label>
            <select
              {...register('status')}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="picked_up">Picked Up</option>
              <option value="in_transit">In Transit</option>
              <option value="at_hub">At Hub</option>
              <option value="out_for_delivery">Out for Delivery</option>
              <option value="delivered">Delivered</option>
              <option value="exception">Exception</option>
              <option value="returned">Returned</option>
            </select>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('admin.shipments.dateFrom', 'From Date')}
            </label>
            <input
              {...register('dateFrom')}
              type="date"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('admin.shipments.dateTo', 'To Date')}
            </label>
            <input
              {...register('dateTo')}
              type="date"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          
          <div className="flex items-end gap-2 md:col-span-2">
            <button
              type="submit"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              {t('admin.shipments.searchButton', 'Search')}
            </button>
            <button
              type="button"
              onClick={handleReset}
              className="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
              {t('admin.shipments.resetButton', 'Reset')}
            </button>
          </div>
        </form>
      </div>

      {/* Results Table */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('tracking_number')}
                >
                  <div className="flex items-center">
                    {t('admin.shipments.trackingNumber', 'Tracking Number')}
                    {searchParams.sortBy === 'tracking_number' && (
                      <span className="ml-1">{searchParams.sortOrder === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('current_status')}
                >
                  <div className="flex items-center">
                    {t('admin.shipments.status', 'Status')}
                    {searchParams.sortBy === 'current_status' && (
                      <span className="ml-1">{searchParams.sortOrder === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {t('admin.shipments.origin', 'Origin')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {t('admin.shipments.destination', 'Destination')}
                </th>
                <th
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('estimated_delivery')}
                >
                  <div className="flex items-center">
                    {t('admin.shipments.eta', 'ETA')}
                    {searchParams.sortBy === 'estimated_delivery' && (
                      <span className="ml-1">{searchParams.sortOrder === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('created_at')}
                >
                  <div className="flex items-center">
                    {t('admin.shipments.created', 'Created')}
                    {searchParams.sortBy === 'created_at' && (
                      <span className="ml-1">{searchParams.sortOrder === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {t('admin.shipments.actions', 'Actions')}
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {isLoading ? (
                [...Array(5)].map((_, i) => (
                  <tr key={i} className="animate-pulse">
                    {[...Array(7)].map((_, j) => (
                      <td key={j} className="px-6 py-4">
                        <div className="h-4 bg-gray-200 rounded"></div>
                      </td>
                    ))}
                  </tr>
                ))
              ) : error ? (
                <tr>
                  <td colSpan={7} className="px-6 py-4 text-center text-red-500">
                    {t('admin.shipments.error', 'Error loading shipments')}
                  </td>
                </tr>
              ) : data?.data.shipments.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-4 text-center text-gray-500">
                    {t('admin.shipments.noResults', 'No shipments found')}
                  </td>
                </tr>
              ) : (
                data?.data.shipments.map((shipment: AdminShipment) => (
                  <tr key={shipment.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="font-mono text-sm text-blue-600">
                        {shipment.trackingNumber}
                      </span>
                      {shipment.referenceNumber && (
                        <p className="text-xs text-gray-500">{shipment.referenceNumber}</p>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusBadgeColor(shipment.currentStatus)}`}>
                        {shipment.currentStatus.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {shipment.originFacility?.name || '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {shipment.destinationFacility?.name || '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {shipment.estimatedDelivery
                        ? new Date(shipment.estimatedDelivery).toLocaleDateString()
                        : '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {new Date(shipment.createdAt).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                      <button
                        onClick={() => navigate(`/admin/shipments/${shipment.id}`)}
                        className="text-blue-600 hover:text-blue-900"
                      >
                        {t('admin.shipments.viewDetails', 'View')}
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {data && data.data.pagination.lastPage > 1 && (
          <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <div className="text-sm text-gray-700">
              Showing {((data.data.pagination.currentPage - 1) * data.data.pagination.perPage) + 1} to{' '}
              {Math.min(data.data.pagination.currentPage * data.data.pagination.perPage, data.data.pagination.total)} of{' '}
              {data.data.pagination.total} results
            </div>
            <div className="flex items-center space-x-2">
              <button
                onClick={() => handlePageChange(data.data.pagination.currentPage - 1)}
                disabled={data.data.pagination.currentPage === 1}
                className="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
              >
                Previous
              </button>
              {[...Array(Math.min(5, data.data.pagination.lastPage))].map((_, i) => {
                const page = i + 1;
                return (
                  <button
                    key={page}
                    onClick={() => handlePageChange(page)}
                    className={`px-3 py-1 border rounded-md ${
                      data.data.pagination.currentPage === page
                        ? 'bg-blue-600 text-white'
                        : 'hover:bg-gray-50'
                    }`}
                  >
                    {page}
                  </button>
                );
              })}
              <button
                onClick={() => handlePageChange(data.data.pagination.currentPage + 1)}
                disabled={data.data.pagination.currentPage === data.data.pagination.lastPage}
                className="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default ShipmentsPage;
