import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { useAuth } from '../context/AuthContext';
import { Calendar, MapPin, Clock, ArrowRight, Sparkles } from 'lucide-react';
import logoAccolades from '../assets/hero.png';

interface Event {
  id: number;
  name: string;
  description: string;
  banner_image: string;
  event_date: string;
  venue: string;
  registration_fee: number;
  status: string;
  registration_deadline: string;
}

export const Landing: React.FC = () => {
  const { profile } = useAuth();
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);


  useEffect(() => {
    const fetchEvents = async () => {
      try {
        const { data, error } = await supabase
          .from('events')
          .select('*')
          .in('status', ['upcoming', 'registration_open'])
          .order('event_date', { ascending: true });

        if (error) throw error;
        setEvents(data || []);
      } catch (err) {
        console.error('Error fetching events:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchEvents();
  }, []);

  let dashboardPath = '/login';
  if (profile) {
    if (profile.role === 'admin') {
      dashboardPath = '/admin';
    } else if (profile.role === 'scanner') {
      dashboardPath = '/scanner';
    } else {
      dashboardPath = '/student';
    }
  }

  return (
    <div className="container main-content">
      {/* Hero Section */}
      <header className="hero show-alert-anim">
        <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
          <img
            src={logoAccolades}
            alt="Accolades Logo"
            style={{
              maxWidth: '320px',
              height: 'auto',
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              padding: '0.75rem 2rem',
              borderRadius: 'var(--radius-md)',
              boxShadow: 'var(--shadow-lg)',
              display: 'inline-block',
              border: '1px solid var(--border-color)',
            }}
          />
        </div>
        <h1 className="hero-title">Accolades Connect</h1>
        <p className="hero-subtitle">
          Department of Computer Application Event Hub. Register for academic workshops, technical hackathons, and cultural activities. Track attendance and manage certificates seamlessly.
        </p>

        <div style={{ display: 'flex', gap: '1.25rem', justifyContent: 'center', marginTop: '2rem', flexWrap: 'wrap' }}>
          {profile ? (
            <Link to={dashboardPath} className="btn btn-primary" style={{ padding: '0.85rem 2rem' }}>
              Go to Portal Dashboard <ArrowRight size={16} />
            </Link>
          ) : (
            <>
              <Link to="/register" className="btn btn-primary" style={{ padding: '0.85rem 2rem' }}>
                Student Sign Up
              </Link>
              <Link to="/login" className="btn btn-secondary" style={{ padding: '0.85rem 2rem' }}>
                Access Portal
              </Link>
            </>
          )}
        </div>
      </header>

      {/* Events List Section */}
      <section style={{ marginTop: '5rem', marginBottom: '3rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2.5rem', flexWrap: 'wrap', gap: '1rem' }}>
          <h2 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '1.85rem' }}>
            <Sparkles style={{ color: 'var(--primary)' }} /> Upcoming & Open Events
          </h2>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.95rem', fontWeight: '500', background: 'rgba(255, 255, 255, 0.03)', padding: '0.4rem 1rem', borderRadius: '50px', border: '1px solid var(--border-color)' }}>
            {events.length} Active Event{events.length === 1 ? '' : 's'}
          </span>
        </div>

        {loading ? (
          <div style={{ textAlign: 'center', padding: '4rem' }}>
            <p style={{ color: 'var(--text-muted)' }}>Loading scheduled events...</p>
          </div>
        ) : events.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '4rem 2rem' }}>
            <Calendar size={48} style={{ color: 'var(--text-muted)', marginBottom: '1.5rem' }} />
            <h3 style={{ fontSize: '1.35rem', marginBottom: '0.5rem' }}>No events scheduled right now</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
              Check back later or log in to view registration history.
            </p>
          </div>
        ) : (
          <div className="landing-cards">
            {events.map((event) => (
              <article key={event.id} className="card show-alert-anim" style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
                {event.banner_image && (
                  <img
                    src={event.banner_image}
                    alt={event.name}
                    style={{
                      width: '100%',
                      height: '180px',
                      objectFit: 'cover',
                      borderRadius: 'var(--radius-sm)',
                      marginBottom: '1.25rem',
                      border: '1px solid rgba(255, 255, 255, 0.05)'
                    }}
                  />
                )}
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem', gap: '0.5rem' }}>
                    <span className={`badge ${event.status === 'registration_open' ? 'badge-approved' : 'badge-pending'}`}>
                      {event.status.replace('_', ' ')}
                    </span>
                    <span style={{ fontWeight: '700', fontSize: '1.2rem', color: 'var(--primary)' }}>
                      {event.registration_fee > 0 ? `₹${event.registration_fee}` : 'Free'}
                    </span>
                  </div>

                  <h3 style={{ marginBottom: '0.75rem', fontSize: '1.4rem' }}>{event.name}</h3>
                  <p style={{
                    color: 'var(--text-muted)',
                    fontSize: '0.9rem',
                    lineHeight: '1.6',
                    marginBottom: '1.5rem',
                    display: '-webkit-box',
                    WebkitLineClamp: 3,
                    WebkitBoxOrient: 'vertical',
                    overflow: 'hidden',
                    flex: 1
                  }}>
                    {event.description || 'No description available for this event.'}
                  </p>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.65rem', marginBottom: '1.5rem', fontSize: '0.85rem', color: 'var(--text-muted)', borderTop: '1px solid var(--border-color)', paddingTop: '1.25rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <Calendar size={14} style={{ color: 'var(--primary)' }} />
                      <span>{new Date(event.event_date).toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })}</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <MapPin size={14} style={{ color: 'var(--primary)' }} />
                      <span>{event.venue}</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <Clock size={14} style={{ color: 'var(--primary)' }} />
                      <span>Deadline: {new Date(event.registration_deadline).toLocaleString()}</span>
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: 'auto' }}>
                  {profile ? (
                    profile.role === 'student' ? (
                      <Link to={`/student/register-event/${event.id}`} className="btn btn-primary" style={{ width: '100%' }}>
                        Register Now <ArrowRight size={14} />
                      </Link>
                    ) : (
                      <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)', textAlign: 'center', display: 'block', padding: '0.5rem 0' }}>
                        Logged in as {profile.role}
                      </span>
                    )
                  ) : (
                    <Link to={`/login?redirect=/student/register-event/${event.id}`} className="btn btn-secondary" style={{ width: '100%' }}>
                      Login to Register
                    </Link>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  );
};
