import { useState } from 'react';
import { Home, Wrench, Rocket, Terminal, Shield, Users, ShoppingCart, FileText, Database, Zap, Activity } from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, ResponsiveContainer } from 'recharts';

const Dashboard = () => {
  const [activeMenuItem, setActiveMenuItem] = useState('HOME');

  const menuItems = [
    { name: 'HOME', icon: Home, active: true },
    { name: 'TOOLS', icon: Wrench, active: false },
    { name: 'LAUNCH', icon: Rocket, active: false },
    { name: 'CONSOLE', icon: Terminal, active: false },
    { name: 'LIVEPROOF', icon: Shield, active: false },
    { name: 'CUSTOMERS', icon: Users, active: false },
    { name: 'MARKETPLACE', icon: ShoppingCart, active: false },
    { name: 'MY DOCUMENTS', icon: FileText, active: false },
  ];

  const chartData = [
    { time: '00:00', success: 45, error: 12 },
    { time: '04:00', success: 52, error: 8 },
    { time: '08:00', success: 48, error: 15 },
    { time: '12:00', success: 61, error: 7 },
    { time: '16:00', success: 55, error: 11 },
    { time: '20:00', success: 67, error: 5 },
    { time: '24:00', success: 59, error: 9 },
  ];

  const activities = [
    { id: 1, title: 'New Telegram News Channel', description: 'For users who regularly publish our official news and updates', time: '2 hours ago' },
    { id: 2, title: 'Please Take Note Of This Package Downgrade', description: 'If anything is incorrect in the current account, please remember that we will not be responsible for any', time: '4 hours ago' },
    { id: 3, title: 'Our Website Supports All Platforms', description: 'Our cloud-based platform is fully optimized. Please visit our website and check whether our website is optimized for your device', time: '6 hours ago' },
    { id: 4, title: 'Please Check Our Terms And Conditions', description: 'Please check our terms and conditions for the all the legal advice', time: '8 hours ago' },
  ];

  return (
    <div className="flex h-screen bg-gray-900 text-white">
      {/* Sidebar */}
      <div className="w-64 bg-gray-800 border-r border-gray-700">
        <div className="p-6">
          <div className="flex items-center space-x-3 mb-8">
            <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
              <Shield className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-lg font-bold text-blue-400">Nightmare Stresser</h1>
            </div>
          </div>
          
          <nav className="space-y-2">
            {menuItems.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.name}
                  onClick={() => setActiveMenuItem(item.name)}
                  className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-left transition-colors ${
                    activeMenuItem === item.name
                      ? 'bg-blue-600 text-white'
                      : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                  }`}
                >
                  <Icon className="w-5 h-5" />
                  <span className="text-sm font-medium">{item.name}</span>
                </button>
              );
            })}
          </nav>
        </div>
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Metrics */}
        <div className="p-6 bg-gray-800 border-b border-gray-700">
          <div className="grid grid-cols-4 gap-6">
            <div className="flex items-center space-x-4">
              <div className="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                <Users className="w-6 h-6 text-white" />
              </div>
              <div>
                <div className="text-2xl font-bold text-white">632,576</div>
                <div className="text-sm text-gray-400">REGISTERED USERS</div>
              </div>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                <Database className="w-6 h-6 text-white" />
              </div>
              <div>
                <div className="text-2xl font-bold text-white">52</div>
                <div className="text-sm text-gray-400">TOTAL STRESS</div>
              </div>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                <Zap className="w-6 h-6 text-white" />
              </div>
              <div>
                <div className="text-2xl font-bold text-white">113</div>
                <div className="text-sm text-gray-400">RUNNING STRESS</div>
              </div>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                <Activity className="w-6 h-6 text-white" />
              </div>
              <div>
                <div className="text-2xl font-bold text-white">29</div>
                <div className="text-sm text-gray-400">TOTAL ATTACKS</div>
              </div>
            </div>
          </div>
        </div>

        {/* Content Area */}
        <div className="flex-1 flex overflow-hidden">
          {/* Charts Section */}
          <div className="flex-1 p-6">
            <div className="grid grid-cols-2 gap-6 mb-6">
              <div className="bg-gray-800 rounded-lg p-4">
                <h3 className="text-lg font-semibold text-white mb-4">📊 Weekly Attack Graph</h3>
                <div className="h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={chartData}>
                      <XAxis dataKey="time" stroke="#6B7280" />
                      <YAxis stroke="#6B7280" />
                      <Line 
                        type="monotone" 
                        dataKey="success" 
                        stroke="#06B6D4" 
                        strokeWidth={2}
                        dot={{ fill: '#06B6D4', strokeWidth: 2, r: 4 }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              </div>
              
              <div className="bg-gray-800 rounded-lg p-4">
                <h3 className="text-lg font-semibold text-white mb-4">📈 Latest News</h3>
                <div className="h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={chartData}>
                      <XAxis dataKey="time" stroke="#6B7280" />
                      <YAxis stroke="#6B7280" />
                      <Line 
                        type="monotone" 
                        dataKey="error" 
                        stroke="#EF4444" 
                        strokeWidth={2}
                        dot={{ fill: '#EF4444', strokeWidth: 2, r: 4 }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              </div>
            </div>
          </div>

          {/* Activity Feed */}
          <div className="w-80 bg-gray-800 border-l border-gray-700 p-6">
            <h3 className="text-lg font-semibold text-white mb-6">📢 Latest News</h3>
            <div className="space-y-4">
              {activities.map((activity) => (
                <div key={activity.id} className="flex space-x-3">
                  <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <div className="w-3 h-3 bg-white rounded-full"></div>
                  </div>
                  <div className="flex-1">
                    <div className="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm">
                      <div className="font-semibold mb-1">{activity.title}</div>
                      <div className="text-blue-100 text-xs">{activity.description}</div>
                    </div>
                    <div className="text-xs text-gray-400 mt-1">{activity.time}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
