-- Create a dedicated low-privilege application user (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fiberloop') THEN
        CREATE USER fiberloop WITH PASSWORD 'fiberloop_secret';
    END IF;
END
$$;

-- Create the database (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'fiberloop') THEN
        CREATE DATABASE fiberloop OWNER fiberloop;
    END IF;
END
$$;

-- Grant all privileges on the database to the fiberloop user
GRANT ALL PRIVILEGES ON DATABASE fiberloop TO fiberloop;

-- Set default privileges for future tables in the fiberloop database
-- Note: These apply to the current database context
-- For existing database, we need to connect to it

-- Create extension for UUID if needed in the fiberloop database
-- This will be created when we first connect
