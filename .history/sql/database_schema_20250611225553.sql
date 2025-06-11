-- Face Recognition Attendance System Database Schema
-- Created for: Web-Based Attendance System using face-api.js

CREATE DATABASE IF NOT EXISTS face_attendance_db;
USE face_attendance_db;

-- Users table (stores registered users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    face_descriptor TEXT, -- JSON encoded face descriptors
    profile_image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Attendance table (stores daily attendance records)
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    confidence_score DECIMAL(5,4), -- Face recognition confidence
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date)
);

-- Settings table (system configurations)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Attendance logs (detailed tracking)
CREATE TABLE attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action ENUM('check_in', 'check_out', 'failed_attempt') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confidence_score DECIMAL(5,4),
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (employee_id, name, email, password, role) VALUES 
('ADMIN001', 'System Administrator', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('attendance_start_time', '09:00:00', 'Daily attendance start time'),
('attendance_end_time', '18:00:00', 'Daily attendance end time'),
('late_threshold', '09:15:00', 'Time after which attendance is marked as late'),
('face_confidence_threshold', '0.6', 'Minimum confidence score for face recognition'),
('max_attempts_per_day', '3', 'Maximum failed recognition attempts per day');

-- Create indexes for better performance
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_attendance_user_date ON attendance(user_id, date);
CREATE INDEX idx_logs_timestamp ON attendance_logs(timestamp);
CREATE INDEX idx_users_employee_id ON users(employee_id);
CREATE INDEX idx_users_email ON users(email);

-- Create views for easy data retrieval
CREATE VIEW daily_attendance AS
SELECT 
    u.id,
    u.employee_id,
    u.name,
    u.email,
    a.date,
    a.check_in_time,
    a.check_out_time,
    a.status,
    a.confidence_score
FROM users u
LEFT JOIN attendance a ON u.id = a.user_id
WHERE u.status = 'active';

CREATE VIEW attendance_summary AS
SELECT 
    u.id,
    u.name,
    u.employee_id,
    COUNT(a.id) as total_days,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
FROM users u
LEFT JOIN attendance a ON u.id = a.user_id
WHERE u.status = 'active'
GROUP BY u.id, u.name, u.employee_id;