import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useForm } from 'react-hook-form';
import toast from 'react-hot-toast';
import { shipmentApi } from '../../services/adminApi';
import { useAuth } from '../../contexts/AuthContext';
import { CreateEventRequest } from '../../types/admin.types';

const ShipmentDetailPage: React.FC = () => {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { hasRole } = useAuth();
  const [showAddEvent, setShowAddEvent] = useState(false);
  const [showRawPayloads, setShowRawPayloads] = useState(false);

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin', 'shipment', id, showRawPayloads],
    queryFn: () => shipmentApi.getDetails(id!, showRawPayloads),
    enabled: !!id,
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateEventRequest>();

  const addEventMutation = useMutation({
    mutationFn: (event: CreateEventRequest) => shipmentApi.addEvent(id!, event),
    onSuccess: () => {
      toast.success('Event added successfully');
      queryClient.invalidateQueries(['admin', 'shipment', id]);
      setShowAddEvent(false);
      reset();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.error || 'Failed to add event');
    },
  });

  const onSubmitEvent = (data: CreateEventRequest) => {
    addEventMutation.mutate(data);
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

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-8 bg-gray-200 rounded w-1/4"></div>
        <div className="bg-white rounded-lg shadow p-6">
          <div className="space-y-3">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="h-4 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="text-center py-12">
        <p className="text-red-500">{t('admin.shipments.notFound', 'Shipment not found')}</p>
        <button
          onClick={() => navigate('/admin/shipments')}
          className="mt-4 text-blue-600 hover:text-blue-800"
        >
          {t('admin.shipments.backToList', 'Back to list')}
        </button>
      </div>
    );
  }

  const { shipment, events, subscriptions } = data.data;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <button
            onClick={() => navigate('/admin/shipments')}
            className="text-gray-500 hover:text-gray-700 mb-2 flex items-center"
          >
            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {t('admin.shipments.backToList', 'Back to list')}
          </button>
          <h1 className="text-2xl font-bold text-gray-900 font-mono">
            {shipment.trackingNumber}
          </h1>
        </div>
        <span className={`px-3 py-1 text-sm font-medium rounded-full ${getStatusBadgeColor(shipment.currentStatus)}`}>
          {shipment.currentStatus.replace('_', ' ')}
        </span>
      </div>

      {/* Shipment Details */}
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">
          {t('admin.shipments.details', 'Shipment Details')}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.referenceNumber', 'Reference Number')}</p>
            <p className="font-medium">{shipment.referenceNumber || '-'}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.serviceType', 'Service Type')}</p>
            <p className="font-medium capitalize">{shipment.serviceType}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.eta', 'Estimated Delivery')}</p>
            <p className="font-medium">
              {shipment.estimatedDelivery
                ? new Date(shipment.estimatedDelivery).toLocaleString()
                : '-'}
            </p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.origin', 'Origin')}</p>
            <p className="font-medium">{shipment.originFacility?.name || '-'}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.destination', 'Destination')}</p>
            <p className="font-medium">{shipment.destinationFacility?.name || '-'}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.currentLocation', 'Current Location')}</p>
            <p className="font-medium">{shipment.currentLocation?.name || '-'}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.created', 'Created')}</p>
            <p className="font-medium">{new Date(shipment.createdAt).toLocaleString()}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">{t('admin.shipments.updated', 'Last Updated')}</p>
            <p className="font-medium">{new Date(shipment.updatedAt).toLocaleString()}</p>
          </div>
        </div>
      </div>

      {/* Events Timeline */}
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">
            {t('admin.shipments.events', 'Events')} ({events.length})
          </h2>
          <div className="flex items-center space-x-4">
            <label className="flex items-center text-sm text-gray-600">
              <input
                type="checkbox"
                checked={showRawPayloads}
                onChange={(e) => setShowRawPayloads(e.target.checked)}
                className="mr-2"
              />
              Show raw payloads
            </label>
            {hasRole(['admin', 'ops']) && (
              <button
                onClick={() => setShowAddEvent(!showAddEvent)}
                className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
              >
                {showAddEvent ? 'Cancel' : 'Add Event'}
              </button>
            )}
          </div>
        </div>

        {/* Add Event Form */}
        {showAddEvent && (
          <form onSubmit={handleSubmit(onSubmitEvent)} className="mb-6 p-4 border rounded-lg bg-gray-50">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Event Code *</label>
                <select
                  {...register('eventCode', { required: 'Event code is required' })}
                  className="w-full px-3 py-2 border rounded-md"
                >
                  <option value="">Select event code</option>
                  <option value="PICKUP">PICKUP</option>
                  <option value="IN_TRANSIT">IN_TRANSIT</option>
                  <option value="AT_HUB">AT_HUB</option>
                  <option value="OUT_FOR_DELIVERY">OUT_FOR_DELIVERY</option>
                  <option value="DELIVERED">DELIVERED</option>
                  <option value="EXCEPTION">EXCEPTION</option>
                  <option value="RETURNED">RETURNED</option>
                </select>
                {errors.eventCode && <p className="text-red-500 text-sm mt-1">{errors.eventCode.message}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Event Time *</label>
                <input
                  {...register('eventTime', { required: 'Event time is required' })}
                  type="datetime-local"
                  className="w-full px-3 py-2 border rounded-md"
                />
                {errors.eventTime && <p className="text-red-500 text-sm mt-1">{errors.eventTime.message}</p>}
              </div>
              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input
                  {...register('description')}
                  type="text"
                  className="w-full px-3 py-2 border rounded-md"
                  placeholder="Optional description"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <input
                  {...register('location')}
                  type="text"
                  className="w-full px-3 py-2 border rounded-md"
                  placeholder="Location name"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <input
                  {...register('notes')}
                  type="text"
                  className="w-full px-3 py-2 border rounded-md"
                  placeholder="Internal notes"
                />
              </div>
            </div>
            <div className="mt-4">
              <button
                type="submit"
                disabled={addEventMutation.isLoading}
                className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
              >
                {addEventMutation.isLoading ? 'Adding...' : 'Add Event'}
              </button>
            </div>
          </form>
        )}

        {/* Events List */}
        <div className="space-y-4">
          {events.length === 0 ? (
            <p className="text-gray-500 text-center py-4">No events recorded</p>
          ) : (
            events.map((event, index) => (
              <div key={event.id} className="flex items-start">
                <div className="flex flex-col items-center mr-4">
                  <div className={`w-3 h-3 rounded-full ${index === 0 ? 'bg-blue-600' : 'bg-gray-300'}`}></div>
                  {index < events.length - 1 && (
                    <div className="w-0.5 h-full bg-gray-200 mt-1"></div>
                  )}
                </div>
                <div className="flex-1 pb-4">
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-gray-900">{event.eventCode}</span>
                    <span className="text-sm text-gray-500">
                      {new Date(event.eventTime).toLocaleString()}
                    </span>
                  </div>
                  {event.description && (
                    <p className="text-sm text-gray-600 mt-1">{event.description}</p>
                  )}
                  {event.facility && (
                    <p className="text-sm text-gray-500 mt-1">
                      {event.facility.name} ({event.facility.code})
                    </p>
                  )}
                  {showRawPayloads && event.rawPayload && (
                    <pre className="mt-2 p-2 bg-gray-100 rounded text-xs overflow-x-auto">
                      {JSON.stringify(event.rawPayload, null, 2)}
                    </pre>
                  )}
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Subscriptions */}
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">
          {t('admin.shipments.subscriptions', 'Subscriptions')} ({subscriptions.length})
        </h2>
        
        {subscriptions.length === 0 ? (
          <p className="text-gray-500 text-center py-4">No subscriptions</p>
        ) : (
          <div className="space-y-2">
            {subscriptions.map((sub) => (
              <div key={sub.id} className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center">
                  <span className={`w-2 h-2 rounded-full mr-3 ${sub.active ? 'bg-green-500' : 'bg-gray-400'}`}></span>
                  <div>
                    <span className="font-medium capitalize">{sub.channel}</span>
                    <span className="text-gray-500 ml-2">{sub.contactValue}</span>
                  </div>
                </div>
                <div className="text-sm text-gray-500">
                  {sub.events.join(', ')}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default ShipmentDetailPage;
