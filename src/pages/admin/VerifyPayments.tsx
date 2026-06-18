import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Check, X, ShieldAlert, Loader2, Image, AlertCircle, RefreshCw } from 'lucide-react';

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

export const VerifyPayments: React.FC = () => {
  const { profile } = useAuth();
  const [reviews, setReviews] = useState<PaymentReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  // Zoomed Image modal
  const [zoomedImage, setZoomedImage] = useState<string | null>(null);

  // Rejection state
  const [rejectId, setRejectId] = useState<number | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');

  const fetchPendingPayments = async () => {
    setLoading(true);
    try {
      const { data, error } = await supabase
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

      if (error) throw error;
      setReviews((data as any) || []);
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
    if (!window.confirm('Approve this payment? This will activate the student entry ticket and generate a QR token.')) {
      return;
    }

    setActionLoading(paymentId);
    try {
      // Update payment status - this triggers SQL trigger to update registration and create token
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

      // Refresh
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
      // Find the review to get the registration ID
      const targetReview = reviews.find((r) => r.id === rejectId);
      const registrationId = targetReview?.event_registrations.id;

      // Update payment record
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

      // Trigger does not update registration automatically for reject, let's update event registration
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

      // Reset rejection modal
      setRejectId(null);
      setRejectionReason('');

      // Refresh
      fetchPendingPayments();
    } catch (err: any) {
      alert(err.message || 'Rejection failed.');
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading pending payments...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div className="hero" style={{ padding: '2rem 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 className="hero-title" style={{ fontSize: '2.5rem' }}>Verify UPI Payments</h1>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.25rem' }}>
            Verify uploaded payment receipts and approve entry tickets.
          </p>
        </div>
        <button onClick={fetchPendingPayments} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <RefreshCw size={14} /> Refresh
        </button>
      </div>

      {reviews.length === 0 ? (
        <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
          <Check size={24} style={{ color: 'var(--success)', marginBottom: '1rem', width: '48px', height: '48px', background: 'rgba(16, 185, 129, 0.1)', padding: '10px', borderRadius: '50%' }} />
          <h3>All payments verified!</h3>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            There are no pending registrations requiring payment verification.
          </p>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }} className="show-alert-anim">
          {reviews.map((review) => {
            const student = review.event_registrations.profiles;
            const event = review.event_registrations.events;
            const regId = review.event_registrations.id;

            return (
              <div key={review.id} className="card" style={{ display: 'grid', gridTemplateColumns: '3fr 1fr', gap: '1.5rem', alignItems: 'center' }}>
                <div>
                  <h3 style={{ marginBottom: '0.5rem', color: '#ffffff' }}>
                    Student: {student.name} ({student.course})
                  </h3>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', fontSize: '0.875rem', color: 'var(--text-muted)', marginBottom: '1rem' }}>
                    <div>
                      <span>Roll Number: </span>
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

                {/* Screenshot preview */}
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.75rem' }}>
                  <div
                    onClick={() => setZoomedImage(review.proof_image)}
                    style={{ width: '100%', height: '80px', background: 'var(--bg-input)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border-color)', display: 'flex', justifyContent: 'center', alignItems: 'center', overflow: 'hidden', cursor: 'pointer' }}
                    title="Click to Zoom"
                  >
                    <img src={review.proof_image} alt="Receipt Proof" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem', width: '100%' }}>
                    <button
                      onClick={() => handleApprove(review.id, regId)}
                      disabled={actionLoading !== null}
                      className="btn btn-success btn-sm"
                      style={{ flex: 1, display: 'inline-flex', justifyContent: 'center', alignItems: 'center' }}
                    >
                      {actionLoading === review.id ? <Loader2 size={12} className="animate-spin" /> : <Check size={14} />} Approve
                    </button>
                    <button
                      onClick={() => setRejectId(review.id)}
                      disabled={actionLoading !== null}
                      className="btn btn-danger btn-sm"
                      style={{ flex: 1, display: 'inline-flex', justifyContent: 'center', alignItems: 'center' }}
                    >
                      <X size={14} /> Reject
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

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
