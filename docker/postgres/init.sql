-- Initialize PostgreSQL database for parcel tracking system
-- Create additional databases for testing if needed

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create test database
CREATE DATABASE parcel_tracking_test;

-- Grant permissions
GRANT ALL PRIVILEGES ON DATABASE parcel_tracking TO parcel_user;
GRANT ALL PRIVILEGES ON DATABASE parcel_tracking_test TO parcel_user;