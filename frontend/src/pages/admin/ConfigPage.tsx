import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { configApi } from '../../services/adminApi';

type TabType = 'facilities' | 'event-codes' | 'eta-rules' | 'system';

const ConfigPage: React.FC = () => {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<TabType>('facilities');

  const tabs: { id: TabType; name: string }[] = [
    { id: 'facilities', name: 'Facilities' },
    { id: 'event-codes', name: 'Event Codes' },
    { id: 'eta-rules', name: 'ETA Rules' },
    { id: 'system', name: 'System' },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">
        {t('admin.config.title', 'Configuration')}
      </h1>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              {tab.name}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      {activeTab === 'facilities' && <FacilitiesTab />}
      {activeTab === 'event-codes' && <EventCodesTab />}
      {activeTab === 'eta-rules' && <EtaRulesTab />}
      {activeTab === 'system' && <SystemTab />}
    </div>
  );
};

const FacilitiesTab: React.FC = () => {
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'config', 'facilities', search],
    queryFn: () => configApi.getFacilities({ search, perPage: 100 }),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search facilities..."
          className="px-4 py-2 border rounded-md w-64"
        />
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">City</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {isLoading ? (
              [...Array(5)].map((_, i) => (
                <tr key={i} className="animate-pulse">
                  {[...Array(5)].map((_, j) => (
                    <td key={j} className="px-6 py-4">
                      <div className="h-4 bg-gray-200 rounded"></div>
                    </td>
                  ))}
                </tr>
              ))
            ) : data?.facilities.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                  No facilities found
                </td>
              </tr>
            ) : (
              data?.facilities.map((facility) => (
                <tr key={facility.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap font-mono text-sm">
                    {facility.code}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">{facility.name}</td>
                  <td className="px-6 py-4 whitespace-nowrap capitalize">{facility.type}</td>
                  <td className="px-6 py-4 whitespace-nowrap">{facility.city || '-'}</td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`px-2 py-1 text-xs rounded-full ${
                        facility.isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                      }`}
                    >
                      {facility.isActive ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
};

const EventCodesTab: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'config', 'event-codes'],
    queryFn: () => configApi.getEventCodes(),
  });

  return (
    <div className="bg-white rounded-lg shadow overflow-hidden">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status Mapping</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {isLoading ? (
            [...Array(5)].map((_, i) => (
              <tr key={i} className="animate-pulse">
                {[...Array(4)].map((_, j) => (
                  <td key={j} className="px-6 py-4">
                    <div className="h-4 bg-gray-200 rounded"></div>
                  </td>
                ))}
              </tr>
            ))
          ) : (
            data &&
            Object.entries(data).map(([code, info]: [string, any]) => (
              <tr key={code} className="hover:bg-gray-50">
                <td className="px-6 py-4 whitespace-nowrap font-mono text-sm font-medium">
                  {code}
                </td>
                <td className="px-6 py-4">{info.description}</td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className="px-2 py-1 bg-gray-100 rounded text-sm">{info.status}</span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap capitalize">{info.category}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
};

const EtaRulesTab: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'config', 'eta-rules'],
    queryFn: () => configApi.getEtaRules(),
  });

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-4">
        {[...Array(3)].map((_, i) => (
          <div key={i} className="h-20 bg-gray-200 rounded"></div>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4">ETA Rules</h3>
        {data?.rules.length === 0 ? (
          <p className="text-gray-500">No ETA rules configured</p>
        ) : (
          <div className="space-y-2">
            {data?.rules.map((rule: any, i: number) => (
              <div key={i} className="p-3 border rounded-lg">
                <div className="flex justify-between">
                  <span className="font-medium">
                    {rule.origin_region} to {rule.destination_region}
                  </span>
                  <span className="text-gray-500">{rule.base_days} days</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4">ETA Lanes</h3>
        {data?.lanes.length === 0 ? (
          <p className="text-gray-500">No ETA lanes configured</p>
        ) : (
          <div className="space-y-2">
            {data?.lanes.map((lane: any, i: number) => (
              <div key={i} className="p-3 border rounded-lg">
                <div className="flex justify-between">
                  <span className="font-medium">
                    {lane.origin_facility_id} to {lane.destination_facility_id}
                  </span>
                  <span className="text-gray-500">{lane.transit_days} days</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

const SystemTab: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'config', 'system'],
    queryFn: () => configApi.getSystemConfig(),
  });

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-4">
        {[...Array(3)].map((_, i) => (
          <div key={i} className="h-20 bg-gray-200 rounded"></div>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {data &&
        Object.entries(data).map(([section, config]: [string, any]) => (
          <div key={section} className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4 capitalize">{section}</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {Object.entries(config).map(([key, value]: [string, any]) => (
                <div key={key} className="p-3 border rounded-lg">
                  <p className="text-sm text-gray-500">{key.replace(/_/g, ' ')}</p>
                  <p className="font-medium">{String(value)}</p>
                </div>
              ))}
            </div>
          </div>
        ))}
    </div>
  );
};

export default ConfigPage;
