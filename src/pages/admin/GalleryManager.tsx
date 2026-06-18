import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Image, UploadCloud, Trash2, Loader2, AlertCircle } from 'lucide-react';

interface GalleryPhoto {
  name: string;
  url: string;
  created_at: string;
}

export const GalleryManager: React.FC = () => {
  const { profile } = useAuth();
  const [photos, setPhotos] = useState<GalleryPhoto[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [deletingName, setDeletingName] = useState<string | null>(null);
  const [file, setFile] = useState<File | null>(null);

  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const fetchPhotos = async () => {
    setLoading(true);
    setError(null);
    try {
      // List all files in the gallery_photos bucket
      const { data, error: listError } = await supabase.storage
        .from('gallery_photos')
        .list('', {
          limit: 100,
          sortBy: { column: 'created_at', order: 'desc' },
        });

      // Handle bucket not found error or other storage errors gracefully
      if (listError) {
        throw listError;
      }

      if (data) {
        const photosList = data
          .filter((item) => item.name !== '.emptyFolderPlaceholder')
          .map((item) => {
            const { data: urlData } = supabase.storage
              .from('gallery_photos')
              .getPublicUrl(item.name);
            return {
              name: item.name,
              url: urlData.publicUrl,
              created_at: item.created_at || '',
            };
          });

        setPhotos(photosList);
      }
    } catch (err: any) {
      console.error('Error listing gallery photos:', err);
      setError(
        'Could not load gallery photos. Please ensure the "gallery_photos" storage bucket is created in your Supabase dashboard and is set to Public.'
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPhotos();
  }, []);

  const handleUploadPhoto = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!file) return;

    setUploading(true);
    setError(null);
    setSuccess(null);

    try {
      const ext = file.name.split('.').pop();
      const filename = `photo_${Date.now()}.${ext}`;

      const { error: uploadError } = await supabase.storage
        .from('gallery_photos')
        .upload(filename, file);

      if (uploadError) throw uploadError;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'gallery_photo_uploaded',
        details: `Uploaded photo: ${filename}`,
      });

      setSuccess('Photo uploaded successfully to highlights gallery.');
      setFile(null);
      // Reset input element
      const fileInput = document.getElementById('photo-upload-input') as HTMLInputElement;
      if (fileInput) fileInput.value = '';

      fetchPhotos();
    } catch (err: any) {
      setError(err.message || 'Failed to upload photo. Ensure file size is below limits and bucket exists.');
    } finally {
      setUploading(false);
    }
  };

  const handleDeletePhoto = async (name: string) => {
    if (!window.confirm('Are you sure you want to delete this photo from the highlights slider?')) {
      return;
    }

    setDeletingName(name);
    setError(null);
    setSuccess(null);

    try {
      const { error: deleteError } = await supabase.storage
        .from('gallery_photos')
        .remove([name]);

      if (deleteError) throw deleteError;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'gallery_photo_deleted',
        details: `Deleted photo: ${name}`,
      });

      setSuccess('Photo deleted successfully.');
      fetchPhotos();
    } catch (err: any) {
      setError(err.message || 'Failed to delete photo.');
    } finally {
      setDeletingName(null);
    }
  };

  if (loading && photos.length === 0) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading gallery highlights...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem' }}>
        <h2>Manage Gallery Highlights</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
          Upload and delete student portal homepage showcase photos. (Allowed formats: JPG, PNG, WEBP. Max size: 5MB)
        </p>
      </div>

      {error && (
        <div className="alert alert-danger show-alert-anim">
          <AlertCircle className="alert-icon" />
          <div className="alert-content">{error}</div>
        </div>
      )}

      {success && (
        <div className="alert alert-success show-alert-anim">
          <div className="alert-content">{success}</div>
        </div>
      )}

      <div className="dashboard-panel" style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '2rem' }}>
        {/* Left Side: Gallery Items Grid */}
        <div>
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Uploaded Gallery Photos ({photos.length})</h3>
            </div>

            {photos.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '3rem 1.5rem', color: 'var(--text-muted)' }}>
                <Image style={{ width: '48px', height: '48px', opacity: 0.3, marginBottom: '1rem', display: 'block', marginLeft: 'auto', marginRight: 'auto' }} />
                <p>No photos uploaded yet. Use the panel on the right to upload high-quality event photos.</p>
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '1rem' }}>
                {photos.map((photo) => (
                  <div
                    key={photo.name}
                    className="gallery-admin-card"
                    style={{
                      border: '1px solid var(--border-color)',
                      borderRadius: 'var(--radius-sm)',
                      overflow: 'hidden',
                      background: 'var(--bg-card)',
                      display: 'flex',
                      flexDirection: 'column',
                    }}
                  >
                    <div style={{ position: 'relative', width: '100%', height: '120px', overflow: 'hidden' }}>
                      <img src={photo.url} alt="Highlight" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} />
                    </div>
                    <div style={{ padding: '0.75rem', display: 'flex', flexDirection: 'column', gap: '0.5rem', flexGrow: 1, justifyContent: 'space-between' }}>
                      <div
                        style={{ fontSize: '0.75rem', color: 'var(--text-muted)', wordBreak: 'break-all', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                        title={photo.name}
                      >
                        {photo.name}
                      </div>
                      <button
                        onClick={() => handleDeletePhoto(photo.name)}
                        className="btn btn-danger btn-sm"
                        disabled={deletingName === photo.name}
                        style={{ width: '100%', justifyContent: 'center', display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                      >
                        {deletingName === photo.name ? (
                          <Loader2 className="animate-spin" size={12} />
                        ) : (
                          <Trash2 size={12} />
                        )}
                        Delete
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Right Side: Upload Form Widget */}
        <div>
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Upload New Highlight Photo</h3>
            </div>

            <form onSubmit={handleUploadPhoto}>
              <div className="form-group" style={{ marginBottom: '1.5rem' }}>
                <label className="form-label" htmlFor="photo-upload-input">Select Photo File</label>
                <input
                  type="file"
                  id="photo-upload-input"
                  className="form-control"
                  accept=".jpg,.jpeg,.png,.webp"
                  onChange={(e) => setFile(e.target.files?.[0] || null)}
                  required
                  style={{ padding: '0.5rem' }}
                />
                <small style={{ color: 'var(--text-muted)', fontSize: '0.75rem', display: 'block', marginTop: '0.4rem' }}>
                  Allowed formats: JPG, JPEG, PNG, WEBP. Recommended aspect ratio is 16:9 for the home slider.
                </small>
              </div>

              <button
                type="submit"
                disabled={uploading || !file}
                className="btn btn-primary"
                style={{ width: '100%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}
              >
                {uploading ? (
                  <Loader2 className="animate-spin" size={16} />
                ) : (
                  <UploadCloud size={16} />
                )}
                Upload Photo
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};
