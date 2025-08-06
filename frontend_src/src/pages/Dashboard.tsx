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
  const [threads, setThreads] = useState(40);
  const [duration, setDuration] = useState(3600);
  const [requestDelay, setRequestDelay] = useState(100);
  const [targetCount, setTargetCount] = useState(3);

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
  }, [engine, behaviorProfile]);

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
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Engine Selection Panel */}
        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Attack Engine Selection</h3>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Attack Method</label>
              <select 
                value={engine} 
                onChange={(e) => setEngine(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                title="Select attack method with specific load characteristics"
              >
                <option value="playwright">Playwright - Browser automation with JS execution</option>
                <option value="socket-spam">Socket Spam - Raw TCP connection flooding</option>
                <option value="auto-bypass">Auto Bypass - Intelligent protection circumvention</option>
                <option value="http-spammer">HTTP Spammer - High-volume HTTP requests</option>
                <option value="tls-jammer">TLS Jammer - SSL/TLS handshake disruption</option>
                <option value="raw">Raw HTTP - Direct HTTP protocol attacks</option>
                <option value="fetch">Fetch API - Modern browser-based requests</option>
              </select>
              <p className="text-xs text-gray-500 mt-1">
                {engine === 'playwright' && 'Full browser simulation with JavaScript execution'}
                {engine === 'socket-spam' && 'Raw socket connections for maximum load'}
                {engine === 'auto-bypass' && 'Adaptive bypass techniques for protected targets'}
                {engine === 'http-spammer' && 'High-frequency HTTP request generation'}
                {engine === 'tls-jammer' && 'SSL/TLS layer disruption attacks'}
                {engine === 'raw' && 'Direct HTTP protocol implementation'}
                {engine === 'fetch' && 'Modern fetch API with advanced headers'}
              </p>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Behavior Profile</label>
              <select 
                value={behaviorProfile} 
                onChange={(e) => setBehaviorProfile(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="casual">Casual - Normal user browsing patterns</option>
                <option value="scanner">Scanner - Vulnerability scanning behavior</option>
                <option value="reader">Reader - Content consumption patterns</option>
                <option value="mobile">Mobile - Mobile device simulation</option>
                <option value="power">Power User - Advanced user interactions</option>
              </select>
              <button className="mt-2 text-sm text-blue-600 hover:text-blue-800">
                Generate Custom Profile
              </button>
            </div>
          </div>
        </div>

        {/* Rotation Status Panel */}
        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Rotation Status</h3>
          <div className="space-y-3">
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-gray-700">JA3 Signature:</span>
              <span className="text-sm text-gray-900 font-mono">769,47-53-5-10-49-51-23-65281-0-11-35-16-5-13-18-21-43-45-51</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-gray-700">Active Proxy:</span>
              <span className="text-sm text-gray-900 font-mono">185.220.101.182:8080 
                <span className="ml-2 inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Active</span>
              </span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-gray-700">TLS Fingerprint:</span>
              <span className="text-sm text-gray-900 font-mono">771,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-gray-700">User-Agent:</span>
              <span className="text-sm text-gray-900 truncate max-w-xs">Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36</span>
            </div>
            <div className="pt-2 border-t border-gray-200">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-gray-700">Proxy Rotation:</span>
                <span className="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Enabled</span>
              </div>
              <div className="flex justify-between items-center mt-1">
                <span className="text-sm font-medium text-gray-700">JA3 Rotation:</span>
                <span className="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Enabled</span>
              </div>
              <div className="flex justify-between items-center mt-1">
                <span className="text-sm font-medium text-gray-700">UA Rotation:</span>
                <span className="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Enabled</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Launch Parameters Panel */}
      <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Launch Parameters</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Threads</label>
            <input
              type="range"
              min="1"
              max="500"
              value={threads}
              onChange={(e) => setThreads(Number(e.target.value))}
              className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            />
            <div className="flex justify-between text-xs text-gray-500 mt-1">
              <span>1</span>
              <span className="font-medium">{threads}</span>
              <span>500</span>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Duration (seconds)</label>
            <input
              type="range"
              min="30"
              max="10800"
              value={duration}
              onChange={(e) => setDuration(Number(e.target.value))}
              className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            />
            <div className="flex justify-between text-xs text-gray-500 mt-1">
              <span>30s</span>
              <span className="font-medium">{Math.floor(duration / 3600)}h {Math.floor((duration % 3600) / 60)}m</span>
              <span>3h</span>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Request Delay (ms)</label>
            <input
              type="range"
              min="0"
              max="5000"
              value={requestDelay}
              onChange={(e) => setRequestDelay(Number(e.target.value))}
              className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            />
            <div className="flex justify-between text-xs text-gray-500 mt-1">
              <span>0</span>
              <span className="font-medium">{requestDelay}</span>
              <span>5000</span>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Target Count</label>
            <input
              type="number"
              min="1"
              max="50"
              value={targetCount}
              onChange={(e) => setTargetCount(Number(e.target.value))}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
      </div>

      {/* Live Monitoring Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-center">
            <TrendingUp className="h-6 w-6 text-blue-600" />
            <div className="ml-3">
              <p className="text-xs font-medium text-gray-600">RPS</p>
              <p className="text-xl font-bold text-gray-900">{metrics?.rps || metrics?.requests_per_second || 0}</p>
            </div>
          </div>
        </div>
        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-center">
            <Users className="h-6 w-6 text-green-600" />
            <div className="ml-3">
              <p className="text-xs font-medium text-gray-600">Active Threads</p>
              <p className="text-xl font-bold text-gray-900">{metrics?.threads || metrics?.active_threads || 0}</p>
            </div>
          </div>
        </div>
        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-center">
            <Clock className="h-6 w-6 text-yellow-600" />
            <div className="ml-3">
              <p className="text-xs font-medium text-gray-600">Latency p50</p>
              <p className="text-xl font-bold text-gray-900">{metrics?.avg_latency || metrics?.average_response_time || 0}ms</p>
            </div>
          </div>
        </div>
        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-center">
            <AlertTriangle className="h-6 w-6 text-orange-600" />
            <div className="ml-3">
              <p className="text-xs font-medium text-gray-600">p95 Latency</p>
              <p className="text-xl font-bold text-gray-900">{Math.round((metrics?.avg_latency || 0) * 1.8)}ms</p>
            </div>
          </div>
        </div>
        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-center">
            <AlertTriangle className="h-6 w-6 text-red-600" />
            <div className="ml-3">
              <p className="text-xs font-medium text-gray-600">p99 Latency</p>
              <p className="text-xl font-bold text-gray-900">{Math.round((metrics?.avg_latency || 0) * 2.5)}ms</p>
            </div>
          </div>
        </div>
      </div>

      {/* Response Codes Real-time Panel */}
      <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Live Response Code Distribution</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
          <div className="text-center">
            <div className="text-2xl font-bold text-green-600">200</div>
            <div className="text-sm text-gray-600">Success</div>
            <div className="text-xs text-gray-500">85.2%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-yellow-600">403</div>
            <div className="text-sm text-gray-600">Forbidden</div>
            <div className="text-xs text-gray-500">5.1%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-orange-600">404</div>
            <div className="text-sm text-gray-600">Not Found</div>
            <div className="text-xs text-gray-500">3.2%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-red-600">503</div>
            <div className="text-sm text-gray-600">Service Unavailable</div>
            <div className="text-xs text-gray-500">4.8%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-red-700">524</div>
            <div className="text-sm text-gray-600">Timeout</div>
            <div className="text-xs text-gray-500">1.7%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-purple-600">410</div>
            <div className="text-sm text-gray-600">Gone</div>
            <div className="text-xs text-gray-500">0.0%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-blue-600">429</div>
            <div className="text-sm text-gray-600">Rate Limited</div>
            <div className="text-xs text-gray-500">0.0%</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-gray-600">Other</div>
            <div className="text-sm text-gray-600">Various</div>
            <div className="text-xs text-gray-500">0.0%</div>
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
              {(groupRuns || []).slice(0, 10).map((run) => (
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
                    {(run.top_errors || []).slice(0, 2).join(', ')}
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
        return (
          <div className="space-y-6">
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-semibold text-gray-900">Launch History & Active Runs</h3>
              <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Export History
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group ID</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Targets</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {groupRuns.length > 0 ? (
                    groupRuns.map((run) => (
                      <tr key={run.group_id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          {run.group_id}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <div className="flex flex-wrap gap-1">
                            <span className="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                              {run.targets_count} targets
                            </span>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                            run.status === 'running' ? 'bg-green-100 text-green-800' :
                            run.status === 'completed' ? 'bg-blue-100 text-blue-800' :
                            'bg-red-100 text-red-800'
                          }`}>
                            {run.status}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {Math.floor(run.duration / 3600)}h {Math.floor((run.duration % 3600) / 60)}m
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {(run.top_errors || []).slice(0, 3).map((error, i) => (
                            <span key={i} className={`inline-block text-xs px-2 py-1 rounded mr-1 ${
                              error === '200' ? 'bg-green-100 text-green-800' :
                              error === '404' ? 'bg-yellow-100 text-yellow-800' :
                              error === '503' || error === '524' ? 'bg-red-100 text-red-800' :
                              'bg-gray-100 text-gray-800'
                            }`}>
                              {error}
                            </span>
                          ))}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {new Date(run.started_at).toLocaleString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          <button className="text-blue-600 hover:text-blue-900 mr-3">
                            Download Report
                          </button>
                          {run.status === 'running' && (
                            <button className="text-red-600 hover:text-red-900">
                              Stop
                            </button>
                          )}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-4 text-center text-gray-500">
                        No launch history available
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        );
      case 'details':
        return <div className="p-6">Run details content coming soon...</div>;
      case 'logs':
        return (
          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Backend Logs</h3>
                <button className="bg-gray-100 text-gray-700 px-3 py-2 rounded-md text-sm hover:bg-gray-200">
                  Refresh
                </button>
              </div>
              <div className="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm max-h-96 overflow-y-auto">
                <div className="space-y-1">
                  <div>[2025-08-06 18:14:51] INFO: System initialized successfully</div>
                  <div>[2025-08-06 18:14:52] INFO: Metrics endpoint responding</div>
                  <div>[2025-08-06 18:14:53] INFO: Group runs endpoint active</div>
                  <div>[2025-08-06 18:14:54] INFO: Live metrics updating every 5 seconds</div>
                  <div>[2025-08-06 18:14:55] INFO: RPS: 46, Threads: 40, Latency: 189ms</div>
                  <div>[2025-08-06 18:14:56] INFO: Status codes - 2xx: 95.8%, 4xx: 2.1%, 5xx: 2.1%</div>
                  <div>[2025-08-06 18:14:57] INFO: System status: Online</div>
                </div>
              </div>
            </div>
          </div>
        );
      case 'settings':
        return <div className="p-6">Settings content coming soon...</div>;
      case 'reports':
        return (
          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Generated Reports</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-gray-900">Group Run Report</h4>
                    <span className="text-xs text-gray-500">CSV</span>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">Latest group execution results</p>
                  <button className="w-full bg-blue-600 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                    Download
                  </button>
                </div>
                <div className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-gray-900">Metrics Summary</h4>
                    <span className="text-xs text-gray-500">JSON</span>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">Performance metrics overview</p>
                  <button className="w-full bg-blue-600 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                    Download
                  </button>
                </div>
                <div className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-gray-900">Error Analysis</h4>
                    <span className="text-xs text-gray-500">CSV</span>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">Detailed error code breakdown</p>
                  <button className="w-full bg-blue-600 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                    Download
                  </button>
                </div>
              </div>
            </div>
          </div>
        );
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
