
CREATE EXTENSION IF NOT EXISTS "pgcrypto";


DROP TABLE IF EXISTS public.reviews CASCADE;
DROP TABLE IF EXISTS public.donations CASCADE;
DROP TABLE IF EXISTS public.film_credits CASCADE;
DROP TABLE IF EXISTS public.films CASCADE;
DROP TABLE IF EXISTS public.categories CASCADE;
DROP TABLE IF EXISTS public.user_profiles CASCADE;
DROP TABLE IF EXISTS public.users CASCADE;


CREATE TABLE public.users (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL, 
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);


CREATE TABLE public.user_profiles (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id UUID REFERENCES public.users(id) ON DELETE CASCADE,
    role TEXT CHECK (role IN ('viewer', 'filmmaker', 'admin')) DEFAULT 'viewer',
    avatar_url TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);


CREATE TABLE public.categories (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name TEXT UNIQUE NOT NULL
);


INSERT INTO public.categories (name) VALUES 
('Documentary'), ('Narrative'), ('Experimental'), ('Animation'), ('Music Video');


CREATE TABLE public.films (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    filmmaker_id UUID REFERENCES public.users(id) ON DELETE CASCADE,
    category_id UUID REFERENCES public.categories(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    synopsis TEXT,
    duration_minutes INTEGER DEFAULT 0,
    poster_url TEXT,
    video_url TEXT,
    trailer_url TEXT,
    thumbnail_url TEXT,
    funding_goal NUMERIC(10,2) DEFAULT 0,
    funding_raised NUMERIC(10,2) DEFAULT 0,
    status TEXT CHECK (status IN ('pending', 'approved', 'rejected')) DEFAULT 'pending',
    visibility TEXT CHECK (visibility IN ('public', 'private')) DEFAULT 'public',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);


CREATE TABLE public.film_credits (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    film_id UUID REFERENCES public.films(id) ON DELETE CASCADE,
    role TEXT NOT NULL,
    name TEXT NOT NULL
);


CREATE TABLE public.donations (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id UUID REFERENCES public.users(id) ON DELETE SET NULL,
    film_id UUID REFERENCES public.films(id) ON DELETE CASCADE,
    amount NUMERIC(10,2) NOT NULL,
    message TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);


CREATE TABLE public.reviews (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id UUID REFERENCES public.users(id) ON DELETE CASCADE,
    film_id UUID REFERENCES public.films(id) ON DELETE CASCADE,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);


ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.films ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.film_credits ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.donations ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.reviews ENABLE ROW LEVEL SECURITY;


CREATE POLICY "Enable all for users" ON public.users FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Enable all for profiles" ON public.user_profiles FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Enable all for categories" ON public.categories FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Enable all for films" ON public.films FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Enable all for credits" ON public.film_credits FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Enable all for donations" ON public.donations FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Enable all for reviews" ON public.reviews FOR ALL USING (true) WITH CHECK (true);
