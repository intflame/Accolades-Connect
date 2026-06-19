import React, { useState } from 'react';
import { useNavigate, Link, useSearchParams } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { useAuth } from '../context/AuthContext';
import { Lock, Mail, Loader2, AlertTriangle } from 'lucide-react';

export const Login: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const { data, error: authError } = await supabase.auth.signInWithPassword({
        email,
        password,
      });

      if (authError) throw authError;

      if (data.user) {
        // Refresh the profile locally
        const { data: profileData, error: profileError } = await supabase
          .from('profiles')
          .select('*')
          .eq('id', data.user.id)
          .single();

        if (profileError) {
          throw new Error('Failed to load profile details.');
        }

        if (profileData.status === 'pending_approval') {
          setError('Your account is pending admin approval. Please contact the administrator.');
          await supabase.auth.signOut();
          setLoading(false);
          return;
        }

        if (profileData.status === 'suspended') {
          setError('Your account has been suspended.');
          await supabase.auth.signOut();
          setLoading(false);
          return;
        }

        // Redirect based on role
        const redirect = searchParams.get('redirect');
        if (profileData.role === 'admin') {
          navigate('/admin');
        } else if (profileData.role === 'scanner') {
          navigate('/scanner');
        } else {
          navigate(redirect || '/student');
        }
      }
    } catch (err: any) {
      setError(err.message || 'An error occurred during login.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-wrapper container">
      <div className="card auth-card show-alert-anim">
        <div className="card-header" style={{ textAlign: 'center' }}>
          <h2>Accolades Connect</h2>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            Event Attendance Management System
          </p>
        </div>

        {error && (
          <div className="alert alert-danger">
            <AlertTriangle className="alert-icon" />
            <div className="alert-content">{error}</div>
          </div>
        )}

        <form onSubmit={handleLogin}>
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

          <div className="form-group">
            <label className="form-label">Password</label>
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

          <button
            type="submit"
            disabled={loading}
            className="btn btn-primary"
            style={{ width: '100%', marginTop: '1rem' }}
          >
            {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Sign In'}
          </button>
        </form>

        <div style={{ marginTop: '1.5rem', textAlign: 'center', fontSize: '0.9rem' }}>
          <p style={{ color: 'var(--text-muted)' }}>
            Don't have an account?{' '}
            <Link to="/register" style={{ fontWeight: '600' }}>
              Register Here
            </Link>
          </p>
          <p style={{ marginTop: '0.5rem' }}>
            <Link to="/forgot-password" style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
              Forgot Password?
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};
