import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Megaphone, Calendar, User } from 'lucide-react';

interface Announcement {
  id: number;
  title: string;
  message: string;
  target_role: string;
  created_at: string;
  profiles?: {
    name: string;
  };
}

export const Announcements: React.FC = () => {
  const { profile } = useAuth();
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
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
          .in('target_role', ['all', 'student'])
          .order('created_at', { ascending: false });

        if (error) throw error;
        setAnnouncements((data as any) || []);
      } catch (err) {
        console.error('Error fetching student announcements:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchAnnouncements();
  }, [profile]);

  return (
    <div className="container main-content">
      <div style={{ maxWidth: '800px', margin: '0 auto' }}>
        <div style={{ marginBottom: '2rem' }}>
          <h2>Announcements & Board Updates</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Stay updated with the latest alerts and instructions from department organizers.
          </p>
        </div>

        {loading ? (
          <p style={{ textAlign: 'center', padding: '3rem 0' }}>Loading board notices...</p>
        ) : announcements.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '4rem 2rem' }}>
            <Megaphone size={48} style={{ color: 'var(--text-muted)', marginBottom: '1.5rem' }} />
            <h3>No Announcements Yet</h3>
            <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
              Check back later for event schedules, updates, or notifications.
            </p>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            {announcements.map((ann) => (
              <div key={ann.id} className="card show-alert-anim" style={{ padding: '1.75rem' }}>
                <h3 style={{ marginBottom: '0.75rem', color: 'var(--primary)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <Megaphone size={20} /> {ann.title}
                </h3>
                <p style={{ fontSize: '0.95rem', color: 'var(--text-main)', lineHeight: '1.6', whiteSpace: 'pre-wrap', marginBottom: '1.5rem' }}>
                  {ann.message}
                </p>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.8rem', color: 'var(--text-muted)', borderTop: '1px solid var(--border-color)', paddingTop: '0.75rem' }}>
                  <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <Calendar size={14} /> Posted: {new Date(ann.created_at).toLocaleDateString()} at {new Date(ann.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                  <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <User size={14} /> By: {ann.profiles?.name || 'Administrator'}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
