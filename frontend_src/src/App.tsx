import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Dashboard from './pages/Dashboard';
import Reports from './pages/Reports';
import Targets from './pages/Targets';
import Header from './components/Header';
import './index.css';

function App() {
  return (
    <Router>
      <div className="min-h-screen bg-gradient-to-br from-blue-400 via-blue-500 to-blue-600">
        <Header />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="bg-white rounded-lg shadow-lg p-6">
            <Routes>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<Dashboard />} />
              <Route path="/targets" element={<Targets />} />
              <Route path="/reports" element={<Reports />} />
              <Route path="/runs" element={<Dashboard />} />
            </Routes>
          </div>
        </div>
      </div>
    </Router>
  );
}

export default App;
