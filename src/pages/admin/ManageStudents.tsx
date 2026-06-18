import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Search, RefreshCw, UserCheck, UserX, Trash2, Check, Phone, MessageSquare, Loader2 } from 'lucide-react';

interface StudentProfile {
  id: string;
  name: string;
  email: string;
  course: string;
  class_roll: string;
  university_roll: string;
  contact_number: string;
  whatsapp_number: string;
  status: string;
  batch_id: number | null;
  profile_photo: string | null;
  batch_name?: string;
}

interface Batch {
  id: number;
  name: string;
}

export const ManageStudents: React.FC = () => {
  const { profile } = useAuth();
  const [students, setStudents] = useState<StudentProfile[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState('');
  const [batchFilter, setBatchFilter] = useState<number>(0);
  const [statusFilter, setStatusFilter] = useState('');

  const fetchFiltersAndStudents = async () => {
    setLoading(true);
    try {
      // 1. Fetch Batches
      const { data: batchesData } = await supabase
        .from('batches')
        .select('*')
        .order('name', { ascending: false });
      setBatches(batchesData || []);

      // 2. Fetch Profiles with role = 'student'
      let query = supabase
        .from('profiles')
        .select(`
          *,
          batches(name)
        `)
        .eq('role', 'student');

      if (batchFilter > 0) {
        query = query.eq('batch_id', batchFilter);
      }

      if (statusFilter) {
        query = query.eq('status', statusFilter);
      }

      // Supabase text search or filtering
      const { data: studentsData, error: studentsError } = await query;
      if (studentsError) throw studentsError;

      let list: StudentProfile[] = (studentsData || []).map((s: any) => ({
        ...s,
        batch_name: s.batches?.name || 'N/A',
      }));

      // Apply search locally if search term exists (handles case insensitive matches on name, roll, email)
      if (search.trim()) {
        const term = search.toLowerCase();
        list = list.filter(
          (s) =>
            s.name.toLowerCase().includes(term) ||
            s.email.toLowerCase().includes(term) ||
            (s.class_roll && s.class_roll.toLowerCase().includes(term)) ||
            (s.university_roll && s.university_roll.toLowerCase().includes(term))
        );
      }

      // Sort by pending first, then by name
      list.sort((a, b) => {
        if (a.status === 'pending_approval' && b.status !== 'pending_approval') return -1;
        if (a.status !== 'pending_approval' && b.status === 'pending_approval') return 1;
        return a.name.localeCompare(b.name);
      });

      setStudents(list);
    } catch (err) {
      console.error('Error fetching students:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFiltersAndStudents();
  }, [batchFilter, statusFilter]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchFiltersAndStudents();
  };

  const handleResetFilters = () => {
    setSearch('');
    setBatchFilter(0);
    setStatusFilter('');
    // For resetting, we manually trigger fetch with cleared filters
    setTimeout(() => {
      fetchFiltersAndStudents();
    }, 50);
  };

  const handleUpdateStatus = async (studentId: string, newStatus: string, actionName: string) => {
    setActionLoading(studentId);
    try {
      const { error } = await supabase
        .from('profiles')
        .update({ status: newStatus })
        .eq('id', studentId);

      if (error) throw error;

      // Log activity
      const studentEmail = students.find((s) => s.id === studentId)?.email || '';
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: `student_${actionName}`,
        details: `${actionName.toUpperCase()} student account ID: ${studentId}, Email: ${studentEmail}`,
      });

      // Update local state
      setStudents((prev) =>
        prev.map((s) => (s.id === studentId ? { ...s, status: newStatus } : s))
      );
    } catch (err: any) {
      alert(err.message || 'Failed to update student status.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleDeleteStudent = async (studentId: string) => {
    if (
      !window.confirm(
        'WARNING: Permanently delete this student account and all their registrations, payments, and QR scans? This action cannot be undone.'
      )
    ) {
      return;
    }

    setActionLoading(studentId);
    try {
      const studentEmail = students.find((s) => s.id === studentId)?.email || '';

      // Delete from profiles (which should trigger cascade to event registrations, qr tokens, payments etc)
      // Note: Because it references auth.users, we might not be able to delete the auth.user from frontend without auth admin API.
      // But we can delete the profile row.
      const { error } = await supabase.from('profiles').delete().eq('id', studentId);
      if (error) throw error;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'student_deleted',
        details: `Deleted student profile: ${studentEmail} (ID: ${studentId})`,
      });

      setStudents((prev) => prev.filter((s) => s.id !== studentId));
    } catch (err: any) {
      alert(err.message || 'Failed to delete student profile.');
    } finally {
      setActionLoading(null);
    }
  };

  if (loading && students.length === 0) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading student directory...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem' }}>
        <h2>Manage Students</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
          Review student details, approve pending accounts, and lock/unlock access.
        </p>
      </div>

      {/* Search and Filter Bar */}
      <div className="card" style={{ padding: '1.5rem', marginBottom: '2rem' }}>
        <form onSubmit={handleSearchSubmit} style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr auto', gap: '1rem', alignItems: 'flex-end' }}>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="search">Search Name / Roll / Email</label>
            <input
              type="text"
              id="search"
              className="form-control"
              placeholder="Search..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label" htmlFor="batch_id">Filter Batch</label>
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
            <label className="form-label" htmlFor="status">Filter Status</label>
            <select
              id="status"
              className="form-control"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="">All Statuses</option>
              <option value="pending_approval">Pending Approval</option>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>

          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button type="submit" className="btn btn-primary" style={{ padding: '0.75rem 1.25rem' }}>
              <Search size={16} />
            </button>
            <button
              type="button"
              onClick={handleResetFilters}
              className="btn btn-secondary"
              style={{ padding: '0.75rem 1.25rem' }}
              title="Reset Filters"
            >
              <RefreshCw size={16} />
            </button>
          </div>
        </form>
      </div>

      {/* Student Listing Directory */}
      <div className="card show-alert-anim">
        {students.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '4rem 1rem', color: 'var(--text-muted)' }}>
            <p>No student records found matching the criteria.</p>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Photo</th>
                  <th>Name & Email</th>
                  <th>Batch & Rolls</th>
                  <th>Contacts</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {students.map((stud) => (
                  <tr key={stud.id}>
                    <td>
                      {stud.profile_photo ? (
                        <img
                          src={stud.profile_photo}
                          alt="Profile"
                          style={{ width: '40px', height: '40px', borderRadius: '50%', objectFit: 'cover', border: '1px solid var(--primary)' }}
                        />
                      ) : (
                        <div
                          style={{
                            width: '40px',
                            height: '40px',
                            borderRadius: '50%',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: 'rgba(99, 102, 241, 0.2)',
                            color: 'var(--primary)',
                            fontWeight: 'bold',
                            fontSize: '0.95rem',
                          }}
                        >
                          {stud.name ? stud.name.charAt(0).toUpperCase() : 'S'}
                        </div>
                      )}
                    </td>
                    <td>
                      <div style={{ fontWeight: 600 }}>{stud.name}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{stud.email}</div>
                    </td>
                    <td>
                      <div style={{ fontWeight: 500, fontSize: '0.9rem' }}>
                        {stud.course || 'BCA'} - Batch {stud.batch_name}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Class Roll: {stud.class_roll}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Uni Roll: {stud.university_roll}</div>
                    </td>
                    <td>
                      <div style={{ fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '4px' }}>
                        <Phone size={10} /> {stud.contact_number}
                      </div>
                      <div style={{ fontSize: '0.85rem', color: '#34d399', display: 'flex', alignItems: 'center', gap: '4px', marginTop: '0.25rem' }}>
                        <MessageSquare size={10} /> {stud.whatsapp_number}
                      </div>
                    </td>
                    <td>
                      <span className={`badge ${stud.status === 'active' ? 'badge-approved' : stud.status === 'suspended' ? 'badge-pending' : 'badge-pending'}`}>
                        {stud.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td>
                      <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                        {actionLoading === stud.id ? (
                          <Loader2 className="animate-spin" size={16} />
                        ) : (
                          <>
                            {stud.status === 'pending_approval' && (
                              <button
                                onClick={() => handleUpdateStatus(stud.id, 'active', 'approved')}
                                className="btn btn-success btn-sm"
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                              >
                                <Check size={12} /> Approve
                              </button>
                            )}
                            {stud.status === 'active' && (
                              <button
                                onClick={() => handleUpdateStatus(stud.id, 'suspended', 'suspended')}
                                className="btn btn-danger btn-sm"
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                              >
                                <UserX size={12} /> Suspend
                              </button>
                            )}
                            {stud.status === 'suspended' && (
                              <button
                                onClick={() => handleUpdateStatus(stud.id, 'active', 'activated')}
                                className="btn btn-accent btn-sm"
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                              >
                                <UserCheck size={12} /> Activate
                              </button>
                            )}

                            <button
                              onClick={() => handleDeleteStudent(stud.id)}
                              className="btn btn-danger btn-sm"
                              title="Delete Student Account"
                              style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '4px',
                                background: 'rgba(244, 63, 94, 0.1)',
                                borderColor: 'rgba(244, 63, 94, 0.2)',
                                color: 'var(--danger)',
                              }}
                            >
                              <Trash2 size={12} /> Delete
                            </button>
                          </>
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
    </div>
  );
};
