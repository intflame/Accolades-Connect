import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Download, Users, Calendar, Loader2 } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  event_date: string;
}

export const ExportReports: React.FC = () => {
  const { profile } = useAuth();
  const [searchParams] = useSearchParams();
  const eventIdParam = searchParams.get('eventId');

  const [events, setEvents] = useState<Event[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<number>(0);
  const [reportType, setReportType] = useState<string>('registrations');
  const [loading, setLoading] = useState(false);

  // Column choices
  const [studentCols, setStudentCols] = useState({
    name: true,
    email: true,
    course: true,
    batch: true,
    classRoll: true,
    uniRoll: true,
    contact: true,
    whatsapp: true,
    food: true,
    status: true,
  });

  const [eventReportCols, setEventReportCols] = useState<Record<string, boolean>>({});

  useEffect(() => {
    const fetchEvents = async () => {
      try {
        const { data } = await supabase
          .from('events')
          .select('id, name, event_date')
          .order('event_date', { ascending: true });

        if (data) {
          setEvents(data);
          if (eventIdParam) {
            setSelectedEventId(Number(eventIdParam));
          } else if (data.length > 0) {
            setSelectedEventId(data[0].id);
          }
        }
      } catch (err) {
        console.error('Error fetching events:', err);
      }
    };
    fetchEvents();
  }, [eventIdParam]);

  // Define column options for each report type
  const eventColsConfig: Record<string, { key: string; name: string }[]> = {
    registrations: [
      { key: 'name', name: 'Student Name' },
      { key: 'course', name: 'Course' },
      { key: 'batch', name: 'Batch' },
      { key: 'classRoll', name: 'Class Roll' },
      { key: 'uniRoll', name: 'University Roll' },
      { key: 'email', name: 'Email' },
      { key: 'paymentMethod', name: 'Payment Method' },
      { key: 'paymentStatus', name: 'Payment Status' },
      { key: 'regStatus', name: 'Registration Status' },
      { key: 'eventRole', name: 'Event Role' },
      { key: 'contact', name: 'Contact No.' },
    ],
    attendance: [
      { key: 'name', name: 'Student Name' },
      { key: 'course', name: 'Course' },
      { key: 'batch', name: 'Batch' },
      { key: 'classRoll', name: 'Class Roll' },
      { key: 'uniRoll', name: 'University Roll' },
      { key: 'email', name: 'Email' },
      { key: 'scanType', name: 'Check-in Type' },
      { key: 'scannedAt', name: 'Scanned Time' },
      { key: 'contact', name: 'Contact No.' },
    ],
    absentees: [
      { key: 'name', name: 'Student Name' },
      { key: 'course', name: 'Course' },
      { key: 'batch', name: 'Batch' },
      { key: 'classRoll', name: 'Class Roll' },
      { key: 'uniRoll', name: 'University Roll' },
      { key: 'email', name: 'Email' },
      { key: 'contact', name: 'Contact No.' },
      { key: 'food', name: 'Food Pref.' },
    ],
    food: [
      { key: 'name', name: 'Student Name' },
      { key: 'course', name: 'Course' },
      { key: 'batch', name: 'Batch' },
      { key: 'classRoll', name: 'Class Roll' },
      { key: 'food', name: 'Food Pref.' },
      { key: 'present', name: 'Check-in (Present)' },
      { key: 'contact', name: 'Contact No.' },
    ],
  };

  // Initialize event columns when type changes
  useEffect(() => {
    const config = eventColsConfig[reportType] || [];
    const initialCols: Record<string, boolean> = {};
    config.forEach((c) => {
      initialCols[c.key] = true;
    });
    setEventReportCols(initialCols);
  }, [reportType]);

  const toggleAllStudentCols = () => {
    const allChecked = Object.values(studentCols).every((v) => v);
    setStudentCols({
      name: !allChecked,
      email: !allChecked,
      course: !allChecked,
      batch: !allChecked,
      classRoll: !allChecked,
      uniRoll: !allChecked,
      contact: !allChecked,
      whatsapp: !allChecked,
      food: !allChecked,
      status: !allChecked,
    });
  };

  const toggleAllEventCols = () => {
    const allChecked = Object.values(eventReportCols).every((v) => v);
    const updated = { ...eventReportCols };
    Object.keys(updated).forEach((k) => {
      updated[k] = !allChecked;
    });
    setEventReportCols(updated);
  };

  // CSV trigger helper
  const triggerCSVDownload = (filename: string, headers: string[], rows: any[][]) => {
    const csvContent = [
      headers.join(','),
      ...rows.map((e) => e.map((val) => `"${String(val ?? '').replace(/"/g, '""')}"`).join(',')),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleStudentReport = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const { data, error } = await supabase
        .from('profiles')
        .select(`
          *,
          batches(name)
        `)
        .eq('role', 'student')
        .order('name', { ascending: true });

      if (error) throw error;

      const headers: string[] = [];
      const keys: string[] = [];

      if (studentCols.name) { headers.push('Student Name'); keys.push('name'); }
      if (studentCols.email) { headers.push('Email'); keys.push('email'); }
      if (studentCols.course) { headers.push('Course'); keys.push('course'); }
      if (studentCols.batch) { headers.push('Batch'); keys.push('batch'); }
      if (studentCols.classRoll) { headers.push('Class Roll'); keys.push('class_roll'); }
      if (studentCols.uniRoll) { headers.push('University Roll'); keys.push('university_roll'); }
      if (studentCols.contact) { headers.push('Contact No.'); keys.push('contact_number'); }
      if (studentCols.whatsapp) { headers.push('WhatsApp No.'); keys.push('whatsapp_number'); }
      if (studentCols.food) { headers.push('Food Preference'); keys.push('food_preference'); }
      if (studentCols.status) { headers.push('Status'); keys.push('status'); }

      if (headers.length === 0) {
        alert('Please select at least one column to export.');
        return;
      }

      const rows = (data || []).map((p: any) => {
        return keys.map((k) => {
          if (k === 'batch') return p.batches?.name || 'N/A';
          return p[k];
        });
      });

      // Log report activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'report_exported',
        details: `Exported Student Master Report CSV`,
      });

      const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
      triggerCSVDownload(`student_master_report_${dateStr}.csv`, headers, rows);
    } catch (err: any) {
      alert(err.message || 'Failed to export report.');
    } finally {
      setLoading(false);
    }
  };

  const handleEventReport = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedEventId === 0) return;

    setLoading(true);
    try {
      const eventName = events.find((ev) => ev.id === selectedEventId)?.name || 'Event';
      const cleanEventName = eventName.toLowerCase().replace(/[^a-z0-9]/g, '_');

      // Fetch base registrations
      const { data, error } = await supabase
        .from('event_registrations')
        .select(`
          id,
          status,
          payment_method,
          assigned_role,
          profiles (
            id,
            name,
            course,
            class_roll,
            university_roll,
            email,
            contact_number,
            food_preference,
            batch_id,
            batches (
              name
            )
          ),
          payments (
            status
          ),
          qr_tokens (
            id,
            attendance_scans (
              id,
              scan_type,
              scanned_at
            )
          )
        `)
        .eq('event_id', selectedEventId);

      if (error) throw error;

      const headers: string[] = [];
      const cols = eventColsConfig[reportType] || [];
      cols.forEach((col) => {
        if (eventReportCols[col.key]) {
          headers.push(col.name);
        }
      });

      if (headers.length === 0) {
        alert('Please select at least one column.');
        return;
      }

      // Map registrations into flattened details
      let list = (data || []).map((item: any) => {
        const p = item.profiles || {};
        const qrs = item.qr_tokens || [];
        let entryScan: any = null;
        for (const token of qrs) {
          const scans = token.attendance_scans || [];
          const found = scans.find((s: any) => s.scan_type === 'entry');
          if (found) {
            entryScan = found;
            break;
          }
        }

        // Format role
        let roleName = 'Participant';
        if (item.assigned_role === 'volunteers') roleName = 'Volunteer';
        else if (item.assigned_role === 'OC') roleName = 'Organizing Committee (OC)';
        else if (item.assigned_role === 'CC') roleName = 'Core Committee (CC)';

        return {
          name: p.name || 'Unknown',
          course: p.course || 'BCA',
          batch: p.batches?.name || 'N/A',
          classRoll: p.class_roll || '',
          uniRoll: p.university_roll || '',
          email: p.email || '',
          paymentMethod: item.payment_method || 'N/A',
          paymentStatus: item.payments?.[0]?.status || 'unpaid',
          regStatus: item.status,
          eventRole: roleName,
          contact: p.contact_number || '',
          food: p.food_preference || 'veg',
          scanType: entryScan ? entryScan.scan_type : 'N/A',
          scannedAt: entryScan ? new Date(entryScan.scanned_at).toLocaleString() : 'N/A',
          present: entryScan ? 'PRESENT' : 'ABSENT',
          presentBool: entryScan !== null,
          approvedBool: item.status === 'approved',
        };
      });

      // Filter based on report type
      if (reportType === 'attendance') {
        // Only checked-in
        list = list.filter((item) => item.presentBool);
      } else if (reportType === 'absentees') {
        // Approved but absent
        list = list.filter((item) => item.approvedBool && !item.presentBool);
      } else if (reportType === 'food') {
        // Only approved
        list = list.filter((item) => item.approvedBool);
        // Sort by food preference first, then by name
        list.sort((a, b) => {
          const cmp = a.food.localeCompare(b.food);
          if (cmp !== 0) return cmp;
          return a.name.localeCompare(b.name);
        });
      } else {
        // Registrations - default sorting alphabetical
        list.sort((a, b) => a.name.localeCompare(b.name));
      }

      // Build CSV output rows
      const rows = list.map((item: any) => {
        const r: any[] = [];
        cols.forEach((col) => {
          if (eventReportCols[col.key]) {
            r.push(item[col.key]);
          }
        });
        return r;
      });

      // Log report activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'report_exported',
        details: `Exported event report: ${reportType} for Event ID: ${selectedEventId}`,
      });

      triggerCSVDownload(`event_${reportType}_${cleanEventName}.csv`, headers, rows);
    } catch (err: any) {
      alert(err.message || 'Failed to export report.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2.5rem' }}>
        <h2>Generate Operational Reports</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
          Export CSV formats for registration queues, food preferences, and attendance history logs.
        </p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '2rem' }}>
        
        {/* Student Master Report Card */}
        <div className="card" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <form onSubmit={handleStudentReport} style={{ display: 'flex', flexDirection: 'column', height: '100%', justifyContent: 'space-between' }}>
            <div>
              <h3 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--primary)' }}>
                <Users size={20} /> Student Master Report
              </h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '0.75rem', marginBottom: '1.5rem' }}>
                Export detailed profile records of all registered students. Choose which details to include below.
              </p>

              <div className="form-group" style={{ marginBottom: 0 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                  <label className="form-label" style={{ marginBottom: 0, fontWeight: 600 }}>Select Details to Include</label>
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    onClick={toggleAllStudentCols}
                    style={{ padding: '0.25rem 0.5rem', fontSize: '0.75rem' }}
                  >
                    Toggle All
                  </button>
                </div>
                <div
                  className="students-cols"
                  style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(2, 1fr)',
                    gap: '0.6rem',
                    background: 'var(--bg-input)',
                    padding: '0.85rem',
                    borderRadius: 'var(--radius-sm)',
                    border: '1px solid var(--border-color)',
                  }}
                >
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.name} onChange={(e) => setStudentCols({ ...studentCols, name: e.target.checked })} /> Student Name
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.email} onChange={(e) => setStudentCols({ ...studentCols, email: e.target.checked })} /> Email
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.course} onChange={(e) => setStudentCols({ ...studentCols, course: e.target.checked })} /> Course
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.batch} onChange={(e) => setStudentCols({ ...studentCols, batch: e.target.checked })} /> Batch
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.classRoll} onChange={(e) => setStudentCols({ ...studentCols, classRoll: e.target.checked })} /> Class Roll
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.uniRoll} onChange={(e) => setStudentCols({ ...studentCols, uniRoll: e.target.checked })} /> Uni Roll
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.contact} onChange={(e) => setStudentCols({ ...studentCols, contact: e.target.checked })} /> Contact No.
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.whatsapp} onChange={(e) => setStudentCols({ ...studentCols, whatsapp: e.target.checked })} /> WhatsApp No.
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.food} onChange={(e) => setStudentCols({ ...studentCols, food: e.target.checked })} /> Food Pref.
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                    <input type="checkbox" checked={studentCols.status} onChange={(e) => setStudentCols({ ...studentCols, status: e.target.checked })} /> Status
                  </label>
                </div>
              </div>
            </div>
            <div style={{ marginTop: '1.5rem', borderTop: '1px solid var(--border-color)', paddingTop: '1.25rem' }}>
              <button type="submit" disabled={loading} className="btn btn-primary" style={{ width: '100%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}>
                {loading ? <Loader2 className="animate-spin" size={16} /> : <Download size={16} />}
                Download CSV Report
              </button>
            </div>
          </form>
        </div>

        {/* Event Specific Report Settings Card */}
        <div className="card">
          <form onSubmit={handleEventReport} style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
            <h3 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--accent)', marginBottom: '1rem' }}>
              <Calendar size={20} /> Event Specific Reports
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: '1.5rem' }}>
              Select an event, report type, and choose which details to include in the exported report.
            </p>

            {events.length === 0 ? (
              <p style={{ color: 'var(--danger)', fontSize: '0.85rem' }}>Please configure at least one event first.</p>
            ) : (
              <>
                <div className="form-group">
                  <label className="form-label" htmlFor="event-select">Select Target Event</label>
                  <select
                    id="event-select"
                    className="form-control"
                    value={selectedEventId}
                    onChange={(e) => setSelectedEventId(Number(e.target.value))}
                  >
                    {events.map((ev) => (
                      <option key={ev.id} value={ev.id}>
                        {ev.name} ({new Date(ev.event_date).toLocaleDateString()})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label" htmlFor="type-select">Select Report Type</label>
                  <select
                    id="type-select"
                    className="form-control"
                    value={reportType}
                    onChange={(e) => setReportType(e.target.value)}
                  >
                    <option value="registrations">Registrants & Payments Report</option>
                    <option value="attendance">Checked-in Attendees List</option>
                    <option value="absentees">Absentees List (Approved but Absent)</option>
                    <option value="food">Food Preference Tally List</option>
                  </select>
                </div>

                <div className="form-group" style={{ marginBottom: 0 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                    <label className="form-label" style={{ marginBottom: 0, fontWeight: 600 }}>Select Details to Include</label>
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={toggleAllEventCols}
                      style={{ padding: '0.25rem 0.5rem', fontSize: '0.75rem' }}
                    >
                      Toggle All
                    </button>
                  </div>
                  <div
                    id="event-cols-container"
                    style={{
                      display: 'grid',
                      gridTemplateColumns: 'repeat(2, 1fr)',
                      gap: '0.6rem',
                      background: 'var(--bg-input)',
                      padding: '0.85rem',
                      borderRadius: 'var(--radius-sm)',
                      border: '1px solid var(--border-color)',
                    }}
                  >
                    {(eventColsConfig[reportType] || []).map((col) => (
                      <label key={col.key} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', cursor: 'pointer' }}>
                        <input
                          type="checkbox"
                          checked={eventReportCols[col.key] || false}
                          onChange={(e) => setEventReportCols({ ...eventReportCols, [col.key]: e.target.checked })}
                        />{' '}
                        {col.name}
                      </label>
                    ))}
                  </div>
                </div>

                <div style={{ marginTop: '1.5rem', borderTop: '1px solid var(--border-color)', paddingTop: '1.25rem' }}>
                  <button type="submit" disabled={loading} className="btn btn-accent" style={{ width: '100%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}>
                    {loading ? <Loader2 className="animate-spin" size={16} /> : <Download size={16} />}
                    Download CSV Report
                  </button>
                </div>
              </>
            )}
          </form>
        </div>

      </div>
    </div>
  );
};
