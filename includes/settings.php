<?php
class SystemSettings {
    private $db;
    private $settings = [];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
        while ($row = $stmt->fetch()) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    public function set($key, $value, $description = null) {
        $stmt = $this->db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            description = COALESCE(VALUES(description), description)
        ");
        
        $stmt->execute([$key, $value, $description]);
        $this->settings[$key] = $value;
        return true;
    }
    
    public function getWorkHours() {
        return [
            'start' => $this->get('work_start_time', '09:00:00'),
            'end' => $this->get('work_end_time', '17:00:00')
        ];
    }
    
    public function getLateThreshold() {
        return (int)$this->get('late_threshold_minutes', 15);
    }
    
    public function getFaceConfidenceThreshold() {
        return (float)$this->get('face_confidence_threshold', 0.6);
    }
    
    public function isLateArrival($checkInTime) {
        $workStart = $this->get('work_start_time', '09:00:00');
        $lateThreshold = $this->getLateThreshold();
        
        $startTime = new DateTime($workStart);
        $startTime->add(new DateInterval('PT' . $lateThreshold . 'M'));
        
        $checkIn = new DateTime($checkInTime);
        
        return $checkIn > $startTime;
    }
    
    public function getAttendanceStatus($checkInTime) {
        if ($this->isLateArrival($checkInTime)) {
            return 'late';
        }
        return 'present';
    }
}
?>