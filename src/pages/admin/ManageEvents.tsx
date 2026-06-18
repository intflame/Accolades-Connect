import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Calendar, Trash2, Edit3, Users, Plus, AlertCircle, Loader2 } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  description: string;
  event_date: string;
  venue: string;
  registration_fee: number;
  registration_deadline: string;
  scan_start_time: string;
  scan_end_time: string;
  status: string;
  total_regs?: number;
  approved_regs?: number;
}

export const ManageEvents: React.FC = () => {
  const { profile } = useAuth();
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const navigate = useNavigate();

  const fetchEvents = async () => {
    setLoading(true);
    try {
      // Fetch all events
      const { data: eventsData, error: eventsError } = await supabase
        .from('events')
        .select('*')
        .order('event_date', { ascending: true });

      if (eventsError) throw eventsError;

      const eventsList: Event[] = eventsData || [];

      // For each event, fetch registration counts
      const updatedEvents = await Promise.all(
        eventsList.map(async (event) => {
          // Total registrations count
          const { count: totalCount } = await supabase
            .from('event_registrations')
            .select('*', { count: 'exact', head: true })
            .eq('event_id', event.id);

          // Approved registrations count
          const { count: approvedCount } = await supabase
            .from('event_registrations')
            .select('*', { count: 'exact', head: true })
            .eq('event_id', event.id)
            .eq('status', 'approved');

          return {
            ...event,
            total_regs: totalCount || 0,
            approved_regs: approvedCount || 0,
          };
        })
      );

      setEvents(updatedEvents);
    } catch (err) {
      console.error('Error fetching events list:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEvents();
  }, []);

  const handleStatusChange = async (eventId: number, newStatus: string) => {
    setActionLoading(eventId);
    try {
      const { error } = await supabase
        .from('events')
        .update({ status: newStatus })
        .eq('id', eventId);

      if (error) throw error;

      // Log activity
      const eventName = events.find((e) => e.id === eventId)?.name || `ID ${eventId}`;
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'event_status_updated',
        details: `Updated event status of '${eventName}' to: ${newStatus}`,
      });

      // Update local state
      setEvents((prev) =>
        prev.map((e) => (e.id === eventId ? { ...e, status: newStatus } : e))
      );
    } catch (err: any) {
      alert(err.message || 'Failed to update event status.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleDeleteEvent = async (eventId: number) => {
    if (
      !window.confirm(
        'Are you sure you want to permanently delete this event? This will also remove all registrations, payments, and QR tokens associated with it.'
      )
    ) {
      return;
    }

    setActionLoading(eventId);
    try {
      const eventName = events.find((e) => e.id === eventId)?.name || `ID ${eventId}`;

      const { error } = await supabase.from('events').delete().eq('id', eventId);
      if (error) throw error;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'event_deleted',
        details: `Deleted event: ${eventName}`,
      });

      // Refresh events list
      fetchEvents();
    } catch (err: any) {
      alert(err.message || 'Failed to delete event.');
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading events configuration...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2rem', flexWrap: 'wrap', gap: '0.5rem' }}>
        <div>
          <h2>Manage Events</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Configure departmental events, fees, deadline periods, and view registrations.
          </p>
        </div>
        <button
          onClick={() => navigate('/admin/events/create')}
          className="btn btn-primary btn-sm"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <Plus size={16} /> Create New Event
        </button>
      </div>

      <div className="card show-alert-anim">
        {events.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '4rem 1rem', color: 'var(--text-muted)' }}>
            <Calendar style={{ width: '40px', height: '40px', marginBottom: '1rem' }} />
            <p>No events have been created yet.</p>
            <button
              onClick={() => navigate('/admin/events/create')}
              className="btn btn-primary"
              style={{ marginTop: '1rem' }}
            >
              Create Event Now
            </button>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Event Details</th>
                  <th>Venue</th>
                  <th>Fee</th>
                  <th>Deadlines & Scanning Time</th>
                  <th>Registrations</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {events.map((event) => (
                  <tr key={event.id}>
                    <td style={{ fontWeight: 600, fontSize: '1rem' }}>
                      {event.name}
                      {event.description && (
                        <div
                          style={{
                            fontSize: '0.75rem',
                            color: 'var(--text-muted)',
                            fontWeight: 400,
                            marginTop: '0.25rem',
                            maxWidth: '250px',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                          }}
                          title={event.description}
                        >
                          {event.description}
                        </div>
                      )}
                      <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 400, marginTop: '0.25rem' }}>
                        Date: {new Date(event.event_date).toLocaleDateString()}
                      </div>
                    </td>
                    <td>{event.venue}</td>
                    <td style={{ fontWeight: 'bold', color: 'var(--accent)' }}>
                      {event.registration_fee > 0 ? `₹${Number(event.registration_fee).toFixed(2)}` : 'Free'}
                    </td>
                    <td>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                        <strong>Deadline:</strong> {new Date(event.registration_deadline).toLocaleString()}
                      </div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                        <strong>Scan:</strong> {new Date(event.scan_start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - {new Date(event.scan_end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </div>
                    </td>
                    <td>
                      <div style={{ fontSize: '0.9rem' }}>
                        <button
                          onClick={() => navigate(`/admin/registrations?eventId=${event.id}`)}
                          className="btn-link"
                          style={{ fontWeight: 600, background: 'none', border: 'none', cursor: 'pointer', color: 'var(--primary)', padding: 0 }}
                        >
                          {event.total_regs} Total
                        </button>
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--success)' }}>{event.approved_regs} Verified</div>
                    </td>
                    <td>
                      {actionLoading === event.id ? (
                        <Loader2 className="animate-spin" size={16} />
                      ) : (
                        <select
                          value={event.status}
                          onChange={(e) => handleStatusChange(event.id, e.target.value)}
                          className="form-control"
                          style={{ fontSize: '0.8rem', padding: '0.25rem 0.5rem', width: 'auto' }}
                        >
                          <option value="upcoming">Upcoming</option>
                          <option value="registration_open">Open</option>
                          <option value="registration_closed">Closed</option>
                          <option value="completed">Completed</option>
                          <option value="cancelled">Cancelled</option>
                        </select>
                      )}
                    </td>
                    <td>
                      <div style={{ display: 'flex', gap: '0.25rem' }}>
                        <button
                          onClick={() => navigate(`/admin/registrations?eventId=${event.id}`)}
                          className="btn btn-secondary btn-sm"
                          title="View Registered Students"
                          style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '0.4rem' }}
                        >
                          <Users size={14} />
                        </button>
                        <button
                          onClick={() => navigate(`/admin/events/edit/${event.id}`)}
                          className="btn btn-accent btn-sm"
                          title="Edit Event Settings"
                          style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '0.4rem' }}
                        >
                          <Edit3 size={14} />
                        </button>
                        <button
                          onClick={() => handleDeleteEvent(event.id)}
                          className="btn btn-danger btn-sm"
                          title="Delete Event"
                          style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '0.4rem' }}
                          disabled={actionLoading === event.id}
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
