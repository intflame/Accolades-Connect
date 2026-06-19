-- PostgreSQL Schema for Department Event Attendance Management System (Accolades Connect)
-- Setup for Supabase

-- Enable necessary extensions
create extension if not exists "uuid-ossp";

-- 1. Batches Table
create table if not exists public.batches (
  id serial primary key,
  name varchar(50) not null unique,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 2. Profiles Table (combines Users and Students, linked to auth.users)
create table if not exists public.profiles (
  id uuid references auth.users on delete cascade primary key,
  email varchar(150) not null unique,
  role varchar(20) not null check (role in ('admin', 'student', 'scanner')),
  status varchar(20) not null default 'pending_approval' check (status in ('pending_approval', 'active', 'suspended')),
  name varchar(100) not null,
  course varchar(10) check (course in ('BCA', 'MCA')),
  batch_id integer references public.batches(id) on delete set null,
  class_roll varchar(30),
  university_roll varchar(30),
  contact_number varchar(15),
  whatsapp_number varchar(15),
  food_preference varchar(10) default 'veg' check (food_preference in ('veg', 'non-veg')),
  profile_photo varchar(255),
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 3. Events Table
create table if not exists public.events (
  id serial primary key,
  name varchar(150) not null,
  description text,
  banner_image varchar(255),
  event_date date not null,
  venue varchar(150) not null,
  registration_fee decimal(10,2) default 0.00 not null,
  registration_deadline timestamp with time zone not null,
  scan_start_time timestamp with time zone not null,
  scan_end_time timestamp with time zone not null,
  upi_payment_enabled boolean default true not null,
  upi_id varchar(100),
  upi_qr_image varchar(255),
  cash_payment_enabled boolean default true not null,
  food_enabled boolean default true not null,
  certificate_template varchar(255),
  certificate_template_type varchar(20) default 'border_only' check (certificate_template_type in ('border_only', 'full_design')) not null,
  certificate_theme varchar(50) default 'classic_navy' not null,
  certificate_title varchar(255) default 'Certificate of Activity' not null,
  certificate_coordinator varchar(255) default 'Event Coordinator' not null,
  certificate_hod varchar(255) default 'Head of Department' not null,
  certificate_layout_config text,
  canva_template_link varchar(512),
  certifier_campaign_id varchar(255),
  status varchar(30) default 'upcoming' check (status in ('upcoming', 'registration_open', 'registration_closed', 'completed', 'cancelled')) not null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 4. Event Registrations Table
create table if not exists public.event_registrations (
  id serial primary key,
  event_id integer references public.events(id) on delete cascade not null,
  student_id uuid references public.profiles(id) on delete cascade not null,
  status varchar(30) default 'pending_payment' check (status in ('pending_payment', 'pending_verification', 'approved', 'rejected', 'cancelled')) not null,
  payment_method varchar(10) check (payment_method in ('upi', 'cash')),
  assigned_role varchar(20) default 'participant' check (assigned_role in ('participant', 'volunteers', 'OC', 'CC')) not null,
  applied_role varchar(20) check (applied_role in ('participant', 'volunteers', 'OC', 'CC')),
  role_status varchar(10) default 'none' check (role_status in ('none', 'pending', 'approved', 'rejected')) not null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null,
  unique (student_id, event_id)
);

-- 5. Payments Table
create table if not exists public.payments (
  id serial primary key,
  registration_id integer references public.event_registrations(id) on delete cascade not null,
  payment_method varchar(10) check (payment_method in ('upi', 'cash')) not null,
  proof_image varchar(255) not null,
  status varchar(20) default 'pending' check (status in ('pending', 'approved', 'rejected')) not null,
  verified_by uuid references public.profiles(id) on delete set null,
  verification_time timestamp with time zone,
  rejection_reason text,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 6. QR Tokens Table
create table if not exists public.qr_tokens (
  id serial primary key,
  registration_id integer references public.event_registrations(id) on delete cascade not null,
  token varchar(64) not null unique,
  status varchar(20) default 'active' check (status in ('active', 'expired', 'disabled', 'used')) not null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 7. Attendance Scans Table
create table if not exists public.attendance_scans (
  id serial primary key,
  qr_token_id integer references public.qr_tokens(id) on delete cascade not null,
  scan_type varchar(10) default 'entry' check (scan_type in ('entry', 'food', 'exit')) not null,
  scanned_by uuid references public.profiles(id) on delete cascade not null,
  scanned_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 8. Activity Logs Table
create table if not exists public.activity_logs (
  id serial primary key,
  user_id uuid references public.profiles(id) on delete set null,
  action varchar(255) not null,
  details text,
  ip_address varchar(45),
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- 9. Certificates Table
create table if not exists public.certificates (
  id serial primary key,
  registration_id integer references public.event_registrations(id) on delete cascade not null unique,
  certificate_code varchar(50) not null unique,
  issued_by uuid references public.profiles(id) on delete set null,
  issued_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- RLS (Row Level Security) Policies

-- Helper Function to resolve role checks safely without causing infinite recursion in RLS policies
create or replace function public.get_my_role()
returns text as $$
  select role from public.profiles where id = auth.uid();
$$ language sql security definer set search_path = public;

-- Enable RLS on all tables
alter table public.batches enable row level security;
alter table public.profiles enable row level security;
alter table public.events enable row level security;
alter table public.event_registrations enable row level security;
alter table public.payments enable row level security;
alter table public.qr_tokens enable row level security;
alter table public.attendance_scans enable row level security;
alter table public.activity_logs enable row level security;
alter table public.certificates enable row level security;

-- Batches Policies
create policy "Allow read access for all users" on public.batches for select using (true);
create policy "Allow write access for admins only" on public.batches for all using (
  public.get_my_role() = 'admin'
);

-- Profiles Policies
create policy "Allow users to read profiles" on public.profiles for select using (
  id = auth.uid() or 
  public.get_my_role() in ('admin', 'scanner')
);
create policy "Allow users to update own profile" on public.profiles for update using (id = auth.uid());
create policy "Allow admins full control of profiles" on public.profiles for all using (
  public.get_my_role() = 'admin'
);

-- Events Policies
create policy "Allow read access to events for everyone" on public.events for select using (true);
create policy "Allow write access to events for admins only" on public.events for all using (
  public.get_my_role() = 'admin'
);

-- Event Registrations Policies
create policy "Allow users to view own registrations" on public.event_registrations for select using (
  student_id = auth.uid() or
  public.get_my_role() in ('admin', 'scanner')
);
create policy "Allow students to insert own registrations" on public.event_registrations for insert with check (
  student_id = auth.uid()
);
create policy "Allow students to update own registrations" on public.event_registrations for update using (
  student_id = auth.uid()
);
create policy "Allow admins full control of registrations" on public.event_registrations for all using (
  public.get_my_role() = 'admin'
);

-- Payments Policies
create policy "Allow users to view own payments" on public.payments for select using (
  exists (
    select 1 from public.event_registrations r
    where r.id = registration_id and r.student_id = auth.uid()
  ) or
  public.get_my_role() = 'admin'
);
create policy "Allow students to upload payments" on public.payments for insert with check (
  exists (
    select 1 from public.event_registrations r
    where r.id = registration_id and r.student_id = auth.uid()
  )
);
create policy "Allow admins full control of payments" on public.payments for all using (
  public.get_my_role() = 'admin'
);

-- QR Tokens Policies
create policy "Allow users to view own QR tokens" on public.qr_tokens for select using (
  exists (
    select 1 from public.event_registrations r
    where r.id = registration_id and r.student_id = auth.uid()
  ) or
  public.get_my_role() in ('admin', 'scanner')
);
create policy "Allow admins full control of QR tokens" on public.qr_tokens for all using (
  public.get_my_role() = 'admin'
);

-- Attendance Scans Policies
create policy "Allow scanners and admins to view all scans" on public.attendance_scans for select using (
  public.get_my_role() in ('admin', 'scanner') or
  exists (
    select 1 from public.qr_tokens t
    join public.event_registrations r on r.id = t.registration_id
    where t.id = qr_token_id and r.student_id = auth.uid()
  )
);
create policy "Allow scanners and admins to insert scans" on public.attendance_scans for insert with check (
  public.get_my_role() in ('admin', 'scanner')
);

-- Activity Logs Policies
create policy "Allow admins to view activity logs" on public.activity_logs for select using (
  public.get_my_role() = 'admin'
);
create policy "Allow system to insert logs" on public.activity_logs for insert with check (true);

-- Certificates Policies
create policy "Allow anyone to view certificates" on public.certificates for select using (true);
create policy "Allow admins to insert/update certificates" on public.certificates for all using (
  public.get_my_role() = 'admin'
);

-- TRIGGERS AND FUNCTIONS

-- Create new profile trigger function
create or replace function public.handle_new_user()
returns trigger as $$
begin
  insert into public.profiles (id, email, role, status, name, course, batch_id, class_roll, university_roll, contact_number, whatsapp_number, food_preference, profile_photo)
  values (
    new.id,
    new.email,
    coalesce(new.raw_user_meta_data->>'role', 'student'),
    case 
      when coalesce(new.raw_user_meta_data->>'role', 'student') = 'admin' then 'active'
      when coalesce(new.raw_user_meta_data->>'role', 'student') = 'scanner' then 'active'
      else 'active' -- students are active immediately upon registration
    end,
    coalesce(new.raw_user_meta_data->>'name', ''),
    new.raw_user_meta_data->>'course',
    (new.raw_user_meta_data->>'batch_id')::integer,
    new.raw_user_meta_data->>'class_roll',
    new.raw_user_meta_data->>'university_roll',
    new.raw_user_meta_data->>'contact_number',
    new.raw_user_meta_data->>'whatsapp_number',
    coalesce(new.raw_user_meta_data->>'food_preference', 'veg'),
    new.raw_user_meta_data->>'profile_photo'
  );
  return new;
end;
$$ language plpgsql security definer;

-- Create the trigger
drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();

-- Trigger to automatically update registration status when payment is approved
create or replace function public.handle_payment_approval()
returns trigger as $$
begin
  if new.status = 'approved' and old.status != 'approved' then
    -- Update registration status to approved (which will fire the registration trigger)
    update public.event_registrations
    set status = 'approved'
    where id = new.registration_id;
  elsif new.status = 'rejected' and old.status != 'rejected' then
    -- Update registration status to rejected
    update public.event_registrations
    set status = 'rejected'
    where id = new.registration_id;
  end if;
  return new;
end;
$$ language plpgsql security definer;

-- Create trigger on payments
drop trigger if exists on_payment_update on public.payments;
create trigger on_payment_update
  after update on public.payments
  for each row execute procedure public.handle_payment_approval();

-- Trigger to automatically create QR token when registration status is approved (UPI, Cash, or Free)
create or replace function public.handle_registration_approval()
returns trigger as $$
begin
  if new.status = 'approved' and (tg_op = 'INSERT' or old.status != 'approved') then
    -- Generate a unique 32-character hexadecimal QR token using built-in uuid-ossp
    insert into public.qr_tokens (registration_id, token, status)
    values (
      new.id,
      replace(uuid_generate_v4()::text, '-', ''),
      'active'
    )
    on conflict do nothing;
  end if;
  return new;
end;
$$ language plpgsql security definer;

-- Create trigger on event_registrations
drop trigger if exists on_registration_update on public.event_registrations;
create trigger on_registration_update
  after insert or update on public.event_registrations
  for each row execute procedure public.handle_registration_approval();

-- 10. Announcements Table
create table if not exists public.announcements (
  id serial primary key,
  title varchar(150) not null,
  message text not null,
  target_role varchar(20) not null check (target_role in ('all', 'student', 'scanner')),
  created_by uuid references public.profiles(id) on delete set null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Enable RLS
alter table public.announcements enable row level security;

-- Policies for Announcements
create policy "Allow read access to announcements for everyone" on public.announcements for select using (true);
create policy "Allow write access to announcements for admins only" on public.announcements for all using (
  public.get_my_role() = 'admin'
);
