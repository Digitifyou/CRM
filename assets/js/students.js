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
let allStudents = []; 

/**
 * Fetches all courses and populates the dropdown
 */
async function loadCoursesDropdown() {
    try {
        const response = await fetch(COURSES_API);
        if (!response.ok) throw new Error('Could not fetch courses');
        const courses = await response.json();

        courseSelectDropdown.innerHTML = '<option value="">-- Select Course --</option>';
        courses.forEach(course => {
            const option = `<option value="${course.course_id}">${course.course_name} (₹${course.standard_fee})</option>`;
            courseSelectDropdown.insertAdjacentHTML('beforeend', option);
        });
    } catch (error) {
        console.error('Error loading courses:', error);
    }
}

/**
 * Renders a list of students into the table
 */
function renderStudentTable(students) {
    studentTableBody.innerHTML = ''; 

    if (students.length === 0) {
        studentTableBody.innerHTML = '<tr><td colspan="7" class="text-center">No students found.</td></tr>';
        return;
    }

    students.forEach(student => {
        const inquiryDate = new Date(student.created_at).toLocaleDateString();
        
        const row = document.createElement('tr');
        row.dataset.id = student.student_id;
        row.innerHTML = `
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
            <td class="text-end">
                <button class="btn btn-sm btn-outline-danger delete-btn" title="Delete Lead">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        
        // Add row click event for view profile
        row.addEventListener('click', (e) => {
            // Prevent opening profile if delete button is clicked
            if (!e.target.closest('.delete-btn')) {
                viewStudent(student.student_id);
            }
        });
        
        // Add delete button listener
        const deleteBtn = row.querySelector('.delete-btn');
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Stop row click
            deleteStudent(student.student_id);
        });

        studentTableBody.appendChild(row);
    });
}

/**
 * Fetches all students from the API
 */
async function loadStudents() {
    try {
        const response = await fetch(STUDENTS_API);
        if (!response.ok) throw new Error('Could not fetch students');

        allStudents = await response.json(); 
        renderStudentTable(allStudents); 

    } catch (error) {
        console.error('Error loading students:', error);
        studentTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load students.</td></tr>';
    }
}

/**
 * Handles adding a student
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

        addStudentForm.reset(); 
        addStudentModal.hide(); 
        await loadStudents(); 

    } catch (error) {
        console.error('Failed to add student:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Deletes a student record
 */
async function deleteStudent(id) {
    if (!confirm('Are you sure? This will permanently delete this lead and all related enrollment history.')) {
        return;
    }

    try {
        const response = await fetch(`${STUDENTS_API}?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to delete student');
        }

        // Remove from UI
        allStudents = allStudents.filter(s => s.student_id != id);
        renderStudentTable(allStudents);
        
    } catch (error) {
        console.error('Delete error:', error);
        alert(error.message);
    }
}


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

function viewStudent(id) {
    window.location.href = `/student_profile.php?id=${id}`;
}

// --- Event Listeners ---
document.addEventListener('DOMContentLoaded', () => {
    loadStudents();
    loadCoursesDropdown(); 

    addStudentForm.addEventListener('submit', handleAddStudent);
    searchBar.addEventListener('input', handleSearch);
});