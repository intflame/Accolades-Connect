const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const { createClient } = require('@supabase/supabase-js');

// 1. Parse .env.local for credentials
const envPath = path.join(__dirname, '../.env.local');
if (!fs.existsSync(envPath)) {
  console.error('Error: .env.local file not found at project root.');
  process.exit(1);
}

const envFile = fs.readFileSync(envPath, 'utf-8');
const env = {};
envFile.split('\n').forEach((line) => {
  const parts = line.split('=');
  if (parts.length >= 2) {
    const key = parts[0].trim();
    const value = parts.slice(1).join('=').trim().replace(/^["']|["']$/g, '');
    env[key] = value;
  }
});

const supabaseUrl = env['VITE_SUPABASE_URL'];
const supabaseServiceKey = env['SUPABASE_SERVICE_ROLE_KEY'];

if (!supabaseUrl || !supabaseServiceKey) {
  console.error('Error: VITE_SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY is missing in .env.local.');
  process.exit(1);
}

// 2. Initialize Supabase Client with Admin Service Key
const supabase = createClient(supabaseUrl, supabaseServiceKey, {
  auth: {
    autoRefreshToken: false,
    persistSession: false
  }
});

async function runMigration() {
  console.log('🚀 Starting Data Migration: MySQL ➜ Supabase PostgreSQL...');

  // Connect to local MySQL
  const connection = await mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'dept_event_attendance'
  });

  console.log('✅ Connected to local MySQL database.');

  try {
    // --- 1. Batches ---
    console.log('\n📦 Migrating: Batches...');
    const [batches] = await connection.execute('SELECT * FROM batches');
    console.log(`Found ${batches.length} batches in MySQL.`);
    for (const b of batches) {
      const { error } = await supabase.from('batches').upsert({
        id: b.id,
        name: b.name,
        created_at: b.created_at
      });
      if (error) console.error(`❌ Failed to migrate batch "${b.name}":`, error);
    }
    console.log('✅ Batches migrated.');

    // --- 2. Users & Students ---
    console.log('\n👤 Migrating: Users & Students...');
    // We join users and students to migrate both simultaneously
    const [users] = await connection.execute(`
      SELECT u.id as user_id, u.email, u.password as password_hash, u.role, u.status, u.created_at as user_created_at,
             s.id as student_id, s.name, s.course, s.batch_id, s.class_roll, s.university_roll, s.contact_number, s.whatsapp_number, s.food_preference, s.profile_photo
      FROM users u
      LEFT JOIN students s ON u.id = s.user_id
    `);
    console.log(`Found ${users.length} users in MySQL.`);

    // Fetch existing users in Supabase to avoid double creation
    const { data: { users: existingUsers }, error: listError } = await supabase.auth.admin.listUsers({
      perPage: 1000
    });
    if (listError) throw listError;

    const userIdToUuidMap = {};
    const studentIdToUuidMap = {};

    for (const u of users) {
      let uuid = '';
      const existing = existingUsers.find((eu) => eu.email?.toLowerCase() === u.email.toLowerCase());

      if (existing) {
        console.log(`ℹ️ User ${u.email} already exists in Supabase. Mapping existing UUID.`);
        uuid = existing.id;
      } else {
        // Create user in Supabase Auth using password_hash (bcrypt)
        const { data: authUser, error: authError } = await supabase.auth.admin.createUser({
          email: u.email,
          password_hash: u.password_hash,
          email_confirm: true,
          user_metadata: {
            role: u.role,
            name: u.name || (u.role === 'admin' ? 'Administrator' : 'Scanner'),
            course: u.course || null,
            batch_id: u.batch_id || null,
            class_roll: u.class_roll || null,
            university_roll: u.university_roll || null,
            contact_number: u.contact_number || null,
            whatsapp_number: u.whatsapp_number || null,
            food_preference: u.food_preference || 'veg',
            profile_photo: u.profile_photo || null
          }
        });

        if (authError) {
          console.error(`❌ Failed to create auth user ${u.email}:`, authError.message);
          continue;
        }
        uuid = authUser.user.id;
        console.log(`➕ Created auth user for ${u.email}`);
      }

      // Populate mappings
      userIdToUuidMap[u.user_id] = uuid;
      if (u.student_id) {
        studentIdToUuidMap[u.student_id] = uuid;
      }

      // Upsert profile record explicitly to enforce correct status and details
      const { error: profileError } = await supabase.from('profiles').upsert({
        id: uuid,
        email: u.email,
        role: u.role,
        status: u.status === 'pending_otp' ? 'pending_approval' : u.status,
        name: u.name || (u.role === 'admin' ? 'Administrator' : 'Scanner'),
        course: u.course || null,
        batch_id: u.batch_id || null,
        class_roll: u.class_roll || null,
        university_roll: u.university_roll || null,
        contact_number: u.contact_number || null,
        whatsapp_number: u.whatsapp_number || null,
        food_preference: u.food_preference || 'veg',
        profile_photo: u.profile_photo || null,
        created_at: u.user_created_at
      });

      if (profileError) {
        console.error(`❌ Failed to upsert profile for ${u.email}:`, profileError.message);
      }
    }
    console.log('✅ Users & Profiles migrated.');

    // --- 3. Events ---
    console.log('\n📅 Migrating: Events...');
    const [events] = await connection.execute('SELECT * FROM events');
    console.log(`Found ${events.length} events in MySQL.`);
    for (const e of events) {
      const { error } = await supabase.from('events').upsert({
        id: e.id,
        name: e.name,
        description: e.description,
        banner_image: e.banner_image,
        event_date: e.event_date,
        venue: e.venue,
        registration_fee: e.registration_fee,
        registration_deadline: e.registration_deadline,
        scan_start_time: e.scan_start_time,
        scan_end_time: e.scan_end_time,
        upi_payment_enabled: Boolean(e.upi_payment_enabled),
        upi_id: e.upi_id,
        upi_qr_image: e.upi_qr_image,
        cash_payment_enabled: Boolean(e.cash_payment_enabled),
        food_enabled: Boolean(e.food_enabled),
        certificate_template: e.certificate_template,
        certificate_template_type: e.certificate_template_type,
        certificate_theme: e.certificate_theme,
        certificate_title: e.certificate_title,
        certificate_coordinator: e.certificate_coordinator,
        certificate_hod: e.certificate_hod,
        certificate_layout_config: e.certificate_layout_config,
        canva_template_link: e.canva_template_link,
        certifier_campaign_id: e.certifier_campaign_id,
        status: e.status,
        created_at: e.created_at
      });
      if (error) console.error(`❌ Failed to migrate event "${e.name}":`, error.message);
    }
    console.log('✅ Events migrated.');

    // --- 4. Event Registrations ---
    console.log('\n📝 Migrating: Event Registrations...');
    const [registrations] = await connection.execute('SELECT * FROM event_registrations');
    console.log(`Found ${registrations.length} registrations in MySQL.`);
    for (const r of registrations) {
      const studentUuid = studentIdToUuidMap[r.student_id];
      if (!studentUuid) {
        console.warn(`⚠️ Warning: Registration ID ${r.id} references student ID ${r.student_id} who has no active user uuid. Skipping.`);
        continue;
      }

      const { error } = await supabase.from('event_registrations').upsert({
        id: r.id,
        event_id: r.event_id,
        student_id: studentUuid,
        status: r.status,
        payment_method: r.payment_method,
        assigned_role: r.assigned_role,
        applied_role: r.applied_role,
        role_status: r.role_status,
        created_at: r.created_at
      });
      if (error) console.error(`❌ Failed to migrate registration ID ${r.id}:`, error.message);
    }
    console.log('✅ Registrations migrated.');

    // --- 5. Payments ---
    console.log('\n💳 Migrating: Payments...');
    const [payments] = await connection.execute('SELECT * FROM payments');
    console.log(`Found ${payments.length} payments in MySQL.`);
    for (const p of payments) {
      const adminUuid = p.verified_by ? userIdToUuidMap[p.verified_by] : null;

      const { error } = await supabase.from('payments').upsert({
        id: p.id,
        registration_id: p.registration_id,
        payment_method: p.payment_method,
        proof_image: p.proof_image,
        status: p.status,
        verified_by: adminUuid,
        verification_time: p.verification_time,
        rejection_reason: p.rejection_reason,
        created_at: p.created_at
      });
      if (error) console.error(`❌ Failed to migrate payment ID ${p.id}:`, error.message);
    }
    console.log('✅ Payments migrated.');

    // --- 6. QR Tokens ---
    console.log('\n🎟️ Migrating: QR Tokens...');
    const [tokens] = await connection.execute('SELECT * FROM qr_tokens');
    console.log(`Found ${tokens.length} QR tokens in MySQL.`);
    for (const t of tokens) {
      const { error } = await supabase.from('qr_tokens').upsert({
        id: t.id,
        registration_id: t.registration_id,
        token: t.token,
        status: t.status,
        created_at: t.created_at
      });
      if (error) console.error(`❌ Failed to migrate QR Token ID ${t.id}:`, error.message);
    }
    console.log('✅ QR Tokens migrated.');

    // --- 7. Attendance Scans ---
    console.log('\n⏱️ Migrating: Attendance Scans...');
    const [scans] = await connection.execute('SELECT * FROM attendance_scans');
    console.log(`Found ${scans.length} scans in MySQL.`);
    for (const s of scans) {
      const scannerUuid = userIdToUuidMap[s.scanned_by];
      if (!scannerUuid) {
        console.warn(`⚠️ Warning: Scan ID ${s.id} references scanner ID ${s.scanned_by} who has no active user uuid. Skipping.`);
        continue;
      }

      const { error } = await supabase.from('attendance_scans').upsert({
        id: s.id,
        qr_token_id: s.qr_token_id,
        scan_type: s.scan_type,
        scanned_by: scannerUuid,
        scanned_at: s.scanned_at
      });
      if (error) console.error(`❌ Failed to migrate scan ID ${s.id}:`, error.message);
    }
    console.log('✅ Attendance Scans migrated.');

    // --- 8. Activity Logs (Skipped) ---
    console.log('\n📋 Migrating: Activity Logs... (Skipped by request)');

    // --- 9. Certificates ---
    console.log('\n📜 Migrating: Certificates...');
    const [certs] = await connection.execute('SELECT * FROM certificates');
    console.log(`Found ${certs.length} certificates in MySQL.`);
    for (const c of certs) {
      const issuerUuid = c.issued_by ? userIdToUuidMap[c.issued_by] : null;

      const { error } = await supabase.from('certificates').upsert({
        id: c.id,
        registration_id: c.registration_id,
        certificate_code: c.certificate_code,
        issued_by: issuerUuid,
        issued_at: c.issued_at
      });
      if (error) console.error(`❌ Failed to migrate certificate ID ${c.id}:`, error.message);
    }
    console.log('✅ Certificates migrated.');

    console.log('\n⭐ Database migration successful!');
    console.log('\n⚠️ ATTENTION: You MUST reset your PostgreSQL primary key serial sequences in Supabase SQL editor.');
    console.log('Please copy-paste and RUN the following SQL script in your Supabase SQL editor:');
    console.log(`
--------------------------------------------------------------------------------------
SELECT setval('batches_id_seq', coalesce((SELECT max(id)+1 FROM batches), 1), false);
SELECT setval('events_id_seq', coalesce((SELECT max(id)+1 FROM events), 1), false);
SELECT setval('event_registrations_id_seq', coalesce((SELECT max(id)+1 FROM event_registrations), 1), false);
SELECT setval('payments_id_seq', coalesce((SELECT max(id)+1 FROM payments), 1), false);
SELECT setval('qr_tokens_id_seq', coalesce((SELECT max(id)+1 FROM qr_tokens), 1), false);
SELECT setval('attendance_scans_id_seq', coalesce((SELECT max(id)+1 FROM attendance_scans), 1), false);
SELECT setval('activity_logs_id_seq', coalesce((SELECT max(id)+1 FROM activity_logs), 1), false);
SELECT setval('certificates_id_seq', coalesce((SELECT max(id)+1 FROM certificates), 1), false);
--------------------------------------------------------------------------------------
`);

  } catch (err) {
    console.error('❌ Migration failed with error:', err);
  } finally {
    await connection.end();
  }
}

runMigration();
