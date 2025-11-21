// /assets/js/settings_integrations.js

const API_CUSTOM_FIELDS = '/api/v1/custom_fields.php'; 
const API_META_MAPPING = '/api/v1/meta_mapping.php'; 
const API_META_ACCOUNTS = '/api/v1/meta_accounts.php'; 
const API_FORM_FETCHER = '/api/v1/meta_form_fetcher.php'; 

// --- DOM Elements ---
const integrationsPane = document.getElementById('integrations-pane');

// Meta Elements
const metaConnectionStatus = document.getElementById('meta-connection-status');
const metaLoginButton = document.getElementById('meta-login-button');
const metaMappingTable = document.getElementById('meta-mapping-table');
const metaMappingForm = document.getElementById('meta-mapping-form');
const saveMappingButton = document.getElementById('save-mapping-button');
const authStatusMessage = document.getElementById('auth-status-message'); 
const metaFormSelect = document.getElementById('meta-form-select'); 
const loadFieldsButton = document.getElementById('load-fields-button'); 

let crmFields = []; 
let currentFormFields = []; 
let pageData = {}; 


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
    if (!metaMappingTable) return;
    metaMappingTable.innerHTML = '';
    
    if (currentFormFields.length === 0) {
        metaMappingTable.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No fields loaded. Select a form first.</td></tr>';
        if (saveMappingButton) saveMappingButton.disabled = true;
        return;
    }
    
    currentFormFields.forEach(metaField => {
        const savedConfig = savedMapping[metaField.name] || { crm_field_key: '', is_built_in: true };
        
        let optionsHtml = '<option value="">-- Ignore Field --</option>';
        
        crmFields.forEach(crmField => {
            const selected = crmField.key === savedConfig.crm_field_key ? 'selected' : '';
            optionsHtml += `<option value="${crmField.key}|${crmField.is_built_in ? 1 : 0}" ${selected}>${crmField.name}</option>`;
        });
        
        const row = `
            <tr>
                <td><strong>${metaField.display || metaField.name}</strong><br><small class="text-muted">${metaField.name}</small></td>
                <td>
                    <select class="form-select form-select-sm" name="${metaField.name}" required>
                        ${optionsHtml}
                    </select>
                </td>
            </tr>
        `;
        metaMappingTable.insertAdjacentHTML('beforeend', row);
    });

    if (saveMappingButton) saveMappingButton.disabled = false;
}

/**
 * Handles saving the field mapping rules.
 */
async function saveFieldMapping(event) {
    event.preventDefault();
    if (saveMappingButton) saveMappingButton.disabled = true;

    const mappingDataToSend = {};
    const selects = metaMappingTable.querySelectorAll('select');

    selects.forEach(select => {
        const metaField = select.name;
        const selectedValue = select.value;
        
        if (selectedValue) {
            const [crmFieldKey, isBuiltInFlag] = selectedValue.split('|');
            
            mappingDataToSend[metaField] = {
                crm_field_key: crmFieldKey,
                is_built_in: isBuiltInFlag === '1'
            };
        }
    });

    if (Object.keys(mappingDataToSend).length === 0) {
        alert("Please map at least one field.");
        if (saveMappingButton) saveMappingButton.disabled = false;
        return;
    }

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
    if (saveMappingButton) saveMappingButton.disabled = false;
}


/**
 * Fetches the list of forms for the Ad Account.
 */
async function fetchAndRenderForms() {
    if (!metaFormSelect) return; 

    metaFormSelect.innerHTML = '<option value="">Loading forms...</option>';
    if (loadFieldsButton) loadFieldsButton.disabled = true;

    try {
        const response = await fetch(API_FORM_FETCHER);
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || "Failed to fetch forms. Check permissions.");
        }
        
        const data = await response.json();
        const forms = data.forms || [];
        
        metaFormSelect.innerHTML = '<option value="">-- Select a Form --</option>';
        
        if (forms.length === 0) {
            metaFormSelect.innerHTML = '<option value="">No Lead Forms found for your account.</option>';
            return;
        }

        forms.forEach(form => {
            const option = document.createElement('option');
            option.value = form.id;
            option.dataset.pageId = form.page_id;
            option.textContent = `${form.name} (Page ID: ${form.page_id})`;
            metaFormSelect.appendChild(option);
        });

        if (loadFieldsButton) loadFieldsButton.disabled = false;

    } catch (e) {
        console.warn(`Error fetching forms: ${e.message}`);
        metaFormSelect.innerHTML = '<option value="">Error loading forms.</option>';
    }
}

/**
 * Handles the click to fetch fields for the currently selected form.
 */
async function handleLoadFields() {
    const selectedForm = metaFormSelect.options[metaFormSelect.selectedIndex];
    const formId = selectedForm.value;
    
    if (!formId) {
        alert('Please select a valid form first.');
        return;
    }

    pageData.id = selectedForm.dataset.pageId;
    pageData.name = selectedForm.textContent;

    if (metaMappingTable) metaMappingTable.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Fetching fields...</td></tr>';
    if (loadFieldsButton) loadFieldsButton.disabled = true;

    try {
        const response = await fetch(`${API_FORM_FETCHER}?form_id=${formId}`);
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || "Failed to fetch fields for the form.");
        }
        
        const data = await response.json();
        currentFormFields = data.fields || [];

        renderMappingTable();
        if (loadFieldsButton) loadFieldsButton.disabled = false;
        
    } catch (e) {
        alert(`Error: ${e.message}`);
        if (metaMappingTable) metaMappingTable.innerHTML = '<tr><td colspan="2" class="text-center text-danger">Failed to load fields.</td></tr>';
        if (loadFieldsButton) loadFieldsButton.disabled = false;
    }
}


/**
 * Checks the database for saved Meta account credentials.
 */
async function checkMetaConnectionStatus() {
    if (!metaConnectionStatus) return;

    try {
        const response = await fetch(API_META_ACCOUNTS);
        
        if (response.ok) {
            const config = await response.json();
            metaConnectionStatus.textContent = 'Connected';
            metaConnectionStatus.classList.replace('bg-danger', 'bg-success');
            metaLoginButton.disabled = true;
            metaLoginButton.textContent = `Active: ${config.account_name}`; 
            
            if (authStatusMessage) authStatusMessage.classList.add('d-none');
            
            fetchAndRenderForms(); 
        } else {
            metaConnectionStatus.textContent = 'Disconnected';
            metaConnectionStatus.classList.replace('bg-success', 'bg-danger');
            metaLoginButton.disabled = false;
            metaLoginButton.textContent = 'Connect with Facebook';
        }

    } catch (error) {
        console.error('Error checking Meta connection status:', error);
        metaConnectionStatus.textContent = 'Error';
        metaConnectionStatus.classList.replace('bg-success', 'bg-danger');
        metaLoginButton.disabled = false;
        metaLoginButton.textContent = 'Connect with Facebook';
    }
}


/**
 * Handles the click of the "Connect with Facebook" button.
 */
function handleMetaLogin() {
    
    if (typeof FB === 'undefined') {
        alert("Meta SDK not initialized.");
        return;
    }
    
    if (authStatusMessage) {
        authStatusMessage.textContent = 'Awaiting permission grant...';
        authStatusMessage.classList.remove('d-none', 'text-danger', 'text-success');
        authStatusMessage.classList.add('text-muted');
    }
    metaLoginButton.disabled = true;

    FB.login(function(response) {
        if (response.authResponse) {
            const accessToken = response.authResponse.accessToken;
            
            if (authStatusMessage) authStatusMessage.textContent = 'Permissions granted. Retrieving Ad Account details...';

             fetchAdAccountDetails(accessToken).then(accountData => {
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
                    checkMetaConnectionStatus();
                } else {
                    return saveResponse.json().then(errorData => {
                         throw new Error(errorData.error || 'Failed to save configuration to CRM database.');
                    });
                }
            })
            .catch(e => {
                if (authStatusMessage) {
                    authStatusMessage.textContent = `Connection Failed: ${e.message}`;
                    authStatusMessage.classList.replace('text-muted', 'text-danger');
                }
                console.error('Meta Connection Error:', e);
                metaLoginButton.disabled = false;
            });
            
        } else {
             if (authStatusMessage) {
                 authStatusMessage.textContent = 'Connection cancelled or denied.';
                 authStatusMessage.classList.replace('text-muted', 'text-danger');
             }
             metaLoginButton.disabled = false; 
        }
    }, {
        scope: 'leads_retrieval,ads_read,ads_management,pages_manage_ads,pages_show_list,business_management'
    });
}

// Stub for fetchAdAccountDetails
async function fetchAdAccountDetails(token) {
    const response = await fetch(`https://graph.facebook.com/v19.0/me/adaccounts?fields=name,id&access_token=${token}`);
    if(!response.ok) throw new Error("Failed to fetch ad accounts from Meta");
    const data = await response.json();
    if(!data.data || data.data.length === 0) throw new Error("No Ad Accounts found.");
    
    return {
        token: token,
        id: data.data[0].id,
        name: data.data[0].name
    };
}

// --- Event Listeners ---
if (metaLoginButton) metaLoginButton.addEventListener('click', handleMetaLogin);
if (metaMappingForm) metaMappingForm.addEventListener('submit', saveFieldMapping);
if (loadFieldsButton) loadFieldsButton.addEventListener('click', handleLoadFields);

document.addEventListener('DOMContentLoaded', () => {
    checkMetaConnectionStatus();
    if (integrationsPane) {
        integrationsPane.addEventListener('show.bs.tab', checkMetaConnectionStatus);
    }
});