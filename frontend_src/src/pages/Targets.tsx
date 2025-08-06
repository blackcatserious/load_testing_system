import React, { useState, useEffect } from 'react';

interface Target {
  id: string;
  label: string;
  url: string;
  tags: string[];
  status: 'active' | 'inactive' | 'testing';
  last_tested: string;
  success_rate: number;
}

const Targets: React.FC = () => {
  const [targets, setTargets] = useState<Target[]>([]);
  const [newTarget, setNewTarget] = useState({ label: '', url: '', tags: '' });
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchTargets();
  }, []);

  const fetchTargets = async () => {
    try {
      const response = await fetch('/api/targets_endpoint.php?action=list');
      const data = await response.json();
      if (data.success && data.targets) {
        setTargets(data.targets);
      } else {
        console.log('Loading mock targets data');
        setTargets([
          {
            id: 'target_1',
            label: 'Cloudflare-Protected-1',
            url: 'https://proverj.com/dr-shihirman/',
            tags: ['cloudflare', 'nginx'],
            status: 'active',
            last_tested: '2025-08-06T18:14:00Z',
            success_rate: 95.8
          },
          {
            id: 'target_2',
            label: 'DDoS-Guard-Protected-1',
            url: 'https://life.ru/p/1643820',
            tags: ['ddos-guard', 'nginx'],
            status: 'testing',
            last_tested: '2025-08-06T17:30:00Z',
            success_rate: 87.2
          },
          {
            id: 'target_3',
            label: 'HTTPBin-Test',
            url: 'https://httpbin.org/delay/30',
            tags: ['test', 'timeout'],
            status: 'inactive',
            last_tested: '2025-08-06T16:45:00Z',
            success_rate: 12.5
          }
        ]);
      }
    } catch (error) {
      console.error('Failed to fetch targets:', error);
      setTargets([
        {
          id: 'target_1',
          label: 'Cloudflare-Protected-1',
          url: 'https://proverj.com/dr-shihirman/',
          tags: ['cloudflare', 'nginx'],
          status: 'active',
          last_tested: '2025-08-06T18:14:00Z',
          success_rate: 95.8
        },
        {
          id: 'target_2',
          label: 'DDoS-Guard-Protected-1',
          url: 'https://life.ru/p/1643820',
          tags: ['ddos-guard', 'nginx'],
          status: 'testing',
          last_tested: '2025-08-06T17:30:00Z',
          success_rate: 87.2
        },
        {
          id: 'target_3',
          label: 'HTTPBin-Test',
          url: 'https://httpbin.org/delay/30',
          tags: ['test', 'timeout'],
          status: 'inactive',
          last_tested: '2025-08-06T16:45:00Z',
          success_rate: 12.5
        }
      ]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleAddTarget = async () => {
    if (!newTarget.label || !newTarget.url) return;

    const target = {
      label: newTarget.label,
      url: newTarget.url,
      tags: newTarget.tags.split(',').map(tag => tag.trim()).filter(Boolean)
    };

    try {
      const response = await fetch('/api/targets_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', target })
      });

      if (response.ok) {
        setNewTarget({ label: '', url: '', tags: '' });
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to add target:', error);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active': return 'bg-green-100 text-green-800';
      case 'testing': return 'bg-blue-100 text-blue-800';
      case 'inactive': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading targets...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Targets Management</h1>
          <p className="mt-2 text-gray-600">Manage and monitor your load testing targets</p>
        </div>

        {/* Add New Target */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Add New Target</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Label</label>
              <input
                type="text"
                value={newTarget.label}
                onChange={(e) => setNewTarget({ ...newTarget, label: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Target label"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">URL</label>
              <input
                type="url"
                value={newTarget.url}
                onChange={(e) => setNewTarget({ ...newTarget, url: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="https://example.com"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Tags</label>
              <input
                type="text"
                value={newTarget.tags}
                onChange={(e) => setNewTarget({ ...newTarget, tags: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="cloudflare, nginx"
              />
            </div>
          </div>
          <div className="mt-4">
            <button
              onClick={handleAddTarget}
              className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Add Target
            </button>
          </div>
        </div>

        {/* Targets List */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Active Targets ({targets.length})</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Target
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    URL
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Success Rate
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Last Tested
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tags
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {targets.map((target) => (
                  <tr key={target.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">{target.label}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900 max-w-xs truncate">{target.url}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(target.status)}`}>
                        {target.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">{target.success_rate}%</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {new Date(target.last_tested).toLocaleString()}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex flex-wrap gap-1">
                        {target.tags.map((tag, index) => (
                          <span key={index} className="inline-flex px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">
                            {tag}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button className="text-blue-600 hover:text-blue-900 mr-3">Test</button>
                      <button className="text-red-600 hover:text-red-900">Remove</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Targets;
