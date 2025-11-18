// /assets/js/settings_users.js

const API_USERS = '/api/v1/users.php';

// --- DOM Elements ---
const userTableBody = document.getElementById('user-list-table');
const addUserForm = document.getElementById('add-user-form');
const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
const usersPane = document.getElementById('users-pane');

/**
 * Renders the list of users into the table
 * @param {Array} users - An array of user objects
 */
function renderUserTable(users) {
    userTableBody.innerHTML = ''; // Clear table

    if (users.length === 0) {
        userTableBody.innerHTML = '<tr><td colspan="5" class="text-center">No users found.</td></tr>';
        return;
    }

    users.forEach(user => {
        const statusBadge = user.is_active == 1 ?
            '<span class="badge bg-success">Active</span>' :
            '<span class="badge bg-danger">Inactive</span>';

        const row = `
            <tr data-id="${user.user_id}">
                <td>${user.full_name}</td>
                <td>${user.username}</td>
                <td class="text-capitalize">${user.role}</td>
                <td>${statusBadge}</td>
                <td class="text-end">
                    <select class="form-select form-select-sm d-inline-block w-auto me-2" onchange="updateUserRole(${user.user_id}, this.value)">
                        <option value="counselor" ${user.role === 'counselor' ? 'selected' : ''}>Counselor</option>
                        <option value="trainer" ${user.role === 'trainer' ? 'selected' : ''}>Trainer</option>
                        <option value="owner" ${user.role === 'owner' ? 'selected' : ''}>Owner</option>
                        <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                    </select>
                    <button class="btn btn-sm ${user.is_active == 1 ? 'btn-outline-danger' : 'btn-outline-success'}" 
                            onclick="toggleUserStatus(${user.user_id}, ${user.is_active})">
                        <i class="bi bi-${user.is_active == 1 ? 'x-lg' : 'check-lg'}"></i> ${user.is_active == 1 ? 'Deactivate' : 'Activate'}
                    </button>
                </td>
            </tr>
        `;
        userTableBody.insertAdjacentHTML('beforeend', row);
    });
}

/**
 * Fetches all users from the API and renders them
 */
async function loadUsers() {
    try {
        userTableBody.innerHTML = '<tr><td colspan="5" class="text-center">Loading users...</td></tr>';
        const response = await fetch(API_USERS);
        if (!response.ok) throw new Error('Could not fetch users');

        const users = await response.json();
        renderUserTable(users);

    } catch (error) {
        console.error('Error loading users:', error);
        userTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load users.</td></tr>';
    }
}

/**
 * Handles the "Invite User" form submission (POST request)
 */
async function handleAddUser(event) {
    event.preventDefault();

    const formData = new FormData(addUserForm);
    const userData = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(API_USERS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to invite user');
        }

        addUserForm.reset();
        addUserModal.hide();
        await loadUsers(); // Refresh the user list

    } catch (error) {
        console.error('Failed to invite user:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Updates a user's role (PUT request)
 */
async function updateUserRole(userId, newRole) {
    try {
        const response = await fetch(`${API_USERS}?id=${userId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role: newRole })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to update role');
        }

        console.log(`User ${userId} role updated to ${newRole}`);

    } catch (error) {
        console.error('Failed to update role:', error);
        alert('Error: ' + error.message);
        await loadUsers(); // Force refresh on failure
    }
}

/**
 * Toggles a user's active status (PUT or DELETE request logic)
 */
async function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 1 ? 0 : 1;
    const action = newStatus === 0 ? 'deactivate' : 'activate';

    if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
    }

    try {
        const response = await fetch(`${API_USERS}?id=${userId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_active: newStatus })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || `Failed to ${action} user`);
        }

        await loadUsers(); // Refresh the list to reflect the status change

    } catch (error) {
        console.error(`Failed to ${action} user:`, error);
        alert(`Error: Failed to ${action} user. ` + error.message);
    }
}


// --- Global Functions and Event Listeners ---
window.updateUserRole = updateUserRole;
window.toggleUserStatus = toggleUserStatus;

// Event listener for when the Users tab is clicked
usersPane.addEventListener('show.bs.tab', loadUsers);

document.addEventListener('DOMContentLoaded', () => {
    // Only load if the Users tab is the active one on initial load
    if (usersPane && usersPane.classList.contains('active')) {
        loadUsers();
    }

    // Add user form submission
    addUserForm.addEventListener('submit', handleAddUser);
});