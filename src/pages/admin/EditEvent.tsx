import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { ArrowLeft, Loader2, CheckCircle2, ShieldAlert } from 'lucide-react';

const formatDateTimeLocal = (isoString?: string) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  const pad = (n: number) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

export const EditEvent: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { profile } = useAuth();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  // Form Fields
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
  const [status, setStatus] = useState('upcoming');

  // Certificate Settings
  const [certTheme, setCertTheme] = useState('classic_navy');
  const [certTitle, setCertTitle] = useState('Certificate of Activity');
  const [certCoordinator, setCertCoordinator] = useState('Event Coordinator');
  const [certHod, setCertHod] = useState('Head of Department');
  const [canvaLink, setCanvaLink] = useState('');

  // Existing asset paths
  const [currentBanner, setCurrentBanner] = useState('');
  const [currentUpiQr, setCurrentUpiQr] = useState('');
  const [currentCertTemplate, setCurrentCertTemplate] = useState('');

  // Upload Files
  const [bannerFile, setBannerFile] = useState<File | null>(null);
  const [upiQrFile, setUpiQrFile] = useState<File | null>(null);
  const [certTemplateFile, setCertTemplateFile] = useState<File | null>(null);

  useEffect(() => {
    const fetchEventDetails = async () => {
      if (!id) return;
      try {
        const { data, error: fetchError } = await supabase
          .from('events')
          .select('*')
          .eq('id', id)
          .single();

        if (fetchError) throw fetchError;
        if (data) {
          setName(data.name || '');
          setDescription(data.description || '');
          setEventDate(data.event_date || '');
          setVenue(data.venue || '');
          setRegistrationFee(Number(data.registration_fee) || 0);
          setRegistrationDeadline(formatDateTimeLocal(data.registration_deadline));
          setScanStartTime(formatDateTimeLocal(data.scan_start_time));
          setScanEndTime(formatDateTimeLocal(data.scan_end_time));
          setUpiEnabled(data.upi_payment_enabled);
          setUpiId(data.upi_id || '');
          setCashEnabled(data.cash_payment_enabled);
          setFoodEnabled(data.food_enabled);
          setStatus(data.status || 'upcoming');

          setCertTheme(data.certificate_theme || 'classic_navy');
          setCertTitle(data.certificate_title || 'Certificate of Activity');
          setCertCoordinator(data.certificate_coordinator || 'Event Coordinator');
          setCertHod(data.certificate_hod || 'Head of Department');
          setCanvaLink(data.canva_template_link || '');

          setCurrentBanner(data.banner_image || '');
          setCurrentUpiQr(data.upi_qr_image || '');
          setCurrentCertTemplate(data.certificate_template || '');
        }
      } catch (err: any) {
        setError(err.message || 'Failed to fetch event settings.');
      } finally {
        setLoading(false);
      }
    };

    fetchEventDetails();
  }, [id]);

  const handleUpdate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!id || !profile) return;

    setError(null);
    setSubmitting(true);

    try {
      let bannerUrl = currentBanner;
      let upiQrUrl = currentUpiQr;
      let certTemplateUrl = currentCertTemplate;

      // 1. Upload Banner file if specified
      if (bannerFile) {
        const ext = bannerFile.name.split('.').pop();
        const filePath = `banners/event_${id}_${Date.now()}.${ext}`;
        const { error: uploadError } = await supabase.storage.from('event_banners').upload(filePath, bannerFile);
        if (uploadError) throw uploadError;
        const { data } = supabase.storage.from('event_banners').getPublicUrl(filePath);
        bannerUrl = data.publicUrl;
      }

      // 2. Upload UPI QR file if specified and UPI is enabled
      if (upiQrFile && upiEnabled) {
        const ext = upiQrFile.name.split('.').pop();
        const filePath = `qr_codes/event_${id}_${Date.now()}.${ext}`;
        const { error: uploadError } = await supabase.storage.from('event_banners').upload(filePath, upiQrFile);
        if (uploadError) throw uploadError;
        const { data } = supabase.storage.from('event_banners').getPublicUrl(filePath);
        upiQrUrl = data.publicUrl;
      }

      // 3. Upload Certificate Template file if specified
      if (certTemplateFile) {
        const ext = certTemplateFile.name.split('.').pop();
        const filePath = `templates/event_${id}_${Date.now()}.${ext}`;
        const { error: uploadError } = await supabase.storage.from('event_banners').upload(filePath, certTemplateFile);
        if (uploadError) throw uploadError;
        const { data } = supabase.storage.from('event_banners').getPublicUrl(filePath);
        certTemplateUrl = data.publicUrl;
      }

      // 4. Perform Update
      const { error: updateError } = await supabase
        .from('events')
        .update({
          name,
          description,
          event_date: eventDate,
          venue,
          registration_fee: registrationFee,
          registration_deadline: new Date(registrationDeadline).toISOString(),
          scan_start_time: new Date(scanStartTime).toISOString(),
          scan_end_time: new Date(scanEndTime).toISOString(),
          upi_payment_enabled: upiEnabled,
          upi_id: upiEnabled ? (upiId || null) : null,
          upi_qr_image: upiEnabled ? upiQrUrl : null,
          cash_payment_enabled: cashEnabled,
          food_enabled: foodEnabled,
          status,
          certificate_theme: certTheme,
          certificate_title: certTitle,
          certificate_coordinator: certCoordinator,
          certificate_hod: certHod,
          canva_template_link: canvaLink || null,
          banner_image: bannerUrl,
          certificate_template: certTemplateUrl,
        })
        .eq('id', id);

      if (updateError) throw updateError;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'event_updated',
        details: `Updated event settings for: ${name} (ID: ${id})`,
      });

      setSuccess(true);
      setTimeout(() => {
        navigate('/admin/events');
      }, 2000);
    } catch (err: any) {
      setError(err.message || 'Failed to update event settings.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading event information...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <button onClick={() => navigate('/admin/events')} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', marginBottom: '1.5rem' }}>
        <ArrowLeft size={14} /> Back to Events
      </button>

      <div className="card show-alert-anim" style={{ maxWidth: '800px', margin: '0 auto' }}>
        <div className="card-header" style={{ marginBottom: '1.5rem' }}>
          <h2>Edit Event Settings</h2>
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
            <div className="alert-content">Event successfully updated! Redirecting to events list...</div>
          </div>
        )}

        {!success && (
          <form onSubmit={handleUpdate}>
            <div className="form-group">
              <label className="form-label">Event Name</label>
              <input
                type="text"
                required
                className="form-control"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label">Description / Summary</label>
              <textarea
                rows={3}
                className="form-control"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              ></textarea>
            </div>

            <div className="form-group">
              <label className="form-label">Event Showcase Banner Image</label>
              {currentBanner && (
                <div style={{ marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '1rem' }}>
                  <img src={currentBanner} alt="Current Banner" style={{ width: '120px', height: '68px', objectFit: 'cover', borderRadius: '4px', border: '1px solid var(--border-color)' }} />
                  <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Current Banner (uploading a new file will replace this)</span>
                </div>
              )}
              <input
                type="file"
                accept="image/*"
                onChange={(e) => setBannerFile(e.target.files?.[0] || null)}
              />
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
                      {currentUpiQr && (
                        <div style={{ marginBottom: '0.5rem' }}>
                          <img src={currentUpiQr} alt="Current UPI QR" style={{ width: '80px', height: '80px', objectFit: 'contain', border: '1px solid var(--border-color)', borderRadius: '4px' }} />
                        </div>
                      )}
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

            <div className="form-row" style={{ margin: '1.5rem 0' }}>
              <div className="form-group">
                <label className="form-label">Event Status</label>
                <select
                  value={status}
                  onChange={(e) => setStatus(e.target.value)}
                  className="form-control"
                >
                  <option value="upcoming">Upcoming (Listed but Registration Closed)</option>
                  <option value="registration_open">Registration Open (Active Booking)</option>
                  <option value="registration_closed">Registration Closed</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>

              <div className="form-group" style={{ display: 'flex', alignItems: 'center', paddingLeft: '0.5rem', paddingTop: '1.5rem' }}>
                <input
                  type="checkbox"
                  id="foodToggle"
                  checked={foodEnabled}
                  onChange={(e) => setFoodEnabled(e.target.checked)}
                />
                <label htmlFor="foodToggle" style={{ cursor: 'pointer', marginLeft: '0.5rem' }}>Enable Food Gate Tracking</label>
              </div>
            </div>

            {/* Certificate styling configurations */}
            <div style={{ background: 'var(--bg-card)', padding: '1.5rem', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-color)', marginBottom: '1.5rem' }}>
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
                  value={canvaLink}
                  onChange={(e) => setCanvaLink(e.target.value)}
                />
              </div>

              <div className="form-group" style={{ marginBottom: 0 }}>
                <label className="form-label">Upload Custom Certificate Background (A4 Image/PDF Layout) (leave blank to keep current)</label>
                {currentCertTemplate && (
                  <div style={{ marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <a href={currentCertTemplate} target="_blank" rel="noreferrer" className="btn-link" style={{ fontSize: '0.85rem' }}>View Current Certificate Template</a>
                  </div>
                )}
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => setCertTemplateFile(e.target.files?.[0] || null)}
                />
              </div>
            </div>

            <div style={{ display: 'flex', gap: '1rem', justifyContent: 'flex-end', marginTop: '2rem' }}>
              <button type="button" onClick={() => navigate('/admin/events')} className="btn btn-secondary">Cancel</button>
              <button
                type="submit"
                disabled={submitting}
                className="btn btn-primary"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
              >
                {submitting ? <Loader2 className="animate-spin" size={16} /> : 'Save Changes'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};
