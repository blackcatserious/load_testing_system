import React from 'react';

const Header: React.FC = () => {
  return (
    <header className="bg-blue-500/20 backdrop-blur-sm border-b border-blue-300/30">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center py-4">
          <div className="flex items-center">
            <h1 className="text-2xl font-bold text-white">NIGHTMARE STRESSER</h1>
          </div>
          <nav className="hidden md:flex space-x-8">
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium transition-colors">HOME</a>
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium transition-colors">FEATURES</a>
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium transition-colors">TOS</a>
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium transition-colors">METHODS</a>
          </nav>
          <div>
            <button className="bg-blue-600/80 text-white px-6 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors border border-blue-400/50">
              Login
            </button>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;
