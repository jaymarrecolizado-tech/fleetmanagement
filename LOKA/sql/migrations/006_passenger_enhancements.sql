-- Enhance request_passengers table for guest support
ALTER TABLE request_passengers MODIFY COLUMN user_id INT UNSIGNED NULL;
ALTER TABLE request_passengers ADD COLUMN guest_name VARCHAR(100) NULL AFTER user_id;
