<?php
// user_profile.php
// SECURITY CHECK: Ensure user is logged in and include common components
include 'header.php'; 
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <h2 class="mb-4">My Profile Settings</h2>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-building me-2"></i> Academy & Account Details
            </div>
            <div class="card-body">
                <p><strong>Academy Name:</strong> <span id="profile-academy-name" class="fw-bold">Loading...</span></p>
                <p><strong>Academy Phone:</strong> <span id="profile-academy-phone" class="text-muted">Loading...</span></p>
                <p><strong>Website:</strong> <span id="profile-academy-website" class="text-muted">Loading...</span></p>
                
                <hr>
                
                <p><strong>Owner Full Name:</strong> <span id="profile-full-name-display" class="text-muted">Loading...</span></p>
                <p><strong>Username (Login ID):</strong> <span id="profile-username" class="text-muted">Loading...</span></p>
                <p><strong>Role:</strong> <span id="profile-role" class="badge bg-info text-capitalize">Loading...</span></p>
                <p><strong>Academy ID:</strong> <span id="profile-academy-id" class="text-muted">Loading...</span></p>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header">
                <i class="bi bi-pencil me-2"></i> Update Information & Password
            </div>
            <div class="card-body">
                <div id="profile-alert" class="alert d-none" role="alert"></div>

                <form id="profile-update-form">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name (Editable)</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>

                    <h5 class="mt-4 mb-3">Change Password</h5>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>

                    <button type="submit" class="btn btn-success" id="save-profile-button">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const API_USERS = '/api/v1/users.php';

let currentProfileData = {};

/**
 * Fetches the current user's profile data.
 */
async function loadUserProfile() {
    try {
        const response = await fetch(`${API_USERS}?self=true`);
        if (!response.ok) throw new Error('Failed to fetch profile data.');
        
        currentProfileData = await response.json();
        
        // Populate Academy Details (NEW)
        document.getElementById('profile-academy-name').textContent = currentProfileData.academy_name;
        document.getElementById('profile-academy-phone').textContent = currentProfileData.academy_phone;
        document.getElementById('profile-academy-website').textContent = currentProfileData.academy_website || 'N/A';
        
        // Populate Account Details
        document.getElementById('profile-full-name-display').textContent = currentProfileData.full_name;
        document.getElementById('profile-username').textContent = currentProfileData.username;
        document.getElementById('profile-role').textContent = currentProfileData.role;
        document.getElementById('profile-academy-id').textContent = currentProfileData.academy_id;
        
        // Populate editable form
        document.getElementById('full_name').value = currentProfileData.full_name;

    } catch (error) {
        console.error('Error loading user profile:', error);
        document.getElementById('profile-username').textContent = 'Error';
    }
}

/**
 * Handles the profile update submission.
 */
async function handleProfileUpdate(event) {
    event.preventDefault();

    const saveButton = document.getElementById('save-profile-button');
    const alertBox = document.getElementById('profile-alert');
    saveButton.disabled = true;
    alertBox.classList.add('d-none');

    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const newFullName = document.getElementById('full_name').value;
    
    let updateData = {};

    if (newFullName !== currentProfileData.full_name) {
        updateData.full_name = newFullName;
    }

    // 1. Handle Password Change
    if (newPassword) {
        if (newPassword !== confirmPassword) {
            alertBox.classList.remove('d-none', 'alert-success');
            alertBox.classList.add('alert-danger');
            alertBox.textContent = 'Error: New password and confirmation do not match.';
            saveButton.disabled = false;
            return;
        }
        updateData.new_password = newPassword;
    }

    if (Object.keys(updateData).length === 0) {
        alertBox.classList.remove('d-none', 'alert-danger');
        alertBox.classList.add('alert-warning');
        alertBox.textContent = 'No changes detected.';
        saveButton.disabled = false;
        return;
    }

    // 2. Send PUT request using ?self=true
    try {
        const response = await fetch(`${API_USERS}?self=true`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updateData)
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to update profile.');
        }

        alertBox.classList.remove('d-none', 'alert-danger');
        alertBox.classList.add('alert-success');
        alertBox.textContent = 'Profile updated successfully! Refreshing data...';
        
        // Clear password fields on success
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        
        // Reload user data to update full name display
        loadUserProfile(); 

    } catch (error) {
        console.error('Update failed:', error);
        alertBox.classList.remove('d-none', 'alert-success');
        alertBox.classList.add('alert-danger');
        alertBox.textContent = 'Error: ' + error.message;
    }
    saveButton.disabled = false;
}


document.addEventListener('DOMContentLoaded', () => {
    loadUserProfile();
    document.getElementById('profile-update-form').addEventListener('submit', handleProfileUpdate);
});
</script>

<?php include 'footer.php'; ?>