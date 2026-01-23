# LOKA — Database Schema Reference

## Database: `fleet_management`

Uses the existing CodeIgniter 4 database. All tables are shared.

---

## Entity Relationship Diagram (Logical)

```
┌─────────────┐       ┌─────────────┐
│ departments │◄──────│   users     │
└─────────────┘       └──────┬──────┘
       │                     │
       │              ┌──────┴──────┐
       │              │   drivers   │
       │              └──────┬──────┘
       │                     │
       ▼                     ▼
┌─────────────┐       ┌─────────────┐
│  requests   │◄──────│  vehicles   │
└──────┬──────┘       └─────────────┘
       │                     │
       ▼                     ▼
┌─────────────┐       ┌─────────────┐
│ approvals   │       │ maintenance │
└─────────────┘       └─────────────┘
       │                     │
       ▼                     ▼
┌─────────────┐       ┌─────────────┐
│approval_wf  │       │fuel_records │
└─────────────┘       └─────────────┘
```

---

## Tables

### 1. `departments`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(100) | NOT NULL, UNIQUE | Department name |
| description | TEXT | NULL | |
| head_user_id | INT UNSIGNED | FK→users.id | Department head |
| status | ENUM | 'active','inactive' | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | Soft delete |

### 2. `users`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| department_id | INT UNSIGNED | FK→departments.id | |
| email | VARCHAR(255) | NOT NULL, UNIQUE | Login email |
| password | VARCHAR(255) | NOT NULL | Bcrypt hash |
| name | VARCHAR(100) | NOT NULL | Full name |
| phone | VARCHAR(20) | NULL | |
| role | ENUM | 'requester','approver','motorpool_head','admin' | |
| status | ENUM | 'active','inactive','suspended' | |
| last_login_at | DATETIME | NULL | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | Soft delete |

### 3. `vehicle_types`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(50) | NOT NULL, UNIQUE | Type name |
| description | TEXT | NULL | |
| passenger_capacity | INT | NOT NULL, DEFAULT 4 | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | |

### 4. `vehicles`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| vehicle_type_id | INT UNSIGNED | FK→vehicle_types.id | |
| make | VARCHAR(50) | NOT NULL | Manufacturer |
| model | VARCHAR(50) | NOT NULL | Model name |
| year | VARCHAR(4) | NOT NULL | Year |
| plate_number | VARCHAR(20) | NOT NULL, UNIQUE | License plate |
| vin | VARCHAR(17) | NULL, UNIQUE | VIN |
| color | VARCHAR(30) | NULL | |
| engine_number | VARCHAR(50) | NULL | |
| status | ENUM | 'available','in_use','maintenance','out_of_service' | |
| mileage | INT | NOT NULL, DEFAULT 0 | |
| fuel_type | ENUM | 'gasoline','diesel','electric','hybrid' | |
| transmission | ENUM | 'manual','automatic' | |
| notes | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | |

### 5. `drivers`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| user_id | INT UNSIGNED | FK→users.id, UNIQUE | Linked user |
| license_number | VARCHAR(30) | NOT NULL, UNIQUE | |
| license_expiry | DATE | NOT NULL | |
| license_class | VARCHAR(10) | NOT NULL, DEFAULT 'B' | |
| years_experience | INT | NOT NULL, DEFAULT 0 | |
| status | ENUM | 'available','on_trip','on_leave','unavailable' | |
| emergency_contact_name | VARCHAR(100) | NULL | |
| emergency_contact_phone | VARCHAR(20) | NULL | |
| notes | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | |

### 6. `requests`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| user_id | INT UNSIGNED | FK→users.id | Requester |
| vehicle_id | INT UNSIGNED | FK→vehicles.id, NULL | Assigned vehicle |
| driver_id | INT UNSIGNED | FK→drivers.id, NULL | Assigned driver |
| department_id | INT UNSIGNED | FK→departments.id | |
| start_datetime | DATETIME | NOT NULL | Trip start |
| end_datetime | DATETIME | NOT NULL | Trip end |
| purpose | TEXT | NOT NULL | |
| destination | TEXT | NOT NULL | |
| passenger_count | INT | NOT NULL, DEFAULT 1 | |
| status | ENUM | See below | Current status |
| notes | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| cancelled_at | DATETIME | NULL | |
| deleted_at | DATETIME | NULL | |

**Request Status Values:**
- `draft` — Saved but not submitted
- `pending` — Awaiting department approval
- `pending_motorpool` — Approved by dept, awaiting motorpool
- `approved` — Fully approved
- `rejected` — Rejected at any stage
- `cancelled` — Cancelled by requester
- `completed` — Trip completed
- `modified` — Modified, awaiting acknowledgment

### 7. `approval_workflow`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| request_id | INT UNSIGNED | FK→requests.id | |
| department_id | INT UNSIGNED | FK→departments.id | |
| approver_id | INT UNSIGNED | FK→users.id, NULL | Dept approver |
| motorpool_head_id | INT UNSIGNED | FK→users.id, NULL | Motorpool approver |
| step | ENUM | 'department','motorpool' | Current step |
| status | ENUM | 'pending','approved','rejected' | |
| comments | TEXT | NULL | |
| action_at | DATETIME | NULL | When action taken |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |

### 8. `approvals`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| request_id | INT UNSIGNED | FK→requests.id | |
| approver_id | INT UNSIGNED | FK→users.id | Who approved |
| approval_type | ENUM | 'department','motorpool' | |
| status | ENUM | 'approved','rejected' | |
| comments | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |

### 9. `notifications`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| user_id | INT UNSIGNED | FK→users.id | Recipient |
| type | VARCHAR(50) | NOT NULL | Notification type |
| title | VARCHAR(255) | NOT NULL | |
| message | TEXT | NOT NULL | |
| link | VARCHAR(255) | NULL | Action link |
| is_read | BOOLEAN | NOT NULL, DEFAULT 0 | |
| created_at | DATETIME | NOT NULL | |

### 10. `audit_logs`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| user_id | INT UNSIGNED | FK→users.id, NULL | Actor |
| action | VARCHAR(100) | NOT NULL | Action performed |
| entity_type | VARCHAR(50) | NOT NULL | Table affected |
| entity_id | INT UNSIGNED | NULL | Record ID |
| old_data | JSON | NULL | Before state |
| new_data | JSON | NULL | After state |
| ip_address | VARCHAR(45) | NULL | |
| user_agent | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |

### 11. `maintenance`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| vehicle_id | INT UNSIGNED | FK→vehicles.id | |
| maintenance_type | ENUM | 'scheduled','repair','inspection','emergency' | |
| description | TEXT | NOT NULL | |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NULL | |
| cost | DECIMAL(10,2) | NULL | |
| status | ENUM | 'scheduled','in_progress','completed','cancelled' | |
| mechanic_name | VARCHAR(100) | NULL | |
| notes | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | |

### 12. `fuel_records`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| vehicle_id | INT UNSIGNED | FK→vehicles.id | |
| driver_id | INT UNSIGNED | FK→drivers.id, NULL | |
| fuel_date | DATE | NOT NULL | |
| fuel_station | VARCHAR(100) | NULL | |
| fuel_type | VARCHAR(30) | NOT NULL | |
| quantity | DECIMAL(6,2) | NOT NULL | Liters |
| unit_price | DECIMAL(6,2) | NOT NULL | |
| total_cost | DECIMAL(10,2) | NOT NULL | |
| odometer | INT | NOT NULL | |
| receipt_number | VARCHAR(50) | NULL | |
| notes | TEXT | NULL | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULL | |

### 13. `settings`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| key | VARCHAR(100) | NOT NULL, UNIQUE | Setting key |
| value | TEXT | NULL | Setting value |
| description | VARCHAR(255) | NULL | |
| type | ENUM | 'string','integer','boolean','json' | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |

### 14. `remember_tokens`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| user_id | INT UNSIGNED | FK→users.id | |
| selector | VARCHAR(255) | NOT NULL, UNIQUE | Token selector |
| hashed_token | VARCHAR(255) | NOT NULL | Token hash |
| expires | DATETIME | NOT NULL | |
| created_at | DATETIME | NOT NULL | |

### 15. `password_reset_tokens`
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| user_id | INT UNSIGNED | FK→users.id | |
| selector | VARCHAR(255) | NOT NULL, UNIQUE | |
| hashed_token | VARCHAR(255) | NOT NULL | |
| expires | DATETIME | NOT NULL | |
| created_at | DATETIME | NOT NULL | |

---

## Key Indexes

```sql
-- Users
idx_users_email (email)
idx_users_role (role)
idx_users_status (status)

-- Requests
idx_requests_user_id (user_id)
idx_requests_status (status)
idx_requests_dates (start_datetime, end_datetime)

-- Vehicles
idx_vehicles_status (status)
idx_vehicles_type (vehicle_type_id)

-- Drivers
idx_drivers_status (status)

-- Notifications
idx_notifications_user (user_id, is_read)

-- Audit Logs
idx_audit_logs_entity (entity_type, entity_id)
idx_audit_logs_user (user_id)
```

---

## Common Queries

### Get user with department
```sql
SELECT u.*, d.name as department_name 
FROM users u 
LEFT JOIN departments d ON u.department_id = d.id 
WHERE u.id = ?
```

### Get pending requests for approver
```sql
SELECT r.*, u.name as requester_name, d.name as department_name
FROM requests r
JOIN users u ON r.user_id = u.id
JOIN departments d ON r.department_id = d.id
WHERE r.status = 'pending' 
AND r.department_id = ?
ORDER BY r.created_at DESC
```

### Get available vehicles
```sql
SELECT v.*, vt.name as type_name
FROM vehicles v
JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
WHERE v.status = 'available'
AND v.deleted_at IS NULL
```

### Check vehicle availability for date range
```sql
SELECT COUNT(*) as conflict_count
FROM requests
WHERE vehicle_id = ?
AND status IN ('approved', 'pending_motorpool')
AND start_datetime < ?  -- end_datetime param
AND end_datetime > ?    -- start_datetime param
```
