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
    <title>CRM - Enrollments Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Training Academy CRM</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/students.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/enrollments.php">Enrollments</a></li>
                    <li class="nav-item"><a class="nav-link" href="/settings.php">Settings</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Enrollments Pipeline</h2>
            </div>

        <div class="kanban-board-container">
            <div class="kanban-board" id="kanban-board">
                </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/enrollments.js" defer></script>

    <div class="modal fade" id="lostModal" tabindex="-1" aria-labelledby="lostModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lostModalLabel">Mark Enrollment as Lost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="lost-enrollment-form">
                    <input type="hidden" id="lost-enrollment-id" name="enrollment_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="lost_reason" class="form-label">Lost Reason <span class="text-danger">*</span></label>
                            <select class="form-select mb-2" id="lost_reason" name="lost_reason" required>
                                <option value="">-- Select Reason --</option>
                                <option value="Joined Competitor">Joined Competitor</option>
                                <option value="Not Interested Anymore">Not Interested Anymore</option>
                                <option value="Price/Budget Issue">Price/Budget Issue</option>
                                <option value="Unqualified Lead">Unqualified Lead</option>
                                <option value="No Response">No Response</option>
                            </select>
                            <textarea class="form-control" name="lost_reason_details" rows="3" placeholder="Add specific notes... (Optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Lost</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>