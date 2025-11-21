// /assets/js/settings_batches.js

const API_BATCHES = '/api/v1/batches.php';
const API_COURSES = '/api/v1/courses.php';

// Variables now placeholders
let batchTableBody;
let addBatchForm;
let addBatchModal;
let batchCourseSelect;
let batchesPane;

// --- Caching for Courses (Optimized for repeated modal opening) ---
let courseCache = [];


/**
 * Fetches all courses and populates the dropdown
 */
async function loadCoursesDropdown() {
    // Use cached data if available
    if (courseCache.length > 0) {
        populateCourseSelect(courseCache);
        return;
    }

    try {
        const response = await fetch(API_COURSES);
        if (!response.ok) throw new Error('Could not fetch courses');
        const courses = await response.json();
        
        // Cache the result
        courseCache = courses;
        
        populateCourseSelect(courses);

    } catch (error) {
        console.error('CRITICAL: Error loading courses for batch dropdown. Check API_COURSES.', error);
        batchCourseSelect.innerHTML = '<option value="" disabled>ERROR: Could not load courses</option>';
    }
}

/**
 * Populates the actual <select> element with courses
 * @param {Array} courses - Array of course objects
 */
function populateCourseSelect(courses) {
    batchCourseSelect.innerHTML = '<option value="">-- Select Course --</option>'; // Reset
    
    if (courses.length === 0) {
        batchCourseSelect.innerHTML = '<option value="" disabled>No courses defined in Course Management.</option>';
        return;
    }
    
    courses.forEach(course => {
        const option = `<option value="${course.course_id}">${course.course_name}</option>`;
        batchCourseSelect.insertAdjacentHTML('beforeend', option);
    });
}


/**
 * Renders the list of batches into the table
 * @param {Array} batches - An array of batch objects
 */
function renderBatchTable(batches) {
    batchTableBody.innerHTML = ''; // Clear table

    if (batches.length === 0) {
        batchTableBody.innerHTML = '<tr><td colspan="5" class="text-center">No batches defined.</td></tr>';
        return;
    }

    batches.forEach(batch => {
        const startDate = new Date(batch.start_date).toLocaleDateString();
        const seatsStatus = `${batch.filled_seats} / ${batch.total_seats}`;
        const seatsColor = batch.filled_seats >= batch.total_seats ? 'bg-danger' : 'bg-success';

        const row = `
            <tr data-id="${batch.batch_id}">
                <td>${batch.batch_name}</td>
                <td>${batch.course_name}</td>
                <td>${startDate}</td>
                <td>
                    <span class="badge ${seatsColor}">
                        ${seatsStatus}
                    </span>
                </td>
                <td class="text-end">
                    <button class="btn btn-sm btn-danger" onclick="deleteBatch(${batch.batch_id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        batchTableBody.insertAdjacentHTML('beforeend', row);
    });
}

/**
 * Fetches all batches from the API and renders them
 */
async function loadBatches() {
    try {
        batchTableBody.innerHTML = '<tr><td colspan="5" class="text-center">Loading batches...</td></tr>';
        const response = await fetch(API_BATCHES);
        if (!response.ok) throw new Error('Could not fetch batches');

        const batches = await response.json();
        renderBatchTable(batches);

    } catch (error) {
        console.error('Error loading batches:', error);
        batchTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load batches.</td></tr>';
    }
}

/**
 * Handles the "Add Batch" form submission (POST request)
 */
async function handleAddBatch(event) {
    event.preventDefault();

    const formData = new FormData(addBatchForm);
    const batchData = Object.fromEntries(formData.entries());

    // Convert seat count to integer
    batchData.total_seats = parseInt(batchData.total_seats, 10);
    
    // Simple validation check before sending
    if (!batchData.course_id || batchData.course_id === '') {
        alert('Please select a course.');
        return;
    }

    try {
        const response = await fetch(API_BATCHES, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(batchData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to add batch');
        }

        addBatchForm.reset(); // Clear the form
        addBatchModal.hide(); // Hide the modal
        await loadBatches(); // Refresh the batch list

    } catch (error) {
        console.error('Failed to add batch:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Deletes a batch (DELETE request)
 * @param {number} batchId
 */
async function deleteBatch(batchId) {
    if (!confirm('Are you sure you want to delete this batch? This should only be done if no students are assigned.')) {
        return;
    }

    try {
        const response = await fetch(`${API_BATCHES}?id=${batchId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to delete batch');
        }

        // Remove row from UI instantly
        const row = document.querySelector(`tr[data-id="${batchId}"]`);
        if (row) row.remove();

        // Refresh the list to update state
        await loadBatches();

    } catch (error) {
        console.error('Failed to delete batch:', error);
        alert('Error: ' + error.message);
    }
}


// --- Global Functions and Event Listeners ---

window.deleteBatch = deleteBatch;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Element Lookups & Modal Instantiation
    batchTableBody = document.getElementById('batch-list-table');
    addBatchForm = document.getElementById('add-batch-form');
    batchCourseSelect = document.getElementById('batch-course-id');
    batchesPane = document.getElementById('batches-pane');

    if (typeof bootstrap !== 'undefined') {
        const addBatchModalElement = document.getElementById('addBatchModal');
        if (addBatchModalElement) {
             addBatchModal = new bootstrap.Modal(addBatchModalElement);
        }
    }
    
    // 2. Initial Load Logic
    loadBatches();
    loadCoursesDropdown();

    // 3. Event Listeners
    if (batchesPane) {
        batchesPane.addEventListener('show.bs.tab', () => {
            loadBatches();
            loadCoursesDropdown(); 
        });
    }

    const addBatchModalElement = document.getElementById('addBatchModal');
    if (addBatchModalElement) {
        addBatchModalElement.addEventListener('show.bs.modal', loadCoursesDropdown);
    }
    
    if (addBatchForm) {
        addBatchForm.addEventListener('submit', handleAddBatch);
    }
});