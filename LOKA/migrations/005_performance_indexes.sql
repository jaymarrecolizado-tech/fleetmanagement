-- Add missing indexes on foreign keys for performance optimization
-- Compatible with MySQL 5.x (no IF NOT EXISTS support)

-- Helper: Drop index if exists, then create (to make migration idempotent)
-- Note: On first run, DROP INDEX will fail if index doesn't exist - that's OK

-- Index on requests.user_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_user_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_user_id ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_user_id ON requests(user_id);

-- Index on requests.approver_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_approver_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_approver_id ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_approver_id ON requests(approver_id);

-- Index on requests.motorpool_head_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_motorpool_head_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_motorpool_head_id ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_motorpool_head_id ON requests(motorpool_head_id);

-- Index on requests.department_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_department_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_department_id ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_department_id ON requests(department_id);

-- Index on request_passengers.request_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_passengers' AND INDEX_NAME = 'idx_request_passengers_request_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_request_passengers_request_id ON request_passengers', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_request_passengers_request_id ON request_passengers(request_id);

-- Index on drivers.user_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drivers' AND INDEX_NAME = 'idx_drivers_user_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_drivers_user_id ON drivers', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_drivers_user_id ON drivers(user_id);

-- Index on vehicles.vehicle_type_id
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND INDEX_NAME = 'idx_vehicles_vehicle_type_id';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_vehicles_vehicle_type_id ON vehicles', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_vehicles_vehicle_type_id ON vehicles(vehicle_type_id);

-- Index on vehicles.status
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND INDEX_NAME = 'idx_vehicles_status';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_vehicles_status ON vehicles', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_vehicles_status ON vehicles(status);

-- Index on drivers.status
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drivers' AND INDEX_NAME = 'idx_drivers_status';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_drivers_status ON drivers', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_drivers_status ON drivers(status);

-- Composite indexes
SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_status_department';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_status_department ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_status_department ON requests(status, department_id);

SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_status_motorpool';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_status_motorpool ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_status_motorpool ON requests(status, motorpool_head_id);

SELECT @idx := COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND INDEX_NAME = 'idx_requests_user_status';
SET @sql = IF(@idx > 0, 'DROP INDEX idx_requests_user_status ON requests', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX idx_requests_user_status ON requests(user_id, status);
