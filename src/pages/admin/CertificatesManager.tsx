import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Award, ChevronLeft, Search, CheckCircle, XCircle, Users, Check, AlertCircle, Loader2, Sliders, Settings } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  event_date: string;
  venue: string;
  certificate_template: string | null;
  attended_count: number;
  issued_count: number;
}

interface AttendedStudent {
  reg_id: number;
  student_id: string;
  student_name: string;
  course: string;
  batch_name: string;
  class_roll: string;
  university_roll: string;
  scanned_at: string;
  certificate_id: number | null;
  certificate_code: string | null;
  issued_at: string | null;
}

export const CertificatesManager: React.FC = () => {
  const { profile } = useAuth();
  const navigate = useNavigate();
  const [events, setEvents] = useState<Event[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null);
  const [selectedEvent, setSelectedEvent] = useState<Event | null>(null);
  const [students, setStudents] = useState<AttendedStudent[]>([]);
  const [filteredStudents, setFilteredStudents] = useState<AttendedStudent[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Subview Filters
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [selectedRegIds, setSelectedRegIds] = useState<number[]>([]);

  const fetchEventsData = async () => {
    setLoading(true);
    try {
      const { data: eventsData, error: eventsError } = await supabase
        .from('events')
        .select('*')
        .order('event_date', { ascending: false });

      if (eventsError) throw eventsError;

      const eventsList = eventsData || [];

      // Calculate statistics for each event
      const updatedEvents = await Promise.all(
        eventsList.map(async (event) => {
          // Attended students (approved registration AND entry scan)
          const { data: attendees, error: attendeesError } = await supabase
            .from('event_registrations')
            .select(`
              id,
              qr_tokens (
                id,
                attendance_scans (
                  id,
                  scan_type
                )
              )
            `)
            .eq('event_id', event.id)
            .eq('status', 'approved');

          if (attendeesError) throw attendeesError;

          const attendedRows = (attendees || []).filter((item: any) => {
            const tokens = item.qr_tokens || [];
            return tokens.some((token: any) => {
              const scans = token.attendance_scans || [];
              return scans.some((s: any) => s.scan_type === 'entry');
            });
          });

          const attended_count = attendedRows.length;

          // Issued certificates count
          const { count: issued_count, error: issuedError } = await supabase
            .from('certificates')
            .select('id', { count: 'exact', head: true })
            .in('registration_id', attendedRows.map((r) => r.id).length > 0 ? attendedRows.map((r) => r.id) : [-1]);

          if (issuedError) throw issuedError;

          return {
            id: event.id,
            name: event.name,
            event_date: event.event_date,
            venue: event.venue,
            certificate_template: event.certificate_template,
            attended_count,
            issued_count: issued_count || 0,
          };
        })
      );

      setEvents(updatedEvents);
    } catch (err) {
      console.error('Error loading events for certificates:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchAttendedStudents = async (eventId: number) => {
    setLoading(true);
    try {
      // Fetch registrations with attendee logs for this event
      const { data, error } = await supabase
        .from('event_registrations')
        .select(`
          id,
          status,
          student_id,
          profiles (
            id,
            name,
            course,
            class_roll,
            university_roll,
            batch_id,
            batches (
              name
            )
          ),
          qr_tokens (
            id,
            attendance_scans (
              id,
              scan_type,
              scanned_at
            )
          ),
          certificates (
            id,
            certificate_code,
            issued_at
          )
        `)
        .eq('event_id', eventId)
        .eq('status', 'approved');

      if (error) throw error;

      // Filter to only display students who checked in (entry scan)
      const list: AttendedStudent[] = [];

      (data || []).forEach((item: any) => {
        const p = item.profiles || {};
        const qrs = item.qr_tokens || [];
        const cert = item.certificates; // certificates is standard 1-to-1 link

        let entryScan: any = null;
        for (const token of qrs) {
          const scans = token.attendance_scans || [];
          const found = scans.find((s: any) => s.scan_type === 'entry');
          if (found) {
            entryScan = found;
            break;
          }
        }

        if (entryScan) {
          list.push({
            reg_id: item.id,
            student_id: p.id || '',
            student_name: p.name || 'Unknown',
            course: p.course || 'BCA',
            batch_name: p.batches?.name || 'N/A',
            class_roll: p.class_roll || '',
            university_roll: p.university_roll || '',
            scanned_at: entryScan.scanned_at,
            certificate_id: cert ? cert.id : null,
            certificate_code: cert ? cert.certificate_code : null,
            issued_at: cert ? cert.issued_at : null,
          });
        }
      });

      list.sort((a, b) => a.student_name.localeCompare(b.student_name));
      setStudents(list);
      setSelectedRegIds([]);
    } catch (err) {
      console.error('Error fetching attended students:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (selectedEventId) {
      const found = events.find((e) => e.id === selectedEventId);
      if (found) setSelectedEvent(found);
      fetchAttendedStudents(selectedEventId);
    } else {
      setSelectedEvent(null);
      fetchEventsData();
    }
  }, [selectedEventId]);

  // Apply filters locally
  useEffect(() => {
    let filtered = [...students];

    if (statusFilter === 'issued') {
      filtered = filtered.filter((s) => s.certificate_id !== null);
    } else if (statusFilter === 'pending') {
      filtered = filtered.filter((s) => s.certificate_id === null);
    }

    if (search.trim()) {
      const term = search.toLowerCase();
      filtered = filtered.filter(
        (s) =>
          s.student_name.toLowerCase().includes(term) ||
          s.class_roll.toLowerCase().includes(term) ||
          s.batch_name.toLowerCase().includes(term)
      );
    }

    setFilteredStudents(filtered);
  }, [students, statusFilter, search]);

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedRegIds(filteredStudents.map((s) => s.reg_id));
    } else {
      setSelectedRegIds([]);
    }
  };

  const handleSelectRow = (regId: number, checked: boolean) => {
    if (checked) {
      setSelectedRegIds((prev) => [...prev, regId]);
    } else {
      setSelectedRegIds((prev) => prev.filter((id) => id !== regId));
    }
  };

  const handleIssueCertificates = async () => {
    if (selectedRegIds.length === 0) return;
    if (!selectedEvent) return;

    if (!selectedEvent.certificate_template) {
      alert('Cannot issue certificates: A certificate template background has not been uploaded for this event.');
      return;
    }

    if (
      !window.confirm(
        `Are you sure you want to generate and issue certificates to the ${selectedRegIds.length} selected student(s)?`
      )
    ) {
      return;
    }

    setSubmitting(true);
    try {
      let issuedCount = 0;

      for (const regId of selectedRegIds) {
        // Skip if already issued
        const stud = students.find((s) => s.reg_id === regId);
        if (stud && stud.certificate_id) continue;

        // Generate unique code
        const code = `MAR-E${selectedEventId}-${Math.random().toString(36).substring(2, 10).toUpperCase()}`;

        const { error } = await supabase.from('certificates').insert({
          registration_id: regId,
          certificate_code: code,
          issued_by: profile?.id,
        });

        if (error) {
          console.error(`Error issuing for reg_id ${regId}:`, error);
        } else {
          // Log activity
          await supabase.from('activity_logs').insert({
            user_id: profile?.id,
            action: 'certificate_issued',
            details: `Issued certificate ${code} for ${stud?.student_name || 'Student'} in event '${selectedEvent.name}'`,
          });
          issuedCount++;
        }
      }

      alert(`Successfully issued ${issuedCount} certificates.`);
      if (selectedEventId) fetchAttendedStudents(selectedEventId);
    } catch (err: any) {
      alert(err.message || 'An error occurred during certificate issuance.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleRevokeCertificates = async () => {
    if (selectedRegIds.length === 0) return;
    if (!selectedEvent) return;

    if (
      !window.confirm(
        `Are you sure you want to permanently revoke/delete certificates from the ${selectedRegIds.length} selected student(s)?`
      )
    ) {
      return;
    }

    setSubmitting(true);
    try {
      let revokedCount = 0;

      for (const regId of selectedRegIds) {
        const stud = students.find((s) => s.reg_id === regId);
        if (!stud || !stud.certificate_id) continue;

        const { error } = await supabase.from('certificates').delete().eq('registration_id', regId);

        if (error) {
          console.error(`Error revoking for reg_id ${regId}:`, error);
        } else {
          // Log activity
          await supabase.from('activity_logs').insert({
            user_id: profile?.id,
            action: 'certificate_revoked',
            details: `Revoked certificate ${stud.certificate_code} for ${stud.student_name} in event '${selectedEvent.name}'`,
          });
          revokedCount++;
        }
      }

      alert(`Successfully revoked ${revokedCount} certificates.`);
      if (selectedEventId) fetchAttendedStudents(selectedEventId);
    } catch (err: any) {
      alert(err.message || 'An error occurred during revocation.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading && events.length === 0 && !selectedEventId) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading certificates dashboard...</p>
      </div>
    );
  }

  // View 1: Events List Dashboard
  if (!selectedEventId) {
    return (
      <div className="container main-content">
        <header style={{ marginBottom: '2rem' }}>
          <h2>MAR Certificate Generation</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Select an event to issue or revoke student certificates. Note that a certificate template is required for issuance.
          </p>
        </header>

        <div className="card show-alert-anim">
          {events.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '4rem 1rem', color: 'var(--text-muted)' }}>
              <Award style={{ width: '40px', height: '40px', marginBottom: '1rem' }} />
              <p>No events found in the database.</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table">
                <thead>
                  <tr>
                    <th>Event Details</th>
                    <th>Venue</th>
                    <th>Template status</th>
                    <th>Attended Students</th>
                    <th>Certificates Issued</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {events.map((event) => (
                    <tr key={event.id}>
                      <td style={{ fontWeight: 600 }}>
                        {event.name}
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 400, marginTop: '0.25rem' }}>
                          Date: {new Date(event.event_date).toLocaleDateString()}
                        </div>
                      </td>
                      <td>{event.venue}</td>
                      <td>
                        {event.certificate_template ? (
                          <span className="badge badge-approved" style={{ fontSize: '0.75rem' }}>Configured</span>
                        ) : (
                          <span className="badge badge-pending" style={{ fontSize: '0.75rem', background: 'rgba(239, 68, 68, 0.15)', color: 'var(--danger)' }}>Missing Background</span>
                        )}
                      </td>
                      <td style={{ fontWeight: 500 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <Users size={14} style={{ color: 'var(--text-muted)' }} />
                          {event.attended_count}
                        </div>
                      </td>
                      <td style={{ fontWeight: 500 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', color: 'var(--success)' }}>
                          <Award size={14} />
                          {event.issued_count} / {event.attended_count}
                        </div>
                      </td>
                      <td>
                        <button
                          onClick={() => setSelectedEventId(event.id)}
                          className="btn btn-primary btn-sm"
                          style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                        >
                          <Settings size={14} /> Manage
                        </button>
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
  }

  // View 2: Manage Specific Event Certificates Subview
  return (
    <div className="container main-content">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '2rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <button
            onClick={() => setSelectedEventId(null)}
            className="btn-link"
            style={{
              fontWeight: 500,
              fontSize: '0.9rem',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '4px',
              marginBottom: '0.5rem',
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              color: 'var(--primary)',
              padding: 0,
            }}
          >
            <ChevronLeft size={14} /> Back to Certificates Dashboard
          </button>
          <h2>Manage Certificates: {selectedEvent?.name}</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Event Date: {selectedEvent && new Date(selectedEvent.event_date).toLocaleDateString()} &bull; Venue: {selectedEvent?.venue}
          </p>
        </div>

        <div>
          {selectedEvent?.certificate_template ? (
            <a
              href={selectedEvent.certificate_template}
              target="_blank"
              rel="noreferrer"
              className="btn btn-secondary btn-sm"
              style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
            >
              Preview Template
            </a>
          ) : (
            <button
              onClick={() => navigate(`/admin/events/edit/${selectedEventId}`)}
              className="btn btn-danger btn-sm"
              style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
            >
              Upload Template
            </button>
          )}
        </div>
      </div>

      {!selectedEvent?.certificate_template && (
        <div className="alert alert-danger show-alert-anim" style={{ marginBottom: '2rem' }}>
          <AlertCircle className="alert-icon" />
          <div className="alert-content">
            <strong>Warning:</strong> No certificate template uploaded yet. You cannot issue certificates until you upload a template in the event settings.{' '}
            <button
              onClick={() => navigate(`/admin/events/edit/${selectedEventId}`)}
              style={{ color: 'inherit', textDecoration: 'underline', fontWeight: 'bold', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}
            >
              Upload Template Now
            </button>
          </div>
        </div>
      )}

      <div className="card">
        <div style={{ display: 'flex', gap: '1rem', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', justifyContent: 'space-between' }}>
          {/* Search & Filters */}
          <div style={{ display: 'flex', gap: '0.75rem', flexGrow: 1, maxWidth: '500px' }}>
            <div style={{ position: 'relative', flexGrow: 1 }}>
              <Search size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
              <input
                type="text"
                placeholder="Search by name, roll or batch..."
                className="form-control"
                style={{ paddingLeft: '2.25rem' }}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <select
              className="form-control"
              style={{ width: '160px', maxWidth: '100%' }}
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="all">All Students</option>
              <option value="issued">Issued</option>
              <option value="pending">Not Issued</option>
            </select>
          </div>

          <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
            Showing <span>{filteredStudents.length}</span> of {students.length} attended students.
          </div>
        </div>

        {loading ? (
          <p style={{ textAlign: 'center', padding: '3rem 0' }}>Loading students list...</p>
        ) : students.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '4rem 1rem', color: 'var(--text-muted)' }}>
            <p>No students have attended this event yet (no entry scans recorded).</p>
          </div>
        ) : (
          <>
            <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem' }}>
              <button
                onClick={handleIssueCertificates}
                className="btn btn-success btn-sm"
                disabled={selectedRegIds.length === 0 || !selectedEvent?.certificate_template || submitting}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
              >
                <CheckCircle size={14} /> Issue Selected ({selectedRegIds.length})
              </button>
              <button
                onClick={handleRevokeCertificates}
                className="btn btn-danger btn-sm"
                disabled={selectedRegIds.length === 0 || submitting}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
              >
                <XCircle size={14} /> Revoke Selected ({selectedRegIds.length})
              </button>
            </div>

            <div className="table-responsive">
              <table className="table">
                <thead>
                  <tr>
                    <th style={{ width: '40px', textAlign: 'center' }}>
                      <input
                        type="checkbox"
                        checked={filteredStudents.length > 0 && selectedRegIds.length === filteredStudents.length}
                        onChange={(e) => handleSelectAll(e.target.checked)}
                        style={{ cursor: 'pointer' }}
                      />
                    </th>
                    <th>Student Details</th>
                    <th>Batch / Roll</th>
                    <th>Check-in Time</th>
                    <th>Certificate Status</th>
                    <th>Certificate Code</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredStudents.map((student) => (
                    <tr key={student.reg_id}>
                      <td style={{ textAlign: 'center', verticalAlign: 'middle' }}>
                        <input
                          type="checkbox"
                          checked={selectedRegIds.includes(student.reg_id)}
                          onChange={(e) => handleSelectRow(student.reg_id, e.target.checked)}
                          style={{ cursor: 'pointer' }}
                        />
                      </td>
                      <td style={{ verticalAlign: 'middle' }}>
                        <div style={{ fontWeight: 600 }}>{student.student_name}</div>
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{student.course}</div>
                      </td>
                      <td style={{ verticalAlign: 'middle' }}>
                        <div>{student.batch_name}</div>
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Roll: {student.class_roll}</div>
                      </td>
                      <td style={{ verticalAlign: 'middle', fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                        {new Date(student.scanned_at).toLocaleString()}
                      </td>
                      <td style={{ verticalAlign: 'middle' }}>
                        {student.certificate_id ? (
                          <span className="badge badge-approved" style={{ border: '1px solid rgba(16, 185, 129, 0.3)' }}>
                            Issued
                          </span>
                        ) : (
                          <span className="badge badge-pending" style={{ border: '1px solid rgba(245, 158, 11, 0.3)' }}>
                            Not Issued
                          </span>
                        )}
                      </td>
                      <td style={{ verticalAlign: 'middle' }}>
                        {student.certificate_id ? (
                          <>
                            <code style={{ fontFamily: 'monospace', fontSize: '0.85rem', color: 'var(--accent)', fontWeight: 'bold' }}>
                              {student.certificate_code}
                            </code>
                            <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                              On: {student.issued_at && new Date(student.issued_at).toLocaleDateString()}
                            </div>
                          </>
                        ) : (
                          <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>—</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>
    </div>
  );
};
