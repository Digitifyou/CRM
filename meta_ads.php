<?php
// SECURITY CHECK: Ensure user is logged in
require_once __DIR__ . '/api/config.php'; 

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Meta Ads Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .kpi-card { min-height: 120px; }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Training Academy CRM</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/students.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="/enrollments.php">Enrollments</a></li>
                    <li class="nav-item"><a class="nav-link" href="/settings.php">Settings</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/meta_ads.php">Meta Ads</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Meta Ads Performance Dashboard</h2>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadAdsData()"><i class="bi bi-arrow-clockwise"></i> Refresh Data</button>
        </div>
        
        <div id="connection-status" class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i> Not connected. Go to Settings > Integrations to connect your Meta account.
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card kpi-card bg-danger text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Ad Spend (Last 30 Days)</h5>
                        <h3 class="card-text" id="kpi-spend">₹0</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card kpi-card bg-info text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Leads Generated</h5>
                        <h3 class="card-text" id="kpi-leads">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card kpi-card bg-warning text-dark shadow">
                    <div class="card-body">
                        <h5 class="card-title">Cost Per Lead (CPL)</h5>
                        <h3 class="card-text" id="kpi-cpl">₹0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card kpi-card bg-success text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Ad-to-Enroll %</h5>
                        <h3 class="card-text" id="kpi-enroll-rate">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header">
                Campaign & Ad Set Performance
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Campaign / Ad Set</th>
                                <th>Status</th>
                                <th>Spend</th>
                                <th>Leads</th>
                                <th>CPL</th>
                            </tr>
                        </thead>
                        <tbody id="ad-campaign-table">
                            <tr><td colspan="5" class="text-center text-muted">Awaiting connection...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/meta_ads.js" defer></script>
</body>

</html>