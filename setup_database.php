<?php
require_once 'includes/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Setting up Face Recognition Attendance System Database...\n";
    echo "========================================================\n\n";
    
    // Create users table
    echo "Creating users table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            status ENUM('active', 'inactive') DEFAULT 'active',
            face_descriptor TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Create attendance table
    echo "Creating attendance table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            check_in_time TIME,
            check_out_time TIME,
            status ENUM('present', 'late', 'absent') DEFAULT 'present',
            confidence_score DECIMAL(3,2),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_date (user_id, date)
        )
    ");
    
    // Create face_encodings table for storing multiple face samples
    echo "Creating face_encodings table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS face_encodings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            encoding TEXT NOT NULL,
            image_path VARCHAR(255),
            confidence_threshold DECIMAL(3,2) DEFAULT 0.6,
            is_primary BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create attendance_logs table for detailed logging
    echo "Creating attendance_logs table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action ENUM('check_in', 'check_out', 'failed_recognition') NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            confidence_score DECIMAL(3,2),
            image_path VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Create system_settings table
    echo "Creating system_settings table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default system settings
    echo "Inserting default system settings...\n";
    $defaultSettings = [
        ['work_start_time', '09:00:00', 'Standard work start time'],
        ['work_end_time', '17:00:00', 'Standard work end time'],
        ['late_threshold_minutes', '15', 'Minutes after start time to mark as late'],
        ['face_confidence_threshold', '0.6', 'Minimum confidence score for face recognition'],
        ['max_face_samples', '5', 'Maximum number of face samples per user'],
        ['attendance_grace_period', '30', 'Grace period in minutes for attendance marking'],
        ['system_timezone', 'UTC', 'System timezone'],
        ['company_name', 'Your Company', 'Company name for reports'],
        ['admin_email', 'admin@company.com', 'Administrator email address']
    ];
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    
    // Create default admin user
    echo "Creating default admin user...\n";
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT IGNORE INTO users (employee_id, name, email, password, role) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute(['ADMIN001', 'System Administrator', 'admin@company.com', $adminPassword, 'admin']);
    
    // Create indexes for better performance
    echo "Creating database indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_user_date ON attendance(user_id, date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON attendance_logs(timestamp)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_user_action ON attendance_logs(user_id, action)");
    
    echo "\n✅ Database setup completed successfully!\n\n";
    echo "Default Admin Credentials:\n";
    echo "Email: admin@company.com\n";
    echo "Password: admin123\n\n";
    echo "⚠️  Please change the default admin password after first login!\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up database: " . $e->getMessage() . "\n";
    exit(1);
}
?>