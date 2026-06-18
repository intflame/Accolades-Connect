import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Megaphone, Trash2, Calendar, Users, FileText, CheckCircle2, AlertCircle } from 'lucide-react';

interface Announcement {
  id: number;
  title: string;
  message: string;
  target_role: 'all' | 'student' | 'scanner';
  created_at: string;
  profiles?: {
    name: string;
  };
}

export const Announcements: React.FC = () => {
  const { profile } = useAuth();
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Form states
  const [title, setTitle] = useState('');
  const [message, setMessage] = useState('');
  const [targetRole, setTargetRole] = useState<'all' | 'student' | 'scanner'>('all');
  const [successMsg, setSuccessMsg] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  const fetchAnnouncements = async () => {
    setLoading(true);
    try {
      const { data, error } = await supabase
        .from('announcements')
        .select(`
          id,
          title,
          message,
          target_role,
          created_at,
          profiles:created_by (
            name
          )
        `)
        .order('created_at', { ascending: false });

      if (error) throw error;
      setAnnouncements((data as any) || []);
    } catch (err: any) {
      console.error('Error fetching announcements:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAnnouncements();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !message.trim()) return;

    setSubmitting(true);
    setSuccessMsg('');
    setErrorMsg('');

    try {
      const { error } = await supabase
        .from('announcements')
        .insert({
          title: title.trim(),
          message: message.trim(),
          target_role: targetRole,
          created_by: profile?.id
        });

      if (error) throw error;

      setSuccessMsg('Announcement posted successfully!');
      setTitle('');
      setMessage('');
      setTargetRole('all');
      await fetchAnnouncements();
    } catch (err: any) {
      console.error('Error creating announcement:', err);
      setErrorMsg(err.message || 'Failed to post announcement.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this announcement?')) return;

    try {
      const { error } = await supabase
        .from('announcements')
        .delete()
        .eq('id', id);

      if (error) throw error;
      await fetchAnnouncements();
    } catch (err: any) {
      console.error('Error deleting announcement:', err);
      alert(err.message || 'Failed to delete announcement.');
    }
  };

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem' }}>
        <h2>Manage Announcements</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
          Publish notices, event details, and updates for students or scanners.
        </p>
      </div>

      <div className="dashboard-panel">
        {/* Post Announcement Form */}
        <div className="card show-alert-anim">
          <div className="card-header" style={{ marginBottom: '1.5rem', paddingBottom: '0.75rem' }}>
            <h3>Create Announcement</h3>
          </div>

          {successMsg && (
            <div className="alert alert-success" style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <CheckCircle2 size={16} />
              <div className="alert-content">{successMsg}</div>
            </div>
          )}

          {errorMsg && (
            <div className="alert alert-danger" style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <AlertCircle size={16} />
              <div className="alert-content">{errorMsg}</div>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="title">Announcement Title</label>
              <input
                type="text"
                id="title"
                className="form-control"
                placeholder="Enter a descriptive title..."
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
                disabled={submitting}
              />
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="target_role">Target Audience</label>
              <select
                id="target_role"
                className="form-control"
                value={targetRole}
                onChange={(e) => setTargetRole(e.target.value as any)}
                disabled={submitting}
              >
                <option value="all">All Users (Students & Scanners)</option>
                <option value="student">Students Only</option>
                <option value="scanner">Scanners Only</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="message">Message Content</label>
              <textarea
                id="message"
                className="form-control"
                placeholder="Write announcement details here..."
                rows={5}
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                required
                disabled={submitting}
              />
            </div>

            <button type="submit" className="btn btn-primary" style={{ width: '100%' }} disabled={submitting}>
              {submitting ? 'Publishing...' : 'Publish Announcement'}
            </button>
          </form>
        </div>

        {/* Existing Announcements List */}
        <div className="card show-alert-anim">
          <div className="card-header" style={{ marginBottom: '1.5rem', paddingBottom: '0.75rem' }}>
            <h3>Active Announcements ({announcements.length})</h3>
          </div>

          {loading ? (
            <p style={{ textAlign: 'center', padding: '2rem 0' }}>Loading announcements...</p>
          ) : announcements.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text-muted)' }}>
              <Megaphone size={40} style={{ color: 'var(--border-color)', marginBottom: '1rem' }} />
              <h4>No active announcements</h4>
              <p style={{ fontSize: '0.85rem', marginTop: '0.5rem' }}>
                Announcements you publish will appear here and on recipient dashboards.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem', maxHeight: '500px', overflowY: 'auto' }}>
              {announcements.map((ann) => (
                <div key={ann.id} className="card" style={{ padding: '1rem', background: 'rgba(255,255,255,0.02)', border: '1px solid var(--border-color)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '0.5rem', marginBottom: '0.5rem' }}>
                    <h4 style={{ margin: 0, fontSize: '1rem', color: 'var(--text-main)' }}>{ann.title}</h4>
                    <button
                      onClick={() => handleDelete(ann.id)}
                      className="btn btn-danger btn-sm"
                      style={{ padding: '4px 8px', background: 'none', border: 'none', color: '#f87171' }}
                      title="Delete Announcement"
                    >
                      <Trash2 size={14} />
                    </button>
                  </div>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', margin: '0 0 1rem 0', whiteSpace: 'pre-wrap' }}>
                    {ann.message}
                  </p>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.75rem', color: 'var(--text-muted)', borderTop: '1px solid var(--border-color)', paddingTop: '0.5rem' }}>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                      <Calendar size={12} /> {new Date(ann.created_at).toLocaleDateString()}
                    </span>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '4px', textTransform: 'capitalize' }}>
                      <Users size={12} /> Target: {ann.target_role}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
