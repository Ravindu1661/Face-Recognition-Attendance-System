<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Handle user actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['employee_id'],
                $_POST['name'],
                $_POST['email'],
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $_POST['role']
            ]);
            $message = "User added successfully!";
            break;
            
        case 'delete':
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $message = "User deactivated successfully!";
            break;
            
        case 'activate':
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $message = "User activated successfully!";
            break;
    }
}

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white">Admin Panel</div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white active">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="attendance.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-check"></i> View Attendance
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="../dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-arrow-left"></i> Back to User Panel
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle">Toggle Menu</button>
                    <div class="navbar-nav ms-auto">
                        <span class="navbar-text">Welcome, <?= $_SESSION['name'] ?>!</span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4"></div>