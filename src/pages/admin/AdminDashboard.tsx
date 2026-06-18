import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { Users, Calendar, Award, CheckSquare, Plus, AlertCircle, Trash2 } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  event_date: string;
  venue: string;
  registration_fee: number;
  status: string;
}

export const AdminDashboard: React.FC = () => {
  const [events, setEvents] = useState<Event[]>([]);
  const [stats, setStats] = useState({
    studentsCount: 0,
    eventsCount: 0,
    regCount: 0,
    pendingPayments: 0,
  });
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  const fetchStatsAndEvents = async () => {
    setLoading(true);
    try {
      // 1. Fetch Events
      const { data: eventsData, error: eventsError } = await supabase
        .from('events')
        .select('*')
        .order('event_date', { ascending: true });

      if (eventsError) throw eventsError;
      setEvents(eventsData || []);

      // 2. Fetch Stats
      const { count: studentsCount } = await supabase
        .from('profiles')
        .select('*', { count: 'exact', head: true })
        .eq('role', 'student');

      const { count: regCount } = await supabase
        .from('event_registrations')
        .select('*', { count: 'exact', head: true });

      const { count: pendingPayments } = await supabase
        .from('payments')
        .select('*', { count: 'exact', head: true })
        .eq('status', 'pending');

      setStats({
        studentsCount: studentsCount || 0,
        eventsCount: eventsData?.length || 0,
        regCount: regCount || 0,
        pendingPayments: pendingPayments || 0,
      });

    } catch (err) {
      console.error('Error fetching admin stats:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStatsAndEvents();
  }, []);

  const handleDeleteEvent = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this event? This will delete all registrations, payments, and QR codes associated with it.')) {
      return;
    }

    try {
      const { error } = await supabase.from('events').delete().eq('id', id);
      if (error) throw error;
      fetchStatsAndEvents();
    } catch (err: any) {
      alert(err.message || 'Failed to delete event.');
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading administration portal...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div className="hero" style={{ padding: '2rem 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 className="hero-title" style={{ fontSize: '2.5rem' }}>Admin Portal</h1>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.25rem' }}>
            Manage department academic events, verify student payments, and track scans.
          </p>
        </div>
        <button
          onClick={() => navigate('/admin/events/create')}
          className="btn btn-primary"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <Plus size={16} /> Create Event
        </button>
      </div>

      {/* Admin Stats Grid */}
      <div className="dashboard-grid show-alert-anim">
        <div className="stat-card">
          <div className="stat-icon" style={{ background: 'rgba(99, 102, 241, 0.15)', color: '#6366f1' }}>
            <Users size={24} />
          </div>
          <div className="stat-info">
            <div className="stat-value">{stats.studentsCount}</div>
            <div className="stat-label">Total Students</div>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon" style={{ background: 'rgba(248, 123, 27, 0.15)', color: 'var(--primary)' }}>
            <Calendar size={24} />
          </div>
          <div className="stat-info">
            <div className="stat-value">{stats.eventsCount}</div>
            <div className="stat-label">Total Events</div>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon" style={{ background: 'rgba(16, 185, 129, 0.15)', color: '#10b981' }}>
            <Award size={24} />
          </div>
          <div className="stat-info">
            <div className="stat-value">{stats.regCount}</div>
            <div className="stat-label">Total Registrations</div>
          </div>
        </div>

        <div className="stat-card" style={{ cursor: 'pointer' }} onClick={() => navigate('/admin/payments')}>
          <div className="stat-icon" style={{ background: 'rgba(245, 158, 11, 0.15)', color: '#f59e0b' }}>
            <CheckSquare size={24} />
          </div>
          <div className="stat-info">
            <div className="stat-value">{stats.pendingPayments}</div>
            <div className="stat-label" style={{ color: stats.pendingPayments > 0 ? '#fbbf24' : 'var(--text-muted)' }}>
              Pending Payment Reviews
            </div>
          </div>
        </div>
      </div>

      {/* Events management panel */}
      <div style={{ marginTop: '2.5rem' }}>
        <h2 style={{ marginBottom: '1.25rem' }}>Manage Scheduled Events</h2>

        {events.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
            <AlertCircle size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
            <h3>No events listed.</h3>
            <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
              Click the "Create Event" button above to launch your first event.
            </p>
          </div>
        ) : (
          <div className="table-responsive show-alert-anim">
            <table className="table">
              <thead>
                <tr>
                  <th>Event Name</th>
                  <th>Date</th>
                  <th>Venue</th>
                  <th>Registration Fee</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {events.map((event) => (
                  <tr key={event.id}>
                    <td><strong>{event.name}</strong></td>
                    <td>{new Date(event.event_date).toLocaleDateString()}</td>
                    <td>{event.venue}</td>
                    <td>{event.registration_fee > 0 ? `₹${event.registration_fee}` : 'FREE'}</td>
                    <td>
                      <span className={`badge ${event.status === 'registration_open' ? 'badge-approved' : 'badge-pending'}`}>
                        {event.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td>
                      <div style={{ display: 'flex', gap: '0.5rem' }}>
                        <button
                          onClick={() => navigate(`/admin/registrations?eventId=${event.id}`)}
                          className="btn btn-secondary btn-sm"
                        >
                          View Registrations
                        </button>
                        <button
                          onClick={() => handleDeleteEvent(event.id)}
                          className="btn btn-danger btn-sm"
                          style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '0.4rem' }}
                          title="Delete Event"
                        >
                          <Trash2 size={14} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
