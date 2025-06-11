<?php
// Create a reusable navigation component
function renderNavigation($currentPage = '') {
    $navItems = [
        'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
        'attendance.php' => ['icon' => 'camera', 'label' => 'Mark Attendance'],
        'quick_reports.php' => ['icon' => 'tachometer-alt', 'label' => 'Quick Reports'],
        'reports.php' => ['icon' => 'chart-bar', 'label' => 'Detailed Reports']
    ];
    
    if (isAdmin()) {
        $navItems['admin/dashboard.php'] = ['icon' => 'cog', 'label' => 'Admin Panel'];
    }
    
    echo '<div class="navbar-nav ms-auto">';
    echo '<span class="navbar-text me-3">Welcome, ' . $_SESSION['name'] . '!</span>';
    
    foreach ($navItems as $url => $item) {
        $activeClass = ($currentPage === $url) ? ' active' : '';
        echo '<a class="nav-link' . $activeClass . '" href="' . $url . '">';
        echo '<i class="fas fa-' . $item['icon'] . '"></i> ' . $item['label'];
        echo '</a>';
    }
    
    echo '<a class="nav-link" href="logout.php">';
    echo '<i class="fas fa-sign-out-alt"></i> Logout';
    echo '</a>';
    echo '</div>';
}
?>