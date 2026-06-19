import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { Check, X, RefreshCw, FileText } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  event_date: string;
}

interface Batch {
  id: number;
  name: string;
}

interface FoodRow {
  student_id: string;
  student_name: string;
  course: string;
  batch_name: string;
  class_roll: string;
  food_preference: string;
  food_scan_id: number | null;
  food_scanned_at: string | null;
}

export const FoodReport: React.FC = () => {
  const [events, setEvents] = useState<Event[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<number>(0);
  const [loading, setLoading] = useState(true);

  // Filters
  const [batchFilter, setBatchFilter] = useState<number>(0);
  const [foodFilter, setFoodFilter] = useState<string>('');
  const [checkedInFilter, setCheckedInFilter] = useState<string>('');
  const [search, setSearch] = useState<string>('');

  const [attendees, setAttendees] = useState<FoodRow[]>([]);
  const [filteredAttendees, setFilteredAttendees] = useState<FoodRow[]>([]);
  const [stats, setStats] = useState({
    total: 0,
    veg: 0,
    nonveg: 0,
    vegCheckedIn: 0,
    nonvegCheckedIn: 0,
    totalCheckedIn: 0
  });

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
        console.error('Error fetching food report metadata:', err);
      }
    };
    initData();
  }, []);

  const fetchFoodData = async () => {
    if (selectedEventId === 0) return;
    setLoading(true);
    try {
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

      const rows: FoodRow[] = (data || []).map((item: any) => {
        const profile = item.profiles || {};
        const qrs = item.qr_tokens || [];
        
        let foodScan: any = null;
        for (const token of qrs) {
          const scans = token.attendance_scans || [];
          const found = scans.find((s: any) => s.scan_type === 'food');
          if (found) {
            foodScan = found;
            break;
          }
        }

        return {
          student_id: profile.id || '',
          student_name: profile.name || 'Unknown',
          course: profile.course || 'BCA',
          batch_name: profile.batches?.name || 'N/A',
          class_roll: profile.class_roll || '',
          food_preference: profile.food_preference || 'veg',
          food_scan_id: foodScan ? foodScan.id : null,
          food_scanned_at: foodScan ? foodScan.scanned_at : null,
        };
      });

      rows.sort((a, b) => a.student_name.localeCompare(b.student_name));
      setAttendees(rows);

      // Calculate stats
      const total = rows.length;
      const veg = rows.filter((r) => r.food_preference === 'veg').length;
      const nonveg = rows.filter((r) => r.food_preference === 'non-veg').length;
      
      const vegCheckedIn = rows.filter((r) => r.food_preference === 'veg' && r.food_scan_id !== null).length;
      const nonvegCheckedIn = rows.filter((r) => r.food_preference === 'non-veg' && r.food_scan_id !== null).length;
      const totalCheckedIn = vegCheckedIn + nonvegCheckedIn;

      setStats({
        total,
        veg,
        nonveg,
        vegCheckedIn,
        nonvegCheckedIn,
        totalCheckedIn
      });

    } catch (err) {
      console.error('Error fetching food data:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFoodData();
  }, [selectedEventId]);

  useEffect(() => {
    let filtered = [...attendees];

    if (batchFilter > 0) {
      // Find batch name matching batchFilter ID to check against string or batch id if profile joins it
      // Since row doesn't have batch_id explicitly mapped, let's load batch_id too
      // Wait, we can filter using profiles.batch_id which we fetched. Let's make sure batch_id is in rows or filter by batch name.
      // Let's filter by batch name based on the batch list
      const batchObj = batches.find(b => b.id === batchFilter);
      if (batchObj) {
        filtered = filtered.filter((r) => r.batch_name === batchObj.name);
      }
    }

    if (foodFilter) {
      filtered = filtered.filter((r) => r.food_preference === foodFilter);
    }

    if (checkedInFilter === 'yes') {
      filtered = filtered.filter((r) => r.food_scan_id !== null);
    } else if (checkedInFilter === 'no') {
      filtered = filtered.filter((r) => r.food_scan_id === null);
    }

    if (search.trim()) {
      const term = search.toLowerCase();
      filtered = filtered.filter(
        (r) =>
          r.student_name.toLowerCase().includes(term) ||
          r.class_roll.toLowerCase().includes(term)
      );
    }

    setFilteredAttendees(filtered);
  }, [attendees, batchFilter, foodFilter, checkedInFilter, search, batches]);

  const handleExportCSV = () => {
    if (filteredAttendees.length === 0) return;
    
    const headers = ['Student Name', 'Course & Batch', 'Class Roll', 'Food Preference', 'Food Checked In', 'Scan Time'];
    const csvRows = [
      headers.join(','),
      ...filteredAttendees.map((r) => [
        `"${r.student_name.replace(/"/g, '""')}"`,
        `"${r.course} - Batch ${r.batch_name}"`,
        `"${r.class_roll}"`,
        `"${r.food_preference.toUpperCase()}"`,
        `"${r.food_scan_id ? 'YES' : 'NO'}"`,
        `"${r.food_scanned_at ? new Date(r.food_scanned_at).toLocaleString() : 'N/A'}"`
      ].join(','))
    ].join('\n');

    const blob = new Blob([csvRows], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `food_preference_report_event_${selectedEventId}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const selectedEvent = events.find((e) => e.id === selectedEventId);

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '0.5rem' }}>
        <div>
          <h2>Food Preference Report</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Audit catering counts, food coupon check-ins, and veg/non-veg breakdown per event.
          </p>
        </div>
        <button
          onClick={handleExportCSV}
          disabled={filteredAttendees.length === 0}
          className="btn btn-secondary btn-sm"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <FileText size={16} /> Export CSV List
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
            <label className="form-label" htmlFor="food_preference">Cuisine Preference</label>
            <select
              id="food_preference"
              className="form-control"
              value={foodFilter}
              onChange={(e) => setFoodFilter(e.target.value)}
            >
              <option value="">All Preferences</option>
              <option value="veg">Veg Only</option>
              <option value="non-veg">Non-Veg Only</option>
            </select>
          </div>

          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="food_checked_in">Food Checked In</label>
            <select
              id="food_checked_in"
              className="form-control"
              value={checkedInFilter}
              onChange={(e) => setCheckedInFilter(e.target.value)}
            >
              <option value="">All Scans</option>
              <option value="yes">Checked In</option>
              <option value="no">Pending Coupon</option>
            </select>
          </div>

          <button
            onClick={() => {
              setBatchFilter(0);
              setFoodFilter('');
              setCheckedInFilter('');
              setSearch('');
            }}
            className="btn btn-secondary"
            style={{ padding: '0.75rem 1.25rem' }}
          >
            <RefreshCw size={16} />
          </button>
        </div>

        <div className="form-group" style={{ marginTop: '1rem', marginBottom: 0 }}>
          <label className="form-label" htmlFor="search">Search Name / Class Roll</label>
          <input
            type="text"
            id="search"
            className="form-control"
            placeholder="Type student name..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {selectedEventId > 0 && selectedEvent ? (
        <>
          {/* Food Stat Dashboard Cards */}
          <div className="dashboard-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', marginBottom: '2rem' }}>
            <div className="stat-card">
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.8rem' }}>{stats.veg}</div>
                <div className="stat-label">Total Veg Plates (Approved)</div>
                <div style={{ fontSize: '0.75rem', color: 'var(--success)', marginTop: '0.25rem' }}>
                  {stats.vegCheckedIn} served ({stats.veg - stats.vegCheckedIn} pending)
                </div>
              </div>
            </div>

            <div className="stat-card" style={{ borderColor: '#f87171' }}>
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.8rem', color: '#f87171' }}>{stats.nonveg}</div>
                <div className="stat-label">Total Non-Veg Plates (Approved)</div>
                <div style={{ fontSize: '0.75rem', color: '#f87171', marginTop: '0.25rem' }}>
                  {stats.nonvegCheckedIn} served ({stats.nonveg - stats.nonvegCheckedIn} pending)
                </div>
              </div>
            </div>

            <div className="stat-card" style={{ borderColor: 'var(--primary)' }}>
              <div className="stat-info">
                <div className="stat-value" style={{ fontSize: '1.8rem', color: 'var(--primary)' }}>
                  {stats.totalCheckedIn} / {stats.total}
                </div>
                <div className="stat-label">Overall Plates Served</div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                  Progress: {stats.total > 0 ? Math.round((stats.totalCheckedIn / stats.total) * 100) : 0}% check-in rate
                </div>
              </div>
            </div>
          </div>

          {/* List Table */}
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1rem' }}>
              <h3>Catering List ({filteredAttendees.length} records)</h3>
            </div>

            {loading ? (
              <p style={{ textAlign: 'center', padding: '3rem 0' }}>Loading catering logs...</p>
            ) : filteredAttendees.length === 0 ? (
              <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '3rem 0' }}>
                No records match the current filter selection.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Student Name</th>
                      <th>Course & Batch</th>
                      <th>Class Roll</th>
                      <th>Preference</th>
                      <th>Catering Status</th>
                      <th>Served Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredAttendees.map((row) => (
                      <tr key={row.student_id}>
                        <td style={{ fontWeight: 600 }}>{row.student_name}</td>
                        <td>{row.course} - Batch {row.batch_name}</td>
                        <td>{row.class_roll}</td>
                        <td>
                          <span
                            style={{
                              textTransform: 'capitalize',
                              fontSize: '0.8rem',
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
                          {row.food_scan_id ? (
                            <span className="badge badge-approved" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
                              <Check size={10} /> SERVED
                            </span>
                          ) : (
                            <span className="badge badge-pending" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', background: 'rgba(239, 68, 68, 0.15)', color: 'var(--danger)' }}>
                              <X size={10} /> PENDING
                            </span>
                          )}
                        </td>
                        <td>
                          {row.food_scanned_at ? (
                            new Date(row.food_scanned_at).toLocaleString()
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
          <p style={{ color: 'var(--text-muted)' }}>No events are currently configured.</p>
        </div>
      )}
    </div>
  );
};
