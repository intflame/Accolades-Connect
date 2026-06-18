import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { ArrowLeft, Upload, Loader2, CheckCircle2, ShieldAlert } from 'lucide-react';

export const CreateEvent: React.FC = () => {
  const { profile } = useAuth();
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [eventDate, setEventDate] = useState('');
  const [venue, setVenue] = useState('');
  const [registrationFee, setRegistrationFee] = useState<number>(0);
  const [registrationDeadline, setRegistrationDeadline] = useState('');
  const [scanStartTime, setScanStartTime] = useState('');
  const [scanEndTime, setScanEndTime] = useState('');

  // Payment Options
  const [upiEnabled, setUpiEnabled] = useState(true);
  const [upiId, setUpiId] = useState('');
  const [cashEnabled, setCashEnabled] = useState(true);
  const [foodEnabled, setFoodEnabled] = useState(true);

  // Certificate Settings
  const [certTheme, setCertTheme] = useState('classic_navy');
  const [certTitle, setCertTitle] = useState('Certificate of Activity');
  const [certCoordinator, setCertCoordinator] = useState('Event Coordinator');
  const [certHod, setCertHod] = useState('Head of Department');
  const [canvaLink, setCanvaLink] = useState('');

  // Files
  const [bannerFile, setBannerFile] = useState<File | null>(null);
  const [upiQrFile, setUpiQrFile] = useState<File | null>(null);
  const [certTemplateFile, setCertTemplateFile] = useState<File | null>(null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const navigate = useNavigate();

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    if (!profile) return;

    try {
      // 1. Insert Event details
      const { data: newEvent, error: eventError } = await supabase
        .from('events')
        .insert({
          name,
          description,
          event_date: eventDate,
          venue,
          registration_fee: registrationFee,
          registration_deadline: new Date(registrationDeadline).toISOString(),
          scan_start_time: new Date(scanStartTime).toISOString(),
          scan_end_time: new Date(scanEndTime).toISOString(),
          upi_payment_enabled: upiEnabled,
          upi_id: upiId || null,
          cash_payment_enabled: cashEnabled,
          food_enabled: foodEnabled,
          certificate_theme: certTheme,
          certificate_title: certTitle,
          certificate_coordinator: certCoordinator,
          certificate_hod: certHod,
          canva_template_link: canvaLink || null,
          status: 'registration_open',
        })
        .select()
        .single();

      if (eventError) throw eventError;
      const eventId = newEvent.id;

      let bannerUrl = '';
      let upiQrUrl = '';
      let certTemplateUrl = '';

      // 2. Upload Banner
      if (bannerFile) {
        const ext = bannerFile.name.split('.').pop();
        const filePath = `banners/event_${eventId}_${Date.now()}.${ext}`;
        const { error: uploadError } = await supabase.storage.from('event_banners').upload(filePath, bannerFile);
        if (uploadError) console.error(uploadError);
        else {
          const { data } = supabase.storage.from('event_banners').getPublicUrl(filePath);
          bannerUrl = data.publicUrl;
        }
      }

      // 3. Upload UPI QR
      if (upiQrFile && upiEnabled) {
        const ext = upiQrFile.name.split('.').pop();
        const filePath = `qr_codes/event_${eventId}_${Date.now()}.${ext}`;
        const { error: uploadError } = await supabase.storage.from('event_banners').upload(filePath, upiQrFile); // reuse banners bucket or custom folder
        if (uploadError) console.error(uploadError);
        else {
          const { data } = supabase.storage.from('event_banners').getPublicUrl(filePath);
          upiQrUrl = data.publicUrl;
        }
      }

      // 4. Upload Certificate Template
      if (certTemplateFile) {
        const ext = certTemplateFile.name.split('.').pop();
        const filePath = `templates/event_${eventId}_${Date.now()}.${ext}`;
        const { error: uploadError } = await supabase.storage.from('event_banners').upload(filePath, certTemplateFile);
        if (uploadError) console.error(uploadError);
        else {
          const { data } = supabase.storage.from('event_banners').getPublicUrl(filePath);
          certTemplateUrl = data.publicUrl;
        }
      }

      // 5. Update URLs in Event table
      const updates: any = {};
      if (bannerUrl) updates.banner_image = bannerUrl;
      if (upiQrUrl) updates.upi_qr_image = upiQrUrl;
      if (certTemplateUrl) updates.certificate_template = certTemplateUrl;

      if (Object.keys(updates).length > 0) {
        await supabase.from('events').update(updates).eq('id', eventId);
      }

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'event_created',
        details: `Created event ID ${eventId}: ${name}`
      });

      setSuccess(true);
      setTimeout(() => {
        navigate('/admin');
      }, 2000);
    } catch (err: any) {
      setError(err.message || 'Failed to create event.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container main-content">
      <button onClick={() => navigate('/admin')} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', marginBottom: '1.5rem' }}>
        <ArrowLeft size={14} /> Back to Dashboard
      </button>

      <div className="card show-alert-anim" style={{ maxWidth: '800px', margin: '0 auto' }}>
        <div className="card-header" style={{ marginBottom: '1.5rem' }}>
          <h2>Create New Event</h2>
        </div>

        {error && (
          <div className="alert alert-danger">
            <ShieldAlert className="alert-icon" />
            <div className="alert-content">{error}</div>
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            <CheckCircle2 className="alert-icon" />
            <div className="alert-content">Event successfully created! Redirecting to dashboard...</div>
          </div>
        )}

        {!success && (
          <form onSubmit={handleCreate}>
            <div className="form-group">
              <label className="form-label">Event Name</label>
              <input
                type="text"
                required
                className="form-control"
                placeholder="e.g. Accolades 2026: Annual Fest"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label">Description / Summary</label>
              <textarea
                rows={3}
                className="form-control"
                placeholder="Detailed event information..."
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              ></textarea>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Event Date</label>
                <input
                  type="date"
                  required
                  className="form-control"
                  value={eventDate}
                  onChange={(e) => setEventDate(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="form-label">Venue</label>
                <input
                  type="text"
                  required
                  className="form-control"
                  placeholder="e.g. College Auditorium"
                  value={venue}
                  onChange={(e) => setVenue(e.target.value)}
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Registration Fee (INR)</label>
                <input
                  type="number"
                  min="0"
                  className="form-control"
                  value={registrationFee}
                  onChange={(e) => setRegistrationFee(Number(e.target.value))}
                />
              </div>

              <div className="form-group">
                <label className="form-label">Registration Deadline</label>
                <input
                  type="datetime-local"
                  required
                  className="form-control"
                  value={registrationDeadline}
                  onChange={(e) => setRegistrationDeadline(e.target.value)}
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Scanner Gate Start Time</label>
                <input
                  type="datetime-local"
                  required
                  className="form-control"
                  value={scanStartTime}
                  onChange={(e) => setScanStartTime(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="form-label">Scanner Gate Close Time</label>
                <input
                  type="datetime-local"
                  required
                  className="form-control"
                  value={scanEndTime}
                  onChange={(e) => setScanEndTime(e.target.value)}
                />
              </div>
            </div>

            {/* Payment Configurations */}
            {registrationFee > 0 && (
              <div style={{ background: 'var(--bg-card)', padding: '1.5rem', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-color)', marginBottom: '1.5rem' }}>
                <h3 style={{ fontSize: '1.1rem', marginBottom: '1rem' }}>Payment System Configurations</h3>

                <div className="form-row">
                  <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', margin: 0 }}>
                    <input
                      type="checkbox"
                      id="upiToggle"
                      checked={upiEnabled}
                      onChange={(e) => setUpiEnabled(e.target.checked)}
                    />
                    <label htmlFor="upiToggle" style={{ cursor: 'pointer' }}>Enable UPI Payments</label>
                  </div>

                  <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', margin: 0 }}>
                    <input
                      type="checkbox"
                      id="cashToggle"
                      checked={cashEnabled}
                      onChange={(e) => setCashEnabled(e.target.checked)}
                    />
                    <label htmlFor="cashToggle" style={{ cursor: 'pointer' }}>Enable Cash Payments</label>
                  </div>
                </div>

                {upiEnabled && (
                  <div className="form-row" style={{ marginTop: '1rem' }}>
                    <div className="form-group">
                      <label className="form-label">UPI ID / Address</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="e.g. account@upi"
                        value={upiId}
                        onChange={(e) => setUpiId(e.target.value)}
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">UPI QR Code Image</label>
                      <input
                        type="file"
                        accept="image/*"
                        onChange={(e) => setUpiQrFile(e.target.files?.[0] || null)}
                      />
                    </div>
                  </div>
                )}
              </div>
            )}

            <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <input
                type="checkbox"
                id="foodToggle"
                checked={foodEnabled}
                onChange={(e) => setFoodEnabled(e.target.checked)}
              />
              <label htmlFor="foodToggle" style={{ cursor: 'pointer' }}>Enable Food Gate Tracking</label>
            </div>

            {/* Event Media assets */}
            <div className="form-group">
              <label className="form-label">Event Banner Image</label>
              <input
                type="file"
                accept="image/*"
                onChange={(e) => setBannerFile(e.target.files?.[0] || null)}
              />
            </div>

            {/* Certificate styling configurations */}
            <div style={{ background: 'var(--bg-card)', padding: '1.5rem', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-color)', marginBottom: '1.5rem', marginTop: '1.5rem' }}>
              <h3 style={{ fontSize: '1.1rem', marginBottom: '1rem' }}>Certificate Generation Configurations</h3>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Certificate Title</label>
                  <input
                    type="text"
                    className="form-control"
                    value={certTitle}
                    onChange={(e) => setCertTitle(e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Design Theme Template</label>
                  <select
                    className="form-control"
                    value={certTheme}
                    onChange={(e) => setCertTheme(e.target.value)}
                  >
                    <option value="classic_navy">Classic Navy (Default)</option>
                    <option value="modern_minimalist">Modern Minimalist</option>
                    <option value="creative_teal">Creative Teal</option>
                    <option value="elegant_emerald">Elegant Emerald</option>
                  </select>
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Signature 1 (Coordinator Text)</label>
                  <input
                    type="text"
                    className="form-control"
                    value={certCoordinator}
                    onChange={(e) => setCertCoordinator(e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Signature 2 (HOD Text)</label>
                  <input
                    type="text"
                    className="form-control"
                    value={certHod}
                    onChange={(e) => setCertHod(e.target.value)}
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">Canva Design Template Link (Optional)</label>
                <input
                  type="url"
                  className="form-control"
                  placeholder="https://canva.com/design/..."
                  value={canvaLink}
                  onChange={(e) => setCanvaLink(e.target.value)}
                />
              </div>

              <div className="form-group" style={{ marginBottom: 0 }}>
                <label className="form-label">Upload Custom Certificate Background (A4 Image/PDF Layout)</label>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => setCertTemplateFile(e.target.files?.[0] || null)}
                />
                <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                  If uploaded, this background image will replace the default color theme style.
                </p>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="btn btn-primary"
              style={{ width: '100%', marginTop: '1.5rem' }}
            >
              {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Launch Event'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
};
