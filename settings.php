<?php
// /settings.php
// SECURITY CHECK: Ensure user is logged in
require_once __DIR__ . '/api/config.php'; 

// 1. Authentication Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit;
}

// 2. Role Permission Check
$allowed_roles = ['admin', 'owner'];
$current_role = $_SESSION['role'] ?? '';

if (!in_array($current_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link active" href="/settings.php">Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="/meta_ads.php">Meta Ads</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container">
        <h2 class="mb-4">System Settings</h2>

        <div class="row">
            <div class="col-md-3">
                <div class="list-group" id="settings-tabs" role="tablist">
                    <a class="list-group-item list-group-item-action" id="courses-tab" data-bs-toggle="list" href="#courses-pane" role="tab">
                        <i class="bi bi-book me-2"></i> Course Management
                    </a>
                    <a class="list-group-item list-group-item-action" id="batches-tab" data-bs-toggle="list" href="#batches-pane" role="tab">
                        <i class="bi bi-calendar-event me-2"></i> Batch Management
                    </a>
                    <a class="list-group-item list-group-item-action" id="pipeline-tab" data-bs-toggle="list" href="#pipeline-pane" role="tab">
                        <i class="bi bi-diagram-3 me-2"></i> Pipeline Editor
                    </a>
                    <a class="list-group-item list-group-item-action active" id="users-tab" data-bs-toggle="list" href="#users-pane" role="tab">
                        <i class="bi bi-people me-2"></i> User Management
                    </a>
                    <a class="list-group-item list-group-item-action" id="custom-fields-tab" data-bs-toggle="list" href="#custom-fields-pane" role="tab">
                        <i class="bi bi-list-columns-reverse me-2"></i> Custom Fields
                    </a>
                    <a class="list-group-item list-group-item-action" id="integrations-tab" data-bs-toggle="list" href="#integrations-pane" role="tab">
                        <i class="bi bi-globe me-2"></i> Integrations
                    </a>
                    <a class="list-group-item list-group-item-action" id="bulk-import-tab" data-bs-toggle="list" href="#bulk-import-pane" role="tab">
                        <i class="bi bi-cloud-upload me-2"></i> Bulk Import
                    </a>
                </div>
            </div>
            <div class="col-md-9">
                <div class="tab-content">

                    <div class="tab-pane fade" id="courses-pane" role="tabpanel">
                         <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Manage Courses</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class="bi bi-plus-lg"></i> Add New Course
                            </button>
                        </div>
                        <div class="card"><div class="card-body"><table class="table table-hover"><thead><tr><th>Course Name</th><th>Standard Fee (₹)</th><th>Duration</th><th class="text-end">Actions</th></tr></thead><tbody id="course-list-table"><tr><td colspan="4" class="text-center">Loading...</td></tr></tbody></table></div></div>
                    </div>

                    <div class="tab-pane fade" id="batches-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Manage Batches</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                                <i class="bi bi-plus-lg"></i> Add New Batch
                            </button>
                        </div>
                        <div class="card"><div class="card-body"><table class="table table-hover"><thead><tr><th>Batch Name</th><th>Course</th><th>Start Date</th><th>Seats</th><th class="text-end">Actions</th></tr></thead><tbody id="batch-list-table"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody></table></div></div>
                    </div>

                    <div class="tab-pane fade" id="pipeline-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Edit Admissions Funnel Stages</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStageModal">
                                <i class="bi bi-plus-lg"></i> Add New Stage
                            </button>
                        </div>
                        <div class="alert alert-info">Drag and drop stages to re-order.</div>
                        <ul class="list-group list-group-flush" id="pipeline-stages-list"></ul>
                    </div>

                    <div class="tab-pane fade show active" id="users-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Manage CRM Users</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="bi bi-person-plus-fill"></i> Invite New User
                            </button>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead>
                                        <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
                                    </thead>
                                    <tbody id="user-list-table"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="custom-fields-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Custom Fields</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomFieldModal">
                                <i class="bi bi-plus-lg"></i> Add New Field
                            </button>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead><tr><th>Field Name</th><th>Key</th><th>Type</th><th>Scoring</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody id="custom-fields-list-table"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="integrations-pane" role="tabpanel">
                        <h3 class="mb-4">Lead Capture Integrations</h3>

                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i> Google Sheets Integration
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> Active</span>
                                    <span class="text-muted small">Service Account Key is installed.</span>
                                </div>
                                
                                <h5>How to Import from Private Sheets</h5>
                                <ol class="text-muted small" style="line-height: 1.8;">
                                    <li class="mb-2">
                                        <strong>Share the Sheet:</strong> Open your Google Sheet, click "Share", and add this email as a <strong>Viewer</strong>:<br>
                                        <div class="input-group input-group-sm mt-1" style="max-width: 550px;">
                                            <span class="input-group-text bg-light">Service Email</span>
                                            <input type="text" class="form-control bg-white text-dark" value="flowsystmz-crm@digitifyu-401012.iam.gserviceaccount.com" readonly id="service-email-input">
                                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('service-email-input').value)">
                                                <i class="bi bi-clipboard"></i> Copy
                                            </button>
                                        </div>
                                    </li>
                                    <li class="mb-1"><strong>Copy URL:</strong> Copy the full web address (URL) of your Google Sheet from the browser bar.</li>
                                    <li><strong>Import:</strong> Go to the <strong>Bulk Import</strong> tab, select "Google Sheet URL", paste the link, and click <strong>Load Columns</strong>.</li>
                                </ol>
                            </div>
                        </div>

                        <div class="card mb-3" data-platform="meta">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-meta me-2"></i> Meta Lead Ads</span>
                                <span id="meta-connection-status" class="badge bg-danger">Disconnected</span>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-primary btn-sm mb-3" id="meta-login-button">Connect with Facebook</button>
                                <p id="auth-status-message" class="text-muted small d-none">Initializing...</p>
                                
                                <h6>Field Mapping</h6>
                                <form id="meta-mapping-form">
                                    <table class="table table-bordered table-sm">
                                        <thead><tr><th>Meta Field</th><th>CRM Field</th></tr></thead>
                                        <tbody id="meta-mapping-table"><tr><td colspan="2" class="text-center text-muted">Load Meta Forms to map fields.</td></tr></tbody>
                                    </table>
                                    <button type="submit" class="btn btn-success btn-sm" id="save-mapping-button" disabled>Save Mapping</button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header bg-secondary text-white"><i class="bi bi-browser-chrome me-2"></i> Website Webhook</div>
                            <div class="card-body">
                                <input type="text" class="form-control mb-2" readonly value="[Your URL]/api/v1/webhook.php?key=SECURE-KEY">
                                <small class="text-muted">POST data here to create leads.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="bulk-import-pane" role="tabpanel">
                        <h3 class="mb-4">Bulk Import Leads</h3>

                        <div class="card mb-4 border-success bg-light">
                            <div class="card-body py-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> Active</span>
                                    <h6 class="mb-0 text-success">Google Sheets Integration Configured</h6>
                                </div>
                                <p class="small text-muted mb-2">Follow these steps to import from a private sheet:</p>
                                <ol class="small text-muted ps-3 mb-0">
                                    <li><strong>Share the Sheet:</strong> Open your Google Sheet, click "Share", and add this email as a <strong>Viewer</strong>:
                                        <div class="input-group input-group-sm my-1" style="max-width: 500px;">
                                            <input type="text" class="form-control bg-white" value="flowsystmz-crm@digitifyu-401012.iam.gserviceaccount.com" readonly id="service-email-bulk">
                                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('service-email-bulk').value)">Copy</button>
                                        </div>
                                    </li>
                                    <li><strong>Copy URL:</strong> Paste the full browser URL into the field below.</li>
                                </ol>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Source</h5>
                                <form id="bulk-import-form">
                                    <div class="mb-3">
                                        <label class="form-label">Google Sheet URL or Upload CSV</label>
                                        <div class="input-group">
                                            <input type="url" class="form-control" id="google_sheet_link" name="google_sheet_link" placeholder="https://docs.google.com/spreadsheets/d/...">
                                            <span class="input-group-text">OR</span>
                                            <input type="file" class="form-control" id="csv_file_upload" name="csv_file_upload" accept=".csv">
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive mt-3">
                                        <table class="table table-bordered table-sm">
                                            <thead><tr><th>Source Column</th><th>Target CRM Field</th></tr></thead>
                                            <tbody id="import-mapping-table"><tr><td colspan="2" class="text-center text-muted">Enter source and click Load.</td></tr></tbody>
                                        </table>
                                    </div>
                                    
                                    <div id="import-status-alert" class="alert d-none mt-3" role="alert"></div>

                                    <div class="text-end mt-3">
                                        <button type="button" class="btn btn-secondary me-2" id="load-columns-button">Load Columns</button>
                                        <button type="submit" class="btn btn-primary" id="start-import-button" disabled>Start Import</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
    
    <div class="modal fade" id="addCourseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="add-course-form"><div class="modal-body"><input class="form-control mb-3" name="course_name" placeholder="Name" required><input type="number" class="form-control mb-3" name="standard_fee" placeholder="Fee" required><input class="form-control" name="duration" placeholder="Duration"></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div></form></div></div></div>

    <div class="modal fade" id="addBatchModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="add-batch-form"><div class="modal-body"><input class="form-control mb-3" name="batch_name" placeholder="Batch Name" required><select class="form-select mb-3" id="batch-course-id" name="course_id" required></select><div class="row"><div class="col"><input type="date" class="form-control" name="start_date" required></div><div class="col"><input type="number" class="form-control" name="total_seats" placeholder="Seats" required></div></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div></form></div></div></div>

    <div class="modal fade" id="addStageModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Stage</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="add-stage-form"><div class="modal-body"><input class="form-control" name="stage_name" placeholder="Stage Name" required></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Add</button></div></form></div></div></div>

    <div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Invite User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="add-user-form"><div class="modal-body"><input class="form-control mb-3" id="user_full_name" name="full_name" placeholder="Full Name" required><input class="form-control mb-3" id="username" name="username" placeholder="Username" required><input type="password" class="form-control mb-3" id="password" name="password" placeholder="Password" required><select class="form-select" id="role" name="role"><option value="counselor">Counselor</option><option value="admin">Admin</option></select></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Invite</button></div></form></div></div></div>

    <div class="modal fade" id="addCustomFieldModal" tabindex="-1" aria-labelledby="addCustomFieldModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomFieldModalLabel">Define New Lead Field</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="add-custom-field-form">
                    <input type="hidden" name="field_id" id="field_id_input">
                    <input type="hidden" name="scoring_rules_json_hidden" id="scoring_rules_json_hidden"> 
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Display Name</label>
                            <input type="text" class="form-control" id="field_name" name="field_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Key</label>
                            <input type="text" class="form-control" id="field_key" name="field_key" placeholder="e.g. expected_salary">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" id="field_type" name="field_type" required onchange="toggleFieldSections()">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="select">Dropdown</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="field-options-group">
                            <label class="form-label">Options (comma-separated)</label>
                            <textarea class="form-control" id="options" name="options"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_required" id="is_required">
                                    <label class="form-check-label">Required</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_score_field" id="is_score_field">
                                    <label class="form-check-label">Lead Scoring</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 d-none" id="score-rules-panel">
                            <hr>
                            <label class="form-label">Scoring Rules</label>
                            <div class="mb-2"><small class="text-success">High (100pts):</small> <input class="form-control form-control-sm score-input-rules" data-level="High"></div>
                            <div class="mb-2"><small class="text-warning">Medium (50pts):</small> <input class="form-control form-control-sm score-input-rules" data-level="Medium"></div>
                            <div class="mb-2"><small class="text-info">Low (25pts):</small> <input class="form-control form-control-sm score-input-rules" data-level="Low"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Field</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/assets/js/settings_import.js"></script>
    <script src="/assets/js/settings_integrations.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/settings_courses.js"></script>
    <script src="/assets/js/settings_pipeline.js"></script>
    <script src="/assets/js/settings_users.js"></script>
    <script src="/assets/js/settings_batches.js"></script>
    <script src="/assets/js/settings_custom_fields.js"></script>
    
    <script>
        window.META_APP_ID = '1170946974995892'; 
        window.fbAsyncInit = function() {
            FB.init({ appId: window.META_APP_ID, cookie: true, xfbml: true, version: 'v19.0', status: true, oauth: true });
            checkMetaConnectionStatus();
        };
        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = window.location.protocol + "//connect.facebook.net/en_US/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
    </script>
    
</body>
</html>