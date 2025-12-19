-- Run this in your Supabase SQL Editor to enable shared sessions on Vercel
CREATE TABLE IF NOT EXISTS public.sessions (
    id TEXT PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- Enable RLS
ALTER TABLE public.sessions ENABLE ROW LEVEL SECURITY;

-- Allow all access for now (or restrict to service role if possible, but for simplicity with the current API client):
CREATE POLICY "Enable all access for anon" ON public.sessions FOR ALL USING (true) WITH CHECK (true);
