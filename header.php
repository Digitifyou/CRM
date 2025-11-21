<?php
// /header.php
// Ensures the session is started and includes necessary assets.

// Check if config is loaded (to ensure $pdo/session is ready)
if (!defined('ACADEMY_ID')) {
    // Attempt to load the config.php file if ACADEMY_ID constant is not defined
    $config_path = __DIR__ . '/api/config.php';
    if (file_exists($config_path)) {
        require_once $config_path;
    }
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // If not logged in, redirect to login page
    header('Location: login.html');
    exit;
}

$academy_owner = $_SESSION['full_name'] ?? 'CRM User';
$page_name = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Academy CRM - <?php echo ucfirst(str_replace(['.php', '_'], ['', ' '], $page_name)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="/dashboard.php">
                <i class="bi bi-mortarboard-fill me-2 text-primary"></i> FlowSystmz CRM
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link <?php echo ($page_name == 'dashboard.php' ? 'active' : ''); ?>" href="/dashboard.php"><i class="bi bi-house me-1"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($page_name == 'students.php' || $page_name == 'student_profile.php' ? 'active' : ''); ?>" href="/students.php"><i class="bi bi-people me-1"></i> Students</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($page_name == 'enrollments.php' ? 'active' : ''); ?>" href="/enrollments.php"><i class="bi bi-bar-chart me-1"></i> Pipeline</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($page_name == 'settings.php' ? 'active' : ''); ?>" href="/settings.php"><i class="bi bi-gear me-1"></i> Settings</a></li>
                    
                    <?php /*
                    <li class="nav-item"><a class="nav-link <?php echo ($page_name == 'meta_ads.php' ? 'active' : ''); ?>" href="/meta_ads.php"><i class="bi bi-facebook me-1"></i> Meta Ads</a></li> 
                    */ ?>
                </ul>
                
                <a href="/user_profile.php" class="nav-link text-white d-flex align-items-center me-3">
                    <i class="bi bi-person-circle me-1"></i> <span class="fw-bold"><?php echo $academy_owner; ?></span>
                </a>

                <a href="/logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4 pt-4 min-vh-100">
    