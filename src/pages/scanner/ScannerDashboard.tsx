import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { Calendar, MapPin, Scan, Play, AlertCircle } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  description: string;
  event_date: string;
  venue: string;
  status: string;
  food_enabled: boolean;
}

export const ScannerDashboard: React.FC = () => {
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    const fetchActiveEvents = async () => {
      setLoading(true);
      try {
        const { data, error } = await supabase
          .from('events')
          .select('id, name, description, event_date, venue, status, food_enabled')
          .in('status', ['registration_open', 'upcoming', 'completed']) // let them scan completed events just in case
          .order('event_date', { ascending: true });

        if (error) throw error;
        setEvents(data || []);
      } catch (err) {
        console.error('Error fetching scanner events:', err);
      } finally {
        setLoading(false);
      }
    };
    fetchActiveEvents();
  }, []);

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

      {events.length === 0 ? (
        <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
          <AlertCircle size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
          <h3>No events found.</h3>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            There are currently no active or upcoming events to scan.
          </p>
        </div>
      ) : (
        <div className="landing-cards show-alert-anim">
          {events.map((event) => (
            <div key={event.id} className="card">
              <h3 style={{ marginBottom: '0.75rem' }}>{event.name}</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '1.25rem' }}>
                {event.description || 'No description provided.'}
              </p>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.85rem', color: 'var(--text-main)', marginBottom: '1.5rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <Calendar size={14} className="text-muted" />
                  <span>{new Date(event.event_date).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <MapPin size={14} className="text-muted" />
                  <span>{event.venue}</span>
                </div>
              </div>

              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <button
                  onClick={() => navigate(`/scanner/scan/${event.id}`)}
                  className="btn btn-primary"
                  style={{ flex: 1, display: 'inline-flex', alignItems: 'center', gap: '6px', justifyContent: 'center' }}
                >
                  <Scan size={16} /> Open Scanner Gate
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
