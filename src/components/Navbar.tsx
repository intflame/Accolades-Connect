import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Sun, Moon, LogOut, Menu, X, Award, Calendar, CheckSquare, ChevronDown, Users, Shield, FileText, Image, Database, User, Megaphone } from 'lucide-react';
import logoCa from '../assets/logo_ca.png';

export const Navbar: React.FC = () => {
  const { profile, loading, signOut } = useAuth();
  const [isLightTheme, setIsLightTheme] = useState(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
      document.body.classList.add('light-theme');
      return true;
    }
    return false;
  });
  const [isOpen, setIsOpen] = useState(false);
  const [activeDropdown, setActiveDropdown] = useState<'operations' | 'system' | null>(null);
  const navigate = useNavigate();
  const location = useLocation();

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

  if (loading) return null;

  return (
    <nav className="navbar">
      <div className="container navbar-inner">
        {/* Brand Link */}
        <Link to="/" className="brand" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <img src={logoCa} alt="CA Logo" style={{ height: '32px', width: '32px', objectFit: 'cover', background: '#fff', padding: '2px', borderRadius: '50%' }} />
          <span>Accolades Connect</span>
        </Link>

        {/* Mobile Toggle Button */}
        <button className="nav-toggle" onClick={() => setIsOpen(!isOpen)}>
          {isOpen ? <X size={24} /> : <Menu size={24} />}
        </button>

        {/* Nav Links */}
        <ul className={`nav-links ${isOpen ? 'active' : ''}`}>
          {!profile ? (
            // --- Guest/Public Links ---
            <>
              <li>
                <Link
                  to="/"
                  className={`nav-link ${location.pathname === '/' ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  Home
                </Link>
              </li>
              <li>
                <Link
                  to="/login"
                  className={`nav-link ${location.pathname === '/login' ? 'active' : ''}`}
                  onClick={() => setIsOpen(false)}
                >
                  Login
                </Link>
              </li>
              <li>
                <Link
                  to="/register"
                  className="btn btn-primary btn-sm"
                  onClick={() => setIsOpen(false)}
                  style={{ display: 'inline-flex', alignItems: 'center' }}
                >
                  Register
                </Link>
              </li>
            </>
          ) : (
            // --- Authenticated Links ---
            <>
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
                  <li>
                    <Link
                      to="/student/profile"
                      className={`nav-link ${location.pathname === '/student/profile' ? 'active' : ''}`}
                      onClick={() => setIsOpen(false)}
                    >
                      <User size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
                      My Profile
                    </Link>
                  </li>
                  <li>
                    <Link
                      to="/student/announcements"
                      className={`nav-link ${location.pathname === '/student/announcements' ? 'active' : ''}`}
                      onClick={() => setIsOpen(false)}
                    >
                      <Megaphone size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
                      Announcements
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
                      onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                    >
                      Dashboard
                    </Link>
                  </li>

                  {/* Operations Dropdown */}
                  <li style={{ position: 'relative' }} className="nav-dropdown-wrapper">
                    <button
                      className="nav-link dropdown-toggle-btn"
                      onClick={() => setActiveDropdown(activeDropdown === 'operations' ? null : 'operations')}
                      style={{ background: 'none', border: 'none', color: 'inherit', font: 'inherit', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '4px' }}
                    >
                      Operations <ChevronDown size={14} />
                    </button>
                    {activeDropdown === 'operations' && (
                      <div
                        className="dropdown-menu-glass"
                        style={{
                          position: isOpen ? 'static' : 'absolute',
                          top: '100%',
                          left: 0,
                          background: 'var(--bg-card)',
                          backdropFilter: 'var(--glass-blur)',
                          border: '1px solid var(--border-color)',
                          borderRadius: 'var(--radius-sm)',
                          padding: '0.5rem 0',
                          minWidth: '190px',
                          display: 'flex',
                          flexDirection: 'column',
                          zIndex: 1000,
                          boxShadow: 'var(--shadow-lg)',
                          marginTop: isOpen ? '0.5rem' : '0'
                        }}
                      >
                        <Link
                          to="/admin/events"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Calendar size={14} /> Manage Events
                        </Link>
                        <Link
                          to="/admin/registrations"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Users size={14} /> Registrations
                        </Link>
                        <Link
                          to="/admin/payments"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <CheckSquare size={14} /> Verify Payments
                        </Link>
                        <Link
                          to="/admin/attendance"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <CheckSquare size={14} /> Live Attendance
                        </Link>
                      </div>
                    )}
                  </li>

                  {/* Management Dropdown */}
                  <li style={{ position: 'relative' }} className="nav-dropdown-wrapper">
                    <button
                      className="nav-link dropdown-toggle-btn"
                      onClick={() => setActiveDropdown(activeDropdown === 'system' ? null : 'system')}
                      style={{ background: 'none', border: 'none', color: 'inherit', font: 'inherit', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '4px' }}
                    >
                      Management <ChevronDown size={14} />
                    </button>
                    {activeDropdown === 'system' && (
                      <div
                        className="dropdown-menu-glass"
                        style={{
                          position: isOpen ? 'static' : 'absolute',
                          top: '100%',
                          left: 0,
                          background: 'var(--bg-card)',
                          backdropFilter: 'var(--glass-blur)',
                          border: '1px solid var(--border-color)',
                          borderRadius: 'var(--radius-sm)',
                          padding: '0.5rem 0',
                          minWidth: '190px',
                          display: 'flex',
                          flexDirection: 'column',
                          zIndex: 1000,
                          boxShadow: 'var(--shadow-lg)',
                          marginTop: isOpen ? '0.5rem' : '0'
                        }}
                      >
                        <Link
                          to="/admin/batches"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Database size={14} /> Batches Cohort
                        </Link>
                        <Link
                          to="/admin/students"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Users size={14} /> Student Directory
                        </Link>
                        <Link
                          to="/admin/scanners"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Shield size={14} /> Scanner Users
                        </Link>
                        <Link
                          to="/admin/certificates"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Award size={14} /> Certificates
                        </Link>
                        <Link
                          to="/admin/gallery"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Image size={14} /> Gallery Manager
                        </Link>
                        <Link
                          to="/admin/reports"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <FileText size={14} /> Export Reports
                        </Link>
                        <Link
                          to="/admin/announcements"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <Megaphone size={14} /> Announcements
                        </Link>
                        <Link
                          to="/admin/food-report"
                          className="dropdown-item-link"
                          onClick={() => { setIsOpen(false); setActiveDropdown(null); }}
                          style={{ padding: '0.5rem 1rem', color: 'var(--text-main)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                        >
                          <FileText size={14} /> Food Preference Report
                        </Link>
                      </div>
                    )}
                  </li>
                </>
              )}
            </>
          )}

          {/* --- Common Controls (Theme, Avatar & Logout) --- */}
          <li style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            {/* User Profile Avatar (Only if authenticated) */}
            {profile && (
              profile.role === 'student' ? (
                <Link to="/student/profile" style={{ display: 'flex', alignItems: 'center' }} title="My Profile">
                  {profile.profile_photo ? (
                    <img
                      src={profile.profile_photo}
                      alt="Profile Avatar"
                      style={{ width: '32px', height: '32px', borderRadius: '50%', objectFit: 'cover', border: '1.5px solid var(--primary)' }}
                      className="avatar-hover"
                    />
                  ) : (
                    <div
                      style={{
                        width: '32px',
                        height: '32px',
                        borderRadius: '50%',
                        background: 'rgba(255, 255, 255, 0.08)',
                        border: '1.5px solid var(--border-color)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'var(--text-main)'
                      }}
                    >
                      <User size={16} />
                    </div>
                  )}
                </Link>
              ) : (
                <div style={{ display: 'flex', alignItems: 'center' }} title={`${profile.name} (${profile.role})`}>
                  {profile.profile_photo ? (
                    <img
                      src={profile.profile_photo}
                      alt="Profile Avatar"
                      style={{ width: '32px', height: '32px', borderRadius: '50%', objectFit: 'cover', border: '1.5px solid var(--primary)' }}
                    />
                  ) : (
                    <div
                      style={{
                        width: '32px',
                        height: '32px',
                        borderRadius: '50%',
                        background: 'rgba(255, 255, 255, 0.08)',
                        border: '1.5px solid var(--border-color)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'var(--text-main)'
                      }}
                    >
                      <User size={16} />
                    </div>
                  )}
                </div>
              )
            )}

            <button className="theme-toggle-btn" onClick={toggleTheme} title="Toggle Theme">
              {isLightTheme ? <Moon className="moon-icon" size={18} /> : <Sun className="sun-icon" size={18} />}
            </button>

            {profile && (
              <button onClick={handleLogout} className="btn btn-danger btn-sm btn-logout" style={{ display: 'inline-flex', alignItems: 'center' }}>
                <LogOut size={14} style={{ marginRight: '4px' }} /> Sign Out
              </button>
            )}
          </li>
        </ul>
      </div>
    </nav>
  );
};
