// /assets/js/settings_integrations.js

const API_INTEGRATIONS = '/api/v1/integrations.php';
const API_CUSTOM_FIELDS = '/api/v1/custom_fields.php'; 
const API_SYSTEM_FIELDS = '/api/v1/system_fields.php'; 
const API_META_MAPPING = '/api/v1/meta_mapping.php'; // NEW

// --- DOM Elements ---
const integrationsPane = document.getElementById('integrations-pane');
const metaConnectionStatus = document.getElementById('meta-connection-status');
const metaLoginButton = document.getElementById('meta-login-button');
const metaMappingTable = document.getElementById('meta-mapping-table');
const metaMappingForm = document.getElementById('meta-mapping-form');
const saveMappingButton = document.getElementById('save-mapping-button');

// --- Mock Data ---
// In a real scenario, this list would be fetched from Meta after authorization
const MOCK_META_FIELDS = [
    { name: 'FULL_NAME', display: 'Full Name' },
    { name: 'EMAIL', display: 'Email Address' },
    { name: 'PHONE_NUMBER', display: 'Phone Number' },
    { name: 'CITY', display: 'City' },
    { name: 'WORK_EXPERIENCE_LEVEL', display: 'Work Experience Level' },
    { name: 'COURSE_QUESTION', display: 'Course Question' },
];

let crmFields = []; // Cache of CRM field keys/names


/**
 * Helper to fetch all CRM fields (Built-in + Custom) for the mapping dropdown.
 */
async function fetchAllCrmFields() {
    crmFields = [
        { key: 'full_name', name: 'Full Name', is_built_in: true },
        { key: 'email', name: 'Email Address', is_built_in: true },
        { key: 'phone', name: 'Phone Number', is_built_in: true },
        { key: 'lead_source', name: 'Lead Source', is_built_in: true },
        { key: 'course_interested_id', name: 'Course Interested ID', is_built_in: true },
        { key: 'qualification', name: 'Qualification', is_built_in: true },
        { key: 'work_experience', name: 'Work Experience', is_built_in: true }
    ];
    
    // Fetch custom fields to add to the list
    try {
        const response = await fetch(API_CUSTOM_FIELDS);
        const customFields = await response.json();
        
        customFields.forEach(field => {
            crmFields.push({
                key: field.field_key,
                name: `${field.field_name} (Custom)`,
                is_built_in: false
            });
        });
    } catch (e) {
        console.error('Could not fetch CRM custom fields for mapping.', e);
    }
}

/**
 * Renders the mapping table.
 * @param {Object} savedMapping - Current mapping config from DB.
 */
function renderMappingTable(savedMapping = {}) {
    metaMappingTable.innerHTML = '';
    
    MOCK_META_FIELDS.forEach(metaField => {
        // Find the saved config for this meta field, or set defaults
        const savedConfig = savedMapping[metaField.name] || { crm_field_key: '', is_built_in: true };
        
        let optionsHtml = '<option value="">-- Ignore Field --</option>';
        
        crmFields.forEach(crmField => {
            const selected = crmField.key === savedConfig.crm_field_key ? 'selected' : '';
            // Store is_built_in flag in the option's value attribute for retrieval
            optionsHtml += `<option value="${crmField.key}|${crmField.is_built_in ? 1 : 0}" ${selected}>${crmField.name}</option>`;
        });
        
        const row = `
            <tr>
                <td><strong>${metaField.display}</strong><br><small class="text-muted">${metaField.name}</small></td>
                <td>
                    <select class="form-select form-select-sm" name="${metaField.name}" required>
                        ${optionsHtml}
                    </select>
                </td>
            </tr>
        `;
        metaMappingTable.insertAdjacentHTML('beforeend', row);
    });

    saveMappingButton.disabled = false;
}

/**
 * Saves the Meta account details to the database (meta_accounts table).
 */
async function saveMetaAccount(token, accountId, name) {
    // Current user ID must be obtained from session/login context (Mocked as 1)
    const userId = 1; 
    
    const response = await fetch(API_SYSTEM_FIELDS, { // Using system_fields API for this simple update (should be a new meta_accounts API)
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            field_key: 'meta_account_config', // Using a generic key for the system_field_config table
            display_name: name,
            access_token: token,
            ad_account_id: accountId
        })
    });
    return response.ok;
}


/**
 * Handles saving the field mapping rules.
 */
async function saveFieldMapping(event) {
    event.preventDefault();
    saveMappingButton.disabled = true;

    // 1. Collect and serialize mapping data
    const mappingDataToSend = {};
    const selects = metaMappingTable.querySelectorAll('select');
    let isValid = true;

    selects.forEach(select => {
        const metaField = select.name;
        const selectedValue = select.value; // e.g., 'full_name|1' or ''
        
        if (selectedValue) {
            const [crmFieldKey, isBuiltInFlag] = selectedValue.split('|');
            
            // Map to the structure expected by the PHP backend
            mappingDataToSend[metaField] = {
                crm_field_key: crmFieldKey,
                is_built_in: isBuiltInFlag === '1'
            };
        }
    });

    if (!isValid || Object.keys(mappingDataToSend).length === 0) {
        alert("Please map at least one field.");
        saveMappingButton.disabled = false;
        return;
    }

    // 2. Send mapping data to API
    try {
        const response = await fetch(API_META_MAPPING, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(mappingDataToSend)
        });

        if (!response.ok) throw new Error('Failed to save mapping to database.');

        alert('Field mapping saved successfully!');
    } catch (e) {
        alert('Error saving mapping: ' + e.message);
        console.error('Mapping Save Error:', e);
    }
    saveMappingButton.disabled = false;
}

/**
 * Loads the mapping section data and populates the table.
 */
async function loadMappingSection() {
    await fetchAllCrmFields();
    
    // 1. Simulate fetching SAVED mapping data from DB
    let savedMapping = {};
    try {
        const response = await fetch(API_META_MAPPING);
        if (response.ok) {
            savedMapping = await response.json();
        }
    } catch (e) {
        console.warn("Could not fetch existing mapping:", e);
    }
    
    // 2. Render the table with existing data
    renderMappingTable(savedMapping);
}


/**
 * Placeholder for simulating Facebook Login (Actual implementation uses Meta SDK)
 */
function handleMetaLogin() {
    alert("Simulating Facebook Login/Authorization. In a real app, this redirects to Meta, gets an access token, and saves it.");
    
    // Simulate connection success and save token
    const mockToken = 'EAAI...[long_access_token]';
    const mockAccountId = 'act_123456789';
    
    // Simulate saving account details (using the simplified method)
    saveMetaAccount(mockToken, mockAccountId, 'My Business Account');

    // Update UI status
    metaConnectionStatus.textContent = 'Connected';
    metaConnectionStatus.classList.replace('bg-danger', 'bg-success');

    // After connecting, load mapping form
    loadMappingSection();
}


// --- Event Listeners ---
if (metaLoginButton) metaLoginButton.addEventListener('click', handleMetaLogin);
if (metaMappingForm) metaMappingForm.addEventListener('submit', saveFieldMapping);


document.addEventListener('DOMContentLoaded', () => {
    // Check initial status and load mapping if connected (Simulated Check)
    if (metaConnectionStatus && metaConnectionStatus.textContent === 'Connected') {
        loadMappingSection();
    }
    
    // Initial fetch of all CRM fields for the mapping table
    if (integrationsPane && integrationsPane.classList.contains('active')) {
        loadMappingSection();
    }
});