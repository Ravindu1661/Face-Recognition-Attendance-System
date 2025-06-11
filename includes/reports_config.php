<?php
// Reports configuration

class ReportsConfig {
    // Working hours configuration
    const STANDARD_WORK_START = '09:00:00';
    const STANDARD_WORK_END = '17:00:00';
    const STANDARD_WORK_HOURS = 8;
    const LUNCH_BREAK_DURATION = 1; // hours
    
    // Late threshold
    const LATE_THRESHOLD_MINUTES = 15;
    
    // Status colors
    const STATUS_COLORS = [
        'present' => 'success',
        'late' => 'warning',
        'absent' => 'danger',
        'holiday' => 'info'
    ];
    
    // Chart colors
    const CHART_COLORS = [
        'primary' => '#4e73df',
        'success' => '#1cc88a',
        'warning' => '#f6c23e',
        'danger' => '#e74a3b',
        'info' => '#36b9cc'
    ];
    
    // Export formats
    const EXPORT_FORMATS = [
        'csv' => 'CSV',
        'pdf' => 'PDF',
        'excel' => 'Excel'
    ];
    
    public static function getWorkingDays($startDate, $endDate) {
        $workingDays = 0;
        $currentDate = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        
        while ($currentDate <= $endDateTime) {
            $dayOfWeek = $currentDate->format('N');
            if ($dayOfWeek < 6) { // Monday = 1, Sunday = 7
                $workingDays++;
            }
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $workingDays;
    }
    
    public static function calculateWorkingHours($timeIn, $timeOut) {
        if (!$timeIn || !$timeOut) {
            return 0;
        }
        
        $start = new DateTime($timeIn);
        $end = new DateTime($timeOut);
        $diff = $end->diff($start);
        
        $hours = $diff->h + ($diff->i / 60);
        
        // Subtract lunch break if working more than 6 hours
        if ($hours > 6) {
            $hours -= self::LUNCH_BREAK_DURATION;
        }
        
        return max(0, $hours);
    }
    
    public static function isLate($timeIn) {
        if (!$timeIn) {
            return false;
        }
        
        $checkIn = new DateTime($timeIn);
        $threshold = new DateTime(self::STANDARD_WORK_START);
        $threshold->add(new DateInterval('PT' . self::LATE_THRESHOLD_MINUTES . 'M'));
        
        return $checkIn > $threshold;
    }
    
    public static function getAttendanceStatus($timeIn, $timeOut, $date) {
        if (!$timeIn) {
            return 'absent';
        }
        
        if (self::isLate($timeIn)) {
            return 'late';
        }
        
        return 'present';
    }
}
?>