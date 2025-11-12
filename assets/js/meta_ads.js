// /assets/js/meta_ads.js

const API_META_ADS = '/api/v1/meta_ads.php';

// --- DOM Elements ---
const statusAlert = document.getElementById('connection-status');
const kpiSpend = document.getElementById('kpi-spend');
const kpiLeads = document.getElementById('kpi-leads');
const kpiCpl = document.getElementById('kpi-cpl');
const kpiEnrollRate = document.getElementById('kpi-enroll-rate');
const campaignTableBody = document.getElementById('ad-campaign-table');


/**
 * Formats a number as INR currency string.
 * @param {number} amount
 */
function formatCurrency(amount) {
    // Check if amount is valid before formatting
    const num = Number(amount);
    if (isNaN(num) || num === 0) return '₹0';
    return `₹${num.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

/**
 * Fetches and displays Meta Ads performance data.
 */
async function loadAdsData() {
    statusAlert.classList.remove('alert-danger', 'alert-success');
    statusAlert.classList.add('alert-warning');
    statusAlert.innerHTML = '<i class="bi bi-clock"></i> Loading recent ads data...';

    campaignTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Fetching data...</td></tr>';

    try {
        const response = await fetch(API_META_ADS);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Failed to fetch data from Meta backend. Check Settings > Integrations.');
        }

        // --- Update KPIs ---
        const spend = data.kpis.spend;
        const leads = data.kpis.leads;
        const cpl = data.kpis.cpl;
        const enrollRate = data.kpis.enrollment_rate;

        kpiSpend.textContent = formatCurrency(spend);
        kpiLeads.textContent = leads.toLocaleString();
        kpiCpl.textContent = formatCurrency(cpl);
        kpiEnrollRate.textContent = `${enrollRate.toFixed(2)}%`;

        // --- Update Campaign Table ---
        renderCampaignTable(data.campaigns);

        statusAlert.classList.remove('alert-warning');
        statusAlert.classList.add('alert-success');
        statusAlert.innerHTML = `<i class="bi bi-check-circle-fill"></i> Connected to Ad Account: <strong>${data.account_name}</strong>. Data loaded successfully.`;

    } catch (error) {
        statusAlert.classList.remove('alert-warning');
        statusAlert.classList.add('alert-danger');
        statusAlert.innerHTML = `<i class="bi bi-x-octagon-fill"></i> Connection Failed. ${error.message}`;
        console.error('Meta Ads Error:', error);

        kpiSpend.textContent = kpiLeads.textContent = kpiCpl.textContent = kpiEnrollRate.textContent = '---';
        campaignTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Check connection in Settings.</td></tr>';
    }
}

/**
 * Renders the campaign and ad set breakdown.
 * @param {Array} campaigns
 */
function renderCampaignTable(campaigns) {
    campaignTableBody.innerHTML = '';
    if (campaigns.length === 0) {
        campaignTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No active campaigns found.</td></tr>';
        return;
    }

    campaigns.forEach(campaign => {
        // Campaign Row (Parent)
        let campaignRow = `
            <tr class="table-info">
                <td><i class="bi bi-folder-fill me-2"></i> <strong>${campaign.name}</strong></td>
                <td>${campaign.status}</td>
                <td>${formatCurrency(campaign.spend)}</td>
                <td>${campaign.leads}</td>
                <td>${formatCurrency(campaign.cpl)}</td>
            </tr>
        `;
        campaignTableBody.insertAdjacentHTML('beforeend', campaignRow);

        // Ad Set Rows (Children)
        campaign.adsets.forEach(adset => {
            let adsetRow = `
                <tr>
                    <td class="ps-4"><i class="bi bi-caret-right-fill me-2"></i> ${adset.name}</td>
                    <td>${adset.status}</td>
                    <td>${formatCurrency(adset.spend)}</td>
                    <td>${adset.leads}</td>
                    <td>${formatCurrency(adset.cpl)}</td>
                </tr>
            `;
            campaignTableBody.insertAdjacentHTML('beforeend', adsetRow);
        });
    });
}


document.addEventListener('DOMContentLoaded', loadAdsData);
window.loadAdsData = loadAdsData; // Make accessible from the refresh button