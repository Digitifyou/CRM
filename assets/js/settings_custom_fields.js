// /assets/js/settings_custom_fields.js

const API_CUSTOM_FIELDS = '/api/v1/custom_fields.php';
const API_SYSTEM_FIELDS = '/api/v1/system_fields.php'; 

// --- Default Built-in Fields for Display (System Fields) ---
let DEFAULT_FIELDS = [
    { field_id: -1, field_name: 'Full Name', field_key: 'full_name', field_type: 'text', is_score_field: false, is_required: true, is_default: true, options: null, scoring_rules: null },
    { field_id: -2, field_name: 'Email Address', field_key: 'email', field_type: 'text', is_score_field: false, is_required: false, is_default: true, options: null, scoring_rules: null },
    { field_id: -3, field_name: 'Phone Number', field_key: 'phone', field_type: 'text', is_score_field: false, is_required: true, is_default: true, options: null, scoring_rules: null },
    { field_id: -4, field_name: 'Status (CRM)', field_key: 'status', field_type: 'select', is_score_field: false, is_required: true, is_default: true, options: 'Inquiry, Active Student, Alumni', scoring_rules: null },
    { field_id: -5, field_name: 'Course Interested', field_key: 'course_interested_id', field_type: 'select', is_score_field: true, is_required: false, is_default: true, options: 'Courses List', scoring_rules: '{"High": "Full Stack", "Medium": "Digital Marketing", "Low": "Other"}' }, 
    { field_id: -6, field_name: 'Lead Source', field_key: 'lead_source', field_type: 'select', is_score_field: true, is_required: false, is_default: true, options: 'Meta, Google, Referral, Walk-in', scoring_rules: '{"High": "Referral, Walk-in", "Medium": "Meta, Google", "Low": "Other"}' }, 
    { field_id: -7, field_name: 'Qualification', field_key: 'qualification', field_type: 'text', is_score_field: true, is_required: false, is_default: true, options: null, scoring_rules: '{"default": "Low"}' }, 
    { field_id: -8, field_name: 'Work Experience', field_key: 'work_experience', field_type: 'select', is_score_field: true, is_required: false, is_default: true, options: 'Fresher, 0-2 Years, 2-5 Years, 5+ Years', scoring_rules: '{"High": "Fresher", "Medium": "2-5 Years, 0-2 Years", "Low": "5+ Years, Career Gap"}' }, 
    { field_id: -9, field_name: 'Lead Score', field_key: 'lead_score', field_type: 'number', is_score_field: true, is_required: false, is_default: true, options: 'System Calculated', scoring_rules: null },
];

// --- Store all fields (default + custom) for easy lookup ---
let allFieldsCache = {};


// --- DOM Elements ---
const customFieldsTableBody = document.getElementById('custom-fields-list-table');
const addCustomFieldForm = document.getElementById('add-custom-field-form');
const addCustomFieldModal = new bootstrap.Modal(document.getElementById('addCustomFieldModal'));
const customFieldsPane = document.getElementById('custom-fields-pane');

const fieldOptionsGroup = document.getElementById('field-options-group');
const fieldOptionsTextarea = document.getElementById('options');
const isScoreFieldCheckbox = document.getElementById('is_score_field');

// Get hidden rule input and score weight select separately for safety checks
const hiddenRulesInput = document.getElementById('scoring_rules_json_hidden');
const scoreWeightLevelSelect = document.getElementById('score_weight_level');

// NEW/MODIFIED ELEMENTS FOR SCORING UI
const scoreRulesPanel = document.getElementById('score-rules-panel');
const scoreRuleInputs = document.querySelectorAll('.score-input-rules'); // Get all High/Medium/Low textareas


const modalTitle = document.getElementById('addCustomFieldModalLabel');
const fieldIdInput = document.getElementById('field_id_input');
const fieldKeyInput = document.getElementById('field_key');
const fieldNameInput = document.getElementById('field_name');
const fieldTypeSelect = document.getElementById('field_type');
const isRequiredCheckbox = document.getElementById('is_required');


/**
 * Toggles the visibility of the Options and Score Rule panels.
 */
function toggleFieldSections() {
    const isSelect = fieldTypeSelect.value === 'select';
    const isScored = isScoreFieldCheckbox.checked;

    // 1. Toggle Options Input (needed for select fields only)
    if (isSelect) {
        fieldOptionsGroup.classList.remove('d-none');
    } else {
        fieldOptionsGroup.classList.add('d-none');
    }
    
    // 2. Toggle Score Rule Panel
    if (isScored) {
        scoreRulesPanel.classList.remove('d-none');
    } else {
        scoreRulesPanel.classList.add('d-none');
    }
}


/**
 * Populates the three rule text areas (High, Medium, Low) based on stored JSON.
 * @param {string} rulesJson - JSON string of scoring rules, e.g., '{"High": "Fresher", "Low": "Career Gap"}'
 */
function populateRuleInputs(rulesJson) {
    const defaultRules = { High: '', Medium: '', Low: '' };
    let rules = defaultRules;
    
    if (rulesJson) {
        try {
            // Note: Scoring rules are stored as a stringified JSON object
            rules = JSON.parse(rulesJson);
        } catch (e) {
            console.error("Error parsing scoring rules JSON:", e);
        }
    }
    
    scoreRuleInputs.forEach(input => {
        const level = input.getAttribute('data-level');
        input.value = rules[level] || '';
    });
}


/**
 * Merges loaded system configurations with the hardcoded DEFAULT_FIELDS array.
 * This is the crucial step to making built-in field changes persist across sessions.
 * @param {Object} config - Object containing system_field_config data keyed by field_key.
 */
function applySystemConfig(config) {
    DEFAULT_FIELDS = DEFAULT_FIELDS.map(defaultField => {
        const key = defaultField.field_key;
        if (config[key]) {
            const saved = config[key];
            // Merge saved configuration into the default definition
            return {
                ...defaultField,
                // Only overwrite fields that are configurable (name, required, scoring)
                field_name: saved.display_name || defaultField.field_name,
                is_required: saved.is_required == 1,
                is_score_field: saved.is_score_field == 1,
                scoring_rules: saved.scoring_rules || defaultField.scoring_rules
            };
        }
        return defaultField;
    });
}


/**
 * Builds the combined list of all fields and caches them for quick lookup.
 * @param {Array} customFields - Array of custom fields fetched from API.
 */
function buildFieldCache(customFields) {
    allFieldsCache = {};
    const combinedFields = [...DEFAULT_FIELDS, ...customFields];
    
    combinedFields.forEach(field => {
        const key = field.field_id > 0 ? field.field_id : field.field_key;
        allFieldsCache[key] = field;
    });
    
    return combinedFields;
}


/**
 * Renders the list of custom fields into the table
 */
async function renderCustomFieldsTable(customFields) {
    customFieldsTableBody.innerHTML = ''; 
    
    const allFields = buildFieldCache(customFields);
    
    if (allFields.length === 0) {
        customFieldsTableBody.innerHTML = '<tr><td colspan="5" class="text-center">No custom fields defined.</td></tr>';
        return;
    }

    allFields.forEach(field => {
        const isDefault = field.is_default || false;
        const isScoreLocked = isDefault && field.field_key === 'lead_score'; 
        
        // Determine score display: Configured or Simple
        let scoreDisplay;
        if (field.is_score_field) {
            const hasRules = field.scoring_rules && field.scoring_rules.length > 2; // Check if rules JSON is present
            const scoreText = hasRules ? 'Rules Set' : 'Simple Yes/No';

            scoreDisplay = `<i class="bi bi-star-fill text-warning me-1"></i> ${scoreText}`;
        } else {
            scoreDisplay = `<i class="bi bi-star text-muted me-1"></i> No`;
        }
            
        const requiredBadge = field.is_required ? '<span class="badge bg-danger ms-2">REQUIRED</span>' : '';
        const defaultTag = isDefault ? '<span class="badge bg-secondary ms-2">BUILT-IN</span>' : '';
        const rowClass = isDefault ? 'table-secondary' : '';

        const row = document.createElement('tr');
        row.className = rowClass;
        row.dataset.id = field.field_id > 0 ? field.field_id : field.field_key;
        
        row.innerHTML = `
            <td>${field.field_name} ${requiredBadge} ${defaultTag}</td>
            <td>${field.field_key}</td>
            <td class="text-capitalize">${field.field_type}</td>
            <td>${scoreDisplay}</td>
            <td class="text-end">
                ${isScoreLocked ? 
                    '<span class="text-muted">System Value</span>' :
                    `<button class="btn btn-sm btn-outline-primary me-2" data-edit-id="${row.dataset.id}" onclick="editCustomField(event)">
                        <i class="bi bi-pencil"></i> Edit
                    </button>`
                }
                ${(isDefault || isScoreLocked) ? 
                    '' : 
                    `<button class="btn btn-sm btn-danger" onclick="deleteCustomField(${field.field_id})">
                        <i class="bi bi-trash"></i>
                    </button>`
                }
            </td>
        `;
        customFieldsTableBody.appendChild(row);
    });
}

/**
 * Fetches all custom fields AND system configurations from the APIs and renders them
 */
async function loadCustomFields() {
    try {
        customFieldsTableBody.innerHTML = '<tr><td colspan="5" class="text-center">Loading custom fields...</td></tr>';
        
        // Fetch custom fields and system configuration in parallel
        const [customResponse, systemResponse] = await Promise.all([
            fetch(API_CUSTOM_FIELDS),
            fetch(API_SYSTEM_FIELDS)
        ]);

        if (!customResponse.ok) throw new Error('Could not fetch custom fields');
        if (!systemResponse.ok) throw new Error('Could not fetch system configuration');

        const customFields = await customResponse.json();
        const systemConfig = await systemResponse.json();

        // MERGE: Apply saved configuration to the default fields array
        applySystemConfig(systemConfig);

        renderCustomFieldsTable(customFields);

    } catch (error) {
        console.error('Error loading custom fields:', error);
        customFieldsTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load custom fields.</td></tr>';
    }
}


/**
 * Populates the modal with data for editing
 */
function editCustomField(event) {
    const fieldIdKey = event.target.closest('button').dataset.editId;
    const fieldData = allFieldsCache[fieldIdKey];
    
    if (!fieldData) return;
    
    const isCustom = fieldData.field_id > 0;
    
    // 1. Reset and Set IDs
    resetAddModal();
    modalTitle.textContent = `Edit Field: ${fieldData.field_name}`;
    fieldIdInput.value = isCustom ? fieldData.field_id : fieldData.field_key; 
    
    // 2. Populate form fields
    fieldNameInput.value = fieldData.field_name;
    fieldKeyInput.value = fieldData.field_key;
    fieldTypeSelect.value = fieldData.field_type;
    
    // Checkboxes 
    isRequiredCheckbox.checked = fieldData.is_required;
    isScoreFieldCheckbox.checked = fieldData.is_score_field;
    
    // Score Weight and Rules 
    if (hiddenRulesInput) {
        hiddenRulesInput.value = fieldData.scoring_rules || ''; 
    }
    
    // 3. Handle field locking for built-in fields
    fieldKeyInput.disabled = true; 
    fieldTypeSelect.disabled = !isCustom;
    
    // 4. Handle Lead Score lock
    isScoreFieldCheckbox.disabled = fieldData.field_key === 'lead_score';
    
    // 5. Handle Select options
    fieldOptionsTextarea.value = '';
    if (fieldData.field_type === 'select') {
        let optionsDisplay = fieldData.options;
        try {
            const optionsArray = JSON.parse(fieldData.options);
            optionsDisplay = Array.isArray(optionsArray) ? optionsArray.join(', ') : fieldData.options;
        } catch (e) {
        }
        fieldOptionsTextarea.value = optionsDisplay || '';
        fieldOptionsTextarea.disabled = !isCustom; 
    } 

    // 6. Toggle visibility and generate dynamic elements
    toggleFieldSections();
    // Re-run rule population for all fields that are scored
    if (isScoreFieldCheckbox.checked) {
         populateRuleInputs(fieldData.scoring_rules);
    }

    // 7. Open Modal
    addCustomFieldModal.show();
}
window.editCustomField = editCustomField;


/**
 * Resets the modal for adding a new field
 */
function resetAddModal() {
    modalTitle.textContent = 'Define New Lead Field';
    fieldIdInput.value = ''; 
    fieldKeyInput.disabled = false;
    fieldTypeSelect.disabled = false;
    isScoreFieldCheckbox.disabled = false;
    fieldOptionsTextarea.disabled = false;
    
    // Reset score related fields before calling form.reset()
    if (scoreWeightLevelSelect) scoreWeightLevelSelect.value = 'Low'; 
    if (hiddenRulesInput) hiddenRulesInput.value = '';
    
    addCustomFieldForm.reset(); 
    
    // Reset visibility and dynamic containers
    toggleFieldSections(); 
    populateRuleInputs(null); // Clear rule inputs
}

/**
 * Handles the form submission (POST for Create, PUT for Update)
 */
async function handleAddCustomField(event) {
    event.preventDefault();

    const isUpdate = !!fieldIdInput.value;
    // Check if it's a built-in field (non-numeric key or negative ID)
    const isDefaultField = isUpdate && isNaN(parseInt(fieldIdInput.value)) || parseInt(fieldIdInput.value) < 0; 
    
    const formData = new FormData(addCustomFieldForm);
    const fieldData = {};
    
    // --- Manual Serialization of Input Values to ensure fresh data ---
    // Read values directly from the DOM elements
    fieldData.field_name = fieldNameInput.value;
    fieldData.field_key = fieldKeyInput.value;
    fieldData.field_type = fieldTypeSelect.value;
    fieldData.is_required = isRequiredCheckbox.checked;
    fieldData.is_score_field = isScoreFieldCheckbox.checked;
    fieldData.options = fieldOptionsTextarea.value;
    
    if (isUpdate) fieldData.field_id = fieldIdInput.value;


    // 1. Serialize Scoring Rules
    if (fieldData.is_score_field) {
        const rules = {};
        scoreRuleInputs.forEach(input => {
            const level = input.getAttribute('data-level');
            const values = input.value.split(',').map(v => v.trim()).filter(v => v.length > 0);
            if (values.length > 0) {
                rules[level] = input.value; // Store as comma-separated string for simplicity
            }
        });
        fieldData.scoring_rules = JSON.stringify(rules);
    } else {
        fieldData.scoring_rules = null;
    }
    
    // Remove fields not needed for specific actions
    delete fieldData.score_weight_level; 
    if (isUpdate) delete fieldData.field_key;


    // --- LOGIC: HANDLE DEFAULT FIELD UPDATE (DATABASE PERSISTENCE) ---
    if (isUpdate && isDefaultField) {
        // The endpoint is different for system fields
        try {
            const response = await fetch(API_SYSTEM_FIELDS, {
                method: 'POST', // Use POST for UPSERT
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    field_key: fieldIdInput.value,
                    display_name: fieldData.field_name,
                    is_required: fieldData.is_required,
                    is_score_field: fieldData.is_score_field,
                    scoring_rules: fieldData.scoring_rules
                })
            });

            if (!response.ok) {
                 const errorData = await response.json();
                 throw new Error(errorData.error || 'Failed to save built-in field config');
            }

            // After successful DB save, refresh the UI
            addCustomFieldModal.hide();
            alert('Built-in Field settings updated successfully!');
            await loadCustomFields();
            return; 

        } catch (error) {
             console.error('Submission failed:', error);
             alert('Error: ' + error.message);
             return;
        }
    }
    // --- END DEFAULT FIELD UPDATE LOGIC ---


    // --- LOGIC: HANDLE CUSTOM FIELD UPDATE (API CALL - Original logic) ---
    try {
        if (isUpdate) {
            fieldData.field_id = parseInt(fieldIdInput.value); 
        } else {
            delete fieldData.field_id;
        }

        const response = await fetch(API_CUSTOM_FIELDS + (isUpdate ? `?id=${fieldData.field_id}` : ''), {
            method: isUpdate ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(fieldData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || `Failed to ${isUpdate ? 'update' : 'add'} custom field`);
        }

        resetAddModal();
        addCustomFieldModal.hide(); 
        await loadCustomFields(); // Refresh the list
        alert(`Custom Field successfully ${isUpdate ? 'updated' : 'added'}!`);

    } catch (error) {
        console.error('Submission failed:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Deletes a custom field (DELETE request)
 */
async function deleteCustomField(fieldId) {
    if (!confirm('WARNING: Deleting this field definition may cause issues if the column exists in the database. Are you sure?')) {
        return;
    }

    try {
        const response = await fetch(`${API_CUSTOM_FIELDS}?id=${fieldId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to delete field');
        }

        const row = document.querySelector(`tr[data-id="${fieldId}"]`);
        if (row) row.remove();
        
        await loadCustomFields(); 

    } catch (error) {
        console.error('Failed to delete field:', error);
        alert('Error: ' + error.message);
    }
}


// --- Event Listeners ---

// Listen for input changes on score rules textareas to keep the rule JSON updated locally
scoreRuleInputs.forEach(input => {
    input.addEventListener('input', () => {
        // No action needed here, serialization happens on submit.
    });
});

// Hide/Show sections based on type and score checkbox
if (fieldTypeSelect) fieldTypeSelect.addEventListener('change', toggleFieldSections);
if (isScoreFieldCheckbox) isScoreFieldCheckbox.addEventListener('change', toggleFieldSections);


// Event listener for when the Custom Fields tab is clicked
if (customFieldsPane) {
    customFieldsPane.addEventListener('show.bs.tab', loadCustomFields);
}

// Event listener for when the modal is about to be shown (used only for CREATE button)
const addModalEl = document.getElementById('addCustomFieldModal');
if (addModalEl) {
    addModalEl.addEventListener('show.bs.modal', function(event) {
        if (event.relatedTarget && event.relatedTarget.getAttribute('data-bs-target') === '#addCustomFieldModal') {
             if (!fieldIdInput.value || fieldIdInput.value < 0) {
                resetAddModal();
             }
        }
    });
}


document.addEventListener('DOMContentLoaded', () => {
    // MODIFIED: Unconditionally load fields so data is available immediately
    loadCustomFields();
    
    if (addCustomFieldForm) {
        addCustomFieldForm.addEventListener('submit', handleAddCustomField);
    }
});