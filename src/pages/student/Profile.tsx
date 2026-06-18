import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { User, Phone, Mail, Award, Edit2, Check, X, Upload, Loader2 } from 'lucide-react';

interface Batch {
  id: number;
  name: string;
}

export const Profile: React.FC = () => {
  const { profile, refreshProfile } = useAuth();
  const [batches, setBatches] = useState<Batch[]>([]);
  
  // Form edit states
  const [isEditing, setIsEditing] = useState(false);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  
  const [name, setName] = useState('');
  const [course, setCourse] = useState<'BCA' | 'MCA'>('BCA');
  const [batchId, setBatchId] = useState<number | ''>('');
  const [classRoll, setClassRoll] = useState('');
  const [universityRoll, setUniversityRoll] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [whatsappNumber, setWhatsappNumber] = useState('');
  const [foodPreference, setFoodPreference] = useState<'veg' | 'non-veg'>('veg');
  const [photoUrl, setPhotoUrl] = useState('');
  const [msg, setMsg] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  useEffect(() => {
    // Populate form data from active profile
    if (profile) {
      setName(profile.name || '');
      setCourse(profile.course || 'BCA');
      setBatchId(profile.batch_id || '');
      setClassRoll(profile.class_roll || '');
      setUniversityRoll(profile.university_roll || '');
      setContactNumber(profile.contact_number || '');
      setWhatsappNumber(profile.whatsapp_number || '');
      setFoodPreference(profile.food_preference || 'veg');
      setPhotoUrl(profile.profile_photo || '');
    }
  }, [profile]);

  useEffect(() => {
    const fetchBatches = async () => {
      const { data } = await supabase
        .from('batches')
        .select('*')
        .order('name', { ascending: false });
      setBatches(data || []);
    };
    fetchBatches();
  }, []);

  const handlePhotoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !profile) return;

    setUploading(true);
    setMsg(null);

    try {
      const fileExt = file.name.split('.').pop();
      const filePath = `${profile.id}/profile_${Date.now()}.${fileExt}`;

      // Upload to profile_photos bucket
      const { error: uploadError } = await supabase.storage
        .from('profile_photos')
        .upload(filePath, file, {
          upsert: true
        });

      if (uploadError) throw uploadError;

      // Get public URL
      const { data: { publicUrl } } = supabase.storage
        .from('profile_photos')
        .getPublicUrl(filePath);

      // Save to database
      const { error: updateError } = await supabase
        .from('profiles')
        .update({ profile_photo: publicUrl })
        .eq('id', profile.id);

      if (updateError) throw updateError;

      setPhotoUrl(publicUrl);
      if (refreshProfile) {
        await refreshProfile();
      }
      setMsg({ type: 'success', text: 'Profile photo updated successfully!' });
    } catch (err: any) {
      console.error(err);
      setMsg({ type: 'error', text: err.message || 'Failed to upload photo.' });
    } finally {
      setUploading(false);
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!profile) return;

    setLoading(true);
    setMsg(null);

    try {
      const { error } = await supabase
        .from('profiles')
        .update({
          name: name.trim(),
          course,
          batch_id: batchId === '' ? null : Number(batchId),
          class_roll: classRoll.trim(),
          university_roll: universityRoll.trim(),
          contact_number: contactNumber.trim(),
          whatsapp_number: whatsappNumber.trim(),
          food_preference: foodPreference
        })
        .eq('id', profile.id);

      if (error) throw error;

      if (refreshProfile) {
        await refreshProfile();
      }
      setIsEditing(false);
      setMsg({ type: 'success', text: 'Profile updated successfully!' });
    } catch (err: any) {
      console.error(err);
      setMsg({ type: 'error', text: err.message || 'Failed to update profile details.' });
    } finally {
      setLoading(false);
    }
  };

  const currentBatchName = batches.find(b => b.id === Number(batchId))?.name || 'N/A';

  return (
    <div className="container main-content">
      <div style={{ maxWidth: '720px', margin: '0 auto' }}>
        <div style={{ marginBottom: '2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h2>My Student Profile</h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
              Manage your credentials, photo, and preferences.
            </p>
          </div>
          {!isEditing && (
            <button
              onClick={() => setIsEditing(true)}
              className="btn btn-primary btn-sm"
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
            >
              <Edit2 size={14} /> Edit Profile
            </button>
          )}
        </div>

        {msg && (
          <div className={`alert ${msg.type === 'success' ? 'alert-success' : 'alert-danger'}`} style={{ marginBottom: '1.5rem' }}>
            <div className="alert-content">{msg.text}</div>
          </div>
        )}

        <div className="card show-alert-anim" style={{ padding: '2rem' }}>
          {/* Avatar Area */}
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', marginBottom: '2.5rem', textAlign: 'center' }}>
            <div style={{ position: 'relative' }}>
              <img
                src={photoUrl || '/placeholder-avatar.svg'}
                alt="Profile Avatar"
                style={{ width: '120px', height: '120px', borderRadius: '50%', objectFit: 'cover', border: '3px solid var(--primary)' }}
                onError={(e) => {
                  (e.target as HTMLImageElement).src =
                    'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="%23F87B1B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                }}
              />
              <label
                style={{
                  position: 'absolute',
                  bottom: 0,
                  right: 0,
                  background: 'var(--primary)',
                  borderRadius: '50%',
                  padding: '8px',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  boxShadow: 'var(--shadow-md)',
                  transition: 'background 0.2s'
                }}
                title="Change Avatar"
              >
                {uploading ? <Loader2 size={16} className="animate-spin" /> : <Upload size={16} />}
                <input
                  type="file"
                  accept="image/*"
                  onChange={handlePhotoUpload}
                  disabled={uploading}
                  style={{ display: 'none' }}
                />
              </label>
            </div>
            <h3 style={{ marginTop: '1rem', marginBottom: '0.25rem' }}>{name}</h3>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)', background: 'var(--bg-input)', padding: '4px 10px', borderRadius: '12px' }}>
              ID: {profile?.email}
            </span>
          </div>

          {isEditing ? (
            <form onSubmit={handleSave}>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Full Name</label>
                  <input
                    type="text"
                    required
                    className="form-control"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    disabled={loading}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Course</label>
                  <select
                    className="form-control"
                    value={course}
                    onChange={(e) => setCourse(e.target.value as any)}
                    disabled={loading}
                  >
                    <option value="BCA">BCA</option>
                    <option value="MCA">MCA</option>
                  </select>
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Batch</label>
                  <select
                    className="form-control"
                    value={batchId}
                    onChange={(e) => setBatchId(e.target.value === '' ? '' : Number(e.target.value))}
                    disabled={loading}
                  >
                    <option value="">Select Batch</option>
                    {batches.map((batch) => (
                      <option key={batch.id} value={batch.id}>
                        Batch {batch.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Food Preference</label>
                  <select
                    className="form-control"
                    value={foodPreference}
                    onChange={(e) => setFoodPreference(e.target.value as any)}
                    disabled={loading}
                  >
                    <option value="veg">Vegetarian</option>
                    <option value="non-veg">Non-Vegetarian</option>
                  </select>
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Class Roll Number</label>
                  <input
                    type="text"
                    required
                    className="form-control"
                    value={classRoll}
                    onChange={(e) => setClassRoll(e.target.value)}
                    disabled={loading}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">University Roll Number</label>
                  <input
                    type="text"
                    required
                    className="form-control"
                    value={universityRoll}
                    onChange={(e) => setUniversityRoll(e.target.value)}
                    disabled={loading}
                  />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Contact Number</label>
                  <input
                    type="tel"
                    required
                    className="form-control"
                    value={contactNumber}
                    onChange={(e) => setContactNumber(e.target.value)}
                    disabled={loading}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">WhatsApp Number</label>
                  <input
                    type="tel"
                    required
                    className="form-control"
                    value={whatsappNumber}
                    onChange={(e) => setWhatsappNumber(e.target.value)}
                    disabled={loading}
                  />
                </div>
              </div>

              <div style={{ display: 'flex', gap: '1rem', marginTop: '2rem' }}>
                <button
                  type="submit"
                  className="btn btn-primary"
                  style={{ flex: 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}
                  disabled={loading}
                >
                  {loading ? <Loader2 size={16} className="animate-spin" /> : <Check size={16} />} Save Changes
                </button>
                <button
                  type="button"
                  onClick={() => setIsEditing(false)}
                  className="btn btn-secondary"
                  style={{ flex: 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}
                  disabled={loading}
                >
                  <X size={16} /> Cancel
                </button>
              </div>
            </form>
          ) : (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '2rem 1.5rem' }}>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>Full Name</span>
                <strong>{name}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>Course & Cohort</span>
                <strong>{course} - Batch {currentBatchName}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>Class Roll</span>
                <strong>{classRoll || 'N/A'}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>University Roll</span>
                <strong>{universityRoll || 'N/A'}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>Contact Number</span>
                <strong>{contactNumber || 'N/A'}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>WhatsApp Number</span>
                <strong>{whatsappNumber || 'N/A'}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>Food Preference</span>
                <strong style={{ textTransform: 'capitalize' }}>
                  <span
                    style={{
                      padding: '0.15rem 0.5rem',
                      borderRadius: '4px',
                      fontSize: '0.85rem',
                      background: foodPreference === 'veg' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(244, 63, 94, 0.1)',
                      color: foodPreference === 'veg' ? 'var(--success)' : 'var(--danger)'
                    }}
                  >
                    {foodPreference}
                  </span>
                </strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.25rem' }}>Approval Status</span>
                <span className="badge badge-approved">ACTIVE</span>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
