-- Create a dedicated low-privilege application user
CREATE USER fiberloop WITH PASSWORD 'fiberloop_secret';

-- Create the database
CREATE DATABASE fiberloop OWNER fiberloop;

-- Connect to the database and grant privileges
\c fiberloop

-- Grant all privileges on the database to the fiberloop user
GRANT ALL PRIVILEGES ON DATABASE fiberloop TO fiberloop;

-- Grant all privileges on all tables in the public schema
GRANT ALL PRIVILEGES ON SCHEMA public TO fiberloop;

-- Grant all privileges on all tables, sequences, and functions
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO fiberloop;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO fiberloop;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO fiberloop;

-- Set default privileges for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO fiberloop;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO fiberloop;

-- Create extension for UUID if needed
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
GRANT USAGE ON SCHEMA public TO fiberloop;
