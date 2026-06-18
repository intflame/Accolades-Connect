-- Migration: Transition QR Token Generation from Payments to Event Registrations

-- 1. Drop old triggers and functions
DROP TRIGGER IF EXISTS on_payment_update ON public.payments;
DROP FUNCTION IF EXISTS public.handle_payment_approval() CASCADE;

-- 2. Re-create handle_payment_approval to only update event_registrations status
CREATE OR REPLACE FUNCTION public.handle_payment_approval()
RETURNS TRIGGER AS $$
BEGIN
  IF new.status = 'approved' AND old.status != 'approved' THEN
    -- Update registration status to approved (which will fire the registration trigger)
    UPDATE public.event_registrations
    SET status = 'approved'
    WHERE id = new.registration_id;
  ELSIF new.status = 'rejected' AND old.status != 'rejected' THEN
    -- Update registration status to rejected
    UPDATE public.event_registrations
    SET status = 'rejected'
    WHERE id = new.registration_id;
  END IF;
  RETURN new;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 3. Install trigger on payments
CREATE TRIGGER on_payment_update
  AFTER UPDATE ON public.payments
  FOR EACH ROW EXECUTE PROCEDURE public.handle_payment_approval();

-- 4. Create centralized handle_registration_approval function
-- This handles QR token generation whenever any registration becomes 'approved' (UPI, Cash, or Free)
CREATE OR REPLACE FUNCTION public.handle_registration_approval()
RETURNS TRIGGER AS $$
BEGIN
  IF new.status = 'approved' AND (tg_op = 'INSERT' OR old.status != 'approved') THEN
    -- Generate a unique 32-character hexadecimal QR token using Postgres built-in uuid-ossp
    INSERT INTO public.qr_tokens (registration_id, token, status)
    VALUES (
      new.id,
      REPLACE(uuid_generate_v4()::TEXT, '-', ''),
      'active'
    )
    ON CONFLICT DO NOTHING;
  END IF;
  RETURN new;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 5. Install trigger on event_registrations
DROP TRIGGER IF EXISTS on_registration_update ON public.event_registrations;
CREATE TRIGGER on_registration_update
  AFTER INSERT OR UPDATE ON public.event_registrations
  FOR EACH ROW EXECUTE PROCEDURE public.handle_registration_approval();
