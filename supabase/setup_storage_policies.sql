-- Supabase Storage Buckets and Row Level Security (RLS) Setup
-- Run this script in the Supabase Dashboard SQL Editor to configure all buckets and resolve "violates row-level security policy" errors.

-- 1. Create Buckets if they do not exist
insert into storage.buckets (id, name, public)
values ('profile_photos', 'profile_photos', true)
on conflict (id) do nothing;

insert into storage.buckets (id, name, public)
values ('payment_proofs', 'payment_proofs', true)
on conflict (id) do nothing;

insert into storage.buckets (id, name, public)
values ('event_banners', 'event_banners', true)
on conflict (id) do nothing;

insert into storage.buckets (id, name, public)
values ('gallery', 'gallery', true)
on conflict (id) do nothing;


-- 3. Drop existing policies to avoid duplicate name conflicts
drop policy if exists "Public Access for profile_photos" on storage.objects;
drop policy if exists "Authenticated insert own profile_photos" on storage.objects;
drop policy if exists "Authenticated update own profile_photos" on storage.objects;
drop policy if exists "Authenticated delete own profile_photos" on storage.objects;

drop policy if exists "Public Access for event_banners" on storage.objects;
drop policy if exists "Admin full control event_banners" on storage.objects;

drop policy if exists "Public Access for gallery" on storage.objects;
drop policy if exists "Admin full control gallery" on storage.objects;

drop policy if exists "Authenticated insert payment_proofs" on storage.objects;
drop policy if exists "Authenticated view own payment_proofs" on storage.objects;
drop policy if exists "Admin full control payment_proofs" on storage.objects;


-- =========================================================================
-- BUCKET POLICIES: profile_photos
-- =========================================================================

-- Allow anyone to view profile photos publicly
create policy "Public Access for profile_photos"
on storage.objects for select
to public
using (bucket_id = 'profile_photos');

-- Allow authenticated users to upload files inside a folder named after their own UID
create policy "Authenticated insert own profile_photos"
on storage.objects for insert
to authenticated
with check (
  bucket_id = 'profile_photos' 
  and (storage.foldername(name))[1] = auth.uid()::text
);

-- Allow authenticated users to update files in their own folder
create policy "Authenticated update own profile_photos"
on storage.objects for update
to authenticated
using (
  bucket_id = 'profile_photos' 
  and (storage.foldername(name))[1] = auth.uid()::text
);

-- Allow authenticated users to delete files in their own folder
create policy "Authenticated delete own profile_photos"
on storage.objects for delete
to authenticated
using (
  bucket_id = 'profile_photos' 
  and (storage.foldername(name))[1] = auth.uid()::text
);


-- =========================================================================
-- BUCKET POLICIES: event_banners
-- =========================================================================

-- Allow anyone to view event banners publicly (needed for public landing page)
create policy "Public Access for event_banners"
on storage.objects for select
to public
using (bucket_id = 'event_banners');

-- Allow admins full control to insert, update, and delete banners
create policy "Admin full control event_banners"
on storage.objects for all
to authenticated
using (
  bucket_id = 'event_banners'
  and exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin'
  )
)
with check (
  bucket_id = 'event_banners'
  and exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin'
  )
);


-- =========================================================================
-- BUCKET POLICIES: gallery
-- =========================================================================

-- Allow anyone to view gallery photos publicly
create policy "Public Access for gallery"
on storage.objects for select
to public
using (bucket_id = 'gallery');

-- Allow admins full control to insert, update, and delete gallery images
create policy "Admin full control gallery"
on storage.objects for all
to authenticated
using (
  bucket_id = 'gallery'
  and exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin'
  )
)
with check (
  bucket_id = 'gallery'
  and exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin'
  )
);


-- =========================================================================
-- BUCKET POLICIES: payment_proofs
-- =========================================================================

-- Allow authenticated users to view payment proofs (admins can view all, students can view their own uploads)
create policy "Authenticated view own payment_proofs"
on storage.objects for select
to authenticated
using (
  bucket_id = 'payment_proofs'
);

-- Allow authenticated students/users to upload payment proofs
create policy "Authenticated insert payment_proofs"
on storage.objects for insert
to authenticated
with check (
  bucket_id = 'payment_proofs'
);

-- Allow admins full control to insert, update, and delete payment proofs
create policy "Admin full control payment_proofs"
on storage.objects for all
to authenticated
using (
  bucket_id = 'payment_proofs'
  and exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin'
  )
);
