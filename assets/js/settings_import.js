// /assets/js/settings_import.js

const API_IMPORT = '/api/v1/import_leads.php';
const API_CUSTOM_FIELDS = '/api/v1/custom_fields.php'; 

// --- DOM Elements ---
const bulkImportPane = document.getElementById('bulk-import-pane');
const importForm = document.getElementById('bulk-import-form');
const googleSheetLinkInput = document.getElementById('google_sheet_link');
const fileUploadInput = document.getElementById('csv_file_upload'); 
const loadColumnsButton = document.getElementById('load-columns-button');
const startImportButton = document.getElementById('start-import-button');
const mappingTableBody = document.getElementById('import-mapping-table');
const statusAlert = document.getElementById('import-status-alert');
const urlInputGroup = document.getElementById('url-input-group');
const fileInputGroup = document.getElementById('file-input-group');
const downloadSampleCsv = document.getElementById('download-sample-csv');

// --- State and Cache ---
let crmFields = []; 
let sheetHeaders = []; 
let sheetData = [];   

// --- Sample CSV Content (Production-Ready) ---
const SAMPLE_CSV_CONTENT = `Full_Name,Phone,Email,Course_Interested_ID,Qualification,Work_Experience,Custom_Field_Example
Arjun Sharma,9876543210,arjun.s@example.com,1,B.Tech,Fresher,Looking for high-paying job
Priya Patel,9988776655,priya.p@example.com,2,MBA,2-5 Years,Wants evening classes
Vishal Singh,9000011111,vishal.s@example.com,1,BSc,5+ Years,Needs payment plan
`;


/**
 * Helper to fetch all CRM fields (Built-in + Custom) for the mapping dropdown.
 */
async function fetchAllCrmFields() {
    // 1. Built-in fields (must match students table columns)
    crmFields = [
        { key: 'full_name', name: 'Full Name (REQUIRED)', is_built_in: true },
        { key: 'phone', name: 'Phone Number (REQUIRED)', is_built_in: true },
        { key: 'email', name: 'Email Address', is_built_in: true },
        { key: 'course_interested_id', name: 'Course Interested ID (Use Course ID 1, 2, 3...)', is_built_in: true },
        { key: 'lead_source', name: 'Lead Source (e.g., Google, Referral)', is_built_in: true },
        { key: 'qualification', name: 'Qualification', is_built_in: true },
        { key: 'work_experience', name: 'Work Experience', is_built_in: true }
    ];
    
    // 2. Custom fields
    try {
        const customResponse = await fetch(API_CUSTOM_FIELDS);
        const customFields = await customResponse.json();
        
        customFields.forEach(field => {
            crmFields.push({
                key: field.field_key,
                name: `${field.field_name} (Custom Field)`,
                is_built_in: false
            });
        });
    } catch (e) {
        console.error('Could not fetch CRM custom fields for mapping.', e);
    }
}

/**
 * Parses raw CSV/TSV text into an array of objects.
 */
function parseTextData(text) {
    const lines = text.trim().split(/\r?\n/);
    if (lines.length === 0) return { headers: [], data: [] };

    const delimiter = lines[0].includes('\t') ? '\t' : (lines[0].includes(';') ? ';' : ',');
    
    // Simple split function (does not handle complex quoted fields well)
    const splitLine = (line) => line.split(delimiter).map(v => v.trim().replace(/^"|"$/g, ''));

    const headers = splitLine(lines[0]);
    const data = [];

    for (let i = 1; i < lines.length; i++) {
        if (!lines[i].trim()) continue;
        const values = splitLine(lines[i]);
        const rowObject = {};
        
        for (let j = 0; j < headers.length; j++) {
            rowObject[headers[j]] = values[j] || '';
        }
        data.push(rowObject);
    }
    
    return { headers, data };
}


/**
 * Fetches and parses the CSV data based on the selected input method.
 * @param {string} type - 'url' or 'file'
 */
async function loadAndParseSheetData(type) {
    sheetHeaders = []; 
    sheetData = [];   
    
    statusAlert.classList.remove('d-none', 'alert-danger', 'alert-success');
    statusAlert.classList.add('alert-info');
    statusAlert.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading and parsing data...';

    let rawText = '';

    try {
        if (type === 'url') {
            const sheetUrl = googleSheetLinkInput.value.trim();
            if (!sheetUrl) throw new Error('URL link is empty.');

            const response = await fetch(sheetUrl);
            if (!response.ok) {
                throw new Error(`Failed to fetch data. HTTP Status: ${response.status}. Ensure the link is published to the web.`);
            }
            rawText = await response.text();

        } else if (type === 'file') {
            const file = fileUploadInput.files[0];
            if (!file) throw new Error('No file selected.');

            rawText = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (e) => reject(new Error('Error reading file.'));
                reader.readAsText(file);
            });
        }
        
        const { headers, data } = parseTextData(rawText);
        
        if (headers.length === 0 || data.length === 0) {
            throw new Error('No valid columns or rows found in the source content.');
        }

        sheetHeaders = headers;
        sheetData = data;
        
        statusAlert.innerHTML = `<i class="bi bi-check-circle"></i> ${sheetHeaders.length} columns and ${sheetData.length} leads detected. Ready for mapping.`;
        return true;

    } catch (error) {
        console.error('Data Fetch/Parse Error:', error);
        statusAlert.classList.replace('alert-info', 'alert-danger');
        statusAlert.innerHTML = `<i class="bi bi-x-circle"></i> Error: ${error.message}`;
        return false;
    }
}


/**
 * Renders the mapping table using the actual fetched headers.
 */
function renderMappingTable() {
    mappingTableBody.innerHTML = '';
    
    if (sheetHeaders.length === 0) {
        mappingTableBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No source columns loaded.</td></tr>';
        startImportButton.disabled = true;
        return;
    }
    
    startImportButton.disabled = false; 

    sheetHeaders.forEach(header => {
        let optionsHtml = '<option value="">-- Ignore Field --</option>';
        
        crmFields.forEach(crmField => {
            // Basic auto-match based on keywords
            const headerLower = header.toLowerCase().replace(/[^a-z0-9]/g, '');
            const fieldKeyLower = crmField.key.toLowerCase().replace(/[^a-z0-9]/g, '');
            
            const selected = headerLower.includes(fieldKeyLower) ? 'selected' : '';

            optionsHtml += `<option value="${crmField.key}" ${selected}>${crmField.name}</option>`;
        });
        
        const row = `
            <tr>
                <td><strong>${header}</strong></td>
                <td>
                    <select class="form-select form-select-sm mapping-select" name="${header}" required>
                        ${optionsHtml}
                    </select>
                </td>
            </tr>
        `;
        mappingTableBody.insertAdjacentHTML('beforeend', row);
    });
}

/**
 * Handles the "Load Columns" button click.
 */
async function handleLoadColumns() {
    console.log('Loading columns based on input data...');
    // 1. Determine source type based on input data presence
    const fileSelected = fileUploadInput.files.length > 0;
    const urlEntered = googleSheetLinkInput.value.trim().length > 0;

    let selectedType = null;
    if (fileSelected) {
        selectedType = 'file';
    } else if (urlEntered) {
        selectedType = 'url';
    } else {
        // If neither input has data, show an error and stop.
        statusAlert.classList.remove('d-none');
        statusAlert.classList.add('alert-danger');
        statusAlert.innerHTML = 'Please enter a URL link OR select a file to upload.';
        return;
    }

    // 2. Clear previous errors/status
    statusAlert.classList.remove('alert-danger');
    statusAlert.classList.add('d-none');

    // 3. Load and render
    const success = await loadAndParseSheetData(selectedType);
    if (success) {
        renderMappingTable();
    }
}


/**
 * Handles the "Start Bulk Import" form submission.
 */
async function handleBulkImport(event) {
    event.preventDefault();
    startImportButton.disabled = true;
    
    // 1. Collect Mapping
    const fieldMapping = {};
    const selects = mappingTableBody.querySelectorAll('.mapping-select');
    
    let hasName = false;
    let hasPhone = false;

    selects.forEach(select => {
        const sourceHeader = select.name;
        const crmFieldKey = select.value;
        
        if (crmFieldKey) {
            fieldMapping[sourceHeader] = crmFieldKey;
            if (crmFieldKey === 'full_name') hasName = true;
            if (crmFieldKey === 'phone') hasPhone = true;
        }
    });
    
    if (!hasName || !hasPhone) {
        statusAlert.classList.replace('alert-info', 'alert-danger');
        statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle"></i> You must map a source column to **Full Name** and **Phone Number** to proceed.';
        startImportButton.disabled = false;
        return;
    }

    // 2. Prepare Data Payload
    const payload = {
        field_mapping: fieldMapping,
        import_data: sheetData 
    };

    statusAlert.classList.replace('alert-success', 'alert-info');
    statusAlert.innerHTML = `<i class="bi bi-gear-wide"></i> Starting import of ${sheetData.length} records...`;

    // 3. Send to API
    try {
        const response = await fetch(API_IMPORT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'API failed to process import.');
        }

        let alertClass = result.error_count > 0 ? 'alert-warning' : 'alert-success';
        let message = `<i class="bi bi-check-circle-fill"></i> Import Complete! **${result.success_count} leads** successfully imported.`;
        
        if (result.error_count > 0) {
            message += ` (${result.error_count} records skipped or failed). Check your browser console for specific errors.`;
            if (result.errors) console.table(result.errors);
        }
        
        statusAlert.classList.replace('alert-info', alertClass);
        statusAlert.innerHTML = message;
        
        // Reset UI after import
        googleSheetLinkInput.value = ''; 
        fileUploadInput.value = '';
        mappingTableBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Import finished.</td></tr>';

    } catch (error) {
        console.error('Import Error:', error);
        statusAlert.classList.replace('alert-info', 'alert-danger');
        statusAlert.innerHTML = `<i class="bi bi-x-circle"></i> Import failed: ${error.message}`;
    }
    
    startImportButton.disabled = false;
}

/**
 * Toggles visibility of URL vs File input fields.
 * NOTE: This function is preserved but its body is empty as the visibility toggle logic was removed from the HTML.
 */
function handleSourceTypeChange(event) {
    // We only manage required attributes implicitly by checking input data now.
    // However, we preserve the required logic here for explicit form validation if needed.
    if (event.target.value === 'url') {
        googleSheetLinkInput.setAttribute('required', 'required');
        fileUploadInput.removeAttribute('required');
    } else {
        googleSheetLinkInput.removeAttribute('required');
        fileUploadInput.setAttribute('required', 'required');
    }

    // Clear previous state on type change
    sheetHeaders = [];
    sheetData = [];
    mappingTableBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Select a source and click Load to see columns.</td></tr>';
    startImportButton.disabled = true;
    statusAlert.classList.add('d-none');
}

/**
 * Creates and triggers the download of the sample CSV file.
 */
function handleDownloadSample() {
    const filename = 'sample_leads_import.csv';
    const blob = new Blob([SAMPLE_CSV_CONTENT], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    
    // Create a temporary link element and click it
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}


// --- Initialization ---
document.addEventListener('DOMContentLoaded', async () => {
    await fetchAllCrmFields();
    
    loadColumnsButton.addEventListener('click', handleLoadColumns);
    importForm.addEventListener('submit', handleBulkImport);
    downloadSampleCsv.addEventListener('click', handleDownloadSample);
    
    // No more radio button listeners needed as the detection is now done in handleLoadColumns
    // However, if the user leaves the radio buttons in the HTML, we'll keep the listener to manage the 'required' state.
    document.querySelectorAll('input[name="source_type"]').forEach(radio => {
        radio.addEventListener('change', handleSourceTypeChange);
    });

    // Manually set required state on load, assuming URL is the default checked item
    googleSheetLinkInput.setAttribute('required', 'required');
    fileUploadInput.removeAttribute('required');
});