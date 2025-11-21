// /assets/js/settings_import.js

const API_IMPORT = '/api/v1/import_leads.php';
const API_CUSTOM_FIELDS = '/api/v1/custom_fields.php'; 
const API_GOOGLE_SYNC = '/api/v1/google_sync.php'; // NEW: Google Sync API

// --- DOM Elements ---
const bulkImportPane = document.getElementById('bulk-import-pane');
const importForm = document.getElementById('bulk-import-form');
const googleSheetLinkInput = document.getElementById('google_sheet_link');
const fileUploadInput = document.getElementById('csv_file_upload'); 
const loadColumnsButton = document.getElementById('load-columns-button');
const startImportButton = document.getElementById('start-import-button');
const mappingTableBody = document.getElementById('import-mapping-table');
const statusAlert = document.getElementById('import-status-alert');
const downloadSampleCsv = document.getElementById('download-sample-csv');

// --- State and Cache ---
let crmFields = []; 
let sheetHeaders = []; 
let sheetData = [];   

// --- Sample CSV Content ---
const SAMPLE_CSV_CONTENT = `Full_Name,Phone,Email,Course_Interested_ID,Qualification,Work_Experience,Custom_Field_Example
Arjun Sharma,9876543210,arjun.s@example.com,1,B.Tech,Fresher,Looking for high-paying job
Priya Patel,9988776655,priya.p@example.com,2,MBA,2-5 Years,Wants evening classes
Vishal Singh,9000011111,vishal.s@example.com,1,BSc,5+ Years,Needs payment plan
`;


/**
 * Helper to fetch all CRM fields for the mapping dropdown.
 */
async function fetchAllCrmFields() {
    crmFields = [
        { key: 'full_name', name: 'Full Name (REQUIRED)', is_built_in: true },
        { key: 'phone', name: 'Phone Number (REQUIRED)', is_built_in: true },
        { key: 'email', name: 'Email Address', is_built_in: true },
        { key: 'course_interested_id', name: 'Course Interested ID', is_built_in: true },
        { key: 'lead_source', name: 'Lead Source', is_built_in: true },
        { key: 'qualification', name: 'Qualification', is_built_in: true },
        { key: 'work_experience', name: 'Work Experience', is_built_in: true }
    ];
    
    try {
        const customResponse = await fetch(API_CUSTOM_FIELDS);
        if (customResponse.ok) {
            const customFields = await customResponse.json();
            customFields.forEach(field => {
                crmFields.push({
                    key: field.field_key,
                    name: `${field.field_name} (Custom)`,
                    is_built_in: false
                });
            });
        }
    } catch (e) {
        console.error('Could not fetch CRM custom fields.', e);
    }
}

/**
 * Parses raw CSV text into JSON.
 */
function parseTextData(text) {
    const lines = text.trim().split(/\r?\n/);
    if (lines.length === 0) return { headers: [], data: [] };

    const delimiter = lines[0].includes('\t') ? '\t' : (lines[0].includes(';') ? ';' : ',');
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
 * Fetches data from Google Sheets (via Backend) OR CSV File.
 */
async function handleLoadColumns() {
    // 1. Reset UI
    sheetHeaders = []; 
    sheetData = [];   
    statusAlert.classList.remove('d-none', 'alert-danger', 'alert-success');
    statusAlert.classList.add('alert-info');
    statusAlert.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing data...';
    loadColumnsButton.disabled = true;

    try {
        // 2. Determine Source
        const file = fileUploadInput.files[0];
        const sheetUrl = googleSheetLinkInput.value.trim();

        if (file) {
            // --- OPTION A: CSV FILE ---
            statusAlert.innerHTML = '<i class="bi bi-file-earmark-text"></i> Reading file...';
            const rawText = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (e) => reject(new Error('Error reading file.'));
                reader.readAsText(file);
            });
            const parsed = parseTextData(rawText);
            sheetHeaders = parsed.headers;
            sheetData = parsed.data;

        } else if (sheetUrl) {
            // --- OPTION B: GOOGLE SHEET (New Logic) ---
            statusAlert.innerHTML = '<i class="bi bi-cloud-arrow-down"></i> Connecting to Google...';
            
            const response = await fetch(API_GOOGLE_SYNC, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sheet_url: sheetUrl })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Failed to connect to Google Sheets.');
            }

            sheetHeaders = result.headers;
            sheetData = result.data;

        } else {
            throw new Error('Please enter a Google Sheet URL or upload a CSV file.');
        }
        
        // 3. Validate Data
        if (sheetHeaders.length === 0 || sheetData.length === 0) {
            throw new Error('No data found. The sheet or file appears empty.');
        }

        // 4. Render Mapping Table
        renderMappingTable();
        
        statusAlert.classList.replace('alert-info', 'alert-success');
        statusAlert.innerHTML = `<i class="bi bi-check-circle"></i> Successfully loaded <strong>${sheetData.length} rows</strong>. Please map the columns below.`;

    } catch (error) {
        console.error('Load Error:', error);
        statusAlert.classList.replace('alert-info', 'alert-danger');
        statusAlert.innerHTML = `<i class="bi bi-x-circle"></i> ${error.message}`;
        
        if (error.message.includes('permission') || error.message.includes('403')) {
             statusAlert.innerHTML += `<br><small class="ms-4"><strong>Tip:</strong> Did you share the sheet with the service email?</small>`;
        }
    } finally {
        loadColumnsButton.disabled = false;
    }
}


/**
 * Renders the mapping table using the fetched headers.
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
            // Smart Match: fuzzy match headers to CRM fields
            const headerLower = header.toLowerCase().replace(/[^a-z0-9]/g, '');
            const fieldKeyLower = crmField.key.toLowerCase().replace(/[^a-z0-9]/g, '');
            
            // Check exact match or partial inclusion
            const selected = (headerLower === fieldKeyLower || headerLower.includes(fieldKeyLower)) ? 'selected' : '';

            optionsHtml += `<option value="${crmField.key}" ${selected}>${crmField.name}</option>`;
        });
        
        const row = `
            <tr>
                <td><strong>${header}</strong></td>
                <td>
                    <select class="form-select form-select-sm mapping-select" name="${header}">
                        ${optionsHtml}
                    </select>
                </td>
            </tr>
        `;
        mappingTableBody.insertAdjacentHTML('beforeend', row);
    });
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
        statusAlert.classList.remove('alert-success', 'alert-info');
        statusAlert.classList.add('alert-danger');
        statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle"></i> You must map <strong>Full Name</strong> and <strong>Phone Number</strong>.';
        startImportButton.disabled = false;
        return;
    }

    // 2. Prepare Payload
    const payload = {
        field_mapping: fieldMapping,
        import_data: sheetData 
    };

    statusAlert.classList.remove('alert-danger', 'alert-success');
    statusAlert.classList.add('alert-info');
    statusAlert.innerHTML = `<i class="bi bi-arrow-repeat spinner-border spinner-border-sm"></i> Importing ${sheetData.length} leads...`;

    // 3. Send to API
    try {
        const response = await fetch(API_IMPORT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Import failed on server.');
        }

        let alertClass = result.error_count > 0 ? 'alert-warning' : 'alert-success';
        let message = `<i class="bi bi-check-circle-fill"></i> <strong>Done!</strong> ${result.success_count} imported successfully.`;
        
        if (result.error_count > 0) {
            message += `<br><small>${result.error_count} duplicates/errors skipped.</small>`;
        }
        
        statusAlert.classList.remove('alert-info');
        statusAlert.classList.add(alertClass);
        statusAlert.innerHTML = message;
        
        // Reset UI
        googleSheetLinkInput.value = ''; 
        fileUploadInput.value = '';
        mappingTableBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Import finished.</td></tr>';
        startImportButton.disabled = true;

    } catch (error) {
        console.error('Import Error:', error);
        statusAlert.classList.replace('alert-info', 'alert-danger');
        statusAlert.innerHTML = `<i class="bi bi-x-circle"></i> Import failed: ${error.message}`;
        startImportButton.disabled = false;
    }
}

function handleDownloadSample() {
    const filename = 'sample_leads_import.csv';
    const blob = new Blob([SAMPLE_CSV_CONTENT], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
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
    if (importForm) importForm.addEventListener('submit', handleBulkImport);
    if (downloadSampleCsv) downloadSampleCsv.addEventListener('click', handleDownloadSample);
});