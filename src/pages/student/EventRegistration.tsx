import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Calendar, MapPin, DollarSign, Upload, ArrowLeft, Loader2, CheckCircle, ShieldAlert } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  description: string;
  banner_image: string;
  event_date: string;
  venue: string;
  registration_fee: number;
  registration_deadline: string;
  upi_payment_enabled: boolean;
  upi_id: string;
  upi_qr_image: string;
  cash_payment_enabled: boolean;
  status: string;
}

export const EventRegistration: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { profile } = useAuth();
  const [event, setEvent] = useState<Event | null>(null);
  const [existingReg, setExistingReg] = useState<any | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<'upi' | 'cash'>('upi');
  const [proofFile, setProofFile] = useState<File | null>(null);
  const [proofPreview, setProofPreview] = useState<string | null>(null);

  const [loading, setLoading] = useState(true);
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const navigate = useNavigate();

  const fetchEventDetails = async () => {
    if (!id || !profile) return;
    setLoading(true);

    try {
      // 1. Fetch Event
      const { data: eventData, error: eventError } = await supabase
        .from('events')
        .select('*')
        .eq('id', Number(id))
        .single();

      if (eventError) throw eventError;
      setEvent(eventData);

      // Check if registration deadline has passed
      const deadline = new Date(eventData.registration_deadline);
      if (deadline < new Date()) {
        setError('Registration deadline for this event has passed.');
      }

      // 2. Fetch Existing Registration
      const { data: regData, error: regError } = await supabase
        .from('event_registrations')
        .select(`
          id,
          status,
          payment_method,
          payments (
            id,
            status,
            proof_image,
            rejection_reason
          )
        `)
        .eq('event_id', Number(id))
        .eq('student_id', profile.id)
        .maybeSingle();

      if (regError) throw regError;
      setExistingReg(regData);

      if (eventData.registration_fee === 0) {
        setPaymentMethod('cash'); // Free event
      } else if (!eventData.upi_payment_enabled && eventData.cash_payment_enabled) {
        setPaymentMethod('cash');
      }
    } catch (err) {
      console.error('Error fetching event registration details:', err);
      setError('Event not found.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEventDetails();
  }, [id, profile]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setProofFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setProofPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitLoading(true);

    if (!event || !profile) return;

    // Validation
    const isFree = event.registration_fee === 0;
    if (!isFree && paymentMethod === 'upi' && !proofFile) {
      setError('Please upload payment proof for UPI payments.');
      setSubmitLoading(false);
      return;
    }

    try {
      // 1. Create or Update Event Registration
      let registrationId: number;

      if (existingReg) {
        registrationId = existingReg.id;
        // Update payment method and reset status to pending verification or pending payment
        const { error: updateRegError } = await supabase
          .from('event_registrations')
          .update({
            payment_method: paymentMethod,
            status: isFree ? 'approved' : paymentMethod === 'upi' ? 'pending_verification' : 'pending_payment'
          })
          .eq('id', registrationId);

        if (updateRegError) throw updateRegError;
      } else {
        // Create new registration
        const { data: newReg, error: regError } = await supabase
          .from('event_registrations')
          .insert({
            event_id: event.id,
            student_id: profile.id,
            payment_method: paymentMethod,
            status: isFree ? 'approved' : 'pending_payment', // approved if free, else pending
            assigned_role: 'participant',
          })
          .select()
          .single();

        if (regError) throw regError;
        registrationId = newReg.id;
      }

      // 2. Process Free Registration Token
      if (isFree) {
        // Generate active QR Token directly
        const randomToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        const { error: qrError } = await supabase
          .from('qr_tokens')
          .insert({
            registration_id: registrationId,
            token: randomToken,
            status: 'active'
          });

        if (qrError) console.error('Error generating free QR token:', qrError);
        setSuccess(true);
        setSubmitLoading(false);
        return;
      }

      // 3. Process UPI Payment Upload
      if (paymentMethod === 'upi' && proofFile) {
        const fileExt = proofFile.name.split('.').pop();
        const filePath = `proofs/reg_${registrationId}_${Date.now()}.${fileExt}`;

        const { error: uploadError } = await supabase.storage
          .from('payment_proofs')
          .upload(filePath, proofFile, {
            upsert: true
          });

        if (uploadError) throw uploadError;

        const { data: { publicUrl } } = supabase.storage
          .from('payment_proofs')
          .getPublicUrl(filePath);

        // Delete existing pending/rejected payment if any
        if (existingReg?.payments) {
          await supabase.from('payments').delete().eq('registration_id', registrationId);
        }

        // Insert new payment record
        const { error: paymentError } = await supabase
          .from('payments')
          .insert({
            registration_id: registrationId,
            payment_method: 'upi',
            proof_image: publicUrl,
            status: 'pending',
          });

        if (paymentError) throw paymentError;

        // Update registration status to pending verification
        await supabase
          .from('event_registrations')
          .update({ status: 'pending_verification' })
          .eq('id', registrationId);
      }

      setSuccess(true);
    } catch (err: any) {
      setError(err.message || 'Failed to complete registration.');
    } finally {
      setSubmitLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading event registration details...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <button onClick={() => navigate('/student')} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', marginBottom: '1.5rem' }}>
        <ArrowLeft size={14} /> Back to Dashboard
      </button>

      {error && !event && (
        <div className="alert alert-danger show-alert-anim">
          <ShieldAlert className="alert-icon" />
          <div className="alert-content">{error}</div>
        </div>
      )}

      {event && (
        <div className="dashboard-panel">
          {/* Left panel: Event details */}
          <div className="card show-alert-anim">
            {event.banner_image && (
              <img
                src={event.banner_image}
                alt={event.name}
                style={{ width: '100%', height: '220px', objectFit: 'cover', borderRadius: 'var(--radius-md)', marginBottom: '1.5rem' }}
              />
            )}
            <h1 style={{ fontSize: '2rem', marginBottom: '1rem' }}>{event.name}</h1>
            <p style={{ color: 'var(--text-muted)', marginBottom: '2rem' }}>
              {event.description || 'No description provided.'}
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1.5rem', borderTop: '1px solid var(--border-color)', paddingTop: '1.5rem' }}>
              <div>
                <h4 style={{ color: 'var(--text-muted)', fontSize: '0.85rem', textTransform: 'uppercase', marginBottom: '0.25rem' }}>Date & Time</h4>
                <p style={{ fontWeight: '600' }}>{new Date(event.event_date).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
              </div>
              <div>
                <h4 style={{ color: 'var(--text-muted)', fontSize: '0.85rem', textTransform: 'uppercase', marginBottom: '0.25rem' }}>Venue</h4>
                <p style={{ fontWeight: '600' }}>{event.venue}</p>
              </div>
              <div>
                <h4 style={{ color: 'var(--text-muted)', fontSize: '0.85rem', textTransform: 'uppercase', marginBottom: '0.25rem' }}>Registration Fee</h4>
                <p style={{ fontWeight: '600', color: 'var(--primary)', fontSize: '1.25rem' }}>
                  {event.registration_fee > 0 ? `₹${event.registration_fee}` : 'FREE Entry'}
                </p>
              </div>
            </div>
          </div>

          {/* Right panel: Registration actions */}
          <div className="card show-alert-anim">
            {success ? (
              <div style={{ textAlign: 'center', padding: '1.5rem' }}>
                <CheckCircle size={56} style={{ color: 'var(--success)', marginBottom: '1rem' }} />
                <h3>Registration Submitted!</h3>
                <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem', fontSize: '0.95rem' }}>
                  {event.registration_fee === 0
                    ? 'Your entry pass has been generated. You can view it on your dashboard.'
                    : paymentMethod === 'upi'
                    ? 'Your payment proof has been uploaded. An administrator will verify it shortly.'
                    : 'Please pay the cash registration fee to the department coordinator to approve your pass.'}
                </p>
                <button onClick={() => navigate('/student')} className="btn btn-primary" style={{ width: '100%', marginTop: '2rem' }}>
                  Go to Dashboard
                </button>
              </div>
            ) : (
              <>
                <div className="card-header" style={{ marginBottom: '1.5rem', paddingBottom: '0.75rem' }}>
                  <h3>Event Entry Pass</h3>
                </div>

                {error && (
                  <div className="alert alert-danger">
                    <ShieldAlert className="alert-icon" />
                    <div className="alert-content">{error}</div>
                  </div>
                )}

                {existingReg && existingReg.status === 'approved' ? (
                  <div className="alert alert-success">
                    <CheckCircle className="alert-icon" />
                    <div className="alert-content">You are already registered & approved for this event!</div>
                  </div>
                ) : existingReg && existingReg.status === 'pending_verification' ? (
                  <div style={{ textAlign: 'center', padding: '1rem' }}>
                    <Loader2 className="alert-icon animate-spin" style={{ margin: '0 auto 1rem', width: '36px', height: '36px', color: 'var(--primary)' }} />
                    <h4>Verifying UPI Payment</h4>
                    <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '0.5rem' }}>
                      We are currently verifying your payment screenshot. You'll receive your entry ticket once approved.
                    </p>
                  </div>
                ) : (
                  <form onSubmit={handleRegister}>
                    {event.registration_fee > 0 ? (
                      <>
                        <div className="form-group">
                          <label className="form-label">Select Payment Method</label>
                          <div style={{ display: 'flex', gap: '1rem', marginTop: '0.5rem' }}>
                            {event.upi_payment_enabled && (
                              <label style={{ display: 'flex', flex: 1, alignItems: 'center', gap: '0.5rem', cursor: 'pointer', padding: '0.75rem', border: '1px solid var(--border-color)', borderRadius: 'var(--radius-md)', background: paymentMethod === 'upi' ? 'rgba(248,123,27,0.1)' : 'transparent', borderColor: paymentMethod === 'upi' ? 'var(--primary)' : 'var(--border-color)' }}>
                                <input
                                  type="radio"
                                  name="paymentMethod"
                                  value="upi"
                                  checked={paymentMethod === 'upi'}
                                  onChange={() => setPaymentMethod('upi')}
                                />
                                Pay via UPI
                              </label>
                            )}
                            {event.cash_payment_enabled && (
                              <label style={{ display: 'flex', flex: 1, alignItems: 'center', gap: '0.5rem', cursor: 'pointer', padding: '0.75rem', border: '1px solid var(--border-color)', borderRadius: 'var(--radius-md)', background: paymentMethod === 'cash' ? 'rgba(248,123,27,0.1)' : 'transparent', borderColor: paymentMethod === 'cash' ? 'var(--primary)' : 'var(--border-color)' }}>
                                <input
                                  type="radio"
                                  name="paymentMethod"
                                  value="cash"
                                  checked={paymentMethod === 'cash'}
                                  onChange={() => setPaymentMethod('cash')}
                                />
                                Cash Payment
                              </label>
                            )}
                          </div>
                        </div>

                        {paymentMethod === 'upi' && (
                          <div style={{ background: 'var(--bg-input)', padding: '1rem', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-color)', marginBottom: '1.5rem', textAlign: 'center' }}>
                            <h4 style={{ marginBottom: '0.5rem' }}>UPI ID: {event.upi_id}</h4>
                            {event.upi_qr_image && (
                              <img
                                src={event.upi_qr_image}
                                alt="UPI QR Code"
                                style={{ maxWidth: '180px', width: '100%', margin: '0.5rem auto 1rem', display: 'block', borderRadius: 'var(--radius-sm)' }}
                              />
                            )}
                            <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                              Pay exactly <strong>₹{event.registration_fee}</strong> and upload the screenshot proof below.
                            </p>
                          </div>
                        )}

                        {paymentMethod === 'upi' && (
                          <div className="form-group">
                            <label className="form-label">Upload Payment Proof screenshot</label>
                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '1rem' }}>
                              {proofPreview && (
                                <img
                                  src={proofPreview}
                                  alt="Proof Preview"
                                  style={{ maxWidth: '100%', maxHeight: '200px', objectFit: 'contain', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border-color)' }}
                                />
                              )}
                              <label className="btn btn-secondary" style={{ width: '100%', cursor: 'pointer' }}>
                                <Upload size={16} /> Choose Image
                                <input
                                  type="file"
                                  accept="image/*"
                                  onChange={handleFileChange}
                                  style={{ display: 'none' }}
                                />
                              </label>
                            </div>
                          </div>
                        )}

                        {paymentMethod === 'cash' && (
                          <div className="alert alert-info">
                            Please contact the event organizers / class coordinators to complete your cash payment of <strong>₹{event.registration_fee}</strong>. Your ticket will be activated after payment confirmation.
                          </div>
                        )}
                      </>
                    ) : (
                      <div className="alert alert-success">
                        This event is <strong>FREE</strong> to register. Just click the button below to generate your pass.
                      </div>
                    )}

                    <button
                      type="submit"
                      disabled={submitLoading}
                      className="btn btn-primary"
                      style={{ width: '100%', marginTop: '1.5rem' }}
                    >
                      {submitLoading ? <Loader2 className="alert-icon animate-spin" /> : existingReg?.status === 'rejected' ? 'Re-submit Registration' : 'Register & Pay'}
                    </button>

                    {existingReg?.status === 'rejected' && existingReg.payments?.[0]?.rejection_reason && (
                      <div className="alert alert-danger" style={{ marginTop: '1rem', fontSize: '0.85rem' }}>
                        <strong>Previous Rejection Reason:</strong> {existingReg.payments[0].rejection_reason}
                      </div>
                    )}
                  </form>
                )}
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
