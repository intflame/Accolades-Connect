-- Migration: Create Announcements Table

CREATE TABLE IF NOT EXISTS public.announcements (
  id SERIAL PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  target_role VARCHAR(20) NOT NULL CHECK (target_role IN ('all', 'student', 'scanner')),
  created_by UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL
);

-- Enable Row Level Security (RLS)
ALTER TABLE public.announcements ENABLE ROW LEVEL SECURITY;

-- Enable Read Access for all authenticated users
CREATE POLICY "Allow read access to announcements for everyone" 
ON public.announcements 
FOR SELECT 
USING (true);

-- Enable Write/Full Access for admins only
CREATE POLICY "Allow write access to announcements for admins only" 
ON public.announcements 
FOR ALL 
USING (
  public.get_my_role() = 'admin'
);

-- Adjust sequence to prevent primary key collision
SELECT setval('announcements_id_seq', COALESCE((SELECT MAX(id)+1 FROM announcements), 1), false);
