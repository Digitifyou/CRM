// /assets/js/student_profile.js

const API_STUDENTS = '/api/v1/students.php';
const API_ACTIVITY = '/api/v1/activity_log.php';
const API_COURSES = '/api/v1/courses.php'; // New API dependency
const API_ENROLLMENTS = '/api/v1/enrollments.php'; // New API dependency

// --- DOM Elements ---
const profileName = document.getElementById('profile-name');
const studentStatusBadge = document.getElementById('student-status-badge');
const enrollmentHistoryList = document.getElementById('enrollment-history');
const activityTimeline = document.getElementById('activity-timeline');
const addActivityForm = document.getElementById('add-activity-form');
const activityStudentId = document.getElementById('activity-student-id');

// Elements for Editing
const studentDetailsForm = document.getElementById('student-details-form');
// MODIFIED: Use a robust selector for the edit button
const editToggleButton = document.getElementById('edit-toggle-button');
const saveButton = document.getElementById('save-button');
const cancelButton = document.getElementById('cancel-button');

const detailFields = {
    // Note: We use the detail-value span IDs for display
    status: document.getElementById('detail-status'),
    email: document.getElementById('detail-email'),
    phone: document.getElementById('detail-phone'),
    createdAt: document.getElementById('detail-created-at'),
    courseInterested: document.getElementById('detail-course-interested'),
    leadSource: document.getElementById('detail-lead-source'),
    leadScore: document.getElementById('detail-lead-score'),
    qualification: document.getElementById('detail-qualification'),
    workExperience: document.getElementById('detail-work-experience')
};

let studentId; // Store the ID of the student being viewed
let currentStudentData = {}; // Cache the current data
let courseList = []; // Cache course list for dropdown

/**
 * Utility function to get the student ID from the URL
 * @returns {string|null}
 */
function getStudentIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

/**
 * Fetches courses for the dropdown
 */
async function loadCoursesForDropdown() {
    try {
        const response = await fetch(API_COURSES);
        if (!response.ok) throw new Error('Could not fetch courses');
        courseList = await response.json();
    } catch (error) {
        console.error('Error loading courses:', error);
    }
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

    activityStudentId.value = studentId;
    await loadCoursesForDropdown(); // Load courses first

    try {
        const studentResponse = await fetch(`${API_STUDENTS}?id=${studentId}`);
        if (!studentResponse.ok) {
            const errorData = await studentResponse.json();
            throw new Error(errorData.error || 'Failed to fetch student data');
        }

        const student = await studentResponse.json();
        currentStudentData = student; // Cache current data
        
        renderStudentDetails(student);
        renderEnrollmentHistory(student.enrollments);
        await loadActivityTimeline();

    } catch (error) {
        console.error('Error loading student profile:', error);
        profileName.textContent = 'Error loading profile.';
        alert('Failed to load profile: ' + error.message);
    }
}

/**
 * Renders the main details section and populates inputs
 */
function renderStudentDetails(student) {
    profileName.textContent = student.full_name;

    // Status Badge (Top Right)
    studentStatusBadge.textContent = student.status;
    studentStatusBadge.className = `badge ${getBadgeClass(student.status)} text-capitalize`;

    // Static Display Fields
    detailFields.status.textContent = student.status.toUpperCase();
    detailFields.email.textContent = student.email || 'N/A';
    detailFields.phone.textContent = student.phone || 'N/A';
    detailFields.createdAt.textContent = student.created_at ? new Date(student.created_at).toLocaleDateString() : 'N/A';
    detailFields.courseInterested.textContent = student.course_name || 'N/A';
    detailFields.leadSource.textContent = student.lead_source || 'N/A';
    detailFields.qualification.textContent = student.qualification || 'N/A';
    detailFields.workExperience.textContent = student.work_experience || 'N/A';
    detailFields.leadScore.textContent = student.lead_score;
    detailFields.leadScore.className = `badge ${student.lead_score > 60 ? 'bg-success' : 'bg-secondary'}`;

    // Editable Inputs (Populate values)
    document.getElementById('input-status').value = student.status;
    document.getElementById('input-email').value = student.email || '';
    document.getElementById('input-phone').value = student.phone || '';
    document.getElementById('input-qualification').value = student.qualification || '';
    document.getElementById('input-lead-source').value = student.lead_source || '';
    document.getElementById('input-work-experience').value = student.work_experience || '';
    
    // Course Dropdown (Special handling)
    const courseSelect = document.getElementById('input-course-interested');
    courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
    courseList.forEach(course => {
        const option = document.createElement('option');
        option.value = course.course_id;
        option.textContent = course.course_name;
        if (course.course_id === student.course_interested_id) {
            option.selected = true;
        }
        courseSelect.appendChild(option);
    });

    // CRITICAL: Ensure view mode is active on load
    toggleEditMode(false);
}

/**
 * Toggles the UI between view and edit modes
 * @param {boolean} isEditing - True to switch to inputs, false to switch to spans
 */
function toggleEditMode(isEditing) {
    const detailInputs = studentDetailsForm.querySelectorAll('.detail-input');
    const detailValues = studentDetailsForm.querySelectorAll('.detail-value');

    detailInputs.forEach(input => {
        input.classList.toggle('d-block', isEditing);
        input.classList.toggle('d-none', !isEditing);
    });

    detailValues.forEach(value => {
        value.classList.toggle('d-none', isEditing);
        value.classList.toggle('d-inline-block', !isEditing);
    });
    
    // Toggle buttons
    if (editToggleButton) editToggleButton.classList.toggle('d-none', isEditing);
    if (saveButton) saveButton.classList.toggle('d-none', !isEditing);
    if (cancelButton) cancelButton.classList.toggle('d-none', !isEditing);
}

/**
 * Sends updated data to the API
 */
async function handleSaveProfile(event) {
    event.preventDefault();

    const formData = new FormData(studentDetailsForm);
    const updatedData = {};

    // Only include fields that are part of the PUT request structure
    const editableFields = ['full_name', 'email', 'phone', 'status', 'course_interested_id', 'lead_source', 'qualification', 'work_experience'];
    
    // Cache the old status and the new status
    const oldStatus = currentStudentData.status; 
    let newStatus = '';
    
    // Reconstruct data, ensuring nulls for empty strings/selects
    editableFields.forEach(key => {
        let value = formData.get(key);

        if (key === 'status') {
             newStatus = value; // Capture the new status
        }
        
        // Special case for phone and email which are standard inputs in the template but not fully implemented here
        if (key === 'phone' || key === 'email') {
             const input = document.getElementById(`input-${key}`);
             value = input.value;
        }

        // Handle the case where the input ID doesn't directly map to a simple name (like course_interested_id)
        if (key === 'course_interested_id') {
             value = formData.get('course_interested_id') || null;
        }

        if (value === '') {
            updatedData[key] = null;
        } else if (value !== null) {
            updatedData[key] = value;
        }
    });

    try {
        const response = await fetch(`${API_STUDENTS}?id=${studentId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updatedData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to update student profile');
        }

        // --- NEW LOGIC: Check if the student status changed to 'inquiry' ---
        if (oldStatus !== newStatus && newStatus === 'inquiry') {
            await reopenEnrollmentDeal();
            alert('Profile updated and Enrollment deal REOPENED!');
        } else {
            alert('Profile updated successfully!');
        }
        // --- END NEW LOGIC ---
        
        toggleEditMode(false); // Switch back to view mode
        await loadStudentProfile(); // Reload data to show fresh values

    } catch (error) {
        console.error('Save failed:', error);
        alert('Error saving profile: ' + error.message);
    }
}


/**
 * Sends a PUT request to the Enrollments API to set the latest deal status to 'open'.
 */
async function reopenEnrollmentDeal() {
    // 1. Get the latest enrollment ID from the cached data (assuming the first in the array is the latest)
    if (!currentStudentData.enrollments || currentStudentData.enrollments.length === 0) {
        console.warn('Cannot reopen enrollment: No deal found for this student.');
        return; 
    }
    
    const latestEnrollmentId = currentStudentData.enrollments[0].enrollment_id;
    
    try {
        const response = await fetch(`${API_ENROLLMENTS}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                enrollment_id: latestEnrollmentId,
                status: 'open',       // Reopen the status
                new_stage_id: 1       // Move to the first stage: New Inquiry
            })
        });

        if (!response.ok) throw new Error('Failed to reopen enrollment deal.');

        console.log(`Enrollment ID ${latestEnrollmentId} reopened and stage reset successfully.`);
        
    } catch (error) {
        console.error('Error reopening deal:', error);
    }
}


// --- Enrollment and Activity Logic (Unchanged) ---

function renderEnrollmentHistory(enrollments) {
    enrollmentHistoryList.innerHTML = ''; 

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

function renderActivityTimeline(activities) {
    activityTimeline.innerHTML = ''; 

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

        addActivityForm.reset(); 
        document.getElementById('activity_type').value = ''; 
        await loadActivityTimeline(); 

    } catch (error) {
        console.error('Failed to log activity:', error);
        alert('Error: ' + error.message);
    }
}

// --- Utility Functions for Badges ---
function getBadgeClass(status) {
    switch (status) {
        case 'active_student': return 'bg-success';
        case 'alumni': return 'bg-primary';
        case 'inquiry':
        default: return 'bg-info';
    }
}

function getStatusBadge(status) {
    switch (status) {
        case 'enrolled': return '<span class="badge bg-success">Enrolled</span>';
        case 'lost': return '<span class="badge bg-danger">Lost</span>';
        case 'open':
        default: return '<span class="badge bg-warning text-dark">Open Deal</span>';
    }
}

function getLogTypeBadge(type) {
    switch (type) {
        case 'call': return '<i class="bi bi-telephone-fill me-1"></i> Call Log';
        case 'email': return '<i class="bi bi-envelope-fill me-1"></i> Email Sent';
        case 'sms': return '<i class="bi bi-chat-text-fill me-1"></i> SMS Sent';
        case 'status_change': return '<i class="bi bi-shuffle me-1"></i> Status Change';
        case 'note':
        default: return '<i class="bi bi-stickies-fill me-1"></i> Note Added';
    }
}

// --- Event Listeners & Initialization ---

document.addEventListener('DOMContentLoaded', () => {
    loadStudentProfile();
    addActivityForm.addEventListener('submit', handleAddActivity);
    
    // Attaches listeners to buttons retrieved at the top of the script
    if (editToggleButton) editToggleButton.addEventListener('click', () => toggleEditMode(true));
    if (cancelButton) cancelButton.addEventListener('click', () => {
        // Reset inputs to original data before toggling back
        renderStudentDetails(currentStudentData); 
        toggleEditMode(false);
    });
    if (studentDetailsForm) studentDetailsForm.addEventListener('submit', handleSaveProfile);
});