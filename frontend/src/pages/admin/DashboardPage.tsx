import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { dashboardApi } from '../../services/adminApi';

const DashboardPage: React.FC = () => {
  const { t } = useTranslation();

  const { data: health, isLoading: healthLoading } = useQuery({
    queryKey: ['admin', 'health'],
    queryFn: () => dashboardApi.getHealth(),
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['admin', 'stats', 'today'],
    queryFn: () => dashboardApi.getStats('today'),
    refetchInterval: 60000, // Refresh every minute
  });

  const { data: sla, isLoading: slaLoading } = useQuery({
    queryKey: ['admin', 'sla'],
    queryFn: () => dashboardApi.getSlaMetrics(),
    refetchInterval: 300000, // Refresh every 5 minutes
  });

  const { data: queues, isLoading: queuesLoading } = useQuery({
    queryKey: ['admin', 'queues'],
    queryFn: () => dashboardApi.getQueueStatus(),
    refetchInterval: 10000, // Refresh every 10 seconds
  });

  const getHealthStatusColor = (status: string) => {
    switch (status) {
      case 'healthy':
        return 'text-green-600 bg-green-100';
      case 'degraded':
        return 'text-yellow-600 bg-yellow-100';
      case 'unhealthy':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getQueueStatusColor = (status: string) => {
    switch (status) {
      case 'normal':
        return 'bg-green-500';
      case 'moderate':
        return 'bg-yellow-500';
      case 'high':
        return 'bg-red-500';
      default:
        return 'bg-gray-500';
    }
  };

  return (
    <div className="space-y-6">
      {/* System Health */}
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">
          {t('admin.dashboard.systemHealth', 'System Health')}
        </h2>
        
        {healthLoading ? (
          <div className="animate-pulse space-y-2">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-8 bg-gray-200 rounded"></div>
            ))}
          </div>
        ) : health ? (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-gray-700">
                {t('admin.dashboard.overallStatus', 'Overall Status')}
              </span>
              <span className={`px-3 py-1 rounded-full text-sm font-medium ${getHealthStatusColor(health.status)}`}>
                {health.status.toUpperCase()}
              </span>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {Object.entries(health.services).map(([service, data]) => (
                <div key={service} className="border rounded-lg p-4">
                  <div className="flex items-center justify-between">
                    <span className="font-medium capitalize">{service}</span>
                    <span className={`w-3 h-3 rounded-full ${data.status === 'healthy' ? 'bg-green-500' : 'bg-red-500'}`}></span>
                  </div>
                  {data.latencyMs !== undefined && (
                    <p className="text-sm text-gray-500 mt-1">Latency: {data.latencyMs}ms</p>
                  )}
                  {data.pendingJobs !== undefined && (
                    <p className="text-sm text-gray-500 mt-1">Pending: {data.pendingJobs}</p>
                  )}
                </div>
              ))}
            </div>
          </div>
        ) : (
          <p className="text-gray-500">{t('admin.dashboard.noData', 'No data available')}</p>
        )}
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {/* Shipments Card */}
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-blue-100 text-blue-600">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">
                {t('admin.dashboard.totalShipments', 'Total Shipments')}
              </p>
              {statsLoading ? (
                <div className="h-8 w-20 bg-gray-200 rounded animate-pulse"></div>
              ) : (
                <p className="text-2xl font-semibold text-gray-900">
                  {stats?.shipments.total.toLocaleString() || 0}
                </p>
              )}
            </div>
          </div>
          {stats && (
            <p className="mt-2 text-sm text-gray-500">
              +{stats.shipments.period.toLocaleString()} today
            </p>
          )}
        </div>

        {/* Events Card */}
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-green-100 text-green-600">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">
                {t('admin.dashboard.totalEvents', 'Total Events')}
              </p>
              {statsLoading ? (
                <div className="h-8 w-20 bg-gray-200 rounded animate-pulse"></div>
              ) : (
                <p className="text-2xl font-semibold text-gray-900">
                  {stats?.events.total.toLocaleString() || 0}
                </p>
              )}
            </div>
          </div>
          {stats && (
            <p className="mt-2 text-sm text-gray-500">
              +{stats.events.period.toLocaleString()} today
            </p>
          )}
        </div>

        {/* Subscriptions Card */}
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-purple-100 text-purple-600">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">
                {t('admin.dashboard.activeSubscriptions', 'Active Subscriptions')}
              </p>
              {statsLoading ? (
                <div className="h-8 w-20 bg-gray-200 rounded animate-pulse"></div>
              ) : (
                <p className="text-2xl font-semibold text-gray-900">
                  {stats?.subscriptions.active.toLocaleString() || 0}
                </p>
              )}
            </div>
          </div>
          {stats && (
            <p className="mt-2 text-sm text-gray-500">
              of {stats.subscriptions.total.toLocaleString()} total
            </p>
          )}
        </div>

        {/* Users Card */}
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">
                {t('admin.dashboard.activeUsers', 'Active Users')}
              </p>
              {statsLoading ? (
                <div className="h-8 w-20 bg-gray-200 rounded animate-pulse"></div>
              ) : (
                <p className="text-2xl font-semibold text-gray-900">
                  {stats?.users.loggedInToday || 0}
                </p>
              )}
            </div>
          </div>
          {stats && (
            <p className="mt-2 text-sm text-gray-500">
              logged in today
            </p>
          )}
        </div>
      </div>

      {/* SLA Metrics & Queue Status */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* SLA Metrics */}
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">
            {t('admin.dashboard.slaMetrics', 'SLA Metrics (Last 7 Days)')}
          </h2>
          
          {slaLoading ? (
            <div className="animate-pulse space-y-4">
              {[1, 2].map((i) => (
                <div key={i} className="h-16 bg-gray-200 rounded"></div>
              ))}
            </div>
          ) : sla ? (
            <div className="space-y-4">
              <div className="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                <div>
                  <p className="text-sm text-gray-600">On-Time Delivery Rate</p>
                  <p className="text-2xl font-bold text-green-600">{sla.onTimeDeliveryRate}%</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-500">
                    {sla.onTimeCount} / {sla.deliveredCount}
                  </p>
                </div>
              </div>
              
              <div className="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                <div>
                  <p className="text-sm text-gray-600">Exception Rate</p>
                  <p className="text-2xl font-bold text-red-600">{sla.exceptionRate}%</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-500">
                    {sla.exceptionCount} exceptions
                  </p>
                </div>
              </div>
            </div>
          ) : (
            <p className="text-gray-500">{t('admin.dashboard.noData', 'No data available')}</p>
          )}
        </div>

        {/* Queue Status */}
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">
            {t('admin.dashboard.queueStatus', 'Queue Status')}
          </h2>
          
          {queuesLoading ? (
            <div className="animate-pulse space-y-2">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 bg-gray-200 rounded"></div>
              ))}
            </div>
          ) : queues ? (
            <div className="space-y-3">
              {Object.entries(queues).map(([name, data]) => (
                <div key={name} className="flex items-center justify-between p-3 border rounded-lg">
                  <div className="flex items-center">
                    <span className={`w-3 h-3 rounded-full mr-3 ${getQueueStatusColor(data.status)}`}></span>
                    <span className="font-medium capitalize">{name}</span>
                  </div>
                  <div className="text-right">
                    {data.size !== null ? (
                      <span className="text-sm text-gray-600">{data.size} pending</span>
                    ) : (
                      <span className="text-sm text-red-500">Error</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500">{t('admin.dashboard.noData', 'No data available')}</p>
          )}
        </div>
      </div>

      {/* Shipment Status Distribution */}
      {stats && (
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">
            {t('admin.dashboard.statusDistribution', 'Shipment Status Distribution')}
          </h2>
          
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
            {Object.entries(stats.shipments.byStatus).map(([status, count]) => (
              <div key={status} className="text-center p-3 border rounded-lg">
                <p className="text-2xl font-bold text-gray-900">{count.toLocaleString()}</p>
                <p className="text-xs text-gray-500 capitalize">{status.replace('_', ' ')}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default DashboardPage;
