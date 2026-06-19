import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { Mail, CheckCircle, ShieldAlert, Loader2, KeyRound, Lock } from 'lucide-react';

export const ForgotPassword: React.FC = () => {
  const [email, setEmail] = useState('');
  const [otp, setOtp] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  
  const [step, setStep] = useState<'request' | 'verify'>('request');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);
  
  const navigate = useNavigate();

  const handleRequestReset = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const { error: resetError } = await supabase.auth.resetPasswordForEmail(email);

      if (resetError) throw resetError;

      setStep('verify');
    } catch (err: any) {
      console.error('Password reset error details:', err);
      setError(err?.message || err?.error_description || 'Failed to send reset code.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyAndReset = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (password !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    if (otp.length !== 6) {
      setError('Please enter a valid 6-digit OTP code.');
      return;
    }

    setLoading(true);

    try {
      // 1. Verify the OTP code for password recovery
      const { error: verifyError } = await supabase.auth.verifyOtp({
        email,
        token: otp,
        type: 'recovery',
      });

      if (verifyError) throw verifyError;

      // 2. Update the password for the now authenticated recovery session
      const { error: updateError } = await supabase.auth.updateUser({
        password: password,
      });

      if (updateError) throw updateError;

      // 3. Clear session/sign out so they must log in fresh
      await supabase.auth.signOut();

      setSuccess(true);
      setTimeout(() => {
        navigate('/login');
      }, 3000);
    } catch (err: any) {
      console.error('Password verify/reset error details:', err);
      setError(err?.message || err?.error_description || 'Failed to reset password. Verify your code and try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-wrapper container">
      <div className="card auth-card show-alert-anim" style={{ maxWidth: '480px' }}>
        <div className="card-header" style={{ textAlign: 'center' }}>
          <h2>Reset Password</h2>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            {step === 'request'
              ? 'We will send a 6-digit verification code to your email'
              : 'Enter verification code and your new password'}
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
              Password successfully updated! Redirecting to login...
            </div>
          </div>
        )}

        {!success && step === 'request' && (
          <form onSubmit={handleRequestReset}>
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
              {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Send Verification Code'}
            </button>
          </form>
        )}

        {!success && step === 'verify' && (
          <form onSubmit={handleVerifyAndReset}>
            <div className="form-group">
              <label className="form-label">6-Digit Verification Code</label>
              <div style={{ position: 'relative' }}>
                <KeyRound
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
                  type="text"
                  required
                  maxLength={6}
                  pattern="\d{6}"
                  className="form-control"
                  placeholder="123456"
                  value={otp}
                  onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                  style={{
                    paddingLeft: '2.5rem',
                    letterSpacing: '0.35rem',
                    fontSize: '1.15rem',
                    fontWeight: 'bold',
                    textAlign: 'center',
                  }}
                />
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">New Password</label>
              <div style={{ position: 'relative' }}>
                <Lock
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
                  type="password"
                  required
                  className="form-control"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  style={{ paddingLeft: '2.5rem' }}
                />
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Confirm New Password</label>
              <div style={{ position: 'relative' }}>
                <Lock
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
                  type="password"
                  required
                  className="form-control"
                  placeholder="••••••••"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
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
              {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Reset Password'}
            </button>
            
            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => setStep('request')}
              disabled={loading}
              style={{ width: '100%', marginTop: '0.5rem' }}
            >
              Back
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
