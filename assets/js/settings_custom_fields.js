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


// --- DOM Elements (now placeholders) ---
let customFieldsTableBody;
let addCustomFieldForm;
let addCustomFieldModal;
let customFieldsPane;

let fieldOptionsGroup;
let fieldOptionsTextarea;
let isScoreFieldCheckbox;
let scoreRulesPanel;
let scoreRuleInputs;

let modalTitle;
let fieldIdInput;
let fieldKeyInput;
let fieldNameInput;
let fieldTypeSelect;
let isRequiredCheckbox;


/**
 * Toggles the visibility of the Options and Score Rule panels.
 */
function toggleFieldSections() {
    const isSelect = fieldTypeSelect.value === 'select';
    const isScored = isScoreFieldCheckbox.checked;

    if (isSelect) {
        fieldOptionsGroup.classList.remove('d-none');
    } else {
        fieldOptionsGroup.classList.add('d-none');
    }
    
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
 * @param {Object} config - Object containing system_field_config data keyed by field_key.
 */
function applySystemConfig(config) {
    DEFAULT_FIELDS = DEFAULT_FIELDS.map(defaultField => {
        const key = defaultField.field_key;
        if (config[key]) {
            const saved = config[key];
            return {
                ...defaultField,
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
        
        let scoreDisplay;
        if (field.is_score_field) {
            const hasRules = field.scoring_rules && field.scoring_rules.length > 2; 
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
        
        const [customResponse, systemResponse] = await Promise.all([
            fetch(API_CUSTOM_FIELDS),
            fetch(API_SYSTEM_FIELDS)
        ]);

        if (!customResponse.ok) throw new Error('Could not fetch custom fields');
        if (!systemResponse.ok) throw new Error('Could not fetch system configuration');

        const customFields = await customResponse.json();
        const systemConfig = await systemResponse.json();

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
    
    resetAddModal();
    modalTitle.textContent = `Edit Field: ${fieldData.field_name}`;
    fieldIdInput.value = isCustom ? fieldData.field_id : fieldData.field_key; 
    
    fieldNameInput.value = fieldData.field_name;
    fieldKeyInput.value = fieldData.field_key;
    fieldTypeSelect.value = fieldData.field_type;
    
    isRequiredCheckbox.checked = fieldData.is_required;
    isScoreFieldCheckbox.checked = fieldData.is_score_field;
    
    const hiddenRulesInput = document.getElementById('scoring_rules_json_hidden');
    if (hiddenRulesInput) {
        hiddenRulesInput.value = fieldData.scoring_rules || ''; 
    }
    
    fieldKeyInput.disabled = true; 
    fieldTypeSelect.disabled = !isCustom;
    
    isScoreFieldCheckbox.disabled = fieldData.field_key === 'lead_score';
    
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

    toggleFieldSections();
    if (isScoreFieldCheckbox.checked) {
         populateRuleInputs(fieldData.scoring_rules);
    }

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
    
    const hiddenRulesInput = document.getElementById('scoring_rules_json_hidden');
    if (hiddenRulesInput) hiddenRulesInput.value = '';
    
    addCustomFieldForm.reset(); 
    
    toggleFieldSections(); 
    populateRuleInputs(null); 
}

/**
 * Handles the form submission (POST for Create, PUT for Update)
 */
async function handleAddCustomField(event) {
    event.preventDefault();

    const isUpdate = !!fieldIdInput.value;
    const isDefaultField = isUpdate && isNaN(parseInt(fieldIdInput.value)) || parseInt(fieldIdInput.value) < 0; 
    
    const formData = new FormData(addCustomFieldForm);
    const fieldData = {};
    
    fieldData.field_name = fieldNameInput.value;
    fieldData.field_key = fieldKeyInput.value;
    fieldData.field_type = fieldTypeSelect.value;
    fieldData.is_required = isRequiredCheckbox.checked;
    fieldData.is_score_field = isScoreFieldCheckbox.checked;
    fieldData.options = fieldOptionsTextarea.value;
    
    if (isUpdate) fieldData.field_id = fieldIdInput.value;

    const hiddenRulesInput = document.getElementById('scoring_rules_json_hidden');

    if (fieldData.is_score_field) {
        const rules = {};
        scoreRuleInputs.forEach(input => {
            const level = input.getAttribute('data-level');
            const values = input.value.split(',').map(v => v.trim()).filter(v => v.length > 0);
            if (values.length > 0) {
                rules[level] = input.value; 
            }
        });
        fieldData.scoring_rules = JSON.stringify(rules);
    } else {
        fieldData.scoring_rules = null;
    }
    
    delete fieldData.score_weight_level; 
    if (isUpdate) delete fieldData.field_key;


    if (isUpdate && isDefaultField) {
        try {
            const response = await fetch(API_SYSTEM_FIELDS, {
                method: 'POST', 
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
        await loadCustomFields(); 
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

document.addEventListener('DOMContentLoaded', () => {
    // 1. Element Lookups & Modal Instantiation
    customFieldsTableBody = document.getElementById('custom-fields-list-table');
    addCustomFieldForm = document.getElementById('add-custom-field-form');
    customFieldsPane = document.getElementById('custom-fields-pane');
    
    fieldOptionsGroup = document.getElementById('field-options-group');
    fieldOptionsTextarea = document.getElementById('options');
    isScoreFieldCheckbox = document.getElementById('is_score_field');
    scoreRulesPanel = document.getElementById('score-rules-panel');
    scoreRuleInputs = document.querySelectorAll('.score-input-rules');
    
    modalTitle = document.getElementById('addCustomFieldModalLabel');
    fieldIdInput = document.getElementById('field_id_input');
    fieldKeyInput = document.getElementById('field_key');
    fieldNameInput = document.getElementById('field_name');
    fieldTypeSelect = document.getElementById('field_type');
    isRequiredCheckbox = document.getElementById('is_required');


    if (typeof bootstrap !== 'undefined') {
        const addCustomFieldModalElement = document.getElementById('addCustomFieldModal');
        if (addCustomFieldModalElement) {
            addCustomFieldModal = new bootstrap.Modal(addCustomFieldModalElement);
        }
    }
    
    // 2. Initial Load Logic
    loadCustomFields();
    
    // 3. Event Listeners
    document.getElementById('field_type').addEventListener('change', toggleFieldSections);
    isScoreFieldCheckbox.addEventListener('change', toggleFieldSections);
    
    if (customFieldsPane) {
        customFieldsPane.addEventListener('show.bs.tab', loadCustomFields);
    }

    if (addCustomFieldForm) {
        addCustomFieldForm.addEventListener('submit', handleAddCustomField);
    }
});