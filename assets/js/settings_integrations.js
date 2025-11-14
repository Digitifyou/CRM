// /assets/js/settings_integrations.js

const API_INTEGRATIONS = '/api/v1/integrations.php';
const API_CUSTOM_FIELDS = '/api/v1/custom_fields.php'; 
const API_SYSTEM_FIELDS = '/api/v1/system_fields.php'; 
const API_META_MAPPING = '/api/v1/meta_mapping.php'; 
const API_META_ACCOUNTS = '/api/v1/meta_accounts.php'; 
const API_FORM_FETCHER = '/api/v1/meta_form_fetcher.php'; 

// --- DOM Elements ---
const integrationsPane = document.getElementById('integrations-pane');
const metaConnectionStatus = document.getElementById('meta-connection-status');
const metaLoginButton = document.getElementById('meta-login-button');
const metaMappingTable = document.getElementById('meta-mapping-table');
const metaMappingForm = document.getElementById('meta-mapping-form');
const saveMappingButton = document.getElementById('save-mapping-button');
const authStatusMessage = document.getElementById('auth-status-message'); 

// --- Mock/Standard Data ---
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
 */
function renderMappingTable(savedMapping = {}) {
    metaMappingTable.innerHTML = '';
    
    MOCK_META_FIELDS.forEach(metaField => {
        // Find the saved config for this meta field, or set defaults
        const savedConfig = savedMapping[metaField.name] || { crm_field_key: '', is_built_in: true };
        
        let optionsHtml = '<option value="">-- Ignore Field --</option>';
        
        crmFields.forEach(crmField => {
            const selected = crmField.key === savedConfig.crm_field_key ? 'selected' : '';
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
 * Handles saving the field mapping rules.
 */
async function saveFieldMapping(event) {
    event.preventDefault();
    saveMappingButton.disabled = true;

    // 1. Collect and serialize mapping data
    const mappingDataToSend = {};
    const selects = metaMappingTable.querySelectorAll('select');

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

    if (Object.keys(mappingDataToSend).length === 0) {
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
    
    // 1. Fetch SAVED mapping data from DB
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
 * Core function to query the Graph API to get the user's Ad Account ID and Name.
 */
async function fetchAdAccountDetails(accessToken) {
    try {
        // We request /me/adaccounts to get the user's associated accounts
        // Using FB.api for SDK integrated calls
        return new Promise((resolve, reject) => {
            FB.api('/me/adaccounts', {
                fields: 'name,account_id',
                access_token: accessToken
            }, function(response) {
                if (response && !response.error) {
                    if (!response.data || response.data.length === 0) {
                        return reject(new Error("No Ad Accounts found associated with this user."));
                    }
                    
                    // Use the first ad account found for simplicity
                    const account = response.data[0];
                    
                    resolve({
                        id: account.account_id,
                        name: account.name,
                        token: accessToken 
                    });
                } else {
                    reject(new Error(response.error.message || "Unknown Meta API error during account fetch."));
                }
            });
        });

    } catch (e) {
        console.error("Error fetching Ad Account details:", e);
        throw e;
    }
}


/**
 * Handles the click of the "Connect with Facebook" button, initiating the OAuth flow.
 */
function handleMetaLogin() {
    
    if (typeof FB === 'undefined') {
        alert("Meta SDK not initialized. Please ensure your App ID is set correctly in settings.html.");
        return;
    }
    
    authStatusMessage.textContent = 'Awaiting permission grant...';
    authStatusMessage.classList.remove('d-none', 'text-danger', 'text-success');
    authStatusMessage.classList.add('text-muted');
    metaLoginButton.disabled = true;

    // 1. Initiate Meta Login flow
    FB.login(function(response) {
        if (response.authResponse) {
            const accessToken = response.authResponse.accessToken;
            
            authStatusMessage.textContent = 'Permissions granted. Retrieving Ad Account details...';

            // 2. Use the token to fetch account details
            fetchAdAccountDetails(accessToken).then(accountData => {
                // 3. Save the token and account ID to the CRM backend
                return fetch(API_META_ACCOUNTS, { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        access_token: accountData.token,
                        ad_account_id: accountData.id.replace('act_', ''), 
                        account_name: accountData.name
                    })
                });
            })
            .then(saveResponse => {
                if (saveResponse.ok) {
                    alert("Connection successful! Refreshing status...");
                    checkMetaConnectionStatus(); // Refresh UI status
                } else {
                    return saveResponse.json().then(errorData => {
                         throw new Error(errorData.error || 'Failed to save configuration to CRM database.');
                    });
                }
            })
            .catch(e => {
                // Handle any error from fetchAdAccountDetails or the final save
                authStatusMessage.textContent = `Connection Failed: ${e.message}`;
                authStatusMessage.classList.replace('text-muted', 'text-danger');
                console.error('Meta Connection Error:', e);
                metaLoginButton.disabled = false; // Re-enable button on failure
            });
            
        } else {
             authStatusMessage.textContent = 'Connection cancelled or denied.';
             authStatusMessage.classList.replace('text-muted', 'text-danger');
             metaLoginButton.disabled = false; // Re-enable button on cancellation
        }
    }, {
        // Permissions required for Ad Accounts and Lead Ads Webhooks
        scope: 'leads_retrieval,ads_read,ads_management,pages_manage_ads,pages_show_list,business_management'
    });
}


/**
 * Checks the database for saved Meta account credentials using the dedicated API endpoint.
 */
async function checkMetaConnectionStatus() {
    try {
        const response = await fetch(API_META_ACCOUNTS);
        
        if (response.ok) {
            const config = await response.json();
            metaConnectionStatus.textContent = 'Connected';
            metaConnectionStatus.classList.replace('bg-danger', 'bg-success');
            metaLoginButton.disabled = true;
            metaLoginButton.textContent = `Active: ${config.account_name}`;
            
            if (authStatusMessage) authStatusMessage.classList.add('d-none');
            
            loadMappingSection();
        } else {
            // No account found or 404 response
            metaConnectionStatus.textContent = 'Disconnected';
            metaConnectionStatus.classList.replace('bg-success', 'bg-danger');
            metaLoginButton.disabled = false;
            metaLoginButton.textContent = 'Connect with Facebook';
        }

    } catch (error) {
        console.error('Error checking Meta connection status:', error);
            // Treat any fetch error as disconnected until proven otherwise
        metaConnectionStatus.textContent = 'Error';
        metaConnectionStatus.classList.replace('bg-success', 'bg-danger');
        metaLoginButton.disabled = false;
        metaLoginButton.textContent = 'Connect with Facebook';
    }
}


// --- Event Listeners ---
metaLoginButton.addEventListener('click', handleMetaLogin);
if (metaMappingForm) metaMappingForm.addEventListener('submit', saveFieldMapping);


document.addEventListener('DOMContentLoaded', () => {
    // We rely on the FB.init callback in settings.html to call checkMetaConnectionStatus
    // if the page is fully loaded.
    
    // Check when the tab becomes active
    if (integrationsPane) {
        integrationsPane.addEventListener('show.bs.tab', checkMetaConnectionStatus);
    }
});