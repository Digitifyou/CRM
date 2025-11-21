<?php
// SECURITY CHECK: Ensure user is logged in
include 'header.php'; 
?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0" id="profile-name">Loading Student...</h2>
            <span class="badge bg-secondary" id="student-status-badge"></span>
        </div>

        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-4 shadow">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-person-circle me-2"></i> Student Details
                    </div>
                    
                    <form id="student-details-form">
                        <div class="card-body">
                            <div class="mb-3">
                                <strong><i class="bi bi-gear"></i> Status:</strong> 
                                <select class="form-select form-select-sm detail-input" name="status" id="input-status" required>
                                    <option value="inquiry">Inquiry</option>
                                    <option value="active_student">Active Student</option>
                                    <option value="alumni">Alumni</option>
                                </select>
                                <span class="detail-value badge bg-info" id="detail-status"></span>
                            </div>
                            
                            <p class="card-text">
                                <strong><i class="bi bi-envelope"></i> Email:</strong> 
                                <span class="detail-value" id="detail-email">N/A</span>
                                <input type="email" class="form-control form-control-sm detail-input" name="email" id="input-email"><br>
                                
                                <strong><i class="bi bi-phone"></i> Phone:</strong> 
                                <span class="detail-value" id="detail-phone">N/A</span>
                                <input type="tel" class="form-control form-control-sm detail-input" name="phone" id="input-phone" required><br>
                                
                                <strong><i class="bi bi-calendar"></i> Inquiry Date:</strong> 
                                <span class="detail-value" id="detail-created-at">N/A</span>
                                <input type="text" class="form-control form-control-sm detail-input" disabled>
                            </p>
                            
                            <hr>
                            <h5>Lead Information</h5>
                            <p class="card-text">
                                <strong>Interested Course:</strong> 
                                <span class="detail-value" id="detail-course-interested">N/A</span>
                                <select class="form-select form-select-sm detail-input" name="course_interested_id" id="input-course-interested"></select><br>
                                
                                <strong>Lead Source:</strong> 
                                <span class="detail-value" id="detail-lead-source">N/A</span>
                                <select class="form-select form-select-sm detail-input" name="lead_source" id="input-lead-source">
                                    <option value="">N/A</option>
                                    <option value="Meta">Meta</option>
                                    <option value="Google">Google</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Walk-in">Walk-in</option>
                                    <option value="Other">Other</option>
                                </select><br>

                                <strong>Lead Score:</strong>
                                <span class="badge bg-secondary" id="detail-lead-score">0</span>
                                <input type="text" class="form-control form-control-sm detail-input" disabled>
                            </p>
                            
                            <hr>
                            <h5>Background</h5>
                            <p class="card-text">
                                <strong>Qualification:</strong> 
                                <span class="detail-value" id="detail-qualification">N/A</span>
                                <input type="text" class="form-control form-control-sm detail-input" name="qualification" id="input-qualification"><br>
                                
                                <strong>Work Experience:</strong> 
                                <span class="detail-value" id="detail-work-experience">N/A</span>
                                <select class="form-select form-select-sm detail-input" name="work_experience" id="input-work-experience">
                                    <option value="">N/A</option>
                                    <option value="Fresher">Fresher</option>
                                    <option value="0-2 Years">0-2 Years</option>
                                    <option value="2-5 Years">2-5 Years</option>
                                    <option value="5+ Years">5+ Years</option>
                                </select>
                            </p>
                        </div>
                        
                        <div class="card-footer text-end">
                            <button type="button" class="btn btn-primary btn-sm" id="edit-toggle-button">
                                <i class="bi bi-pencil"></i> Edit Profile
                            </button>
                            <button type="submit" class="btn btn-success btn-sm d-none" id="save-button">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm d-none" id="cancel-button">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card mb-4 shadow">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-bar-chart-line me-2"></i> Enrollment & Deal History
                    </div>
                    <ul class="list-group list-group-flush" id="enrollment-history">
                        <li class="list-group-item text-center text-muted">No enrollment records found.</li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-4 shadow">
                    <div class="card-body">
                        <h5 class="card-title">Log New Activity</h5>
                        <form id="add-activity-form">
                            <input type="hidden" name="student_id" id="activity-student-id">
                            <div class="row mb-3">
                                <div class="col-4">
                                    <select class="form-select" id="activity_type" name="activity_type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="note">Note</option>
                                        <option value="call">Call</option>
                                        <option value="email">Email</option>
                                        <option value="sms">SMS</option>
                                        <option value="status_change">Status Change</option>
                                    </select>
                                </div>
                                <div class="col-8">
                                    <textarea class="form-control" name="content" rows="1" placeholder="Add a note, call summary, or email content..." required></textarea>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm">Log Activity</button>
                            </div>
                        </form>
                    </div>
                </div>

                <h4 class="mb-3">Activity Timeline</h4>
                <div id="activity-timeline">
                    <div class="text-center text-muted">Loading timeline...</div>
                </div>
            </div>
        </div>

    <script src="/assets/js/student_profile.js" defer></script>
    <script src="/assets/js/settings_courses.js" defer></script>
<?php include 'footer.php'; ?>