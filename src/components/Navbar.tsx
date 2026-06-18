import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Sun, Moon, LogOut, Menu, X, Award, Calendar, CheckSquare } from 'lucide-react';

export const Navbar: React.FC = () => {
  const { profile, signOut } = useAuth();
  const [isLightTheme, setIsLightTheme] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    // Check saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
      setIsLightTheme(true);
      document.body.classList.add('light-theme');
    }
  }, []);

  const toggleTheme = () => {
    if (isLightTheme) {
      document.body.classList.remove('light-theme');
      localStorage.setItem('theme', 'dark');
      setIsLightTheme(false);
    } else {
      document.body.classList.add('light-theme');
      localStorage.setItem('theme', 'light');
      setIsLightTheme(true);
    }
  };

  const handleLogout = async () => {
    await signOut();
    navigate('/login');
  };

  if (!profile) return null;

  return (
    <nav className="navbar">
      <div className="container navbar-inner">
        <Link to="/" className="brand">
          <span>Accolades Connect</span>
        </Link>

        {/* Mobile Toggle Button */}
        <button className="nav-toggle" onClick={() => setIsOpen(!isOpen)} style={{ display: 'block' }}>
          {isOpen ? <X size={24} /> : <Menu size={24} />}
        </button>

        {/* Nav Links */}
        <ul className={`nav-links ${isOpen ? 'active' : ''}`} style={isOpen ? { display: 'flex', flexDirection: 'column', position: 'absolute', top: '100%', left: 0, width: '100%', background: 'var(--bg-main)', padding: '1rem', borderBottom: '1px solid var(--border-color)' } : {}}>
          {profile.role === 'student' && (
            <>
              <li>
                <Link
                  to="/student"
                  className={`nav-link ${location.pathname === '/student' ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  <Calendar size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
                  Events
                </Link>
              </li>
              <li>
                <Link
                  to="/student/certificates"
                  className={`nav-link ${location.pathname === '/student/certificates' ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  <Award size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
                  Certificates
                </Link>
              </li>
            </>
          )}

          {profile.role === 'scanner' && (
            <li>
              <Link
                to="/scanner"
                className={`nav-link ${location.pathname === '/scanner' ? 'active' : ''}`}
                onClick={() => setIsOpen(false)}
              >
                <CheckSquare size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
                Scan Tickets
              </Link>
            </li>
          )}

          {profile.role === 'admin' && (
            <>
              <li>
                <Link
                  to="/admin"
                  className={`nav-link ${location.pathname === '/admin' ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  Dashboard
                </Link>
              </li>
              <li>
                <Link
                  to="/admin/registrations"
                  className={`nav-link ${location.pathname.startsWith('/admin/registrations') ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  Registrations
                </Link>
              </li>
              <li>
                <Link
                  to="/admin/payments"
                  className={`nav-link ${location.pathname.startsWith('/admin/payments') ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  Verify Payments
                </Link>
              </li>
            </>
          )}

          <li style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <button className="theme-toggle-btn" onClick={toggleTheme} title="Toggle Theme">
              {isLightTheme ? <Moon className="moon-icon" size={18} /> : <Sun className="sun-icon" size={18} />}
            </button>

            <button onClick={handleLogout} className="btn btn-danger btn-sm btn-logout" style={{ display: 'inline-flex', alignItems: 'center' }}>
              <LogOut size={14} style={{ marginRight: '4px' }} /> Sign Out
            </button>
          </li>
        </ul>
      </div>
    </nav>
  );
};
