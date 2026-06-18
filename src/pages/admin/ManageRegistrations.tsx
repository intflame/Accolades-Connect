import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { ArrowLeft, Award, CheckCircle, ShieldAlert, Loader2, RefreshCw } from 'lucide-react';

interface RegistrationRow {
  id: number;
  event_id: number;
  status: string;
  assigned_role: 'participant' | 'volunteers' | 'OC' | 'CC';
  created_at: string;
  profiles: {
    id: string;
    name: string;
    email: string;
    course: string;
    class_roll: string;
    batches: {
      name: string;
    } | null;
  };
  events: {
    name: string;
  };
  certificates: {
    id: number;
    certificate_code: string;
  } | null;
}

export const ManageRegistrations: React.FC = () => {
  const [searchParams] = useSearchParams();
  const eventId = searchParams.get('eventId');
  const { profile } = useAuth();
  const [registrations, setRegistrations] = useState<RegistrationRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [eventName, setEventName] = useState('');
  const navigate = useNavigate();

  const fetchRegistrations = async () => {
    setLoading(true);
    try {
      // Get event details if eventId is provided
      if (eventId) {
        const { data: eventData } = await supabase
          .from('events')
          .select('name')
          .eq('id', Number(eventId))
          .single();
        if (eventData) setEventName(eventData.name);
      }

      let query = supabase
        .from('event_registrations')
        .select(`
          id,
          event_id,
          status,
          assigned_role,
          created_at,
          profiles!inner (
            id,
            name,
            email,
            course,
            class_roll,
            batches (
              name
            )
          ),
          events!inner (
            name
          ),
          certificates (
            id,
            certificate_code
          )
        `);

      if (eventId) {
        query = query.eq('event_id', Number(eventId));
      }

      const { data, error } = await query.order('created_at', { ascending: false });

      if (error) throw error;
      setRegistrations((data as any) || []);
    } catch (err) {
      console.error('Error fetching registrations:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRegistrations();
  }, [eventId]);

  const handleRoleChange = async (regId: number, role: 'participant' | 'volunteers' | 'OC' | 'CC') => {
    setActionLoading(regId);
    try {
      const { error } = await supabase
        .from('event_registrations')
        .update({ assigned_role: role })
        .eq('id', regId);

      if (error) throw error;

      // Log activity
      if (profile) {
        await supabase.from('activity_logs').insert({
          user_id: profile.id,
          action: 'role_updated',
          details: `Updated registration ID ${regId} role to ${role}`
        });
      }

      setRegistrations((prev) =>
        prev.map((r) => (r.id === regId ? { ...r, assigned_role: role } : r))
      );
    } catch (err: any) {
      alert(err.message || 'Failed to update role.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleIssueCertificate = async (reg: RegistrationRow) => {
    if (!profile) return;
    if (!window.confirm(`Issue academic certificate to ${reg.profiles.name}?`)) {
      return;
    }

    setActionLoading(reg.id);
    try {
      // Generate unique certificate verification code
      // Format: AC-EVENTID-REGID-RANDOMCHARS
      const randomChars = Math.random().toString(36).substring(2, 6).toUpperCase();
      const code = `AC-${reg.event_id}-${reg.id}-${randomChars}`;

      const { error } = await supabase
        .from('certificates')
        .insert({
          registration_id: reg.id,
          certificate_code: code,
          issued_by: profile.id
        });

      if (error) throw error;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'certificate_issued',
        details: `Issued certificate ${code} for registration ID ${reg.id}`
      });

      // Refresh data
      fetchRegistrations();
    } catch (err: any) {
      alert(err.message || 'Failed to issue certificate.');
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading event registrations...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div style={{ display: 'flex', gap: '1rem', alignItems: 'center', marginBottom: '1.5rem' }}>
        <button onClick={() => navigate('/admin')} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <ArrowLeft size={14} /> Dashboard
        </button>
      </div>

      <div className="hero" style={{ padding: '1rem 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 className="hero-title" style={{ fontSize: '2rem' }}>
            {eventName ? `Registrations: ${eventName}` : 'All Event Registrations'}
          </h1>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.25rem' }}>
            Assign event roles and issue activity certificates.
          </p>
        </div>
        <button onClick={fetchRegistrations} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <RefreshCw size={14} /> Refresh
        </button>
      </div>

      {registrations.length === 0 ? (
        <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
          <ShieldAlert size={48} style={{ color: 'var(--text-muted)', marginBottom: '1rem' }} />
          <h3>No registrations found.</h3>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            No students have registered for this event yet.
          </p>
        </div>
      ) : (
        <div className="table-responsive show-alert-anim">
          <table className="table">
            <thead>
              <tr>
                <th>Student Details</th>
                <th>Academic</th>
                <th>Status</th>
                <th>Assigned Role</th>
                <th>Certificate</th>
              </tr>
            </thead>
            <tbody>
              {registrations.map((reg) => (
                <tr key={reg.id}>
                  <td>
                    <strong>{reg.profiles.name}</strong>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{reg.profiles.email}</div>
                  </td>
                  <td>
                    <div>{reg.profiles.course} (Roll: {reg.profiles.class_roll})</div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Batch: {reg.profiles.batches?.name ?? 'N/A'}</div>
                  </td>
                  <td>
                    <span className={`badge ${reg.status === 'approved' ? 'badge-approved' : 'badge-pending'}`}>
                      {reg.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td>
                    <select
                      className="form-control"
                      value={reg.assigned_role}
                      onChange={(e) => handleRoleChange(reg.id, e.target.value as any)}
                      disabled={actionLoading === reg.id || reg.status !== 'approved'}
                      style={{ padding: '0.3rem 0.6rem', fontSize: '0.85rem', maxWidth: '140px' }}
                    >
                      <option value="participant">Participant</option>
                      <option value="volunteers">Volunteer</option>
                      <option value="OC">OC Member</option>
                      <option value="CC">Co-coordinator</option>
                    </select>
                  </td>
                  <td>
                    {reg.status !== 'approved' ? (
                      <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Approval required</span>
                    ) : reg.certificates ? (
                      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '2px' }}>
                        <span className="badge badge-approved" style={{ fontSize: '0.7rem' }}>
                          <CheckCircle size={10} /> Issued
                        </span>
                        <span style={{ fontFamily: 'monospace', fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 'bold' }}>
                          {reg.certificates.certificate_code}
                        </span>
                      </div>
                    ) : (
                      <button
                        onClick={() => handleIssueCertificate(reg)}
                        disabled={actionLoading !== null}
                        className="btn btn-primary btn-sm"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                      >
                        <Award size={12} /> Issue Certificate
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};
