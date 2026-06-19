import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Calendar, MapPin, Scan, AlertCircle, Megaphone, Clock, CheckCircle } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  description: string;
  event_date: string;
  venue: string;
  status: string;
  food_enabled: boolean;
}

interface ScanHistoryRow {
  id: number;
  scan_type: 'entry' | 'food' | 'exit';
  scanned_at: string;
  studentName: string;
  studentRoll: string;
  eventName: string;
}

interface Announcement {
  id: number;
  title: string;
  message: string;
  created_at: string;
}

export const ScannerDashboard: React.FC = () => {
  const { profile } = useAuth();
  const [events, setEvents] = useState<Event[]>([]);
  const [history, setHistory] = useState<ScanHistoryRow[]>([]);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  const fetchData = async () => {
    if (!profile) return;
    setLoading(true);
    try {
      // 1. Fetch active events
      const { data: eventsData, error: eventsError } = await supabase
        .from('events')
        .select('id, name, description, event_date, venue, status, food_enabled')
        .in('status', ['registration_open', 'upcoming', 'completed'])
        .order('event_date', { ascending: true });

      if (eventsError) throw eventsError;
      setEvents(eventsData || []);

      // 2. Fetch scanner announcements
      const { data: annData } = await supabase
        .from('announcements')
        .select('id, title, message, created_at')
        .in('target_role', ['all', 'scanner'])
        .order('created_at', { ascending: false })
        .limit(5);

      setAnnouncements(annData || []);

      // 3. Fetch past scans performed by this scanner
      const { data: scansData, error: scansError } = await supabase
        .from('attendance_scans')
        .select(`
          id,
          scan_type,
          scanned_at,
          qr_tokens!inner (
            event_registrations!inner (
              profiles!inner (
                name,
                class_roll
              ),
              events!inner (
                name
              )
            )
          )
        `)
        .eq('scanned_by', profile.id)
        .order('scanned_at', { ascending: false })
        .limit(15);

      if (!scansError && scansData) {
        const mappedHistory: ScanHistoryRow[] = (scansData as any).map((s: any) => {
          const token = s.qr_tokens || {};
          const reg = token.event_registrations || {};
          const student = reg.profiles || {};
          const ev = reg.events || {};
          return {
            id: s.id,
            scan_type: s.scan_type,
            scanned_at: s.scanned_at,
            studentName: student.name || 'Unknown',
            studentRoll: student.class_roll || 'N/A',
            eventName: ev.name || 'Unknown Event'
          };
        });
        setHistory(mappedHistory);
      }
    } catch (err) {
      console.error('Error fetching scanner dashboard data:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [profile]);

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading active scanner events...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div className="hero" style={{ padding: '2rem 0' }}>
        <h1 className="hero-title" style={{ fontSize: '2.5rem' }}>Scanner Control Gate</h1>
        <p className="hero-subtitle" style={{ fontSize: '1.1rem', margin: '0.5rem auto' }}>
          Select an active event below to start checking in student passes.
        </p>
      </div>

      <div className="dashboard-panel">
        {/* Left column: Event list & Scan History */}
        <div>
          {/* Active Events */}
          <div style={{ marginBottom: '2.5rem' }}>
            <h2 style={{ marginBottom: '1.25rem', fontSize: '1.4rem' }}>Select Event to Scan</h2>
            {events.length === 0 ? (
              <div className="card" style={{ textAlign: 'center', padding: '2.5rem' }}>
                <AlertCircle size={40} style={{ color: 'var(--text-muted)', marginBottom: '0.75rem' }} />
                <h3>No events found.</h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                  There are currently no active or upcoming events.
                </p>
              </div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                {events.map((event) => (
                  <div key={event.id} className="card show-alert-anim" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', padding: '1.25rem' }}>
                    <div style={{ flex: 1 }}>
                      <h3 style={{ margin: '0 0 0.5rem 0', fontSize: '1.15rem' }}>{event.name}</h3>
                      <div style={{ display: 'flex', gap: '1.5rem', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                        <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <Calendar size={12} /> {new Date(event.event_date).toLocaleDateString()}
                        </span>
                        <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <MapPin size={12} /> {event.venue}
                        </span>
                      </div>
                    </div>
                    <button
                      onClick={() => navigate(`/scanner/scan/${event.id}`)}
                      className="btn btn-primary"
                      style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                    >
                      <Scan size={14} /> Open Scanner
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Scan History */}
          <div>
            <h2 style={{ marginBottom: '1.25rem', fontSize: '1.4rem' }}>Recent Scans Log</h2>
            {history.length === 0 ? (
              <div className="card" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-muted)' }}>
                <Clock size={32} style={{ color: 'var(--border-color)', marginBottom: '0.5rem' }} />
                <p style={{ fontSize: '0.85rem' }}>No ticket scans recorded by your account yet.</p>
              </div>
            ) : (
              <div className="table-responsive">
                <table className="table" style={{ fontSize: '0.85rem' }}>
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Roll</th>
                      <th>Event</th>
                      <th>Scan Type</th>
                      <th>Scanned At</th>
                    </tr>
                  </thead>
                  <tbody>
                    {history.map((h) => (
                      <tr key={h.id}>
                        <td><strong>{h.studentName}</strong></td>
                        <td>{h.studentRoll}</td>
                        <td>{h.eventName}</td>
                        <td>
                          <span
                            style={{
                              fontSize: '0.75rem',
                              textTransform: 'uppercase',
                              padding: '0.15rem 0.4rem',
                              borderRadius: '4px',
                              fontWeight: 600,
                              background: h.scan_type === 'entry' ? 'rgba(59,130,246,0.1)' : 'rgba(16,185,129,0.1)',
                              color: h.scan_type === 'entry' ? '#60a5fa' : '#34d399'
                            }}
                          >
                            {h.scan_type}
                          </span>
                        </td>
                        <td>{new Date(h.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>

        {/* Right column: Announcements */}
        <div>
          <h2 style={{ marginBottom: '1.25rem', fontSize: '1.4rem' }}>Scanner Bulletins</h2>
          {announcements.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-muted)' }}>
              <Megaphone size={32} style={{ color: 'var(--border-color)', marginBottom: '0.5rem' }} />
              <p style={{ fontSize: '0.85rem' }}>No active scanner bulletins.</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {announcements.map((ann) => (
                <div key={ann.id} className="card show-alert-anim" style={{ padding: '1.25rem', background: 'rgba(255,255,255,0.02)', border: '1px solid var(--border-color)' }}>
                  <h3 style={{ fontSize: '1rem', color: 'var(--primary)', display: 'flex', alignItems: 'center', gap: '6px', margin: '0 0 0.5rem 0' }}>
                    <Megaphone size={14} /> {ann.title}
                  </h3>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-main)', margin: '0 0 0.75rem 0', whiteSpace: 'pre-wrap' }}>
                    {ann.message}
                  </p>
                  <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)', display: 'block', textAlign: 'right' }}>
                    Posted: {new Date(ann.created_at).toLocaleDateString()}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
