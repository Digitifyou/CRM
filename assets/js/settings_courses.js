// /assets/js/settings_courses.js

const COURSES_API = '/api/v1/courses.php';

// --- DOM Elements ---
const courseTableBody = document.getElementById('course-list-table');
const addCourseForm = document.getElementById('add-course-form');
const addCourseModal = new bootstrap.Modal(document.getElementById('addCourseModal'));

/**
 * Renders the list of courses into the table
 * @param {Array} courses - An array of course objects
 */
function renderCourseTable(courses) {
    courseTableBody.innerHTML = ''; // Clear table

    if (courses.length === 0) {
        courseTableBody.innerHTML = '<tr><td colspan="4" class="text-center">No courses defined.</td></tr>';
        return;
    }

    courses.forEach(course => {
        const row = `
            <tr data-id="${course.course_id}">
                <td>${course.course_name}</td>
                <td>${Number(course.standard_fee).toLocaleString('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 })}</td>
                <td>${course.duration || 'N/A'}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-danger" onclick="deleteCourse(${course.course_id})">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `;
        courseTableBody.insertAdjacentHTML('beforeend', row);
    });
}

/**
 * Fetches all courses from the API and renders them
 */
async function loadCourses() {
    try {
        courseTableBody.innerHTML = '<tr><td colspan="4" class="text-center">Loading courses...</td></tr>';
        const response = await fetch(COURSES_API);
        if (!response.ok) throw new Error('Could not fetch courses');

        const courses = await response.json();
        renderCourseTable(courses);

    } catch (error) {
        console.error('Error loading courses:', error);
        courseTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load courses. Check config.php.</td></tr>';
    }
}

/**
 * Handles the "Add Course" form submission (POST request)
 */
async function handleAddCourse(event) {
    event.preventDefault();

    const formData = new FormData(addCourseForm);
    const courseData = Object.fromEntries(formData.entries());

    // Ensure fee is a number
    courseData.standard_fee = parseFloat(courseData.standard_fee);

    try {
        const response = await fetch(COURSES_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(courseData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to add course');
        }

        addCourseForm.reset(); // Clear the form
        addCourseModal.hide(); // Hide the modal
        await loadCourses(); // Refresh the course list

    } catch (error) {
        console.error('Failed to add course:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Deletes a course (DELETE request)
 * @param {number} courseId
 */
async function deleteCourse(courseId) {
    if (!confirm('Are you sure you want to delete this course?')) {
        return;
    }

    try {
        const response = await fetch(`${COURSES_API}?id=${courseId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to delete course');
        }

        // Remove row from UI instantly
        const row = document.querySelector(`tr[data-id="${courseId}"]`);
        if (row) row.remove();

        // Refresh the list to update counts/state
        await loadCourses();

    } catch (error) {
        console.error('Failed to delete course:', error);
        alert('Error: ' + error.message);
    }
}


// --- Global Functions and Event Listeners ---

// Attach deleteCourse to window so it can be called from onclick in renderCourseTable
window.deleteCourse = deleteCourse;

document.addEventListener('DOMContentLoaded', () => {
    loadCourses(); // Initial load of the course list

    addCourseForm.addEventListener('submit', handleAddCourse);
});