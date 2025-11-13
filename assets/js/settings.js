const USER_ACCESS_TOKEN = 'EAA1OKJP7WowBP2fPE1rZAbEjZBsbhY67LmrDjAL0vwJqNkwU8a3XJY1n8ZAC3uppVj90F1ZCRZCGz1B0U8p3bhIqXMoZBvLVIAR1TMdZBoYEs8bn5NMwZCnJH3tWZBU0KVt4B9Y88r6yztkIhpv8fXp2uok1UTtv58VlfDo3rnHt60nED86fKxP4HrlfD1ZB7ZA';
const AD_ACCOUNT_ID = 'act_101582234567890123'; // Mock Ad Account ID
const ACCOUNT_NAME = 'Primary Ad Account for Digitifyou';

// Assuming the saveMetaAccount function is defined (it is in assets/js/settings_integrations.js)
// We need to fetch and redefine it globally for console access, as it's typically hidden.

async function executeSave(token, accountId, name) {
    const API_SYSTEM_FIELDS = '/api/v1/system_fields.php'; // Existing endpoint used for config
    const userId = 1; 

    // Simulate saving account details to the server
    const response = await fetch(API_SYSTEM_FIELDS, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            // Note: field_key is abused here to store the entire account config
            field_key: 'meta_account_config', 
            display_name: name,
            access_token: token,
            ad_account_id: accountId,
            user_id: userId
        })
    });
    return response.ok ? 'SUCCESS' : 'FAILURE';
}

executeSave(USER_ACCESS_TOKEN, AD_ACCOUNT_ID, ACCOUNT_NAME)
    .then(status => {
        if (status === 'SUCCESS') {
            console.log("✅ Meta Access Token and Config Saved Successfully to the CRM.");
            alert("Connection Saved. The CRM is now configured to pull live data.");
            
            // Manually update the UI status since we bypassed handleMetaLogin
            const statusElement = document.getElementById('meta-connection-status');
            if (statusElement) {
                statusElement.textContent = 'Connected';
                statusElement.classList.replace('bg-danger', 'bg-success');
            }
            // You may need to manually call loadMappingSection() if you don't refresh
            // loadMappingSection();
        } else {
            console.error("❌ Failed to save connection details. Check network status and API response.");
        }
    })
    .catch(e => {
        console.error("Critical error during saving:", e);
    });