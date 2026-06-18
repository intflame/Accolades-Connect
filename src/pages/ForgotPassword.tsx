import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { Mail, CheckCircle, ShieldAlert, Loader2 } from 'lucide-react';

export const ForgotPassword: React.FC = () => {
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleReset = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const { error: resetError } = await supabase.auth.resetPasswordForEmail(email, {
        redirectTo: `${window.location.origin}/reset-password`,
      });

      if (resetError) throw resetError;

      setSuccess(true);
    } catch (err: any) {
      setError(err.message || 'Failed to send reset link.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-wrapper container">
      <div className="card auth-card show-alert-anim">
        <div className="card-header" style={{ textAlign: 'center' }}>
          <h2>Forgot Password</h2>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            We will send you a password reset link
          </p>
        </div>

        {error && (
          <div className="alert alert-danger">
            <ShieldAlert className="alert-icon" />
            <div className="alert-content">{error}</div>
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            <CheckCircle className="alert-icon" />
            <div className="alert-content">
              Reset email sent! Please check your inbox for instructions.
            </div>
          </div>
        )}

        {!success && (
          <form onSubmit={handleReset}>
            <div className="form-group">
              <label className="form-label">Email Address</label>
              <div style={{ position: 'relative' }}>
                <Mail
                  size={18}
                  style={{
                    position: 'absolute',
                    left: '12px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'var(--text-muted)',
                  }}
                />
                <input
                  type="email"
                  required
                  className="form-control"
                  placeholder="student@example.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  style={{ paddingLeft: '2.5rem' }}
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="btn btn-primary"
              style={{ width: '100%', marginTop: '1rem' }}
            >
              {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Send Reset Link'}
            </button>
          </form>
        )}

        <div style={{ marginTop: '1.5rem', textAlign: 'center', fontSize: '0.9rem' }}>
          <p>
            <Link to="/login" style={{ fontWeight: '600' }}>
              Back to Login
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};
