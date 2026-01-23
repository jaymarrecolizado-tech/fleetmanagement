-- Add requested_driver_id column to requests table
ALTER TABLE requests ADD COLUMN requested_driver_id INT UNSIGNED NULL AFTER driver_id;

-- Add foreign key constraint
ALTER TABLE requests 
ADD CONSTRAINT fk_requests_requested_driver 
FOREIGN KEY (requested_driver_id) REFERENCES drivers(id) ON DELETE SET NULL;

-- Add index for performance
CREATE INDEX idx_requests_requested_driver ON requests(requested_driver_id);
