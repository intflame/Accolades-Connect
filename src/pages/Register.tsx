import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { useAuth } from '../context/AuthContext';
import { User, Mail, Lock, Phone, UserCheck, ShieldAlert, Upload, Loader2, KeyRound } from 'lucide-react';

interface Batch {
  id: number;
  name: string;
}

export const Register: React.FC = () => {
  const navigate = useNavigate();
  const { refreshProfile } = useAuth();
  const [batches, setBatches] = useState<Batch[]>([]);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [course, setCourse] = useState<'BCA' | 'MCA'>('BCA');
  const [batchId, setBatchId] = useState<number | ''>('');
  const [classRoll, setClassRoll] = useState('');
  const [universityRoll, setUniversityRoll] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [whatsappNumber, setWhatsappNumber] = useState('');
  const [foodPreference, setFoodPreference] = useState<'veg' | 'non-veg'>('veg');
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);

  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  const [step, setStep] = useState<'input' | 'verify'>('input');
  const [otp, setOtp] = useState('');
  const [tempUserId, setTempUserId] = useState<string | null>(null);

  useEffect(() => {
    const fetchBatches = async () => {
      const { data, error } = await supabase
        .from('batches')
        .select('*')
        .order('name', { ascending: false });

      if (error) {
        console.error('Error fetching batches:', error);
      } else {
        setBatches(data || []);
        if (data && data.length > 0) {
          setBatchId(data[0].id);
        }
      }
    };
    fetchBatches();
  }, []);

  const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setPhotoFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setPhotoPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const clearForm = () => {
    setName('');
    setEmail('');
    setPassword('');
    setConfirmPassword('');
    setClassRoll('');
    setUniversityRoll('');
    setContactNumber('');
    setWhatsappNumber('');
    setPhotoFile(null);
    setPhotoPreview(null);
    setOtp('');
  };

  const uploadPhoto = async (userId: string) => {
    if (!photoFile) return;

    try {
      const fileExt = photoFile.name.split('.').pop();
      const filePath = `${userId}/profile_${Date.now()}.${fileExt}`;

      // Upload to bucket
      const { error: uploadError } = await supabase.storage
        .from('profile_photos')
        .upload(filePath, photoFile, {
          upsert: true
        });

      if (uploadError) {
        console.error('Photo upload failed but registration completed:', uploadError);
      } else {
        // Get public URL
        const { data: { publicUrl } } = supabase.storage
          .from('profile_photos')
          .getPublicUrl(filePath);

        // Update profiles table
        const { error: updateError } = await supabase
          .from('profiles')
          .update({ profile_photo: publicUrl })
          .eq('id', userId);

        if (updateError) {
          console.error('Failed to update profile photo URL in database:', updateError);
        }
      }
    } catch (err) {
      console.error('Error during photo upload:', err);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (password !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    if (!batchId) {
      setError('Please select a batch.');
      return;
    }

    setLoading(true);

    try {
      // 1. Sign up the user via Supabase Auth
      // Pass all metadata in user metadata so the database trigger can read it
      const { data: authData, error: authError } = await supabase.auth.signUp({
        email,
        password,
        options: {
          data: {
            name,
            role: 'student',
            course,
            batch_id: batchId,
            class_roll: classRoll,
            university_roll: universityRoll,
            contact_number: contactNumber,
            whatsapp_number: whatsappNumber,
            food_preference: foodPreference,
          },
        },
      });

      if (authError) throw authError;

      const userId = authData.user?.id;
      if (!userId) {
        throw new Error('Registration failed. Please try again.');
      }

      if (authData.session) {
        // Case A: Email verification is disabled. Set status to active in database.
        await supabase
          .from('profiles')
          .update({ status: 'active' })
          .eq('id', userId);

        await uploadPhoto(userId);

        await refreshProfile();

        setSuccess(true);
        clearForm();

        setTimeout(() => {
          navigate('/student');
        }, 3000);
      } else {
        // Case B: Email verification is enabled. Transition to OTP step.
        setTempUserId(userId);
        setStep('verify');
      }
    } catch (err: any) {
      setError(err.message || 'An error occurred during registration.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOTP = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (otp.length !== 6 && otp.length !== 8) {
      setError('Please enter a valid verification code (6 or 8 characters).');
      return;
    }

    setLoading(true);

    try {
      // 1. Verify the OTP code for signup
      const { error: verifyError } = await supabase.auth.verifyOtp({
        email,
        token: otp,
        type: 'signup',
      });

      if (verifyError) throw verifyError;

      // Update profile status to active in database since email is now verified
      if (tempUserId) {
        await supabase
          .from('profiles')
          .update({ status: 'active' })
          .eq('id', tempUserId);

        // 2. Upload Profile Photo if selected (now that we are authenticated)
        await uploadPhoto(tempUserId);
      }

      await refreshProfile();

      setSuccess(true);
      clearForm();

      setTimeout(() => {
        navigate('/student');
      }, 3000);
    } catch (err: any) {
      console.error('Registration OTP verification error details:', err);
      setError(err?.message || err?.error_description || 'Failed to verify code. Please check the code and try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-wrapper container">
      <div className="card auth-card show-alert-anim" style={{ maxWidth: step === 'verify' ? '480px' : '640px' }}>
        <div className="card-header" style={{ textAlign: 'center' }}>
          <h2>{step === 'verify' ? 'Verify Email' : 'Student Registration'}</h2>
          <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            {step === 'verify'
              ? `We have sent a verification code to ${email}`
              : 'Join the Accolades Connect Portal'}
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
            <UserCheck className="alert-icon" />
            <div className="alert-content">
              Registration successful! Redirecting to student dashboard...
            </div>
          </div>
        )}

        {!success && step === 'input' && (
          <form onSubmit={handleRegister}>
            <div className="profile-photo-container" style={{ justifyContent: 'center' }}>
              <img
                src={photoPreview || '/placeholder-avatar.svg'}
                alt="Profile Preview"
                className="profile-photo-preview"
                onError={(e) => {
                  (e.target as HTMLImageElement).src =
                    'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 24 24" fill="none" stroke="%23F87B1B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                }}
              />
              <div>
                <label className="btn btn-secondary btn-sm" style={{ cursor: 'pointer' }}>
                  <Upload size={14} /> Upload Photo
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handlePhotoChange}
                    style={{ display: 'none' }}
                  />
                </label>
                <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                  JPG, PNG up to 2MB
                </p>
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Full Name</label>
                <div style={{ position: 'relative' }}>
                  <User
                    size={16}
                    style={{
                      position: 'absolute',
                      left: '10px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      color: 'var(--text-muted)',
                    }}
                  />
                  <input
                    type="text"
                    required
                    className="form-control"
                    placeholder="John Doe"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    style={{ paddingLeft: '2.25rem' }}
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">Email Address</label>
                <div style={{ position: 'relative' }}>
                  <Mail
                    size={16}
                    style={{
                      position: 'absolute',
                      left: '10px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      color: 'var(--text-muted)',
                    }}
                  />
                  <input
                    type="email"
                    required
                    className="form-control"
                    placeholder="johndoe@email.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    style={{ paddingLeft: '2.25rem' }}
                  />
                </div>
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Password</label>
                <div style={{ position: 'relative' }}>
                  <Lock
                    size={16}
                    style={{
                      position: 'absolute',
                      left: '10px',
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
                    style={{ paddingLeft: '2.25rem' }}
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">Confirm Password</label>
                <div style={{ position: 'relative' }}>
                  <Lock
                    size={16}
                    style={{
                      position: 'absolute',
                      left: '10px',
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
                    style={{ paddingLeft: '2.25rem' }}
                  />
                </div>
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Course</label>
                <select
                  className="form-control"
                  value={course}
                  onChange={(e) => setCourse(e.target.value as 'BCA' | 'MCA')}
                >
                  <option value="BCA">BCA</option>
                  <option value="MCA">MCA</option>
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Academic Batch</label>
                <select
                  className="form-control"
                  required
                  value={batchId}
                  onChange={(e) => setBatchId(Number(e.target.value))}
                >
                  {batches.map((batch) => (
                    <option key={batch.id} value={batch.id}>
                      {batch.name}
                    </option>
                  ))}
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
                  placeholder="e.g. 10"
                  value={classRoll}
                  onChange={(e) => setClassRoll(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="form-label">University Roll Number</label>
                <input
                  type="text"
                  required
                  className="form-control"
                  placeholder="e.g. 12023002010"
                  value={universityRoll}
                  onChange={(e) => setUniversityRoll(e.target.value)}
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Contact Number</label>
                <div style={{ position: 'relative' }}>
                  <Phone
                    size={16}
                    style={{
                      position: 'absolute',
                      left: '10px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      color: 'var(--text-muted)',
                    }}
                  />
                  <input
                    type="tel"
                    required
                    className="form-control"
                    placeholder="9876543210"
                    value={contactNumber}
                    onChange={(e) => setContactNumber(e.target.value)}
                    style={{ paddingLeft: '2.25rem' }}
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">WhatsApp Number</label>
                <div style={{ position: 'relative' }}>
                  <Phone
                    size={16}
                    style={{
                      position: 'absolute',
                      left: '10px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      color: 'var(--text-muted)',
                    }}
                  />
                  <input
                    type="tel"
                    required
                    className="form-control"
                    placeholder="9876543210"
                    value={whatsappNumber}
                    onChange={(e) => setWhatsappNumber(e.target.value)}
                    style={{ paddingLeft: '2.25rem' }}
                  />
                </div>
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Food Preference</label>
              <div style={{ display: 'flex', gap: '1.5rem', marginTop: '0.5rem' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                  <input
                    type="radio"
                    name="foodPreference"
                    value="veg"
                    checked={foodPreference === 'veg'}
                    onChange={() => setFoodPreference('veg')}
                  />
                  Vegetarian
                </label>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                  <input
                    type="radio"
                    name="foodPreference"
                    value="non-veg"
                    checked={foodPreference === 'non-veg'}
                    onChange={() => setFoodPreference('non-veg')}
                  />
                  Non-Vegetarian
                </label>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="btn btn-primary"
              style={{ width: '100%', marginTop: '1rem' }}
            >
              {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Register'}
            </button>
          </form>
        )}

        {!success && step === 'verify' && (
          <form onSubmit={handleVerifyOTP}>
            <div className="form-group">
              <label className="form-label">Verification Code</label>
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
                  maxLength={8}
                  pattern="[a-zA-Z0-9]{6,8}"
                  className="form-control"
                  placeholder="Enter verification code..."
                  value={otp}
                  onChange={(e) => setOtp(e.target.value.replace(/[^a-zA-Z0-9]/g, ''))}
                  style={{
                    paddingLeft: '2.5rem',
                    letterSpacing: '0.2rem',
                    fontSize: '1.15rem',
                    fontWeight: 'bold',
                    textAlign: 'center',
                  }}
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="btn btn-primary"
              style={{ width: '100%', marginTop: '1rem' }}
            >
              {loading ? <Loader2 className="alert-icon animate-spin" /> : 'Verify & Complete'}
            </button>

            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => setStep('input')}
              disabled={loading}
              style={{ width: '100%', marginTop: '0.5rem' }}
            >
              Back to Form
            </button>
          </form>
        )}

        <div style={{ marginTop: '1.5rem', textAlign: 'center', fontSize: '0.9rem' }}>
          <p style={{ color: 'var(--text-muted)' }}>
            Already have an account?{' '}
            <Link to="/login" style={{ fontWeight: '600' }}>
              Sign In
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};
