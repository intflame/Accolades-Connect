import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { QRCodeSVG } from 'qrcode.react';
import { Calendar, MapPin, DollarSign, QrCode, Award, Clock, CheckCircle, AlertCircle, XCircle, X } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  description: string;
  banner_image: string;
  event_date: string;
  venue: string;
  registration_fee: number;
  status: string;
}

interface Registration {
  id: number;
  event_id: number;
  status: 'pending_payment' | 'pending_verification' | 'approved' | 'rejected' | 'cancelled';
  payment_method: 'upi' | 'cash';
  created_at: string;
  events: Event;
}

interface QRToken {
  token: string;
  status: string;
}

export const StudentDashboard: React.FC = () => {
  const { profile } = useAuth();
  const [upcomingEvents, setUpcomingEvents] = useState<Event[]>([]);
  const [myRegistrations, setMyRegistrations] = useState<Registration[]>([]);
  const [loading, setLoading] = useState(true);

  // QR Modal state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [activeQR, setActiveQR] = useState<string | null>(null);
  const [activeEventName, setActiveEventName] = useState('');

  const navigate = useNavigate();

  const fetchData = async () => {
    if (!profile) return;
    setLoading(true);

    try {
      // 1. Fetch upcoming events
      const { data: eventsData, error: eventsError } = await supabase
        .from('events')
        .select('*')
        .in('status', ['upcoming', 'registration_open'])
        .order('event_date', { ascending: true });

      if (eventsError) throw eventsError;
      setUpcomingEvents(eventsData || []);

      // 2. Fetch student's registrations
      const { data: regData, error: regError } = await supabase
        .from('event_registrations')
        .select(`
          id,
          event_id,
          status,
          payment_method,
          created_at,
          events (
            id,
            name,
            description,
            banner_image,
            event_date,
            venue,
            registration_fee
          )
        `)
        .eq('student_id', profile.id)
        .order('created_at', { ascending: false });

      if (regError) throw regError;
      setMyRegistrations((regData as any) || []);
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [profile]);

  const handleShowQR = async (registrationId: number, eventName: string) => {
    setActiveEventName(eventName);
    try {
      // Fetch token from qr_tokens
      const { data, error } = await supabase
        .from('qr_tokens')
        .select('token, status')
        .eq('registration_id', registrationId)
        .eq('status', 'active')
        .single();

      if (error) {
        alert('QR code is not generated or active yet. Admin must verify payment first.');
        return;
      }

      if (data) {
        setActiveQR(data.token);
        setIsModalOpen(true);
      }
    } catch (err) {
      console.error('Error fetching QR token:', err);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'approved':
        return <span className="badge badge-approved"><CheckCircle size={12} /> Approved</span>;
      case 'pending_payment':
        return <span className="badge badge-pending"><Clock size={12} /> Pending Payment</span>;
      case 'pending_verification':
        return <span className="badge badge-pending"><Clock size={12} /> Verifying Payment</span>;
      case 'rejected':
        return <span className="badge badge-rejected"><XCircle size={12} /> Rejected</span>;
      default:
        return <span className="badge badge-cancelled">{status}</span>;
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading your dashboard...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div className="hero" style={{ padding: '2rem 0' }}>
        <h1 className="hero-title" style={{ fontSize: '2.5rem' }}>Welcome, {profile?.name}!</h1>
        <p className="hero-subtitle" style={{ fontSize: '1.1rem', margin: '0.5rem auto' }}>
          Register for events, track attendance, and download certificates.
        </p>
      </div>

      {/* 1. Registered Events Section */}
      <div style={{ marginBottom: '3rem' }}>
        <h2 style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <CheckCircle className="text-primary" /> My Event Registrations
        </h2>

        {myRegistrations.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
            <AlertCircle size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
            <h3>You haven't registered for any events yet.</h3>
            <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
              Check the upcoming events list below to register!
            </p>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Event Name</th>
                  <th>Date</th>
                  <th>Venue</th>
                  <th>Status</th>
                  <th>Payment</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {myRegistrations.map((reg) => (
                  <tr key={reg.id}>
                    <td><strong>{reg.events.name}</strong></td>
                    <td>{new Date(reg.events.event_date).toLocaleDateString()}</td>
                    <td>{reg.events.venue}</td>
                    <td>{getStatusBadge(reg.status)}</td>
                    <td>{reg.payment_method ? reg.payment_method.toUpperCase() : 'N/A'}</td>
                    <td>
                      <div style={{ display: 'flex', gap: '0.5rem' }}>
                        {reg.status === 'approved' && (
                          <button
                            onClick={() => handleShowQR(reg.id, reg.events.name)}
                            className="btn btn-primary btn-sm"
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                          >
                            <QrCode size={14} /> View Ticket
                          </button>
                        )}
                        {reg.status === 'pending_payment' && (
                          <button
                            onClick={() => navigate(`/student/register-event/${reg.event_id}`)}
                            className="btn btn-accent btn-sm"
                          >
                            Pay Now
                          </button>
                        )}
                        {reg.status === 'approved' && (
                          <button
                            onClick={() => navigate('/student/certificates')}
                            className="btn btn-secondary btn-sm"
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                          >
                            <Award size={14} /> Certificate
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* 2. Upcoming Events Section */}
      <div>
        <h2 style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <Calendar className="text-primary" /> Upcoming Department Events
        </h2>

        {upcomingEvents.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
            <Calendar size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
            <h3>No upcoming events scheduled right now.</h3>
            <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
              Please check back later!
            </p>
          </div>
        ) : (
          <div className="landing-cards">
            {upcomingEvents.map((event) => {
              const isRegistered = myRegistrations.some((r) => r.event_id === event.id);
              return (
                <div key={event.id} className="card show-alert-anim">
                  {event.banner_image && (
                    <img
                      src={event.banner_image}
                      alt={event.name}
                      style={{ width: '100%', height: '150px', objectFit: 'cover', borderRadius: 'var(--radius-sm)', marginBottom: '1rem' }}
                    />
                  )}
                  <h3 style={{ marginBottom: '0.75rem' }}>{event.name}</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '1.25rem', display: '-webkit-box', WebkitLineClamp: 3, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                    {event.description || 'No description provided.'}
                  </p>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.85rem', color: 'var(--text-main)', marginBottom: '1.5rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <Calendar size={14} className="text-muted" />
                      <span>{new Date(event.event_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <MapPin size={14} className="text-muted" />
                      <span>{event.venue}</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <DollarSign size={14} className="text-muted" />
                      <span>{event.registration_fee > 0 ? `₹${event.registration_fee}` : 'FREE Entry'}</span>
                    </div>
                  </div>

                  <button
                    disabled={isRegistered}
                    onClick={() => navigate(`/student/register-event/${event.id}`)}
                    className={`btn ${isRegistered ? 'btn-secondary' : 'btn-primary'}`}
                    style={{ width: '100%' }}
                  >
                    {isRegistered ? 'Already Registered' : 'Register Now'}
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* QR Ticket Modal */}
      {isModalOpen && activeQR && (
        <div style={{ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(4px)', display: 'flex', justifyContent: 'center', alignItems: 'center', zIndex: 2000 }}>
          <div className="card show-alert-anim" style={{ maxWidth: '380px', width: '90%', textAlign: 'center', position: 'relative' }}>
            <button
              onClick={() => setIsModalOpen(false)}
              style={{ position: 'absolute', top: '15px', right: '15px', background: 'none', border: 'none', color: 'var(--text-main)', cursor: 'pointer' }}
            >
              <X size={24} />
            </button>
            <div className="card-header" style={{ borderBottom: 'none', paddingBottom: 0 }}>
              <h3 style={{ fontSize: '1.25rem' }}>{activeEventName} Entry Pass</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '0.25rem' }}>
                Present this QR code at the entry gate & food counter.
              </p>
            </div>

            <div className="qr-render-box" style={{ margin: '1.5rem auto' }}>
              <QRCodeSVG value={activeQR} size={200} />
            </div>

            <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', fontFamily: 'monospace' }}>
              Token: {activeQR.substring(0, 16)}...
            </p>

            <button
              onClick={() => setIsModalOpen(false)}
              className="btn btn-secondary"
              style={{ width: '100%', marginTop: '1.5rem' }}
            >
              Close Ticket
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
