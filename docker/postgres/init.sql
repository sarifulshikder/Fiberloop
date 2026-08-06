-- Create a dedicated low-privilege application user (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fiberloop') THEN
        CREATE USER fiberloop WITH PASSWORD 'fiberloop_secret';
    END IF;
END
$$;

-- Create the main fiberloop database (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'fiberloop') THEN
        CREATE DATABASE fiberloop OWNER fiberloop;
    END IF;
END
$$;

-- Grant all privileges on the database to the fiberloop user
GRANT ALL PRIVILEGES ON DATABASE fiberloop TO fiberloop;

-- Create the radius database for FreeRADIUS (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'radius') THEN
        CREATE DATABASE radius OWNER fiberloop;
    END IF;
END
$$;

-- Grant all privileges on the radius database to the fiberloop user
GRANT ALL PRIVILEGES ON DATABASE radius TO fiberloop;

-- Connect to the radius database and create the FreeRADIUS schema
\c radius

-- Enable necessary extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- FreeRADIUS PostgreSQL Schema
-- Based on FreeRADIUS 3.2.x schema.sql

-- NAS table
CREATE TABLE IF NOT EXISTS nas (
    id SERIAL PRIMARY KEY,
    nasname VARCHAR(128) NOT NULL,
    shortname VARCHAR(32),
    type VARCHAR(32) DEFAULT 'other',
    ports INTEGER DEFAULT 0,
    secret VARCHAR(64) NOT NULL,
    server VARCHAR(64),
    community VARCHAR(64),
    description VARCHAR(256)
);

-- RADIUS check attributes table
CREATE TABLE IF NOT EXISTS radcheck (
    id SERIAL PRIMARY KEY,
    username VARCHAR(253) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op CHAR(2) NOT NULL DEFAULT '==',
    value VARCHAR(253) NOT NULL DEFAULT '',
    INDEX username_attribute (username, attribute)
);

-- RADIUS reply attributes table
CREATE TABLE IF NOT EXISTS radreply (
    id SERIAL PRIMARY KEY,
    username VARCHAR(253) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op CHAR(2) NOT NULL DEFAULT '=',
    value VARCHAR(253) NOT NULL DEFAULT '',
    INDEX username_attribute (username, attribute)
);

-- RADIUS group check attributes table
CREATE TABLE IF NOT EXISTS radgroupcheck (
    id SERIAL PRIMARY KEY,
    groupname VARCHAR(253) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op CHAR(2) NOT NULL DEFAULT '==',
    value VARCHAR(253) NOT NULL DEFAULT '',
    INDEX group_attribute (groupname, attribute)
);

-- RADIUS group reply attributes table
CREATE TABLE IF NOT EXISTS radgroupreply (
    id SERIAL PRIMARY KEY,
    groupname VARCHAR(253) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op CHAR(2) NOT NULL DEFAULT '=',
    value VARCHAR(253) NOT NULL DEFAULT '',
    INDEX group_attribute (groupname, attribute)
);

-- RADIUS user group mapping
CREATE TABLE IF NOT EXISTS radusergroup (
    username VARCHAR(253) NOT NULL DEFAULT '',
    groupname VARCHAR(253) NOT NULL DEFAULT '',
    priority INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (username, groupname),
    INDEX groupname (groupname),
    INDEX username (username)
);

-- RADIUS accounting table (radacct)
CREATE TABLE IF NOT EXISTS radacct (
    radacctid BIGSERIAL PRIMARY KEY,
    acctsessionid VARCHAR(89) NOT NULL DEFAULT '',
    acctuniqueid VARCHAR(32) NOT NULL DEFAULT '',
    username VARCHAR(253) NOT NULL DEFAULT '',
    groupname VARCHAR(253) DEFAULT '',
    realm VARCHAR(253) DEFAULT '',
    nasipaddress VARCHAR(15) NOT NULL DEFAULT '',
    nasportid VARCHAR(15) DEFAULT '',
    nasporttype VARCHAR(32) DEFAULT '',
    acctstarttime TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    acctupdatetime TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    acctstoptime TIMESTAMP WITH TIME ZONE,
    acctinterval INTEGER DEFAULT 0,
    acctsessiontime INTEGER DEFAULT 0,
    acctauthentic VARCHAR(32) DEFAULT '',
    connectinfo_start VARCHAR(512) DEFAULT '',
    connectinfo_stop VARCHAR(512) DEFAULT '',
    acctinputoctets BIGINT DEFAULT 0,
    acctoutputoctets BIGINT DEFAULT 0,
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    acctterminatecause VARCHAR(32) NOT NULL DEFAULT '',
    servicetype VARCHAR(32) DEFAULT '',
    framedprotocol VARCHAR(32) DEFAULT '',
    framedipaddress VARCHAR(15) DEFAULT '',
    framedipnetmask VARCHAR(15) DEFAULT '',
    framedroute VARCHAR(253) DEFAULT '',
    session_timeout INTEGER DEFAULT 0,
    idle_timeout INTEGER DEFAULT 0,
    framingipaddress VARCHAR(15) DEFAULT '',
    framingipnetmask VARCHAR(15) DEFAULT '',
    INDEX acctsessionid_index (acctsessionid),
    INDEX username_index (username),
    INDEX nasipaddress_index (nasipaddress),
    INDEX acctstarttime_index (acctstarttime),
    INDEX acctstoptime_index (acctstoptime),
    INDEX framedipaddress_index (framedipaddress)
);

-- RADIUS post-authentication logging table
CREATE TABLE IF NOT EXISTS radpostauth (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(253) NOT NULL DEFAULT '',
    pass VARCHAR(253) NOT NULL DEFAULT '',
    reply VARCHAR(253) NOT NULL DEFAULT '',
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    date TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    authdate TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    auth VARCHAR(32) DEFAULT '',
    replymsg VARCHAR(253) DEFAULT '',
    INDEX username_date (username, date),
    INDEX date (date)
);

-- Grant privileges on radius tables to fiberloop user
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO fiberloop;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO fiberloop;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO fiberloop;

-- Set default privileges for future objects
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON TABLES TO fiberloop;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON SEQUENCES TO fiberloop;

-- Reconnect to fiberloop database for any additional setup
\c fiberloop

-- Grant all privileges on all tables in fiberloop to fiberloop user
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO fiberloop;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO fiberloop;

-- Set default privileges for future objects in fiberloop
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON TABLES TO fiberloop;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON SEQUENCES TO fiberloop;
