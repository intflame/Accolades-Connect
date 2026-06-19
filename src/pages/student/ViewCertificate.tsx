import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { QRCodeSVG } from 'qrcode.react';
import { Printer, ArrowLeft, ShieldAlert } from 'lucide-react';

interface CertificateDetails {
  id: number;
  certificate_code: string;
  issued_at: string;
  event_registrations: {
    assigned_role: string;
    student_id: string;
    profiles: {
      name: string;
      course: string;
      batches: {
        name: string;
      } | null;
    };
    events: {
      name: string;
      event_date: string;
      venue: string;
      certificate_template: string;
      certificate_template_type: string;
      certificate_theme: string;
      certificate_title: string;
      certificate_coordinator: string;
      certificate_hod: string;
      certificate_layout_config: string;
    };
  };
}

export const ViewCertificate: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { profile } = useAuth();
  const [cert, setCert] = useState<CertificateDetails | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const navigate = useNavigate();

  useEffect(() => {
    const fetchCertificate = async () => {
      if (!id || !profile) return;
      setLoading(true);

      try {
        const { data, error } = await supabase
          .from('certificates')
          .select(`
            id,
            certificate_code,
            issued_at,
            event_registrations!inner (
              assigned_role,
              student_id,
              profiles!inner (
                name,
                course,
                batches (
                  name
                )
              ),
              events!inner (
                name,
                event_date,
                venue,
                certificate_template,
                certificate_template_type,
                certificate_theme,
                certificate_title,
                certificate_coordinator,
                certificate_hod,
                certificate_layout_config
              )
            )
          `)
          .eq('id', Number(id))
          .single();

        if (error) throw error;
        const certData = data as any;

        // Security check
        if (profile.role === 'student' && certData.event_registrations.student_id !== profile.id) {
          setError('Unauthorized access to this certificate.');
          setCert(null);
          return;
        }

        setCert(certData);
      } catch (err) {
        console.error('Error fetching certificate details:', err);
        setError('Certificate not found.');
      } finally {
        setLoading(false);
      }
    };

    fetchCertificate();
  }, [id, profile]);

  const handlePrint = () => {
    window.print();
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading certificate layout...</p>
      </div>
    );
  }

  if (error || !cert) {
    return (
      <div className="container main-content">
        <button onClick={() => navigate('/student/certificates')} className="btn btn-secondary btn-sm" style={{ marginBottom: '1.5rem' }}>
          <ArrowLeft size={14} /> Back to Certificates
        </button>
        <div className="alert alert-danger">
          <ShieldAlert className="alert-icon" />
          <div className="alert-content">{error || 'Failed to load certificate.'}</div>
        </div>
      </div>
    );
  }

  const reg = cert.event_registrations;
  const student = reg.profiles;
  const event = reg.events;

  // Format role label
  let roleLabel = 'Participant';
  if (reg.assigned_role === 'volunteers') roleLabel = 'Volunteer';
  else if (reg.assigned_role === 'OC') roleLabel = 'Organizing Committee Member';
  else if (reg.assigned_role === 'CC') roleLabel = 'Co-coordinator';

  // Decode layout config
  let layoutConfig: any = {};
  try {
    layoutConfig = JSON.parse(event.certificate_layout_config || '{}');
  } catch (e) {
    console.error('Failed to parse layout config:', e);
  }

  // Setup default variables if not in config
  const titleEnabled = layoutConfig.title_enabled ?? 1;
  const titleTop = layoutConfig.title_top !== undefined ? parseFloat(layoutConfig.title_top) : 20;
  const titleLeft = parseFloat(layoutConfig.title_left ?? 50);
  const titleFontSize = parseFloat(layoutConfig.title_font_size ?? 2.2);
  const titleColor = layoutConfig.title_color ?? '#ffffff';
  const titleAlign = layoutConfig.title_align ?? 'center';

  const subtitleEnabled = layoutConfig.subtitle_enabled ?? 1;
  const subtitleText = layoutConfig.subtitle_text ?? 'This is proudly presented to';
  const subtitleTop = parseFloat(layoutConfig.subtitle_top ?? 32);
  const subtitleLeft = parseFloat(layoutConfig.subtitle_left ?? 50);
  const subtitleFontSize = parseFloat(layoutConfig.subtitle_font_size ?? 1.1);
  const subtitleColor = layoutConfig.subtitle_color ?? '#9ca3af';
  const subtitleAlign = layoutConfig.subtitle_align ?? 'center';

  const nameEnabled = layoutConfig.name_enabled ?? 1;
  const nameTop = parseFloat(layoutConfig.name_top ?? 45);
  const nameLeft = parseFloat(layoutConfig.name_left ?? 50);
  const nameFontSize = parseFloat(layoutConfig.name_font_size ?? 3.2);
  const nameColor = layoutConfig.name_color ?? '#F87B1B';
  const nameAlign = layoutConfig.name_align ?? 'center';

  const detailsEnabled = layoutConfig.details_enabled ?? 1;
  let detailsText = layoutConfig.details_text_template ?? "of {course} (Batch {batch}) for successfully participating as a {role} in the event {event_name}, organized by the Department on {event_date}.";
  detailsText = detailsText
    .replace('{name}', student.name)
    .replace('{course}', student.course)
    .replace('{batch}', student.batches?.name ?? 'N/A')
    .replace('{event_name}', event.name)
    .replace('{role}', roleLabel)
    .replace('{event_date}', new Date(event.event_date).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }));

  const detailsTop = parseFloat(layoutConfig.details_top ?? 58);
  const detailsLeft = parseFloat(layoutConfig.details_left ?? 50);
  const detailsFontSize = parseFloat(layoutConfig.details_font_size ?? 1.0);
  const detailsColor = layoutConfig.details_color ?? '#e5e7eb';
  const detailsAlign = layoutConfig.details_align ?? 'center';

  const qrEnabled = layoutConfig.qr_enabled ?? 1;
  const qrTop = parseFloat(layoutConfig.qr_top ?? 78);
  const qrLeft = parseFloat(layoutConfig.qr_left ?? 15);
  const qrSize = parseFloat(layoutConfig.qr_size ?? 12);

  const codeEnabled = layoutConfig.code_enabled ?? 1;
  const codeTop = parseFloat(layoutConfig.code_top ?? 80);
  const codeLeft = parseFloat(layoutConfig.code_left ?? 50);
  const codeColor = layoutConfig.code_color ?? '#9ca3af';
  const codeAlign = layoutConfig.code_align ?? 'center';

  const signaturesEnabled = layoutConfig.signatures_enabled ?? 1;
  const sigLeftText = layoutConfig.sig_left_text ?? event.certificate_coordinator;
  const sigLeftTop = parseFloat(layoutConfig.sig_left_top ?? 78);
  const sigLeftLeft = parseFloat(layoutConfig.sig_left_left ?? 80);
  const sigRightText = layoutConfig.sig_right_text ?? event.certificate_hod;
  const sigRightTop = parseFloat(layoutConfig.sig_right_top ?? 78);
  const sigRightRight = parseFloat(layoutConfig.sig_right_right ?? 15); // note right alignment logic
  const sigColor = layoutConfig.sig_color ?? '#e5e7eb';

  // Build verification URL for QR code
  const verificationUrl = `${window.location.origin}/verify/${cert.certificate_code}`;

  // Select Background styling based on theme
  const theme = event.certificate_theme ?? 'classic_navy';
  let themeStyle: React.CSSProperties;
  if (event.certificate_template) {
    themeStyle = {
      backgroundImage: `url(${event.certificate_template})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    };
  } else {
    if (theme === 'modern_minimalist') {
      themeStyle = {
        background: '#f8fafc',
        border: '15px double #334155',
        color: '#0f172a',
      };
    } else if (theme === 'creative_teal') {
      themeStyle = {
        background: '#042f2e',
        border: '10px solid #f97316',
        color: '#f3f4f6',
      };
    } else if (theme === 'elegant_emerald') {
      themeStyle = {
        background: '#022c22',
        border: '12px double #fbbf24',
        color: '#f3f4f6',
      };
    } else {
      // default: classic_navy
      themeStyle = {
        background: '#0e1627',
        border: '12px double #d97706',
        color: '#f3f4f6',
      };
    }
  }

  // Helper styles for alignment
  const getAlignmentStyle = (align: string, pctLeft: number) => {
    const styles: React.CSSProperties = {
      position: 'absolute',
      left: `${pctLeft}%`,
    };
    if (align === 'left') {
      styles.textAlign = 'left';
    } else if (align === 'right') {
      styles.textAlign = 'right';
      styles.transform = 'translateX(-100%)';
    } else {
      styles.textAlign = 'center';
      styles.transform = 'translateX(-50%)';
    }
    return styles;
  };

  return (
    <div className="container main-content">
      {/* Hide controls during printing */}
      <div className="no-print" style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1.5rem' }}>
        <button onClick={() => navigate(-1)} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <ArrowLeft size={14} /> Back
        </button>
        <button onClick={handlePrint} className="btn btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
          <Printer size={16} /> Print Certificate
        </button>
      </div>

      {/* Styled A4 certificate container with absolute layout */}
      <div
        className="certificate-print-wrapper"
        style={{
          width: '100%',
          maxWidth: '960px',
          margin: '0 auto',
          boxShadow: '0 10px 30px rgba(0,0,0,0.5)',
          borderRadius: 'var(--radius-sm)',
          overflow: 'hidden',
        }}
      >
        <div
          id="certificate-canvas"
          style={{
            width: '100%',
            aspectRatio: '297/210',
            position: 'relative',
            fontFamily: "'Inter', sans-serif",
            overflow: 'hidden',
            boxSizing: 'border-box',
            ...themeStyle,
          }}
        >
          {/* 1. Certificate Title */}
          {titleEnabled === 1 && (
            <div
              style={{
                ...getAlignmentStyle(titleAlign, titleLeft),
                top: `${titleTop}%`,
                fontSize: `calc(${titleFontSize} * 1vw)`,
                fontWeight: 'bold',
                color: event.certificate_template ? titleColor : (theme === 'modern_minimalist' ? '#0f172a' : titleColor),
                width: '80%',
                lineHeight: 1.2,
              }}
            >
              {event.certificate_title || 'Certificate of Activity'}
            </div>
          )}

          {/* 2. Subtitle */}
          {subtitleEnabled === 1 && (
            <div
              style={{
                ...getAlignmentStyle(subtitleAlign, subtitleLeft),
                top: `${subtitleTop}%`,
                fontSize: `calc(${subtitleFontSize} * 1vw)`,
                color: event.certificate_template ? subtitleColor : (theme === 'modern_minimalist' ? '#475569' : subtitleColor),
                width: '80%',
              }}
            >
              {subtitleText}
            </div>
          )}

          {/* 3. Student Name */}
          {nameEnabled === 1 && (
            <div
              style={{
                ...getAlignmentStyle(nameAlign, nameLeft),
                top: `${nameTop}%`,
                fontSize: `calc(${nameFontSize} * 1vw)`,
                fontFamily: "'Outfit', 'Inter', sans-serif",
                fontWeight: 800,
                color: event.certificate_template ? nameColor : (theme === 'modern_minimalist' ? '#1e3a8a' : nameColor),
                width: '80%',
                letterSpacing: '-0.02em',
              }}
            >
              {student.name}
            </div>
          )}

          {/* 4. Description/Details */}
          {detailsEnabled === 1 && (
            <div
              style={{
                ...getAlignmentStyle(detailsAlign, detailsLeft),
                top: `${detailsTop}%`,
                fontSize: `calc(${detailsFontSize} * 1vw)`,
                color: event.certificate_template ? detailsColor : (theme === 'modern_minimalist' ? '#334155' : detailsColor),
                width: '80%',
                lineHeight: 1.5,
              }}
            >
              {detailsText}
            </div>
          )}

          {/* 5. Verification QR Code */}
          {qrEnabled === 1 && (
            <div
              style={{
                position: 'absolute',
                left: `${qrLeft}%`,
                top: `${qrTop}%`,
                transform: 'translateY(-20%)',
                background: '#ffffff',
                padding: '0.4vw',
                borderRadius: '4px',
                display: 'inline-block',
                boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
              }}
            >
              <QRCodeSVG
                value={verificationUrl}
                style={{ width: `calc(${qrSize} * 1vw)`, height: `calc(${qrSize} * 1vw)` }}
              />
            </div>
          )}

          {/* 6. Verification Code text details */}
          {codeEnabled === 1 && (
            <div
              style={{
                ...getAlignmentStyle(codeAlign, codeLeft),
                top: `${codeTop}%`,
                color: event.certificate_template ? codeColor : (theme === 'modern_minimalist' ? '#64748b' : codeColor),
                fontSize: '0.75vw',
                lineHeight: 1.4,
              }}
            >
              <div style={{ textTransform: 'uppercase', fontSize: '0.6vw', letterSpacing: '0.05em' }}>Verification Code</div>
              <div style={{ fontFamily: 'monospace', fontWeight: 'bold', fontSize: '0.9vw' }}>{cert.certificate_code}</div>
              <div style={{ fontSize: '0.6vw' }}>Issued: {new Date(cert.issued_at).toLocaleDateString()}</div>
            </div>
          )}

          {/* 7. Signatures */}
          {signaturesEnabled === 1 && (
            <>
              {/* Coordinator signature */}
              <div
                style={{
                  position: 'absolute',
                  left: `${sigLeftLeft}%`,
                  top: `${sigLeftTop}%`,
                  width: '18%',
                  textAlign: 'center',
                  borderTop: `1px solid ${event.certificate_template ? sigColor : (theme === 'modern_minimalist' ? '#475569' : sigColor)}`,
                  paddingTop: '0.5vw',
                  fontSize: '0.8vw',
                  fontWeight: 600,
                  color: event.certificate_template ? sigColor : (theme === 'modern_minimalist' ? '#334155' : sigColor),
                }}
              >
                {sigLeftText}
              </div>

              {/* HOD signature */}
              <div
                style={{
                  position: 'absolute',
                  right: `${sigRightRight}%`,
                  top: `${sigRightTop}%`,
                  width: '18%',
                  textAlign: 'center',
                  borderTop: `1px solid ${event.certificate_template ? sigColor : (theme === 'modern_minimalist' ? '#475569' : sigColor)}`,
                  paddingTop: '0.5vw',
                  fontSize: '0.8vw',
                  fontWeight: 600,
                  color: event.certificate_template ? sigColor : (theme === 'modern_minimalist' ? '#334155' : sigColor),
                }}
              >
                {sigRightText}
              </div>
            </>
          )}
        </div>
      </div>

      {/* Add Print Styles dynamically */}
      <style>{`
        @media print {
          body {
            background: #ffffff !important;
            color: #000000 !important;
            margin: 0 !important;
            padding: 0 !important;
          }
          .navbar, .no-print, .footer {
            display: none !important;
          }
          .main-content {
            padding: 0 !important;
            margin: 0 !important;
          }
          .certificate-print-wrapper {
            box-shadow: none !important;
            border-radius: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            height: 100vh !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            page-break-inside: avoid !important;
          }
          #certificate-canvas {
            width: 297mm !important;
            height: 210mm !important;
            border: none !important;
          }
        }
      `}</style>
    </div>
  );
};
