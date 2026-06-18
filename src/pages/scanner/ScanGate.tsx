import React, { useEffect, useState, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Html5QrcodeScanner } from 'html5-qrcode';
import { ArrowLeft, CheckCircle2, AlertTriangle, XCircle, ShieldAlert, Loader2, Scan, Calendar, MapPin } from 'lucide-react';

interface Event {
  id: number;
  name: string;
  venue: string;
  event_date: string;
  food_enabled: boolean;
  scan_start_time: string;
  scan_end_time: string;
}

interface ScanResult {
  status: 'success' | 'warning' | 'error';
  message: string;
  studentName?: string;
  studentRoll?: string;
  studentBatch?: string;
  studentRole?: string;
  foodPref?: string;
}

export const ScanGate: React.FC = () => {
  const { eventId } = useParams<{ eventId: string }>();
  const { profile } = useAuth();
  const [event, setEvent] = useState<Event | null>(null);
  const [scanType, setScanType] = useState<'entry' | 'food' | 'exit'>('entry');
  const [manualToken, setManualToken] = useState('');
  const [loading, setLoading] = useState(true);

  // Scanner status
  const [isScanning, setIsScanning] = useState(true);
  const [processingScan, setProcessingScan] = useState(false);
  const [scanResult, setScanResult] = useState<ScanResult | null>(null);

  const scannerRef = useRef<Html5QrcodeScanner | null>(null);
  const navigate = useNavigate();

  // Play audio beeps using Web Audio API synthesis (100% client side)
  const playBeep = (type: 'success' | 'error' | 'warning') => {
    try {
      const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();

      osc.connect(gain);
      gain.connect(audioCtx.destination);

      if (type === 'success') {
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // High pitch check
        gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.15);
      } else if (type === 'error') {
        osc.frequency.setValueAtTime(180, audioCtx.currentTime); // Deep buzz
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
      } else if (type === 'warning') {
        // Double short beep
        osc.frequency.setValueAtTime(587, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.1);
      }
    } catch (e) {
      console.warn('Audio feedback failed:', e);
    }
  };

  useEffect(() => {
    const fetchEvent = async () => {
      if (!eventId) return;
      try {
        const { data, error } = await supabase
          .from('events')
          .select('id, name, venue, event_date, food_enabled, scan_start_time, scan_end_time')
          .eq('id', Number(eventId))
          .single();

        if (error) throw error;
        setEvent(data);
      } catch (err) {
        console.error('Error loading event:', err);
        alert('Event not found.');
        navigate('/scanner');
      } finally {
        setLoading(false);
      }
    };
    fetchEvent();
  }, [eventId]);

  // Handle scanner initialization/disposal
  useEffect(() => {
    if (loading || !event || !isScanning || processingScan || scanResult) return;

    // Initialize scanner
    const html5QrcodeScanner = new Html5QrcodeScanner(
      'qr-scanner-element',
      { fps: 8, qrbox: { width: 220, height: 220 } },
      /* verbose= */ false
    );

    html5QrcodeScanner.render(
      (text) => handleScan(text),
      (error) => {
        // ignore scan failures
      }
    );

    scannerRef.current = html5QrcodeScanner;

    return () => {
      if (scannerRef.current) {
        scannerRef.current.clear().catch((e) => console.warn('Scanner clear failed:', e));
        scannerRef.current = null;
      }
    };
  }, [loading, event, isScanning, processingScan, scanResult]);

  const handleScan = async (token: string) => {
    if (processingScan) return;
    setProcessingScan(true);
    setIsScanning(false);

    // Stop current scanner to show result
    if (scannerRef.current) {
      try {
        await scannerRef.current.clear();
        scannerRef.current = null;
      } catch (e) {
        console.warn(e);
      }
    }

    await processToken(token);
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!manualToken.trim()) return;
    handleScan(manualToken.trim());
    setManualToken('');
  };

  const processToken = async (token: string) => {
    if (!eventId || !profile) return;

    try {
      // 1. Fetch QR token details (join registrations, profiles, events, batches)
      const { data: tokenData, error: tokenError } = await supabase
        .from('qr_tokens')
        .select(`
          id,
          status,
          event_registrations!inner (
            id,
            event_id,
            status,
            assigned_role,
            student_id,
            profiles!inner (
              name,
              course,
              class_roll,
              food_preference,
              batches (
                name
              )
            )
          )
        `)
        .eq('token', token)
        .maybeSingle();

      if (tokenError) throw tokenError;

      // 2. Validate token existence
      if (!tokenData) {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'Invalid QR Code. Ticket token not recognized in system.'
        });
        setProcessingScan(false);
        return;
      }
      // Handle array or object structure for registrations
      const reg = Array.isArray(tokenData.event_registrations)
        ? tokenData.event_registrations[0]
        : tokenData.event_registrations;

      if (!reg) {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'Invalid registration details connected to this token.'
        });
        setProcessingScan(false);
        return;
      }

      // Handle array or object structure for profiles
      const student = Array.isArray(reg.profiles)
        ? reg.profiles[0]
        : reg.profiles;

      if (!student) {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'Invalid student profile connected to this ticket.'
        });
        setProcessingScan(false);
        return;
      }

      // Handle array or object structure for batches
      const batchName = student.batches
        ? (Array.isArray(student.batches) ? student.batches[0]?.name : (student.batches as any).name)
        : 'N/A';

      // 3. Validate event match
      if (reg.event_id !== Number(eventId)) {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'Wrong Event! This ticket is registered for a different event.'
        });
        setProcessingScan(false);
        return;
      }

      // 4. Validate food availability for food scan type
      if (scanType === 'food' && !event?.food_enabled) {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'Food scans are not enabled/available for this event.'
        });
        setProcessingScan(false);
        return;
      }

      // 5. Validate registration approval status
      if (reg.status !== 'approved') {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: `Registration issue! Ticket status is: ${reg.status.toUpperCase()}. Payment must be approved first.`
        });
        setProcessingScan(false);
        return;
      }

      // 6. Validate token status
      if (tokenData.status === 'disabled') {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'This ticket has been disabled by the administrator.'
        });
        setProcessingScan(false);
        return;
      }

      if (tokenData.status === 'expired') {
        playBeep('error');
        setScanResult({
          status: 'error',
          message: 'This QR ticket has expired.'
        });
        setProcessingScan(false);
        return;
      }

      // 7. Check scan window timing
      const now = new Date();
      const startTime = new Date(event!.scan_start_time);
      const endTime = new Date(event!.scan_end_time);

      if (now < startTime) {
        playBeep('warning');
        setScanResult({
          status: 'warning',
          message: `Scan window not open yet. Starts at: ${startTime.toLocaleTimeString()}`,
          studentName: student.name,
          studentRoll: student.class_roll,
          studentBatch: batchName,
          studentRole: reg.assigned_role,
        });
        setProcessingScan(false);
        return;
      }

      if (now > endTime) {
        playBeep('warning');
        setScanResult({
          status: 'warning',
          message: `Scan window closed! Ended at: ${endTime.toLocaleTimeString()}`,
          studentName: student.name,
          studentRoll: student.class_roll,
          studentBatch: batchName,
          studentRole: reg.assigned_role,
        });
        setProcessingScan(false);
        return;
      }

      // 8. Check duplicate scans
      const { data: existingScan, error: scanError } = await supabase
        .from('attendance_scans')
        .select('id, scanned_at')
        .eq('qr_token_id', tokenData.id)
        .eq('scan_type', scanType)
        .maybeSingle();

      if (scanError) throw scanError;

      if (existingScan) {
        playBeep('warning');
        const scanTime = new Date(existingScan.scanned_at).toLocaleTimeString();
        setScanResult({
          status: 'warning',
          message: `Already Scanned! Checked in for ${scanType.toUpperCase()} at ${scanTime}.`,
          studentName: student.name,
          studentRoll: student.class_roll,
          studentBatch: batchName,
          studentRole: reg.assigned_role,
          foodPref: student.food_preference,
        });
        setProcessingScan(false);
        return;
      }

      // 9. Process Save scan details
      const { error: insertError } = await supabase
        .from('attendance_scans')
        .insert({
          qr_token_id: tokenData.id,
          scan_type: scanType,
          scanned_by: profile.id
        });

      if (insertError) throw insertError;

      // Update token status to used if entry
      if (scanType === 'entry') {
        await supabase
          .from('qr_tokens')
          .update({ status: 'used' })
          .eq('id', tokenData.id);
      }

      // Log activity
      await supabase
        .from('activity_logs')
        .insert({
          user_id: profile.id,
          action: 'attendance_marked',
          details: `Marked ${scanType} check-in for ${student.name}. Roll: ${student.class_roll}. Event: ${event?.name}`
        });

      playBeep('success');
      setScanResult({
        status: 'success',
        message: `${scanType.toUpperCase()} check-in marked successfully!`,
        studentName: student.name,
        studentRoll: student.class_roll,
        studentBatch: batchName,
        studentRole: reg.assigned_role,
        foodPref: student.food_preference,
      });

    } catch (err: any) {
      console.error('Check-in processing error:', err);
      playBeep('error');
      setScanResult({
        status: 'error',
        message: err.message || 'Database exception processing check-in.'
      });
    } finally {
      setProcessingScan(false);
    }
  };

  const handleNextScan = () => {
    setScanResult(null);
    setIsScanning(true);
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading scanner options...</p>
      </div>
    );
  }

  if (!event) return null;

  return (
    <div className="container main-content">
      <button onClick={() => navigate('/scanner')} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', marginBottom: '1.5rem' }}>
        <ArrowLeft size={14} /> Back to Controls
      </button>

      <div className="dashboard-panel">
        {/* Left: Scan type selection & camera wrapper */}
        <div className="card show-alert-anim" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
          <div className="card-header" style={{ width: '100%', textAlign: 'center', marginBottom: '1.5rem' }}>
            <h3>Scan Passes: {event.name}</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '0.25rem' }}>
              Select check-in mode and hold QR code to camera
            </p>
          </div>

          {/* Mode Selector */}
          <div style={{ display: 'flex', gap: '0.5rem', width: '100%', marginBottom: '1.5rem' }}>
            <button
              onClick={() => { setScanType('entry'); handleNextScan(); }}
              className={`btn ${scanType === 'entry' ? 'btn-primary' : 'btn-secondary'}`}
              style={{ flex: 1, fontSize: '0.85rem', padding: '0.5rem' }}
            >
              Entry Gate
            </button>
            {event.food_enabled && (
              <button
                onClick={() => { setScanType('food'); handleNextScan(); }}
                className={`btn ${scanType === 'food' ? 'btn-primary' : 'btn-secondary'}`}
                style={{ flex: 1, fontSize: '0.85rem', padding: '0.5rem' }}
              >
                Food Counter
              </button>
            )}
            <button
              onClick={() => { setScanType('exit'); handleNextScan(); }}
              className={`btn ${scanType === 'exit' ? 'btn-primary' : 'btn-secondary'}`}
              style={{ flex: 1, fontSize: '0.85rem', padding: '0.5rem' }}
            >
              Exit Gate
            </button>
          </div>

          {/* Camera Frame */}
          <div style={{ position: 'relative', width: '100%', maxWidth: '320px', margin: '0 auto 1.5rem' }}>
            {isScanning ? (
              <div style={{ position: 'relative', overflow: 'hidden', borderRadius: 'var(--radius-md)', border: '2px solid rgba(255, 255, 255, 0.1)' }}>
                <div id="qr-scanner-element" style={{ width: '100%' }}></div>
                <div className="scanner-laser"></div>
              </div>
            ) : (
              <div
                style={{
                  width: '100%',
                  aspectRatio: '1',
                  background: 'var(--bg-input)',
                  borderRadius: 'var(--radius-md)',
                  border: '2px solid var(--border-color)',
                  display: 'flex',
                  justifyContent: 'center',
                  alignItems: 'center',
                  color: 'var(--text-muted)',
                }}
              >
                {processingScan ? (
                  <div style={{ textAlign: 'center' }}>
                    <Loader2 className="animate-spin" size={32} style={{ color: 'var(--primary)', margin: '0 auto 0.5rem' }} />
                    <p>Verifying Ticket...</p>
                  </div>
                ) : (
                  <div style={{ textAlign: 'center' }}>
                    <Scan size={32} style={{ color: 'var(--text-muted)', margin: '0 auto 0.5rem' }} />
                    <p>Scanner Paused</p>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Manual Entry Form */}
          <form onSubmit={handleManualSubmit} style={{ width: '100%' }}>
            <div className="form-group" style={{ marginBottom: 0 }}>
              <label className="form-label" style={{ fontSize: '0.8rem' }}>Or Enter Token Manually</label>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Paste certificate or ticket token..."
                  value={manualToken}
                  onChange={(e) => setManualToken(e.target.value)}
                  disabled={processingScan}
                />
                <button type="submit" disabled={processingScan} className="btn btn-secondary">
                  Submit
                </button>
              </div>
            </div>
          </form>
        </div>

        {/* Right: Validation Results */}
        <div className="card show-alert-anim">
          <div className="card-header" style={{ marginBottom: '1.5rem', paddingBottom: '0.75rem' }}>
            <h3>Scan Result</h3>
          </div>

          {!scanResult ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text-muted)' }}>
              <Scan size={48} style={{ color: 'var(--border-color)', marginBottom: '1rem' }} />
              <h4>Awaiting scanned token...</h4>
              <p style={{ fontSize: '0.85rem', marginTop: '0.5rem' }}>
                Point the student ticket QR code to the camera or type the code manually.
              </p>
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '1rem 0' }}>
              {scanResult.status === 'success' ? (
                <div style={{ color: 'var(--success)' }}>
                  <CheckCircle2 size={56} style={{ margin: '0 auto 1rem' }} />
                  <h3 style={{ color: 'var(--success)' }}>Access Granted</h3>
                </div>
              ) : scanResult.status === 'warning' ? (
                <div style={{ color: 'var(--warning)' }}>
                  <AlertTriangle size={56} style={{ margin: '0 auto 1rem' }} />
                  <h3 style={{ color: 'var(--warning)' }}>Warning</h3>
                </div>
              ) : (
                <div style={{ color: 'var(--danger)' }}>
                  <XCircle size={56} style={{ margin: '0 auto 1rem' }} />
                  <h3 style={{ color: 'var(--danger)' }}>Access Denied</h3>
                </div>
              )}

              <div className="alert" style={{
                marginTop: '1.5rem',
                justifyContent: 'center',
                background: scanResult.status === 'success' ? 'rgba(16,185,129,0.08)' : scanResult.status === 'warning' ? 'rgba(245,158,11,0.08)' : 'rgba(244,63,94,0.08)',
                borderColor: scanResult.status === 'success' ? 'rgba(16,185,129,0.2)' : scanResult.status === 'warning' ? 'rgba(245,158,11,0.2)' : 'rgba(244,63,94,0.2)',
                color: scanResult.status === 'success' ? '#a7f3d0' : scanResult.status === 'warning' ? '#fde68a' : '#fecdd3'
              }}>
                <div className="alert-content" style={{ fontWeight: 600 }}>{scanResult.message}</div>
              </div>

              {scanResult.studentName && (
                <div style={{ marginTop: '1.5rem', borderTop: '1px solid var(--border-color)', paddingTop: '1.5rem', textAlign: 'left' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', fontSize: '0.9rem' }}>
                    <div>
                      <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block' }}>Student Name</span>
                      <strong>{scanResult.studentName}</strong>
                    </div>
                    <div>
                      <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block' }}>Academic Roll</span>
                      <strong>{scanResult.studentRoll}</strong>
                    </div>
                    <div>
                      <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block' }}>Academic Batch</span>
                      <strong>{scanResult.studentBatch}</strong>
                    </div>
                    <div>
                      <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block' }}>Event Role</span>
                      <strong>{scanResult.studentRole?.toUpperCase()}</strong>
                    </div>
                    {scanResult.foodPref && (
                      <div style={{ gridColumn: 'span 2', background: scanResult.foodPref === 'veg' ? 'rgba(16,185,129,0.15)' : 'rgba(244,63,94,0.15)', padding: '0.75rem', borderRadius: 'var(--radius-sm)', border: scanResult.foodPref === 'veg' ? '1px solid rgba(16,185,129,0.3)' : '1px solid rgba(244,63,94,0.3)', marginTop: '0.5rem', textAlign: 'center' }}>
                        <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', display: 'block', marginBottom: '0.15rem' }}>Food Counter Preference</span>
                        <strong style={{ fontSize: '1.1rem', color: scanResult.foodPref === 'veg' ? '#34d399' : '#f87171' }}>
                          {scanResult.foodPref === 'veg' ? 'VEGETARIAN MEAL' : 'NON-VEGETARIAN MEAL'}
                        </strong>
                      </div>
                    )}
                  </div>
                </div>
              )}

              <button onClick={handleNextScan} className="btn btn-primary" style={{ width: '100%', marginTop: '2rem' }}>
                Scan Next Ticket
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
