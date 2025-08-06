import React, { useState, useEffect } from 'react';
import { BarChart3, Activity, FileText, Settings as SettingsIcon, Database, Play, TrendingUp, Users, Clock, AlertTriangle } from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { useLiveMetrics } from '../api/hooks';

interface GroupRun {
  group_id: string;
  status: 'running' | 'completed' | 'failed';
  duration: number;
  targets_count: number;
  started_at: string;
  top_errors: string[];
}

type TabType = 'overview' | 'runs' | 'details' | 'logs' | 'settings' | 'reports';

const Dashboard: React.FC = () => {
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [groupRuns, setGroupRuns] = useState<GroupRun[]>([]);

  const { data: metrics, error: metricsError } = useLiveMetrics(5000);

  const [engine, setEngine] = useState(localStorage.getItem('engine') || 'playwright');
  const [behaviorProfile, setBehaviorProfile] = useState(localStorage.getItem('behaviorProfile') || 'casual');
  const [intensity, setIntensity] = useState(localStorage.getItem('intensity') || 'medium');

  useEffect(() => {
    const fetchGroupRuns = async () => {
      try {
        const response = await fetch('/api/group_runs_endpoint.php?action=list');
        if (response.ok) {
          const data = await response.json();
          setGroupRuns(data.groups || []);
        }
      } catch (err) {
        console.error('Failed to fetch group runs:', err);
      }
    };

    fetchGroupRuns();
  }, []);

  useEffect(() => {
    localStorage.setItem('engine', engine);
    localStorage.setItem('behaviorProfile', behaviorProfile);
    localStorage.setItem('intensity', intensity);
  }, [engine, behaviorProfile, intensity]);

  const tabs = [
    { id: 'overview' as TabType, label: 'Overview', icon: BarChart3 },
    { id: 'runs' as TabType, label: 'Runs', icon: Play },
    { id: 'details' as TabType, label: 'Run Details', icon: Activity },
    { id: 'logs' as TabType, label: 'Logs', icon: FileText },
    { id: 'settings' as TabType, label: 'Settings', icon: SettingsIcon },
    { id: 'reports' as TabType, label: 'Reports', icon: Database },
  ];

  const statusCodeData = metrics ? [
    { name: '2xx Success', value: metrics.status_codes?.['2xx'] || metrics.success_count || 0, color: '#10b981' },
    { name: '4xx Client Error', value: metrics.status_codes?.['4xx'] || metrics.client_error_count || 0, color: '#f59e0b' },
    { name: '5xx Server Error', value: metrics.status_codes?.['5xx'] || metrics.server_error_count || 0, color: '#ef4444' },
    { name: '403 Forbidden', value: metrics.status_codes?.['403'] || 0, color: '#8b5cf6' },
    { name: '429 Rate Limited', value: metrics.status_codes?.['429'] || 0, color: '#f97316' },
    { name: '524 Timeout', value: metrics.status_codes?.['524'] || 0, color: '#6b7280' },
  ] : [];

  const renderOverviewTab = () => (
    <div className="space-y-6">
      {/* Control Panel */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Load Testing Controls</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Engine</label>
            <select
              value={engine}
              onChange={(e) => setEngine(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="playwright">Playwright</option>
              <option value="raw">Raw HTTP</option>
              <option value="fetch">Fetch API</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Behavior Profile</label>
            <select
              value={behaviorProfile}
              onChange={(e) => setBehaviorProfile(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="casual">Casual</option>
              <option value="scanner">Scanner</option>
              <option value="power">Power User</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Intensity</label>
            <select
              value={intensity}
              onChange={(e) => setIntensity(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="custom">Custom</option>
            </select>
          </div>
        </div>
      </div>

      {/* Live Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center">
            <TrendingUp className="h-8 w-8 text-blue-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">RPS</p>
              <p className="text-2xl font-bold text-gray-900">{metrics?.rps || metrics?.requests_per_second || 0}</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center">
            <Users className="h-8 w-8 text-green-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Threads</p>
              <p className="text-2xl font-bold text-gray-900">{metrics?.threads || metrics?.active_threads || 0}</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center">
            <Clock className="h-8 w-8 text-yellow-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Avg Latency</p>
              <p className="text-2xl font-bold text-gray-900">{metrics?.avg_latency || metrics?.average_response_time || 0}ms</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center">
            <AlertTriangle className="h-8 w-8 text-red-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Errors</p>
              <p className="text-2xl font-bold text-gray-900">{metrics?.errors || metrics?.error_count || 0}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Status Code Visualization */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Status Code Distribution</h3>
          <ResponsiveContainer width="100%" height={300}>
            <PieChart>
              <Pie
                data={statusCodeData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="value"
              >
                {statusCodeData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Status Code Breakdown</h3>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={statusCodeData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip />
              <Bar dataKey="value" fill="#3b82f6" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Last Targets Section */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Last Targets</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Group ID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Duration
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Targets
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Started
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Top Errors
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {groupRuns.slice(0, 10).map((run) => (
                <tr key={run.group_id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {run.group_id}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                      run.status === 'running' ? 'bg-blue-100 text-blue-800' :
                      run.status === 'completed' ? 'bg-green-100 text-green-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {run.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {run.duration}s
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {run.targets_count}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {new Date(run.started_at).toLocaleString()}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {run.top_errors.slice(0, 2).join(', ')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  const renderTabContent = () => {
    switch (activeTab) {
      case 'overview':
        return renderOverviewTab();
      case 'runs':
        return <div className="p-6">Runs content coming soon...</div>;
      case 'details':
        return <div className="p-6">Run details content coming soon...</div>;
      case 'logs':
        return <div className="p-6">Logs content coming soon...</div>;
      case 'settings':
        return <div className="p-6">Settings content coming soon...</div>;
      case 'reports':
        return <div className="p-6">Reports content coming soon...</div>;
      default:
        return renderOverviewTab();
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
          <p className="text-gray-600 mt-1">
            Manage and monitor your load testing operations
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <div className={`w-3 h-3 rounded-full animate-pulse ${
            metricsError ? 'bg-red-500' : metrics ? 'bg-green-500' : 'bg-yellow-500'
          }`}></div>
          <span className="text-sm text-gray-600">
            {metricsError ? 'System Error' : metrics ? 'System Online' : 'Connecting...'}
          </span>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border border-gray-200">
        <div className="border-b border-gray-200">
          <nav className="flex space-x-8 px-6" aria-label="Tabs">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center space-x-2`}
                >
                  <Icon className="h-4 w-4" />
                  <span>{tab.label}</span>
                </button>
              );
            })}
          </nav>
        </div>
        <div className="p-6">
          {renderTabContent()}
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
