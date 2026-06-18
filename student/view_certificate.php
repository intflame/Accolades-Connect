<?php
/**
 * View & Print Student MAR Certificate (Server-Side FPDF)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control - Anyone logged in can view (checks ownership for students below)
if (!is_logged_in()) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$cert_id = intval($_GET['id'] ?? 0);
if ($cert_id <= 0) {
    die("Invalid Certificate ID.");
}

// Fetch certificate details
try {
    $stmt = $conn->prepare("
        SELECT c.id as certificate_id, c.certificate_code, c.issued_at,
               r.id as reg_id, r.assigned_role, r.student_id,
               s.name as student_name, s.course, b.name as batch_name, s.class_roll, s.university_roll,
               e.id as event_id, e.name as event_name, e.event_date, e.venue, e.certificate_template, e.certificate_template_type,
               e.certificate_theme, e.certificate_title, e.certificate_coordinator, e.certificate_hod,
               e.certificate_layout_config, e.canva_template_link
        FROM certificates c
        JOIN event_registrations r ON c.registration_id = r.id
        JOIN students s ON r.student_id = s.id
        LEFT JOIN batches b ON s.batch_id = b.id
        JOIN events e ON r.event_id = e.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cert_id]);
    $cert = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to fetch certificate: " . $e->getMessage());
    $cert = false;
}

if (!$cert) {
    die("Certificate not found.");
}

// Ownership check for students
if ($_SESSION['user_role'] === 'student') {
    $student = get_current_student();
    if (!$student || intval($cert['student_id']) !== intval($student['id'])) {
        die("Unauthorized access to this certificate.");
    }
}

// Format Role Label
$role_label = 'Participant';
if ($cert['assigned_role'] === 'volunteers') {
    $role_label = 'Volunteer';
} elseif ($cert['assigned_role'] === 'OC') {
    $role_label = 'Organizing Committee Member';
} elseif ($cert['assigned_role'] === 'CC') {
    $role_label = 'Co-coordinator';
}

$template_path = '';
if (!empty($cert['certificate_template'])) {
    $path = UPLOAD_PATH . '/certificate_templates/' . $cert['certificate_template'];
    if (file_exists($path)) {
        $template_path = $path;
    }
}

// Decode custom template layout configurations
$layout_config = json_decode($cert['certificate_layout_config'] ?? '', true) ?: [];

// Title & Subtitle Settings
$title_enabled = intval($layout_config['title_enabled'] ?? 1);
$title_top = isset($layout_config['title_top']) ? floatval($layout_config['title_top']) : null;
$title_left = floatval($layout_config['title_left'] ?? 50);
$title_font_size = floatval($layout_config['title_font_size'] ?? 2.2);
$title_color = $layout_config['title_color'] ?? '#b45309';
$title_font = $layout_config['title_font'] ?? 'Helvetica';
$title_align = $layout_config['title_align'] ?? 'center';

$subtitle_enabled = intval($layout_config['subtitle_enabled'] ?? 1);
$subtitle_text = $layout_config['subtitle_text'] ?? 'This is proudly presented to';
$subtitle_top = floatval($layout_config['subtitle_top'] ?? 30);
$subtitle_left = floatval($layout_config['subtitle_left'] ?? 50);
$subtitle_font_size = floatval($layout_config['subtitle_font_size'] ?? 1.1);
$subtitle_color = $layout_config['subtitle_color'] ?? '#475569';
$subtitle_font = $layout_config['subtitle_font'] ?? 'Helvetica';
$subtitle_align = $layout_config['subtitle_align'] ?? 'center';

// Name Settings
$name_enabled = intval($layout_config['name_enabled'] ?? 1);
$name_top = floatval($layout_config['name_top'] ?? 48);
$name_left = floatval($layout_config['name_left'] ?? 50);
$name_font_size = floatval($layout_config['name_font_size'] ?? 3.2);
$name_color = $layout_config['name_color'] ?? '#b45309';
$name_font = $layout_config['name_font'] ?? 'GreatVibes';
$name_align = $layout_config['name_align'] ?? 'center';

// Description Settings
$details_enabled = intval($layout_config['details_enabled'] ?? 1);
$details_text_template = $layout_config['details_text_template'] ?? "of {course} (Batch {batch}) for successfully participating as a {role} in the event {event_name}, organized by the Department on {event_date}.";
$details_top = floatval($layout_config['details_top'] ?? 61);
$details_left = floatval($layout_config['details_left'] ?? 50);
$details_font_size = floatval($layout_config['details_font_size'] ?? 0.95);
$details_color = $layout_config['details_color'] ?? '#475569';
$details_font = $layout_config['details_font'] ?? 'Helvetica';
$details_align = $layout_config['details_align'] ?? 'center';

// QR Code Settings
$qr_enabled = intval($layout_config['qr_enabled'] ?? 0);
$qr_top = floatval($layout_config['qr_top'] ?? 85);
$qr_left = floatval($layout_config['qr_left'] ?? 15);
$qr_size = floatval($layout_config['qr_size'] ?? 20);

// Code Settings
$code_enabled = ($title_top === null) ? intval($layout_config['code_enabled'] ?? 1) : 0;
$code_top = floatval($layout_config['code_top'] ?? 88);
$code_left = floatval($layout_config['code_left'] ?? 50);
$code_color = $layout_config['code_color'] ?? '#0f172a';
$code_align = $layout_config['code_align'] ?? 'center';

// Signatures Settings
$signatures_enabled = intval($layout_config['signatures_enabled'] ?? 0);
$sig_left_text = $layout_config['sig_left_text'] ?? ($cert['certificate_coordinator'] ?? 'Event Coordinator');
$sig_left_top = floatval($layout_config['sig_left_top'] ?? 80);
$sig_left_left = floatval($layout_config['sig_left_left'] ?? 15);
$sig_right_text = $layout_config['sig_right_text'] ?? ($cert['certificate_hod'] ?? 'Head of Department');
$sig_right_top = floatval($layout_config['sig_right_top'] ?? 80);
$sig_right_right = floatval($layout_config['sig_right_right'] ?? 15);
$sig_color = $layout_config['sig_color'] ?? '#475569';
$sig_font = $layout_config['sig_font'] ?? 'Helvetica';

// Load FPDF & FPDI
define('FPDF_FONTPATH', __DIR__ . '/../includes/fpdf/font/');
require_once __DIR__ . '/../includes/fpdf/fpdf.php';
require_once __DIR__ . '/../includes/fpdi/autoload.php';
use \setasign\Fpdi\Fpdi;

// Hex to RGB helper
function hex2rgb($hex) {
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return [$r, $g, $b];
}

// Create PDF
$pdf = new Fpdi('L', 'mm', 'A4');
$pdf->SetTitle('Certificate - ' . $cert['student_name']);
$pdf->SetAuthor('Accolades Connect');
$pdf->AddPage();

// 1. Draw Background
if (!empty($template_path)) {
    $ext = strtolower(pathinfo($template_path, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        // Import PDF vector background using FPDI
        $pdf->setSourceFile($template_path);
        $tplIdx = $pdf->importPage(1);
        $pdf->useImportedPage($tplIdx, 0, 0, 297, 210);
    } else {
        // Draw Canva/Uploaded background image full page
        $pdf->Image($template_path, 0, 0, 297, 210);
    }
} else {
    // Draw Default Theme Background
    $theme = $cert['certificate_theme'] ?? 'classic_navy';
    
    if ($theme === 'modern_minimalist') {
        $pdf->SetFillColor(248, 250, 252); // #f8fafc
        $pdf->Rect(0, 0, 297, 210, 'F');
        
        $pdf->SetDrawColor(51, 65, 85); // #334155
        $pdf->SetLineWidth(1.5);
        $pdf->Rect(10, 10, 277, 190, 'D');
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(12, 12, 273, 186, 'D');
    } elseif ($theme === 'creative_teal') {
        $pdf->SetFillColor(4, 47, 46); // #042f2e
        $pdf->Rect(0, 0, 297, 210, 'F');
        
        $pdf->SetDrawColor(249, 115, 22); // #f97316
        $pdf->SetLineWidth(2);
        $pdf->Rect(10, 10, 277, 190, 'D');
    } elseif ($theme === 'elegant_emerald') {
        $pdf->SetFillColor(2, 44, 34); // #022c22
        $pdf->Rect(0, 0, 297, 210, 'F');
        
        $pdf->SetDrawColor(251, 191, 36); // #fbbf24
        $pdf->SetLineWidth(2);
        $pdf->Rect(10, 10, 277, 190, 'D');
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(12, 12, 273, 186, 'D');
    } else {
        // Default: classic_navy
        $pdf->SetFillColor(14, 22, 39); // #0e1627
        $pdf->Rect(0, 0, 297, 210, 'F');
        
        $pdf->SetDrawColor(217, 119, 6); // #d97706
        $pdf->SetLineWidth(2.5);
        $pdf->Rect(10, 10, 277, 190, 'D');
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(12, 12, 273, 186, 'D');
    }
}

// 2. Determine Font Colors
$name_rgb = hex2rgb($name_color);
$details_rgb = hex2rgb($details_color);
$code_rgb = hex2rgb($code_color);

// Register GreatVibes font early in case it's used elsewhere
$pdf->AddFont('GreatVibes-Regular', '', 'GreatVibes-Regular.php');

// Helper to resolve font name to FPDF family
$resolve_font = function($font_name) {
    if ($font_name === 'Times') {
        return 'Times';
    } elseif ($font_name === 'Courier') {
        return 'Courier';
    } elseif ($font_name === 'GreatVibes') {
        return 'GreatVibes-Regular';
    }
    return 'Helvetica';
};

// Helper to calculate FPDF X and Cell Alignment
$get_x_and_align = function($left_percent, $align_mode, $cell_width = 200) {
    $page_width = 297;
    $left_coord = ($left_percent / 100) * $page_width;
    
    if ($align_mode === 'left') {
        $x = $left_coord;
        $align = 'L';
    } elseif ($align_mode === 'right') {
        $x = $left_coord - $cell_width;
        $align = 'R';
    } else { // center
        $x = $left_coord - ($cell_width / 2);
        $align = 'C';
    }
    return [$x, $align];
};

// 3. Draw Header & Sub Header
if ($title_top !== null) {
    // Draw Customized Header
    if ($title_enabled) {
        $title_pt = $title_font_size * 12;
        $title_font_family = $resolve_font($title_font);
        $title_style = ($title_font_family !== 'GreatVibes-Regular') ? 'B' : '';
        $pdf->SetFont($title_font_family, $title_style, $title_pt);
        $title_rgb = hex2rgb($title_color);
        $pdf->SetTextColor($title_rgb[0], $title_rgb[1], $title_rgb[2]);
        $title_y = ($title_top / 100) * 210;
        
        list($title_x, $t_align) = $get_x_and_align($title_left, $title_align, 200);
        $pdf->SetXY($title_x, $title_y - ($title_pt * 0.175));
        $pdf->Cell(200, $title_pt * 0.35, $cert['certificate_title'] ?? 'Certificate of Activity', 0, 1, $t_align);
    }

    // Draw Customized Sub Header
    if ($subtitle_enabled) {
        $subtitle_pt = $subtitle_font_size * 12;
        $subtitle_font_family = $resolve_font($subtitle_font);
        $pdf->SetFont($subtitle_font_family, '', $subtitle_pt);
        $subtitle_rgb = hex2rgb($subtitle_color);
        $pdf->SetTextColor($subtitle_rgb[0], $subtitle_rgb[1], $subtitle_rgb[2]);
        $subtitle_y = ($subtitle_top / 100) * 210;
        
        list($subtitle_x, $s_align) = $get_x_and_align($subtitle_left, $subtitle_align, 200);
        $pdf->SetXY($subtitle_x, $subtitle_y - ($subtitle_pt * 0.175));
        $pdf->Cell(200, $subtitle_pt * 0.35, $subtitle_text, 0, 1, $s_align);
    }
} else {
    // Draw original default titles if no custom layout is configured
    $draw_defaults = empty($template_path) || (($cert['certificate_template_type'] ?? 'border_only') !== 'full_design');
    if ($draw_defaults) {
        // Header
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor($details_rgb[0], $details_rgb[1], $details_rgb[2]);
        $pdf->SetXY(0, 25);
        $pdf->Cell(297, 8, 'DEPARTMENT OF COMPUTER APPLICATION', 0, 1, 'C');
        
        // Title
        $pdf->SetFont('Helvetica', 'B', 28);
        $pdf->SetTextColor($name_rgb[0], $name_rgb[1], $name_rgb[2]);
        $pdf->SetY(40);
        $pdf->Cell(297, 12, strtoupper($cert['certificate_title'] ?? 'Certificate of Activity'), 0, 1, 'C');
        
        // Statement
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->SetTextColor($details_rgb[0], $details_rgb[1], $details_rgb[2]);
        $pdf->SetY(60);
        $pdf->Cell(297, 8, 'This is proudly presented to', 0, 1, 'C');
    }
}

// 4. Draw Student Name
if ($title_top === null || $name_enabled) {
    $name_font_family = $resolve_font($name_font);
    $name_style = ($name_font_family !== 'GreatVibes-Regular') ? 'B' : '';
    $pdf->SetFont($name_font_family, $name_style, $name_font_size * 12);
    $pdf->SetTextColor($name_rgb[0], $name_rgb[1], $name_rgb[2]);

    $name_y = ($name_top / 100) * 210;
    $name_pt = $name_font_size * 12;
    
    list($name_x, $n_align) = $get_x_and_align($name_left, $name_align, 200);
    $pdf->SetXY($name_x, $name_y - ($name_pt * 0.175));
    $pdf->Cell(200, $name_pt * 0.35, $cert['student_name'], 0, 1, $n_align);
}

// 5. Draw Details / Description
if ($title_top === null || $details_enabled) {
    $details_font_family = $resolve_font($details_font);
    $pdf->SetFont($details_font_family, '', $details_font_size * 12);
    $pdf->SetTextColor($details_rgb[0], $details_rgb[1], $details_rgb[2]);

    $details_text = $details_text_template;
    $details_text = str_replace('{name}', $cert['student_name'], $details_text);
    $details_text = str_replace('{course}', $cert['course'], $details_text);
    $details_text = str_replace('{batch}', $cert['batch_name'] ?? 'N/A', $details_text);
    $details_text = str_replace('{event_name}', $cert['event_name'], $details_text);
    $details_text = str_replace('{role}', $role_label, $details_text);
    $details_text = str_replace('{event_date}', date('F d, Y', strtotime($cert['event_date'])), $details_text);

    $details_y = ($details_top / 100) * 210;
    $line_h = max(4.0, ($details_font_size * 12) * 0.45); // line height in mm
    
    list($details_x, $d_align) = $get_x_and_align($details_left, $details_align, 200);
    $pdf->SetXY($details_x, $details_y - ($line_h * 1));
    $pdf->MultiCell(200, $line_h, $details_text, 0, $d_align);
}

// 6. Draw QR Code if enabled
$qr_drawn = false;
$temp_qr_path = UPLOAD_PATH . '/certificate_templates/temp_qr_' . $cert['certificate_code'] . '.png';
if ($qr_enabled) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verification_url = $protocol . $host . BASE_URL . "verify.php?code=" . $cert['certificate_code'];
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verification_url);
    
    // Attempt download using file_get_contents
    $qr_img_data = @file_get_contents($qr_url);
    if (!$qr_img_data && function_exists('curl_init')) {
        $ch = curl_init($qr_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $qr_img_data = curl_exec($ch);
        curl_close($ch);
    }
    
    if ($qr_img_data) {
        if (file_put_contents($temp_qr_path, $qr_img_data)) {
            $qr_drawn = true;
        }
    }
    
    if ($qr_drawn) {
        $qr_x_center = ($qr_left / 100) * 297;
        $qr_y_center = ($qr_top / 100) * 210;
        $qr_x = $qr_x_center - ($qr_size / 2);
        $qr_y = $qr_y_center - ($qr_size / 2);
        $pdf->Image($temp_qr_path, $qr_x, $qr_y, $qr_size, $qr_size);
    }
}

// 7. Draw Verification Code Block
if ($title_top === null || $code_enabled) {
    $code_x = ($code_left / 100) * 297;
    $code_y = ($code_top / 100) * 210;
    
    $c_align = 'C';
    if ($code_align === 'left') $c_align = 'L';
    if ($code_align === 'right') $c_align = 'R';
    
    $block_width = 80;
    if ($code_align === 'left') {
        $block_x = $code_x;
    } elseif ($code_align === 'right') {
        $block_x = $code_x - $block_width;
    } else {
        $block_x = $code_x - ($block_width / 2);
    }

    $pdf->SetXY($block_x, $code_y - 6);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor($code_rgb[0], $code_rgb[1], $code_rgb[2]);
    $pdf->Cell($block_width, 4, 'Certificate Verification Code', 0, 1, $c_align);

    $pdf->SetX($block_x);
    $pdf->SetFont('Courier', 'B', 10);
    $pdf->Cell($block_width, 5, $cert['certificate_code'], 0, 1, $c_align);

    $pdf->SetX($block_x);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->Cell($block_width, 4, 'Issued on: ' . date('M d, Y', strtotime($cert['issued_at'])), 0, 1, $c_align);
}

// 8. Draw Signatures if enabled
if ($signatures_enabled) {
    $sig_rgb = hex2rgb($sig_color);
    $pdf->SetDrawColor($sig_rgb[0], $sig_rgb[1], $sig_rgb[2]);
    $pdf->SetTextColor($sig_rgb[0], $sig_rgb[1], $sig_rgb[2]);
    $pdf->SetLineWidth(0.3);
    
    $sig_font_family = $resolve_font($sig_font);
    
    // Left Signature
    $sig_l_x = ($sig_left_left / 100) * 297;
    $sig_l_y = ($sig_left_top / 100) * 210;
    
    $pdf->Line($sig_l_x - 25, $sig_l_y - 2, $sig_l_x + 25, $sig_l_y - 2);
    $pdf->SetXY($sig_l_x - 25, $sig_l_y);
    $pdf->SetFont($sig_font_family, 'B', 8);
    $pdf->Cell(50, 4, $sig_left_text, 0, 0, 'C');
    
    // Right Signature
    $sig_r_x = 297 - (($sig_right_right / 100) * 297);
    $sig_r_y = ($sig_right_top / 100) * 210;
    
    $pdf->Line($sig_r_x - 25, $sig_r_y - 2, $sig_r_x + 25, $sig_r_y - 2);
    $pdf->SetXY($sig_r_x - 25, $sig_r_y);
    $pdf->SetFont($sig_font_family, 'B', 8);
    $pdf->Cell(50, 4, $sig_right_text, 0, 0, 'C');
}

// Output PDF inline directly to browser
$pdf_filename = "Certificate-" . $cert['certificate_code'] . ".pdf";
$pdf->Output('I', $pdf_filename);

// Clean up temporary QR code image if it was drawn
if ($qr_drawn && file_exists($temp_qr_path)) {
    @unlink($temp_qr_path);
}

exit();
