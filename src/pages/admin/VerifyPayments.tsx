import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Check, X, ShieldAlert, Loader2, RefreshCw, Landmark, Coins } from 'lucide-react';

interface PaymentReview {
  id: number;
  payment_method: string;
  proof_image: string;
  created_at: string;
  status: string;
  event_registrations: {
    id: number;
    student_id: string;
    profiles: {
      name: string;
      email: string;
      course: string;
      class_roll: string;
      batches: {
        name: string;
      } | null;
    };
    events: {
      name: string;
      registration_fee: number;
    };
  };
}

interface CashRegistration {
  id: number;
  student_id: string;
  payment_method: 'cash';
  status: string;
  created_at: string;
  profiles: {
    name: string;
    email: string;
    course: string;
    class_roll: string;
    batches: {
      name: string;
    } | null;
  };
  events: {
    name: string;
    registration_fee: number;
  };
}

export const VerifyPayments: React.FC = () => {
  const { profile } = useAuth();
  const [reviews, setReviews] = useState<PaymentReview[]>([]);
  const [cashRegs, setCashRegs] = useState<CashRegistration[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | string | null>(null);

  // Zoomed Image modal
  const [zoomedImage, setZoomedImage] = useState<string | null>(null);

  // Rejection state (UPI)
  const [rejectId, setRejectId] = useState<number | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');

  const fetchPendingPayments = async () => {
    setLoading(true);
    try {
      // 1. Fetch UPI payments
      const { data: upiData, error: upiError } = await supabase
        .from('payments')
        .select(`
          id,
          payment_method,
          proof_image,
          created_at,
          status,
          event_registrations!inner (
            id,
            student_id,
            profiles!inner (
              name,
              email,
              course,
              class_roll,
              batches (
                name
              )
            ),
            events!inner (
              name,
              registration_fee
            )
          )
        `)
        .eq('status', 'pending')
        .order('created_at', { ascending: true });

      if (upiError) throw upiError;
      setReviews((upiData as any) || []);

      // 2. Fetch pending Cash registrations
      const { data: cashData, error: cashError } = await supabase
        .from('event_registrations')
        .select(`
          id,
          student_id,
          payment_method,
          status,
          created_at,
          profiles!inner (
            name,
            email,
            course,
            class_roll,
            batches (
              name
            )
          ),
          events!inner (
            name,
            registration_fee
          )
        `)
        .eq('payment_method', 'cash')
        .eq('status', 'pending_payment')
        .order('created_at', { ascending: true });

      if (cashError) throw cashError;
      setCashRegs((cashData as any) || []);
    } catch (err) {
      console.error('Error fetching payments for verification:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPendingPayments();
  }, []);

  const handleApprove = async (paymentId: number, registrationId: number) => {
    if (!profile) return;
    if (!window.confirm('Approve this UPI payment? This will update registration status and trigger QR token generation.')) {
      return;
    }

    setActionLoading(paymentId);
    try {
      // Update payment status - trigger on_payment_update updates event_registrations and handles token
      const { error } = await supabase
        .from('payments')
        .update({
          status: 'approved',
          verified_by: profile.id,
          verification_time: new Date().toISOString()
        })
        .eq('id', paymentId);

      if (error) throw error;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'payment_approved',
        details: `Approved payment ID ${paymentId} for registration ID ${registrationId}`
      });

      fetchPendingPayments();
    } catch (err: any) {
      alert(err.message || 'Verification failed.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleReject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!profile || !rejectId || !rejectionReason.trim()) return;

    setActionLoading(rejectId);
    try {
      const targetReview = reviews.find((r) => r.id === rejectId);
      const registrationId = targetReview?.event_registrations.id;

      // Update payment record to rejected
      const { error } = await supabase
        .from('payments')
        .update({
          status: 'rejected',
          rejection_reason: rejectionReason,
          verified_by: profile.id,
          verification_time: new Date().toISOString()
        })
        .eq('id', rejectId);

      if (error) throw error;

      // Update event registration status to rejected
      if (registrationId) {
        await supabase
          .from('event_registrations')
          .update({ status: 'rejected' })
          .eq('id', registrationId);
      }

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'payment_rejected',
        details: `Rejected payment ID ${rejectId} for registration ID ${registrationId}. Reason: ${rejectionReason}`
      });

      setRejectId(null);
      setRejectionReason('');
      fetchPendingPayments();
    } catch (err: any) {
      alert(err.message || 'Rejection failed.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleApproveCash = async (regId: number, studentName: string, eventName: string) => {
    if (!profile) return;
    if (!window.confirm(`Confirm receipt of cash payment for student ${studentName} for the event ${eventName}? This will generate their QR entry ticket.`)) {
      return;
    }

    setActionLoading(`cash_${regId}`);
    try {
      // Direct update on event_registrations -> fires handle_registration_approval trigger
      const { error } = await supabase
        .from('event_registrations')
        .update({
          status: 'approved'
        })
        .eq('id', regId);

      if (error) throw error;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'cash_payment_approved',
        details: `Approved cash payment for registration ID ${regId} (${studentName} - ${eventName})`
      });

      fetchPendingPayments();
    } catch (err: any) {
      alert(err.message || 'Failed to approve cash payment.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleRejectCash = async (regId: number, studentName: string, eventName: string) => {
    if (!profile) return;
    if (!window.confirm(`Reject registration/cash payment for student ${studentName} for the event ${eventName}?`)) {
      return;
    }

    setActionLoading(`cash_${regId}`);
    try {
      const { error } = await supabase
        .from('event_registrations')
        .update({
          status: 'rejected'
        })
        .eq('id', regId);

      if (error) throw error;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile.id,
        action: 'cash_payment_rejected',
        details: `Rejected cash payment/registration ID ${regId} (${studentName} - ${eventName})`
      });

      fetchPendingPayments();
    } catch (err: any) {
      alert(err.message || 'Failed to reject cash registration.');
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading pending verification list...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div className="hero" style={{ padding: '2rem 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 className="hero-title" style={{ fontSize: '2.5rem' }}>Payment Verification</h1>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.25rem' }}>
            Verify uploaded UPI screenshots or approve offline Cash ticket registrations.
          </p>
        </div>
        <button onClick={fetchPendingPayments} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <RefreshCw size={14} /> Refresh
        </button>
      </div>

      {/* 1. UPI Verification Section */}
      <div style={{ marginBottom: '3rem' }}>
        <h2 style={{ marginBottom: '1.25rem', fontSize: '1.4rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <Landmark className="text-primary" size={20} /> Pending UPI Payments ({reviews.length})
        </h2>

        {reviews.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '2.5rem' }}>
            <Check size={24} style={{ color: 'var(--success)', marginBottom: '0.75rem', width: '40px', height: '40px', background: 'rgba(16, 185, 129, 0.1)', padding: '8px', borderRadius: '50%' }} />
            <h3>All UPI payments verified!</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '0.25rem' }}>
              No UPI uploads require verification.
            </p>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }} className="show-alert-anim">
            {reviews.map((review) => {
              const student = review.event_registrations.profiles;
              const event = review.event_registrations.events;
              const regId = review.event_registrations.id;

              return (
                <div key={review.id} className="card" style={{ display: 'grid', gridTemplateColumns: '3fr 1fr', gap: '1.5rem', alignItems: 'center', padding: '1.25rem' }}>
                  <div>
                    <h3 style={{ marginBottom: '0.5rem', color: '#ffffff', fontSize: '1.15rem' }}>
                      {student.name} ({student.course})
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                      <div>
                        <span>Roll: </span>
                        <strong style={{ color: '#ffffff' }}>{student.class_roll}</strong>
                      </div>
                      <div>
                        <span>Batch: </span>
                        <strong style={{ color: '#ffffff' }}>{student.batches?.name ?? 'N/A'}</strong>
                      </div>
                      <div>
                        <span>Email: </span>
                        <strong style={{ color: '#ffffff' }}>{student.email}</strong>
                      </div>
                      <div>
                        <span>Event: </span>
                        <strong style={{ color: 'var(--primary)' }}>{event.name}</strong>
                      </div>
                      <div>
                        <span>Amount: </span>
                        <strong style={{ color: 'var(--success)' }}>₹{event.registration_fee}</strong>
                      </div>
                      <div>
                        <span>Submitted: </span>
                        <strong style={{ color: '#ffffff' }}>{new Date(review.created_at).toLocaleString()}</strong>
                      </div>
                    </div>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                    <div
                      onClick={() => setZoomedImage(review.proof_image)}
                      style={{ width: '100%', height: '80px', background: 'var(--bg-input)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border-color)', display: 'flex', justifyContent: 'center', alignItems: 'center', overflow: 'hidden', cursor: 'pointer' }}
                      title="Click to Zoom"
                    >
                      <img src={review.proof_image} alt="Receipt Proof" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>

                    <div style={{ display: 'flex', gap: '0.4rem', width: '100%' }}>
                      <button
                        onClick={() => handleApprove(review.id, regId)}
                        disabled={actionLoading !== null}
                        className="btn btn-success btn-sm"
                        style={{ flex: 1, display: 'inline-flex', justifyContent: 'center', alignItems: 'center', padding: '4px' }}
                      >
                        {actionLoading === review.id ? <Loader2 size={12} className="animate-spin" /> : <Check size={12} />} Approve
                      </button>
                      <button
                        onClick={() => setRejectId(review.id)}
                        disabled={actionLoading !== null}
                        className="btn btn-danger btn-sm"
                        style={{ flex: 1, display: 'inline-flex', justifyContent: 'center', alignItems: 'center', padding: '4px' }}
                      >
                        <X size={12} /> Reject
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* 2. Cash Verification Section */}
      <div>
        <h2 style={{ marginBottom: '1.25rem', fontSize: '1.4rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <Coins className="text-primary" size={20} /> Pending Cash Payments ({cashRegs.length})
        </h2>

        {cashRegs.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '2.5rem' }}>
            <Check size={24} style={{ color: 'var(--success)', marginBottom: '0.75rem', width: '40px', height: '40px', background: 'rgba(16, 185, 129, 0.1)', padding: '8px', borderRadius: '50%' }} />
            <h3>All cash collections checked!</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '0.25rem' }}>
              No cash registrations require verification.
            </p>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }} className="show-alert-anim">
            {cashRegs.map((reg) => {
              const student = reg.profiles;
              const event = reg.events;
              const uniqueActionId = `cash_${reg.id}`;

              return (
                <div key={reg.id} className="card" style={{ display: 'grid', gridTemplateColumns: '3fr 1fr', gap: '1.5rem', alignItems: 'center', padding: '1.25rem' }}>
                  <div>
                    <h3 style={{ marginBottom: '0.5rem', color: '#ffffff', fontSize: '1.15rem' }}>
                      {student.name} ({student.course})
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                      <div>
                        <span>Roll: </span>
                        <strong style={{ color: '#ffffff' }}>{student.class_roll}</strong>
                      </div>
                      <div>
                        <span>Batch: </span>
                        <strong style={{ color: '#ffffff' }}>{student.batches?.name ?? 'N/A'}</strong>
                      </div>
                      <div>
                        <span>Email: </span>
                        <strong style={{ color: '#ffffff' }}>{student.email}</strong>
                      </div>
                      <div>
                        <span>Event: </span>
                        <strong style={{ color: 'var(--primary)' }}>{event.name}</strong>
                      </div>
                      <div>
                        <span>Fee Amount: </span>
                        <strong style={{ color: 'var(--success)' }}>₹{event.registration_fee}</strong>
                      </div>
                      <div>
                        <span>Registered: </span>
                        <strong style={{ color: '#ffffff' }}>{new Date(reg.created_at).toLocaleString()}</strong>
                      </div>
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem', width: '100%' }}>
                    <button
                      onClick={() => handleApproveCash(reg.id, student.name, event.name)}
                      disabled={actionLoading !== null}
                      className="btn btn-success btn-sm"
                      style={{ flex: 1, display: 'inline-flex', justifyContent: 'center', alignItems: 'center', padding: '6px' }}
                    >
                      {actionLoading === uniqueActionId ? <Loader2 size={12} className="animate-spin" /> : <Check size={14} />} Approve Cash
                    </button>
                    <button
                      onClick={() => handleRejectCash(reg.id, student.name, event.name)}
                      disabled={actionLoading !== null}
                      className="btn btn-danger btn-sm"
                      style={{ flex: 1, display: 'inline-flex', justifyContent: 'center', alignItems: 'center', padding: '6px' }}
                    >
                      <X size={14} /> Reject
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Zoom Modal */}
      {zoomedImage && (
        <div style={{ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(4px)', display: 'flex', justifyContent: 'center', alignItems: 'center', zIndex: 2000 }} onClick={() => setZoomedImage(null)}>
          <div style={{ maxWidth: '90%', maxHeight: '90%', position: 'relative' }}>
            <button
              onClick={() => setZoomedImage(null)}
              style={{ position: 'absolute', top: '-40px', right: '0px', background: 'none', border: 'none', color: '#ffffff', fontSize: '1.5rem', cursor: 'pointer' }}
            >
              Close [X]
            </button>
            <img src={zoomedImage} alt="Payment Proof Full" style={{ maxWidth: '100%', maxHeight: '80vh', objectFit: 'contain', borderRadius: 'var(--radius-sm)' }} />
          </div>
        </div>
      )}

      {/* Rejection Reason Modal */}
      {rejectId && (
        <div style={{ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(4px)', display: 'flex', justifyContent: 'center', alignItems: 'center', zIndex: 2000 }}>
          <div className="card show-alert-anim" style={{ maxWidth: '400px', width: '90%' }}>
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Reject Registration Payment</h3>
            </div>
            <form onSubmit={handleReject}>
              <div className="form-group">
                <label className="form-label">Reason for Rejection</label>
                <textarea
                  required
                  rows={3}
                  className="form-control"
                  placeholder="e.g. Screenshot does not match the payment UPI ID, or wrong registration fee paid."
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                ></textarea>
              </div>

              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.5rem' }}>
                <button
                  type="submit"
                  disabled={actionLoading !== null}
                  className="btn btn-danger"
                  style={{ flex: 1 }}
                >
                  {actionLoading !== null ? 'Rejecting...' : 'Reject Payment'}
                </button>
                <button
                  type="button"
                  onClick={() => { setRejectId(null); setRejectionReason(''); }}
                  className="btn btn-secondary"
                  style={{ flex: 1 }}
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
