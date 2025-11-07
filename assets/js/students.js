// /assets/js/students.js

// --- API URLs ---
const STUDENTS_API = '/api/v1/students.php';
const COURSES_API = '/api/v1/courses.php';

// --- DOM Elements ---
const studentTableBody = document.getElementById('student-list-table');
const addStudentForm = document.getElementById('add-student-form');
const courseSelectDropdown = document.getElementById('course_interested_id');
const searchBar = document.getElementById('search-bar');
const addStudentModal = new bootstrap.Modal(document.getElementById('addStudentModal'));

// --- State ---
let allStudents = []; // Local cache of all students for searching

/**
 * Fetches all courses and populates the dropdown
 */
async function loadCoursesDropdown() {
    try {
        const response = await fetch(COURSES_API);
        if (!response.ok) throw new Error('Could not fetch courses');
        const courses = await response.json();

        courses.forEach(course => {
            const option = `<option value="${course.course_id}">${course.course_name} (₹${course.standard_fee})</option>`;
            courseSelectDropdown.insertAdjacentHTML('beforeend', option);
        });
    } catch (error) {
        console.error('Error loading courses:', error);
        courseSelectDropdown.innerHTML = '<option value="">Could not load courses</option>';
    }
}

/**
 * Renders a list of students into the table
 * @param {Array} students - An array of student objects
 */
function renderStudentTable(students) {
    studentTableBody.innerHTML = ''; // Clear table

    if (students.length === 0) {
        studentTableBody.innerHTML = '<tr><td colspan="6" class="text-center">No students found.</td></tr>';
        return;
    }

    students.forEach(student => {
        const inquiryDate = new Date(student.created_at).toLocaleDateString();
        const row = `
            <tr data-id="${student.student_id}" onclick="viewStudent(${student.student_id})">
                <td>${student.full_name}</td>
                <td>${student.phone}</td>
                <td><span class="badge bg-info">${student.status}</span></td>
                <td>${student.course_interested || 'N/A'}</td>
                <td>
                    <span class="badge ${student.lead_score > 60 ? 'bg-success' : 'bg-secondary'}">
                        ${student.lead_score}
                    </span>
                </td>
                <td>${inquiryDate}</td>
            </tr>
        `;
        studentTableBody.insertAdjacentHTML('beforeend', row);
    });
}

/**
 * Fetches all students from the API and renders them
 */
async function loadStudents() {
    try {
        const response = await fetch(STUDENTS_API);
        if (!response.ok) throw new Error('Could not fetch students');

        allStudents = await response.json(); // Store in local cache
        renderStudentTable(allStudents); // Render them

    } catch (error) {
        console.error('Error loading students:', error);
        studentTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load students.</td></tr>';
    }
}

/**
 * Handles the "Add Student" form submission
 */
async function handleAddStudent(event) {
    event.preventDefault();

    const formData = new FormData(addStudentForm);
    const studentData = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(STUDENTS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(studentData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to add student');
        }

        await response.json();

        addStudentForm.reset(); // Clear the form
        addStudentModal.hide(); // Hide the modal
        await loadStudents(); // Refresh the student list

    } catch (error) {
        console.error('Failed to add student:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Filters the locally cached 'allStudents' array based on search term
 */
function handleSearch() {
    const searchTerm = searchBar.value.toLowerCase();

    const filteredStudents = allStudents.filter(student => {
        return (
            student.full_name.toLowerCase().includes(searchTerm) ||
            student.phone.toLowerCase().includes(searchTerm) ||
            (student.email && student.email.toLowerCase().includes(searchTerm))
        );
    });

    renderStudentTable(filteredStudents);
}

/**
 * Placeholder function for when a user clicks a student row
 * In a real app, this would open a new page or a large "details" modal
 */
function viewStudent(id) {
    console.log('Viewing student with ID:', id);
    // In the future, this will be:
    // window.location.href = `/student-profile.html?id=${id}`;

    // **FIXED: Navigate to the new profile page**
    window.location.href = `/student_profile.html?id=${id}`;
}

// --- Event Listeners ---
document.addEventListener('DOMContentLoaded', () => {
    loadStudents();
    loadCoursesDropdown(); // Load courses into the modal

    addStudentForm.addEventListener('submit', handleAddStudent);
    searchBar.addEventListener('input', handleSearch);
});