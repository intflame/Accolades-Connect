import React, { useEffect, useState } from 'react';
import { createClient } from '@supabase/supabase-js';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Trash2, PlusCircle, Loader2, Shield } from 'lucide-react';

interface ScannerUser {
  id: string;
  email: string;
  status: string;
  created_at: string;
}

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL || '';
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY || '';

// Secondary client instance without persistent session storage to prevent logging out the active admin session
const tempClient = createClient(supabaseUrl, supabaseAnonKey, {
  auth: {
    persistSession: false,
    autoRefreshToken: false,
  },
});

export const ManageScanners: React.FC = () => {
  const { profile } = useAuth();
  const [scanners, setScanners] = useState<ScannerUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  // Form Fields
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const fetchScanners = async () => {
    setLoading(true);
    try {
      const { data, error: fetchError } = await supabase
        .from('profiles')
        .select('id, email, status, created_at')
        .eq('role', 'scanner')
        .order('created_at', { ascending: false });

      if (fetchError) throw fetchError;
      setScanners(data || []);
    } catch (err: any) {
      console.error('Error fetching scanner users:', err);
      setError(err.message || 'Failed to fetch scanner users.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchScanners();
  }, []);

  const handleCreateScanner = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    if (!email.trim()) {
      setError('Scanner Email is required.');
      return;
    }
    if (password.length < 6) {
      setError('Password must be at least 6 characters long.');
      return;
    }
    if (password !== confirmPassword) {
      setError('Password confirmation does not match.');
      return;
    }

    setCreating(true);
    try {
      // Create new scanner account using standard signup with option metadata
      const { data, error: signUpError } = await tempClient.auth.signUp({
        email: email.trim(),
        password,
        options: {
          data: {
            role: 'scanner',
            name: 'Scanner Account',
          },
        },
      });

      if (signUpError) throw signUpError;
      if (!data.user) {
        throw new Error('Registration failed. Please check credentials.');
      }

      // Log activity under current admin
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'scanner_created',
        details: `Created scanner account: ${email.trim()}`,
      });

      setSuccess(`Scanner account '${email.trim()}' created successfully.`);
      setEmail('');
      setPassword('');
      setConfirmPassword('');
      fetchScanners();
    } catch (err: any) {
      setError(err.message || 'Failed to create scanner account.');
    } finally {
      setCreating(false);
    }
  };

  const handleDeleteScanner = async (targetId: string, targetEmail: string) => {
    if (targetId === profile?.id) {
      alert('You cannot delete your own admin/scanner account.');
      return;
    }

    if (!window.confirm(`Are you sure you want to delete scanner account '${targetEmail}'?`)) {
      return;
    }

    setDeletingId(targetId);
    setError(null);
    setSuccess(null);

    try {
      // Delete the scanner's profile row
      const { error: deleteError } = await supabase
        .from('profiles')
        .delete()
        .eq('id', targetId);

      if (deleteError) throw deleteError;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'scanner_deleted',
        details: `Deleted scanner account: ${targetEmail}`,
      });

      setSuccess(`Scanner account '${targetEmail}' deleted successfully.`);
      fetchScanners();
    } catch (err: any) {
      setError(err.message || 'Failed to delete scanner account.');
    } finally {
      setDeletingId(null);
    }
  };

  if (loading && scanners.length === 0) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading scanner profiles...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem' }}>
        <h2>Manage Scanners</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
          Configure login credentials for scanners who will verify QR codes at event entry gates.
        </p>
      </div>

      {error && (
        <div className="alert alert-danger show-alert-anim">
          <div className="alert-content">{error}</div>
        </div>
      )}

      {success && (
        <div className="alert alert-success show-alert-anim">
          <div className="alert-content">{success}</div>
        </div>
      )}

      <div className="dashboard-panel">
        {/* Left Side: Scanner List */}
        <div>
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Registered Scanner Accounts</h3>
            </div>

            {scanners.length === 0 ? (
              <p style={{ color: 'var(--text-muted)', textAlign: 'center', padding: '2.25rem 0' }}>
                No scanner logins created yet.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Scanner Email</th>
                      <th>Created Date</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {scanners.map((scan) => (
                      <tr key={scan.id}>
                        <td style={{ fontWeight: 600 }}>{scan.email}</td>
                        <td>{new Date(scan.created_at).toLocaleDateString()}</td>
                        <td>
                          <span className="badge badge-approved">{scan.status}</span>
                        </td>
                        <td>
                          <button
                            onClick={() => handleDeleteScanner(scan.id, scan.email)}
                            className="btn btn-danger btn-sm"
                            disabled={deletingId === scan.id || scan.id === profile?.id}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                          >
                            {deletingId === scan.id ? (
                              <Loader2 className="animate-spin" size={14} />
                            ) : (
                              <Trash2 size={14} />
                            )}
                            Delete
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

        {/* Right Side: Create Scanner Account Form */}
        <div>
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Create Scanner User</h3>
            </div>

            <form onSubmit={handleCreateScanner}>
              <div className="form-group">
                <label className="form-label" htmlFor="email">Scanner Email Address</label>
                <input
                  type="email"
                  id="email"
                  className="form-control"
                  placeholder="scanner1@college.edu.in"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label" htmlFor="password">Password</label>
                <input
                  type="password"
                  id="password"
                  className="form-control"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label" htmlFor="confirm_password">Confirm Password</label>
                <input
                  type="password"
                  id="confirm_password"
                  className="form-control"
                  placeholder="••••••••"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                />
              </div>

              <button
                type="submit"
                className="btn btn-primary"
                style={{ width: '100%', marginTop: '1rem', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}
                disabled={creating}
              >
                {creating ? (
                  <Loader2 className="animate-spin" size={16} />
                ) : (
                  <PlusCircle size={16} />
                )}
                Create Scanner
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};
