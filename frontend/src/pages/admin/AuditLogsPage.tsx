import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { dashboardApi } from '../../services/adminApi';
import { AuditLogSearchParams } from '../../types/admin.types';

const AuditLogsPage: React.FC = () => {
  const { t } = useTranslation();
  const [params, setParams] = useState<AuditLogSearchParams>({
    perPage: 50,
  });
  const [expandedLog, setExpandedLog] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'audit-logs', params],
    queryFn: () => dashboardApi.getAuditLogs(params),
  });

  const handleFilterChange = (key: keyof AuditLogSearchParams, value: string) => {
    setParams((prev) => ({ ...prev, [key]: value || undefined }));
  };

  const getActionBadgeColor = (action: string) => {
    const colors: Record<string, string> = {
      create: 'bg-green-100 text-green-800',
      update: 'bg-blue-100 text-blue-800',
      delete: 'bg-red-100 text-red-800',
      login: 'bg-purple-100 text-purple-800',
      logout: 'bg-gray-100 text-gray-800',
      login_failed: 'bg-red-100 text-red-800',
      role_changed: 'bg-yellow-100 text-yellow-800',
      manual_event: 'bg-indigo-100 text-indigo-800',
      event_correction: 'bg-orange-100 text-orange-800',
      config_change: 'bg-pink-100 text-pink-800',
      export: 'bg-cyan-100 text-cyan-800',
    };
    return colors[action] || 'bg-gray-100 text-gray-800';
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">
        {t('admin.audit.title', 'Audit Logs')}
      </h1>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Action</label>
            <select
              value={params.action || ''}
              onChange={(e) => handleFilterChange('action', e.target.value)}
              className="w-full px-3 py-2 border rounded-md"
            >
              <option value="">All Actions</option>
              <option value="create">Create</option>
              <option value="update">Update</option>
              <option value="delete">Delete</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
              <option value="login_failed">Login Failed</option>
              <option value="role_changed">Role Changed</option>
              <option value="manual_event">Manual Event</option>
              <option value="event_correction">Event Correction</option>
              <option value="config_change">Config Change</option>
              <option value="export">Export</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Entity Type</label>
            <select
              value={params.entityType || ''}
              onChange={(e) => handleFilterChange('entityType', e.target.value)}
              className="w-full px-3 py-2 border rounded-md"
            >
              <option value="">All Types</option>
              <option value="App\\Models\\User">User</option>
              <option value="App\\Models\\Shipment">Shipment</option>
              <option value="App\\Models\\Event">Event</option>
              <option value="App\\Models\\Facility">Facility</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input
              type="date"
              value={params.dateFrom || ''}
              onChange={(e) => handleFilterChange('dateFrom', e.target.value)}
              className="w-full px-3 py-2 border rounded-md"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input
              type="date"
              value={params.dateTo || ''}
              onChange={(e) => handleFilterChange('dateTo', e.target.value)}
              className="w-full px-3 py-2 border rounded-md"
            />
          </div>
        </div>
      </div>

      {/* Logs Table */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {isLoading ? (
              [...Array(10)].map((_, i) => (
                <tr key={i} className="animate-pulse">
                  {[...Array(6)].map((_, j) => (
                    <td key={j} className="px-6 py-4">
                      <div className="h-4 bg-gray-200 rounded"></div>
                    </td>
                  ))}
                </tr>
              ))
            ) : data?.logs.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-6 py-4 text-center text-gray-500">
                  No audit logs found
                </td>
              </tr>
            ) : (
              data?.logs.map((log) => (
                <React.Fragment key={log.id}>
                  <tr className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {new Date(log.createdAt).toLocaleString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {log.user ? (
                        <div>
                          <p className="text-sm font-medium text-gray-900">{log.user.name}</p>
                          <p className="text-xs text-gray-500">{log.user.email}</p>
                        </div>
                      ) : (
                        <span className="text-gray-400">System</span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getActionBadgeColor(log.action)}`}>
                        {log.action.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {log.entityType && (
                        <div>
                          <p className="text-gray-900">{log.entityType.split('\\').pop()}</p>
                          {log.entityId && (
                            <p className="text-xs text-gray-500 font-mono">{log.entityId.slice(0, 8)}...</p>
                          )}
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {log.ipAddress || '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {(log.oldValues || log.newValues || log.metadata) && (
                        <button
                          onClick={() => setExpandedLog(expandedLog === log.id ? null : log.id)}
                          className="text-blue-600 hover:text-blue-800"
                        >
                          {expandedLog === log.id ? 'Hide' : 'View'}
                        </button>
                      )}
                    </td>
                  </tr>
                  {expandedLog === log.id && (
                    <tr>
                      <td colSpan={6} className="px-6 py-4 bg-gray-50">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                          {log.oldValues && (
                            <div>
                              <p className="text-sm font-medium text-gray-700 mb-2">Old Values</p>
                              <pre className="text-xs bg-white p-2 rounded border overflow-x-auto">
                                {JSON.stringify(log.oldValues, null, 2)}
                              </pre>
                            </div>
                          )}
                          {log.newValues && (
                            <div>
                              <p className="text-sm font-medium text-gray-700 mb-2">New Values</p>
                              <pre className="text-xs bg-white p-2 rounded border overflow-x-auto">
                                {JSON.stringify(log.newValues, null, 2)}
                              </pre>
                            </div>
                          )}
                          {log.metadata && (
                            <div>
                              <p className="text-sm font-medium text-gray-700 mb-2">Metadata</p>
                              <pre className="text-xs bg-white p-2 rounded border overflow-x-auto">
                                {JSON.stringify(log.metadata, null, 2)}
                              </pre>
                            </div>
                          )}
                        </div>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))
            )}
          </tbody>
        </table>

        {/* Pagination */}
        {data && data.pagination.lastPage > 1 && (
          <div className="px-6 py-4 border-t flex items-center justify-between">
            <div className="text-sm text-gray-700">
              Page {data.pagination.currentPage} of {data.pagination.lastPage}
            </div>
            <div className="flex space-x-2">
              <button
                onClick={() => setParams((p) => ({ ...p, page: (p.page || 1) - 1 }))}
                disabled={data.pagination.currentPage === 1}
                className="px-3 py-1 border rounded disabled:opacity-50"
              >
                Previous
              </button>
              <button
                onClick={() => setParams((p) => ({ ...p, page: (p.page || 1) + 1 }))}
                disabled={data.pagination.currentPage === data.pagination.lastPage}
                className="px-3 py-1 border rounded disabled:opacity-50"
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

export default AuditLogsPage;
