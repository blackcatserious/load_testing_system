import React from 'react';

const Header: React.FC = () => {
  return (
    <header className="bg-gradient-to-r from-blue-600 to-blue-800 shadow-lg">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center py-4">
          <div className="flex items-center">
            <h1 className="text-2xl font-bold text-white">NIGHTMARE STRESSER</h1>
          </div>
          <nav className="hidden md:flex space-x-8">
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium">HOME</a>
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium">FEATURES</a>
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium">TOS</a>
            <a href="#" className="text-white hover:text-blue-200 px-3 py-2 text-sm font-medium">METHODS</a>
          </nav>
          <div>
            <button className="bg-white text-blue-600 px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-50 transition-colors">
              Login
            </button>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;
