<?php
// SECURITY CHECK: Ensure user is logged in
include 'header.php'; 
$user_name = $_SESSION['full_name'] ?? 'Admin User';
?>

        <h2 class="mb-4">Welcome, <?php echo $user_name; ?>!</h2>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card kpi-card bg-info text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">New Inquiries (Today)</h5>
                        <h3 class="card-text" id="kpi-inquiries-today">0</h3>
                        <p class="card-text"><small id="kpi-inquiries-week">0 this week</small></p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card kpi-card bg-success text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Total Enrollments (MoM)</h5>
                        <h3 class="card-text" id="kpi-enrollments">0</h3>
                        <p class="card-text"><small>This month</small></p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card kpi-card bg-warning text-dark shadow">
                    <div class="card-body">
                        <h5 class="card-title">Inquiry-to-Enrollment %</h5>
                        <h3 class="card-text" id="kpi-conversion">0%</h3>
                        <p class="card-text"><small>Rolling month</small></p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card kpi-card bg-primary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Fees Collected (MoM)</h5>
                        <h3 class="card-text" id="kpi-fees">₹0</h3>
                        <p class="card-text"><small>This month</small></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        Admissions Pipeline Funnel (Open Deals)
                    </div>
                    <div class="card-body">
                        <canvas id="admissionsFunnelChart" style="max-height: 400px;"></canvas>
                        <div class="text-center text-muted mt-3" id="funnel-loading">Loading funnel data...</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        Upcoming Batch Status
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Course</th>
                                    <th>Start Date</th>
                                    <th>Seats</th>
                                </tr>
                            </thead>
                            <tbody id="batch-status-table">
                                <tr><td colspan="4" class="text-center text-muted">Loading batches...</td></tr>
                            </tbody>
                        </table>
                        
                        <h6 class="mt-4">Quick Links</h6>
                        <ul class="list-group list-group-flush border rounded-3">
                            <li class="list-group-item"><a href="/students.php">
                                <i class="bi bi-person-plus me-2"></i> View All Leads/Students</a>
                            </li>
                            <li class="list-group-item"><a href="/enrollments.php">
                                <i class="bi bi-bar-chart me-2"></i> Manage Enrollment Pipeline</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="/assets/js/dashboard-charts.js" defer></script>
<?php include 'footer.php'; ?>