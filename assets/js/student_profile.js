// /assets/js/student_profile.js

const API_STUDENTS = '/api/v1/students.php';
const API_ACTIVITY = '/api/v1/activity_log.php';

// --- DOM Elements ---
const profileName = document.getElementById('profile-name');
const studentStatusBadge = document.getElementById('student-status-badge');
const detailFields = {
    email: document.getElementById('detail-email'),
    phone: document.getElementById('detail-phone'),
    createdAt: document.getElementById('detail-created-at'),
    courseInterested: document.getElementById('detail-course-interested'),
    leadSource: document.getElementById('detail-lead-source'),
    leadScore: document.getElementById('detail-lead-score'),
    qualification: document.getElementById('detail-qualification'),
    workExperience: document.getElementById('detail-work-experience')
};
const enrollmentHistoryList = document.getElementById('enrollment-history');
const activityTimeline = document.getElementById('activity-timeline');
const addActivityForm = document.getElementById('add-activity-form');
const activityStudentId = document.getElementById('activity-student-id');

let studentId; // Store the ID of the student being viewed

/**
 * Utility function to get the student ID from the URL
 * @returns {string|null}
 */
function getStudentIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

/**
 * Fetches student data and renders the profile
 */
async function loadStudentProfile() {
    studentId = getStudentIdFromUrl();
    if (!studentId) {
        profileName.textContent = 'Error: Student ID not found in URL.';
        return;
    }

    // Set the hidden student ID field for the activity form
    activityStudentId.value = studentId;

    try {
        const studentResponse = await fetch(`${API_STUDENTS}?id=${studentId}`);
        if (!studentResponse.ok) {
            const errorData = await studentResponse.json();
            throw new Error(errorData.error || 'Failed to fetch student data');
        }

        const student = await studentResponse.json();

        renderStudentDetails(student);
        renderEnrollmentHistory(student.enrollments);

        // Load activities separately
        await loadActivityTimeline();

    } catch (error) {
        console.error('Error loading student profile:', error);
        profileName.textContent = 'Error loading profile.';
        alert('Failed to load profile: ' + error.message);
    }
}

/**
 * Renders the main details section
 */
function renderStudentDetails(student) {
    profileName.textContent = student.full_name;

    studentStatusBadge.textContent = student.status;
    studentStatusBadge.className = `badge ${getBadgeClass(student.status)} text-capitalize`;

    detailFields.email.textContent = student.email || 'N/A';
    detailFields.phone.textContent = student.phone || 'N/A';
    detailFields.createdAt.textContent = student.created_at ? new Date(student.created_at).toLocaleDateString() : 'N/A';
    detailFields.courseInterested.textContent = student.course_name || 'N/A';
    detailFields.leadSource.textContent = student.lead_source || 'N/A';
    detailFields.qualification.textContent = student.qualification || 'N/A';
    detailFields.workExperience.textContent = student.work_experience || 'N/A';

    // Lead Score Badge
    detailFields.leadScore.textContent = student.lead_score;
    detailFields.leadScore.className = `badge ${student.lead_score > 60 ? 'bg-success' : 'bg-secondary'}`;
}

/**
 * Renders the enrollment history list
 */
function renderEnrollmentHistory(enrollments) {
    enrollmentHistoryList.innerHTML = ''; // Clear previous

    if (enrollments.length === 0) {
        enrollmentHistoryList.innerHTML = '<li class="list-group-item text-center text-muted">No enrollment records found.</li>';
        return;
    }

    enrollments.forEach(enrollment => {
        const listItem = document.createElement('li');
        listItem.className = 'list-group-item';

        const statusBadge = getStatusBadge(enrollment.status);
        const feeStatus = `
            ₹${enrollment.total_fee_agreed} Agreed | Paid: ₹${enrollment.total_fee_paid} | Due: <span class="text-danger">₹${enrollment.balance_due}</span>
        `;

        listItem.innerHTML = `
            <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">${enrollment.course_name || 'No Course Assigned'}</h6>
                ${statusBadge}
            </div>
            <small class="text-muted">Pipeline Stage: ${enrollment.pipeline_stage}</small><br>
            <small class="text-muted">${feeStatus}</small>
        `;
        enrollmentHistoryList.appendChild(listItem);
    });
}

/**
 * Renders the activity timeline
 */
function renderActivityTimeline(activities) {
    activityTimeline.innerHTML = ''; // Clear previous

    if (activities.length === 0) {
        activityTimeline.innerHTML = '<div class="text-center text-muted">No activities logged yet.</div>';
        return;
    }

    activities.forEach(activity => {
        const card = document.createElement('div');
        card.className = 'card mb-3 activity-card';

        const timestamp = new Date(activity.timestamp).toLocaleString();
        const typeBadge = getLogTypeBadge(activity.activity_type);

        card.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">${typeBadge}</h6>
                    <small class="text-muted">${timestamp} by ${activity.user_name || 'System'}</small>
                </div>
                <p class="card-text">${activity.content}</p>
            </div>
        `;
        activityTimeline.appendChild(card);
    });
}

/**
 * Fetches the activity log and calls the render function
 */
async function loadActivityTimeline() {
    activityTimeline.innerHTML = '<div class="text-center text-muted">Loading timeline...</div>';
    try {
        const response = await fetch(`${API_ACTIVITY}?student_id=${studentId}`);
        if (!response.ok) throw new Error('Failed to fetch activities');

        const activities = await response.json();
        renderActivityTimeline(activities);

    } catch (error) {
        console.error('Error loading activity timeline:', error);
        activityTimeline.innerHTML = `<div class="text-center text-danger">Failed to load activities.</div>`;
    }
}

/**
 * Handles the "Add Activity" form submission
 */
async function handleAddActivity(event) {
    event.preventDefault();

    const formData = new FormData(addActivityForm);
    const activityData = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(API_ACTIVITY, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(activityData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to log activity');
        }

        addActivityForm.reset(); // Clear form inputs
        document.getElementById('activity_type').value = ''; // Reset dropdown
        await loadActivityTimeline(); // Refresh the timeline

    } catch (error) {
        console.error('Failed to log activity:', error);
        alert('Error: ' + error.message);
    }
}

// --- Utility Functions for Badges ---

function getBadgeClass(status) {
    switch (status) {
        case 'active_student':
            return 'bg-success';
        case 'alumni':
            return 'bg-primary';
        case 'inquiry':
        default:
            return 'bg-info';
    }
}

function getStatusBadge(status) {
    switch (status) {
        case 'enrolled':
            return '<span class="badge bg-success">Enrolled</span>';
        case 'lost':
            return '<span class="badge bg-danger">Lost</span>';
        case 'open':
        default:
            return '<span class="badge bg-warning text-dark">Open Deal</span>';
    }
}

function getLogTypeBadge(type) {
    switch (type) {
        case 'call':
            return '<i class="bi bi-telephone-fill me-1"></i> Call Log';
        case 'email':
            return '<i class="bi bi-envelope-fill me-1"></i> Email Sent';
        case 'sms':
            return '<i class="bi bi-chat-text-fill me-1"></i> SMS Sent';
        case 'status_change':
            return '<i class="bi bi-shuffle me-1"></i> Status Change';
        case 'note':
        default:
            return '<i class="bi bi-stickies-fill me-1"></i> Note Added';
    }
}


// --- Event Listeners & Initialization ---

document.addEventListener('DOMContentLoaded', () => {
    loadStudentProfile();
    addActivityForm.addEventListener('submit', handleAddActivity);
});