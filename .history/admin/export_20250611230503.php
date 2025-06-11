<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();
$type = $_GET['type'] ?? 'monthly';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'monthly':
        // Monthly report
        fputcsv($output, ['Month', 'Year', 'Total Users', 'Total Attendance', 'Attendance Rate (%)']);
        
        $stmt = $db->query("
            SELECT 
                MONTH(a.date) as month,
                YEAR(a.date) as year,
                COUNT(DISTINCT a.user_id) as unique_users,
                COUNT(a.id) as total_attendance,
                ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 2) as attendance_rate
            FROM attendance a
            WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY YEAR(a.date), MONTH(a.date)
            ORDER BY year DESC, month DESC
        ");
        
        while ($row = $stmt->fetch()) {
            $months = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
            fputcsv($output, [
                $months[$row['month']],
                $row['year'],
                $row['unique_users'],
                $row['total_attendance'],
                $row['attendance_rate']
            ]);
        }
        break;
        
    case 'users':
        // User summary report
        fputcsv($output, ['Employee ID', 'Name', 'Email', 'Total Days', 'Present Days', 'Late Days', 'Attendance Rate (%)']);
        
        $stmt = $db->query("
            SELECT 
                u.employee_id,
                u.name,
                u.email,
                COUNT(a.id) as total_days,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                ROUND((SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_rate
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id 
            WHERE u.status = 'active' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
            GROUP BY u.id, u.employee_id, u.name, u.email
            ORDER BY attendance_rate DESC
        ");
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['employee_id'],
                $row['name'],
                $row['email'],
                $row['total_days'],
                $row['present_days'],
                $row['late_days'],
                $row['attendance_rate']
            ]);
        }
        break;
        
    case 'detailed':
        // Detailed attendance report
        fputcsv($output, ['Date', 'Employee ID', 'Name', 'Check In', 'Check Out', 'Status', 'Confidence Score']);
        
        $stmt = $db->query("
            SELECT 
                a.date,
                u.employee_id,
                u.name,
                a.check_in_time,
                a.check_out_time,
                a.status,
                a.confidence_score
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
            ORDER BY a.date DESC, a.check_in_time DESC
        ");
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['date'],
                $row['employee_id'],
                $row['name'],
                $row['check_in_time'],
                $row['check_out_time'] ?: 'Not recorded',
                ucfirst($row['status']),
                round($row['confidence_score'] * 100, 2) . '%'
            ]);
        }
        break;
}

fclose($output);
exit;
?>