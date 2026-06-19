import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { QRCodeSVG } from 'qrcode.react';
import { Calendar, MapPin, DollarSign, QrCode, Clock, CheckCircle, XCircle, X, UserCheck } from 'lucide-react';

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

interface Payment {
  id: number;
  payment_method: 'upi' | 'cash';
  proof_image: string;
  status: 'pending' | 'approved' | 'rejected';
  rejection_reason?: string;
  created_at: string;
}

interface Registration {
  id: number;
  event_id: number;
  status: 'pending_payment' | 'pending_verification' | 'approved' | 'rejected' | 'cancelled';
  payment_method: 'upi' | 'cash';
  created_at: string;
  events: Event;
  payments?: Payment[];
}

interface AttendanceScan {
  id: number;
  scan_type: 'entry' | 'food' | 'exit';
  scanned_at: string;
  eventName: string;
  eventDate: string;
}

export const StudentDashboard: React.FC = () => {
  const { profile } = useAuth();
  const [activeTab, setActiveTab] = useState<'events' | 'payments' | 'attendance'>('events');
  const [upcomingEvents, setUpcomingEvents] = useState<Event[]>([]);
  const [myRegistrations, setMyRegistrations] = useState<Registration[]>([]);
  const [myScans, setMyScans] = useState<AttendanceScan[]>([]);
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

      // 2. Fetch student's registrations and payments details
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
          ),
          payments (
            id,
            payment_method,
            proof_image,
            status,
            rejection_reason,
            created_at
          )
        `)
        .eq('student_id', profile.id)
        .order('created_at', { ascending: false });

      if (regError) throw regError;
      setMyRegistrations((regData as any) || []);

      // 3. Fetch student's attendance scans (join tokens, registrations, events)
      const { data: scanData, error: scanError } = await supabase
        .from('attendance_scans')
        .select(`
          id,
          scan_type,
          scanned_at,
          qr_tokens!inner (
            registration_id,
            event_registrations!inner (
              student_id,
              events (
                name,
                event_date
              )
            )
          )
        `)
        .eq('qr_tokens.event_registrations.student_id', profile.id)
        .order('scanned_at', { ascending: false });

      if (scanError) throw scanError;

      const mappedScans: AttendanceScan[] = (scanData || []).map((item: any) => {
        const token = item.qr_tokens || {};
        const reg = token.event_registrations || {};
        const ev = reg.events || {};
        return {
          id: item.id,
          scan_type: item.scan_type,
          scanned_at: item.scanned_at,
          eventName: ev.name || 'Unknown Event',
          eventDate: ev.event_date || ''
        };
      });

      setMyScans(mappedScans);
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
      const { data, error } = await supabase
        .from('qr_tokens')
        .select('token, status')
        .eq('registration_id', registrationId)
        .eq('status', 'active')
        .single();

      if (error) {
        alert('QR code is not active. Organizer must approve payment before ticket is generated.');
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
          Register for upcoming events, view details, track payments, and verify attendance records.
        </p>
      </div>

      {/* Tabs Controller */}
      <div style={{ display: 'flex', borderBottom: '1px solid var(--border-color)', gap: '1rem', marginBottom: '2.5rem', overflowX: 'auto' }}>
        <button
          onClick={() => setActiveTab('events')}
          className={`tab-btn ${activeTab === 'events' ? 'active' : ''}`}
          style={{
            background: 'none',
            border: 'none',
            color: activeTab === 'events' ? 'var(--primary)' : 'var(--text-muted)',
            fontWeight: activeTab === 'events' ? '700' : '500',
            fontSize: '1.05rem',
            padding: '0.75rem 1rem',
            cursor: 'pointer',
            borderBottom: activeTab === 'events' ? '2px solid var(--primary)' : 'none',
            whiteSpace: 'nowrap'
          }}
        >
          Upcoming Events
        </button>
        <button
          onClick={() => setActiveTab('payments')}
          className={`tab-btn ${activeTab === 'payments' ? 'active' : ''}`}
          style={{
            background: 'none',
            border: 'none',
            color: activeTab === 'payments' ? 'var(--primary)' : 'var(--text-muted)',
            fontWeight: activeTab === 'payments' ? '700' : '500',
            fontSize: '1.05rem',
            padding: '0.75rem 1rem',
            cursor: 'pointer',
            borderBottom: activeTab === 'payments' ? '2px solid var(--primary)' : 'none',
            whiteSpace: 'nowrap'
          }}
        >
          Payment & Passes History
        </button>
        <button
          onClick={() => setActiveTab('attendance')}
          className={`tab-btn ${activeTab === 'attendance' ? 'active' : ''}`}
          style={{
            background: 'none',
            border: 'none',
            color: activeTab === 'attendance' ? 'var(--primary)' : 'var(--text-muted)',
            fontWeight: activeTab === 'attendance' ? '700' : '500',
            fontSize: '1.05rem',
            padding: '0.75rem 1rem',
            cursor: 'pointer',
            borderBottom: activeTab === 'attendance' ? '2px solid var(--primary)' : 'none',
            whiteSpace: 'nowrap'
          }}
        >
          Attendance History
        </button>
      </div>

      {/* Tab: Upcoming Events */}
      {activeTab === 'events' && (
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
                const myReg = myRegistrations.find((r) => r.event_id === event.id);
                const isRegistered = !!myReg;
                return (
                  <div key={event.id} className="card show-alert-anim">
                    {event.banner_image && (
                      <img
                        src={event.banner_image}
                        alt={event.name}
                        style={{ width: '100%', height: '160px', objectFit: 'cover', borderRadius: 'var(--radius-sm)', marginBottom: '1rem' }}
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

                    {isRegistered ? (
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'rgba(255,255,255,0.03)', padding: '0.5rem', borderRadius: '4px' }}>
                          <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Status:</span>
                          {getStatusBadge(myReg.status)}
                        </div>
                        {myReg.status === 'pending_payment' && (
                          <button
                            onClick={() => navigate(`/student/register-event/${event.id}`)}
                            className="btn btn-accent"
                            style={{ width: '100%' }}
                          >
                            Pay Now
                          </button>
                        )}
                        {myReg.status === 'approved' && (
                          <button
                            onClick={() => handleShowQR(myReg.id, event.name)}
                            className="btn btn-primary"
                            style={{ width: '100%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '4px' }}
                          >
                            <QrCode size={16} /> View entry ticket
                          </button>
                        )}
                      </div>
                    ) : (
                      <button
                        onClick={() => navigate(`/student/register-event/${event.id}`)}
                        className="btn btn-primary"
                        style={{ width: '100%' }}
                      >
                        Register Now
                      </button>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Tab: Payment & Passes History */}
      {activeTab === 'payments' && (
        <div>
          <h2 style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <DollarSign className="text-primary" /> My Registrations & Payment History
          </h2>

          {myRegistrations.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
              <Clock size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
              <h3>No payment records found</h3>
              <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
                You haven't registered or submitted payment details for any events yet.
              </p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table">
                <thead>
                  <tr>
                    <th>Event Title</th>
                    <th>Registration Date</th>
                    <th>Fee Status</th>
                    <th>Payment Method</th>
                    <th>Uploaded Proof</th>
                    <th>Remarks / Notes</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {myRegistrations.map((reg) => {
                    const pay = reg.payments && reg.payments.length > 0 ? reg.payments[0] : null;
                    return (
                      <tr key={reg.id}>
                        <td><strong>{reg.events.name}</strong></td>
                        <td>{new Date(reg.created_at).toLocaleDateString()}</td>
                        <td>{getStatusBadge(reg.status)}</td>
                        <td>{reg.payment_method ? reg.payment_method.toUpperCase() : 'N/A'}</td>
                        <td>
                          {pay ? (
                            <a
                              href={pay.proof_image}
                              target="_blank"
                              rel="noreferrer"
                              style={{ color: 'var(--primary)', fontWeight: 600, fontSize: '0.85rem' }}
                            >
                              View Attachment
                            </a>
                          ) : (
                            <span style={{ color: 'var(--text-muted)' }}>None</span>
                          )}
                        </td>
                        <td style={{ maxWidth: '200px' }}>
                          {pay && pay.status === 'rejected' && pay.rejection_reason && (
                            <span style={{ color: 'var(--danger)', fontSize: '0.8rem', display: 'block' }}>
                              <strong>Reason:</strong> {pay.rejection_reason}
                            </span>
                          )}
                          {reg.status === 'approved' && (
                            <span style={{ color: 'var(--success)', fontSize: '0.8rem' }}>
                              Verified by admin. Entry pass generated.
                            </span>
                          )}
                          {!pay && reg.status === 'pending_payment' && (
                            <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>
                              Awaiting receipt upload.
                            </span>
                          )}
                        </td>
                        <td>
                          <div style={{ display: 'flex', gap: '0.5rem' }}>
                            {reg.status === 'approved' && (
                              <button
                                onClick={() => handleShowQR(reg.id, reg.events.name)}
                                className="btn btn-primary btn-sm"
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                              >
                                <QrCode size={12} /> Ticket
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
                            {reg.status === 'rejected' && (
                              <button
                                onClick={() => navigate(`/student/register-event/${reg.event_id}`)}
                                className="btn btn-secondary btn-sm"
                              >
                                Re-upload Proof
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Tab: Attendance History */}
      {activeTab === 'attendance' && (
        <div>
          <h2 style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <UserCheck className="text-primary" /> My Event Gate Check-Ins
          </h2>

          {myScans.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
              <QrCode size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
              <h3>No entry logs found</h3>
              <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
                Your tickets haven't been scanned at any gates yet. Scans show here in real-time.
              </p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table">
                <thead>
                  <tr>
                    <th>Event Name</th>
                    <th>Event Date</th>
                    <th>Gate/Counter Mode</th>
                    <th>Checked-In Time</th>
                  </tr>
                </thead>
                <tbody>
                  {myScans.map((scan) => (
                    <tr key={scan.id}>
                      <td><strong>{scan.eventName}</strong></td>
                      <td>{new Date(scan.eventDate).toLocaleDateString()}</td>
                      <td>
                        <span
                          style={{
                            textTransform: 'uppercase',
                            fontSize: '0.8rem',
                            padding: '0.2rem 0.5rem',
                            borderRadius: '4px',
                            background:
                              scan.scan_type === 'entry'
                                ? 'rgba(59,130,246,0.15)'
                                : scan.scan_type === 'food'
                                ? 'rgba(16,185,129,0.15)'
                                : 'rgba(239,68,68,0.15)',
                            color:
                              scan.scan_type === 'entry'
                                ? '#60a5fa'
                                : scan.scan_type === 'food'
                                ? '#34d399'
                                : '#f87171',
                            fontWeight: 600
                          }}
                        >
                          {scan.scan_type} Scan
                        </span>
                      </td>
                      <td>{new Date(scan.scanned_at).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

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

            <div className="qr-render-box" style={{ margin: '1.5rem auto', background: 'white', padding: '1rem', borderRadius: 'var(--radius-sm)', display: 'inline-block' }}>
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
