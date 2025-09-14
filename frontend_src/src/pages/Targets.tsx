import React, { useState, useEffect } from 'react';

interface Target {
  id: string;
  label: string;
  url: string;
  tags: string[];
  status: 'active' | 'inactive' | 'testing';
  last_tested: string;
  success_rate: number;
  attack_method?: string;
  engine?: string;
  proxy_profile?: string;
  stealth_profile?: string;
}

const Targets: React.FC = () => {
  const [targets, setTargets] = useState<Target[]>([]);
  const [newTarget, setNewTarget] = useState({ 
    label: '', 
    url: '', 
    tags: '',
    attack_method: 'post-spam',
    engine: 'playwright',
    proxy_profile: 'rotating',
    stealth_profile: 'medium'
  });
  const [bulkTargets, setBulkTargets] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [unlimitedMode, setUnlimitedMode] = useState(true);
  const [showBulkImport, setShowBulkImport] = useState(false);

  useEffect(() => {
    fetchTargets();
  }, []);
  
  const handleStartAttack = async (groupId: string) => {
    try {
      const response = await fetch('/api/start_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start',
          group_id: groupId,
          profile_id: 'ramp-up',
          threads: unlimitedMode ? 100000 : 500,
          duration: unlimitedMode ? 2592000 : 3600, // 30 days or 1 hour
          engine: 'auto-bypass',
          behavior_profile_id: 'high'
        })
      });
      
      if (response.ok) {
        alert('Attack launched successfully!');
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to start attack:', error);
    }
  };
  
  const handleStopAttack = async (groupId: string) => {
    try {
      const response = await fetch('/api/stop_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'stop',
          group_id: groupId
        })
      });
      
      if (response.ok) {
        alert('Attack stopped successfully!');
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to stop attack:', error);
    }
  };

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
            success_rate: 95.8,
            attack_method: 'bypassv2',
            engine: 'playwright',
            proxy_profile: 'rotating',
            stealth_profile: 'high'
          },
          {
            id: 'target_2',
            label: 'DDoS-Guard-Protected',
            url: 'https://life.ru/p/1643820',
            tags: ['ddos-guard', 'cdn'],
            status: 'active',
            last_tested: '2025-08-06T18:10:00Z',
            success_rate: 87.2,
            attack_method: 'auto-bypass',
            engine: 'headless',
            proxy_profile: 'residential',
            stealth_profile: 'extreme'
          },
          {
            id: 'target_3',
            label: 'API-Endpoint',
            url: 'https://httpbin.org/get',
            tags: ['api', 'test'],
            status: 'active',
            last_tested: '2025-08-06T17:55:00Z',
            success_rate: 99.9,
            attack_method: 'http-spammer',
            engine: 'fetch',
            proxy_profile: 'datacenter',
            stealth_profile: 'low'
          }
        ]);
      }
      setIsLoading(false);
    } catch (error) {
      console.error('Failed to fetch targets:', error);
      setIsLoading(false);
    }
  };

  const handleAddTarget = async () => {
    try {
      const target = {
        label: newTarget.label,
        url: newTarget.url,
        tags: newTarget.tags.split(',').map(tag => tag.trim()),
        attack_method: newTarget.attack_method,
        engine: newTarget.engine,
        proxy_profile: newTarget.proxy_profile,
        stealth_profile: newTarget.stealth_profile
      };

      const response = await fetch('/api/targets_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add',
          target
        })
      });

      if (response.ok) {
        setNewTarget({ 
          label: '', 
          url: '', 
          tags: '',
          attack_method: 'post-spam',
          engine: 'playwright',
          proxy_profile: 'rotating',
          stealth_profile: 'medium'
        });
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to add target:', error);
    }
  };

  const handleBulkImport = async () => {
    try {
      // Parse URLs from textarea (one per line)
      const urls = bulkTargets.split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0);
      
      // Create target objects for each URL
      const targetsToImport = urls.map(url => ({
        label: url.replace(/^https?:\/\//, '').split('/')[0],
        url: url,
        tags: ['imported', 'bulk'],
        attack_method: 'auto-bypass',
        engine: 'playwright',
        proxy_profile: 'rotating',
        stealth_profile: 'high'
      }));
      
      const response = await fetch('/api/targets_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'import',
          targets: targetsToImport
        })
      });
      
      if (response.ok) {
        setBulkTargets('');
        setShowBulkImport(false);
        fetchTargets();
        alert(`Successfully imported ${targetsToImport.length} targets`);
      }
    } catch (error) {
      console.error('Failed to bulk import targets:', error);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return 'bg-green-100 text-green-800';
      case 'inactive':
        return 'bg-gray-100 text-gray-800';
      case 'testing':
        return 'bg-yellow-100 text-yellow-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const toggleUnlimitedMode = () => {
    setUnlimitedMode(!unlimitedMode);
  };

  const handleStartAllAttacks = async () => {
    try {
      const response = await fetch('/api/group_runs_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start_group',
          targets: targets.map(t => t.url),
          profile_id: 'ramp-up',
          threads: unlimitedMode ? 100000 : 500,
          duration: unlimitedMode ? 2592000 : 3600,
          engine: 'auto-bypass',
          behavior_profile_id: 'high'
        })
      });
      
      if (response.ok) {
        const data = await response.json();
        alert(`Battle attack launched on all targets! Group ID: ${data.group_id}`);
      }
    } catch (error) {
      console.error('Failed to start group attack:', error);
    }
  };

  const handleStopAllAttacks = async () => {
    try {
      const response = await fetch('/api/group_runs_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'stop_all'
        })
      });
      
      if (response.ok) {
        alert('All attacks stopped successfully!');
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to stop all attacks:', error);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-white">Targets</h1>
          <p className="text-gray-300 mt-1">
            Manage and monitor your load testing targets
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
            unlimitedMode ? 'bg-red-800 text-red-200' : 'bg-blue-800 text-blue-200'
          }`}>
            {unlimitedMode ? '⚡ UNLIMITED MODE' : 'Standard Mode'}
          </span>
          <button 
            onClick={toggleUnlimitedMode}
            className={`px-3 py-1 rounded-md text-sm font-medium ${
              unlimitedMode 
                ? 'bg-red-600 text-white hover:bg-red-700' 
                : 'bg-blue-600 text-white hover:bg-blue-700'
            }`}
          >
            {unlimitedMode ? 'Disable Unlimited' : 'Enable Unlimited'}
          </button>
        </div>
      </div>

      {/* BATTLE MODE - UNLIMITED TARGETS */}
      <div className="bg-gradient-to-r from-gray-800 to-gray-900 rounded-lg shadow-lg border border-gray-700 p-6 text-white mb-8">
        <h2 className="text-xl font-bold mb-4">🎯 BATTLE MODE - UNLIMITED TARGETS</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button 
            onClick={handleStartAllAttacks}
            className="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-500 font-bold shadow-lg transform hover:scale-105 transition-all"
          >
            ⚡ LAUNCH ALL TARGETS
          </button>
          <button 
            onClick={handleStopAllAttacks}
            className="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-500 font-bold shadow-lg transform hover:scale-105 transition-all"
          >
            🛑 STOP ALL ATTACKS
          </button>
        </div>
        <div className="mt-4 text-sm opacity-90">
          <p>⚠️ UNLIMITED MODE: 100,000+ threads per target | 10M+ proxies | 20-30 parallel groups</p>
          <p>🎯 Target degradation mode: 503/524 errors | Infrastructure attack: DNS + CDN</p>
          <p>🔄 Advanced methods: HTTP/2 flood, Slowloris, TLS abuse, Crawl &amp; Drown</p>
        </div>
      </div>

      <div className="bg-gray-800 rounded-lg shadow-lg border border-gray-700 p-6">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-semibold text-white">Target List</h2>
          <div className="flex space-x-2">
            <button 
              onClick={() => setShowBulkImport(!showBulkImport)}
              className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              {showBulkImport ? 'Cancel Bulk Import' : 'Bulk Import'}
            </button>
            <button 
              onClick={fetchTargets}
              className="bg-gray-700 text-gray-300 px-4 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
              Refresh
            </button>
          </div>
        </div>

        {/* Bulk Import Form */}
        {showBulkImport && (
          <div className="mb-6 p-4 border border-blue-700 rounded-lg bg-blue-900">
            <h3 className="text-lg font-medium text-blue-200 mb-2">Bulk Import Targets</h3>
            <p className="text-sm text-blue-300 mb-4">
              Enter one URL per line. Each URL will be imported as a separate target.
            </p>
            <textarea
              value={bulkTargets}
              onChange={(e) => setBulkTargets(e.target.value)}
              className="w-full h-32 p-2 border border-blue-600 rounded-md bg-blue-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
              placeholder="https://example.com&#10;https://another-example.com&#10;https://third-example.com"
            ></textarea>
            <div className="flex justify-end">
              <button
                onClick={handleBulkImport}
                className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                Import Targets
              </button>
            </div>
          </div>
        )}

        {/* Add Target Form */}
        <div className="mb-6 p-4 border border-gray-700 rounded-lg bg-gray-900">
          <h3 className="text-lg font-medium text-white mb-4">Add New Target</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Label</label>
              <input
                type="text"
                value={newTarget.label}
                onChange={(e) => setNewTarget({ ...newTarget, label: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Target Label"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">URL</label>
              <input
                type="text"
                value={newTarget.url}
                onChange={(e) => setNewTarget({ ...newTarget, url: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="https://example.com"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Tags (comma separated)</label>
              <input
                type="text"
                value={newTarget.tags}
                onChange={(e) => setNewTarget({ ...newTarget, tags: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="tag1, tag2, tag3"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Attack Method</label>
              <select
                value={newTarget.attack_method}
                onChange={(e) => setNewTarget({ ...newTarget, attack_method: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="auto-bypass">Auto Bypass</option>
                <option value="bypassv2">Bypass v2</option>
                <option value="post-spam">POST Spam</option>
                <option value="http-spammer">HTTP Spammer</option>
                <option value="head-flood">HEAD Flood</option>
                <option value="slowloris">Slowloris</option>
                <option value="tls-flood">TLS Flood</option>
                <option value="http2-flood">HTTP/2 Flood</option>
                <option value="crawl-drown">Crawl &amp; Drown</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Engine</label>
              <select
                value={newTarget.engine}
                onChange={(e) => setNewTarget({ ...newTarget, engine: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="playwright">Playwright</option>
                <option value="fetch">Fetch</option>
                <option value="headless">Headless</option>
                <option value="browser-mix">Browser Mix</option>
                <option value="raw-socket">Raw Socket</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Proxy Profile</label>
              <select
                value={newTarget.proxy_profile}
                onChange={(e) => setNewTarget({ ...newTarget, proxy_profile: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="rotating">Rotating</option>
                <option value="residential">Residential</option>
                <option value="datacenter">Datacenter</option>
                <option value="mobile">Mobile</option>
                <option value="mixed">Mixed</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Stealth Profile</label>
              <select
                value={newTarget.stealth_profile}
                onChange={(e) => setNewTarget({ ...newTarget, stealth_profile: e.target.value })}
                className="w-full p-2 border border-gray-600 rounded-md bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="extreme">Extreme</option>
              </select>
            </div>
          </div>
          <div className="mt-4 flex justify-end">
            <button
              onClick={handleAddTarget}
              className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Add Target
            </button>
          </div>
        </div>

        {/* Targets Table */}
        {isLoading ? (
          <div className="text-center py-8">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-gray-600 border-t-blue-600"></div>
            <p className="mt-2 text-gray-300">Loading targets...</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-600">
              <thead className="bg-gray-700">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Label</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">URL</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Tags</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Success Rate</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Last Tested</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-gray-800 divide-y divide-gray-600">
                {targets.length > 0 ? (
                  targets.map((target) => (
                    <tr key={target.id} className="hover:bg-gray-700">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">
                        {target.label}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-white">
                        <a href={target.url} target="_blank" rel="noopener noreferrer" className="text-blue-400 hover:text-blue-300">
                          {target.url}
                        </a>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex flex-wrap gap-1">
                          {target.tags.map((tag, index) => (
                            <span key={index} className="inline-block bg-blue-800 text-blue-200 text-xs px-2 py-1 rounded">
                              {tag}
                            </span>
                          ))}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(target.status)}`}>
                          {target.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-white">
                        <div className="flex items-center">
                          <div className="w-16 bg-gray-700 rounded-full h-2 mr-2">
                            <div
                              className={`h-2 rounded-full ${
                                (parseFloat(target.success_rate.toString()) || 0) >= 90 ? 'bg-green-500' :
                                (parseFloat(target.success_rate.toString()) || 0) >= 70 ? 'bg-yellow-500' :
                                'bg-red-500'
                              }`}
                              style={{ width: `${parseFloat(target.success_rate.toString()) || 0}%` }}
                            ></div>
                          </div>
                          <span>{target.success_rate}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-white">
                        {new Date(target.last_tested).toLocaleString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button
                          onClick={() => handleStartAttack(target.id)}
                          className="bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 mr-2"
                        >
                          Start Attack
                        </button>
                        <button
                          onClick={() => handleStopAttack(target.id)}
                          className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                        >
                          Stop Attack
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={7} className="px-6 py-4 text-center text-gray-400">
                      No targets available
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default Targets;
