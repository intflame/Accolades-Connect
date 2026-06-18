import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Check, X, FileText, Search, RefreshCw, Phone, MessageSquare } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  event_date: string;
}

interface Batch {
  id: number;
  name: string;
}

interface AttendeeRow {
  student_id: string;
  student_name: string;
  class_roll: string;
  university_roll: string;
  food_preference: string;
  course: string;
  batch_id: number | null;
  batch_name: string;
  reg_id: number;
  reg_status: string;
  scan_id: number | null;
  scan_type: string | null;
  scanned_at: string | null;
}

export const AttendanceLogs: React.FC = () => {
  const { profile } = useAuth();
  const navigate = useNavigate();

  const [events, setEvents] = useState<Event[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<number>(0);
  const [loading, setLoading] = useState(true);

  // Filters
  const [batchFilter, setBatchFilter] = useState<number>(0);
  const [foodFilter, setFoodFilter] = useState<string>('');
  const [attendanceFilter, setAttendanceFilter] = useState<string>('');
  const [search, setSearch] = useState<string>('');

  const [attendees, setAttendees] = useState<AttendeeRow[]>([]);
  const [filteredAttendees, setFilteredAttendees] = useState<AttendeeRow[]>([]);
  const [stats, setStats] = useState({
    approved: 0,
    present: 0,
    absent: 0,
    veg: 0,
    nonveg: 0,
  });

  // Load events and batches
  useEffect(() => {
    const initData = async () => {
      try {
        const { data: eventsData } = await supabase
          .from('events')
          .select('id, name, event_date')
          .order('event_date', { ascending: true });

        const { data: batchesData } = await supabase
          .from('batches')
          .select('*')
          .order('name', { ascending: false });

        setEvents(eventsData || []);
        setBatches(batchesData || []);

        if (eventsData && eventsData.length > 0) {
          setSelectedEventId(eventsData[0].id);
        }
      } catch (err) {
        console.error('Error fetching initial attendance metadata:', err);
      }
    };
    initData();
  }, []);

  const fetchAttendance = async () => {
    if (selectedEventId === 0) return;
    setLoading(true);
    try {
      // Query registrations joined with profile, batch, qr_tokens, and attendance_scans
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
            food_preference,
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
          )
        `)
        .eq('event_id', selectedEventId)
        .eq('status', 'approved');

      if (error) throw error;

      // Map rows
      const rows: AttendeeRow[] = (data || []).map((item: any) => {
        const studProfile = item.profiles || {};
        const qrs = item.qr_tokens || [];
        // Find entry scan
        let entryScan: any = null;
        for (const token of qrs) {
          const scans = token.attendance_scans || [];
          const found = scans.find((s: any) => s.scan_type === 'entry');
          if (found) {
            entryScan = found;
            break;
          }
        }

        return {
          student_id: studProfile.id || '',
          student_name: studProfile.name || 'Unknown',
          class_roll: studProfile.class_roll || '',
          university_roll: studProfile.university_roll || '',
          food_preference: studProfile.food_preference || 'veg',
          course: studProfile.course || 'BCA',
          batch_id: studProfile.batch_id || null,
          batch_name: studProfile.batches?.name || 'N/A',
          reg_id: item.id,
          reg_status: item.status,
          scan_id: entryScan ? entryScan.id : null,
          scan_type: entryScan ? entryScan.scan_type : null,
          scanned_at: entryScan ? entryScan.scanned_at : null,
        };
      });

      // Sort alphabetically by name
      rows.sort((a, b) => a.student_name.localeCompare(b.student_name));
      setAttendees(rows);

      // Compute stats
      const approved = rows.length;
      const present = rows.filter((r) => r.scan_id !== null).length;
      const absent = approved - present;
      const veg = rows.filter((r) => r.food_preference === 'veg').length;
      const nonveg = rows.filter((r) => r.food_preference === 'non-veg').length;

      setStats({
        approved,
        present,
        absent,
        veg,
        nonveg,
      });
    } catch (err) {
      console.error('Error fetching attendance logs:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAttendance();
  }, [selectedEventId]);

  // Apply filters locally when attendees list or filters change
  useEffect(() => {
    let filtered = [...attendees];

    if (batchFilter > 0) {
      filtered = filtered.filter((r) => r.batch_id === batchFilter);
    }

    if (foodFilter) {
      filtered = filtered.filter((r) => r.food_preference === foodFilter);
    }

    if (attendanceFilter === 'present') {
      filtered = filtered.filter((r) => r.scan_id !== null);
    } else if (attendanceFilter === 'absent') {
      filtered = filtered.filter((r) => r.scan_id === null);
    }

    if (search.trim()) {
      const term = search.toLowerCase();
      filtered = filtered.filter(
        (r) =>
          r.student_name.toLowerCase().includes(term) ||
          r.class_roll.toLowerCase().includes(term) ||
          r.university_roll.toLowerCase().includes(term)
      );
    }

    setFilteredAttendees(filtered);
  }, [attendees, batchFilter, foodFilter, attendanceFilter, search]);

  const handleResetFilters = () => {
    setBatchFilter(0);
    setFoodFilter('');
    setAttendanceFilter('');
    setSearch('');
  };

  const selectedEvent = events.find((e) => e.id === selectedEventId);

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '0.5rem' }}>
        <div>
          <h2>Real-time Event Attendance Logs</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Track live student entries, check food tallies, and filter absentees list.
          </p>
        </div>
        <button
          onClick={() => navigate(`/admin/reports?eventId=${selectedEventId}`)}
          className="btn btn-secondary btn-sm"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <FileText size={16} /> Export Reports
        </button>
      </div>

      {/* Filter Selector Card */}
      <div className="card" style={{ padding: '1.25rem', marginBottom: '2rem' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr auto', gap: '0.75rem', alignItems: 'flex-end' }}>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="event_id">Select Event</label>
            <select
              id="event_id"
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

          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="batch_id">Batch</label>
            <select
              id="batch_id"
              className="form-control"
              value={batchFilter}
              onChange={(e) => setBatchFilter(Number(e.target.value))}
            >
              <option value="0">All Batches</option>
              {batches.map((batch) => (
                <option key={batch.id} value={batch.id}>
                  Batch {batch.name}
                </option>
              ))}
            </select>
          </div>

          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="food_preference">Food Preference</label>
            <select
              id="food_preference"
              className="form-control"
              value={foodFilter}
              onChange={(e) => setFoodFilter(e.target.value)}
            >
              <option value="">All Preferences</option>
              <option value="veg">Veg</option>
              <option value="non-veg">Non-Veg</option>
            </select>
          </div>

          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="attendance_status">Attendance</label>
            <select
              id="attendance_status"
              className="form-control"
              value={attendanceFilter}
              onChange={(e) => setAttendanceFilter(e.target.value)}
            >
              <option value="">All Students</option>
              <option value="present">Present (Checked-in)</option>
              <option value="absent">Absent</option>
            </select>
          </div>

          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              onClick={handleResetFilters}
              className="btn btn-secondary"
              style={{ padding: '0.75rem 1.25rem' }}
              title="Reset Filters"
            >
              <RefreshCw size={16} />
            </button>
          </div>
        </div>

        <div className="form-group" style={{ marginTop: '1rem', marginBottom: 0 }}>
          <label className="form-label" htmlFor="search">Search Name / Roll Number</label>
          <input
            type="text"
            id="search"
            className="form-control"
            placeholder="Type search terms..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {selectedEventId > 0 && selectedEvent ? (
        <>
          {/* Event Statistics Badges */}
          <div className="dashboard-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', marginBottom: '2rem' }}>
            <div className="stat-card" style={{ padding: '1rem' }}>
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.5rem' }}>{stats.approved}</div>
                <div className="stat-label">Approved Students</div>
              </div>
            </div>
            <div className="stat-card" style={{ padding: '1rem', borderColor: 'var(--success)' }}>
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.5rem', color: 'var(--success)' }}>{stats.present}</div>
                <div className="stat-label">Checked-In (Present)</div>
              </div>
            </div>
            <div className="stat-card" style={{ padding: '1rem', borderColor: 'var(--danger)' }}>
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.5rem', color: 'var(--danger)' }}>{stats.absent}</div>
                <div className="stat-label">Absent (Pending)</div>
              </div>
            </div>
            <div className="stat-card" style={{ padding: '1rem', borderColor: 'var(--accent)' }}>
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.3rem', color: 'var(--accent)' }}>
                  Veg: {stats.veg} | Non: {stats.nonveg}
                </div>
                <div className="stat-label">Approved Food Ratio</div>
              </div>
            </div>
          </div>

          {/* Attendance Data Table */}
          <div className="card">
            <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
              <h3>Attendance List ({filteredAttendees.length} rows matching filters)</h3>
              <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                Event date: {new Date(selectedEvent.event_date).toLocaleDateString()}
              </div>
            </div>

            {loading ? (
              <p style={{ textAlign: 'center', padding: '3rem 0' }}>Loading logs...</p>
            ) : filteredAttendees.length === 0 ? (
              <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '3rem 0' }}>
                No student records match the select filters.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Student Name</th>
                      <th>Roll & Batch</th>
                      <th>Food Preference</th>
                      <th>Check-in Status</th>
                      <th>Scan Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredAttendees.map((row) => (
                      <tr key={row.reg_id} style={row.scan_id ? {} : { opacity: 0.7 }}>
                        <td style={{ fontWeight: 600 }}>{row.student_name}</td>
                        <td>
                          <div style={{ fontWeight: 500 }}>{row.course} - Batch {row.batch_name}</div>
                          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Roll: {row.class_roll}</div>
                        </td>
                        <td>
                          <span
                            style={{
                              textTransform: 'capitalize',
                              fontSize: '0.85rem',
                              padding: '0.15rem 0.5rem',
                              borderRadius: '4px',
                              background: row.food_preference === 'veg' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(244, 63, 94, 0.1)',
                              color: row.food_preference === 'veg' ? 'var(--success)' : 'var(--danger)',
                            }}
                          >
                            {row.food_preference}
                          </span>
                        </td>
                        <td>
                          {row.scan_id ? (
                            <span className="badge badge-approved" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
                              <Check size={10} /> PRESENT
                            </span>
                          ) : (
                            <span className="badge badge-pending" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', background: 'rgba(239, 68, 68, 0.15)', color: 'var(--danger)' }}>
                              <X size={10} /> ABSENT
                            </span>
                          )}
                        </td>
                        <td>
                          {row.scanned_at ? (
                            new Date(row.scanned_at).toLocaleString()
                          ) : (
                            <span style={{ color: 'var(--text-muted)' }}>N/A</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      ) : (
        <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
          <p style={{ color: 'var(--text-muted)' }}>No events are currently configured. Please create an event first.</p>
        </div>
      )}
    </div>
  );
};
