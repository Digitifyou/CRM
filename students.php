<?php
// /students.php
require_once 'header.php'; // Handles Session, Auth, Config, and Top Navigation
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Student & Lead Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="bi bi-plus-lg"></i> Add New Inquiry
        </button>
    </div>

    <div class="mb-3">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="search-bar" placeholder="Search by name, phone, or email...">
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Course Interested</th>
                        <th>Lead Score</th>
                        <th>Inquiry Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="student-list-table">
                    <tr>
                        <td colspan="7" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">Add New Inquiry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="add-student-form">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lead_source" class="form-label">Lead Source</label>
                            <select class="form-select" id="lead_source" name="lead_source">
                                <option value="">-- Select Source --</option>
                                <option value="Meta">Meta</option>
                                <option value="Google">Google</option>
                                <option value="Referral">Referral</option>
                                <option value="Walk-in">Walk-in</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="course_interested_id" class="form-label">Course Interested In</label>
                        <select class="form-select" id="course_interested_id" name="course_interested_id">
                            <option value="">-- Select Course --</option>
                            </select>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" placeholder="e.g., BE, BSc, 12th Pass">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="work_experience" class="form-label">Work Experience</label>
                            <select class="form-select" id="work_experience" name="work_experience">
                                <option value="">-- Select Experience --</option>
                                <option value="Fresher">Fresher</option>
                                <option value="0-2 Years">0-2 Years</option>
                                <option value="2-5 Years">2-5 Years</option>
                                <option value="5+ Years">5+ Years</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Inquiry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/students.js" defer></script>

<?php
require_once 'footer.php'; // Closes main container, footer, and body
?>