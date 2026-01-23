-- Migration 006: Add Critical Performance Indexes
-- Created: January 22, 2026
-- Purpose: Optimize conflict checking, email queue, audit logs, and notifications

-- Conflict checking indexes for vehicle and driver availability
CREATE INDEX idx_driver_conflict ON requests(driver_id, status, start_datetime, end_datetime);
CREATE INDEX idx_vehicle_conflict ON requests(vehicle_id, status, start_datetime, end_datetime);

-- Email queue processing optimization
CREATE INDEX idx_queue_status_scheduled ON email_queue(status, scheduled_at);

-- Audit log query optimization for user reports
CREATE INDEX idx_audit_user_date ON audit_logs(user_id, created_at DESC);

-- Notification query optimization
CREATE INDEX idx_notifications_created ON notifications(created_at DESC);

-- Additional useful indexes for common queries
CREATE INDEX idx_requests_user_status ON requests(user_id, status);
CREATE INDEX idx_requests_status_dates ON requests(status, start_datetime, end_datetime);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read, created_at DESC);
