// /assets/js/dashboard-charts.js

const API_DASHBOARD = '/api/v1/dashboard.php';

// --- DOM Elements for KPIs ---
const kpiInquiriesToday = document.getElementById('kpi-inquiries-today');
const kpiInquiriesWeek = document.getElementById('kpi-inquiries-week');
const kpiEnrollments = document.getElementById('kpi-enrollments');
const kpiConversion = document.getElementById('kpi-conversion');
const kpiFees = document.getElementById('kpi-fees');
const funnelLoading = document.getElementById('funnel-loading');
const batchStatusTable = document.getElementById('batch-status-table'); // NEW ELEMENT ADDED HERE

/**
 * Loads all dashboard data and updates the UI
 */
async function loadDashboardData() {
    try {
        const response = await fetch(API_DASHBOARD);
        if (!response.ok) throw new Error('Could not fetch dashboard data');

        const data = await response.json();

        updateKPIs(data);
        renderFunnelChart(data.funnel_data);
        renderBatchStatus(data.upcoming_batches); // NEW CALL

    } catch (error) {
        console.error('Error loading dashboard data:', error);
        funnelLoading.textContent = 'Failed to load chart data.';
    }
}

/**
 * Updates the Key Performance Indicator cards
 * @param {object} data - The dashboard data object
 */
function updateKPIs(data) {
    // 1. New Inquiries
    kpiInquiriesToday.textContent = data.new_inquiries_today.toLocaleString();
    kpiInquiriesWeek.textContent = `${data.new_inquiries_week.toLocaleString()} this week`;

    // 2. Total Enrollments
    kpiEnrollments.textContent = data.enrollments_this_month.toLocaleString();

    // 3. Conversion Rate
    kpiConversion.textContent = `${data.conversion_rate}%`;
    kpiConversion.parentElement.parentElement.classList.remove('bg-warning', 'text-dark');
    if (data.conversion_rate > 5) { // Arbitrary good rate
        kpiConversion.parentElement.parentElement.classList.add('bg-success', 'text-white');
    } else {
        kpiConversion.parentElement.parentElement.classList.add('bg-warning', 'text-dark');
    }

    // 4. Fees Collected
    kpiFees.textContent = `₹${data.fees_collected_this_month.toLocaleString('en-IN', { minimumFractionDigits: 0 })}`;
}

/**
 * Renders the Admissions Funnel Bar Chart
 * @param {Array} funnelData - Array of { stage_name, student_count }
 */
function renderFunnelChart(funnelData) {
    funnelLoading.style.display = 'none';

    if (funnelData.length === 0) {
        funnelLoading.textContent = 'No pipeline stages or open deals found.';
        funnelLoading.style.display = 'block';
        return;
    }

    const labels = funnelData.map(d => d.stage_name);
    const dataCounts = funnelData.map(d => d.student_count);

    // Total open deals for context
    const totalDeals = dataCounts.reduce((sum, count) => sum + count, 0);

    const ctx = document.getElementById('admissionsFunnelChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Open Enrollments',
                data: dataCounts,
                backgroundColor: [
                    '#0d6efd', // Blue - Primary
                    '#198754', // Green
                    '#ffc107', // Yellow
                    '#0dcaf0', // Cyan
                    '#fd7e14', // Orange
                    '#d63384' // Pink
                ],
                borderColor: '#fff',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y', // Horizontal bars
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: `Total Open Deals in Pipeline: ${totalDeals}`
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Students'
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}


/**
 * Renders the upcoming batch status table (NEW)
 * @param {Array} batches - Array of upcoming batch objects
 */
function renderBatchStatus(batches) {
    batchStatusTable.innerHTML = ''; // Clear table

    if (batches.length === 0) {
        batchStatusTable.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No upcoming batches planned.</td></tr>';
        return;
    }

    batches.forEach(batch => {
        const startDate = new Date(batch.start_date).toLocaleDateString();
        const seatsStatus = `${batch.filled_seats} / ${batch.total_seats}`;
        
        // Determine color for the seat status
        let seatsColor = 'bg-success';
        if (batch.filled_seats >= batch.total_seats) {
            seatsColor = 'bg-danger'; // Batch is full
        } else if (batch.filled_seats / batch.total_seats > 0.8) {
            seatsColor = 'bg-warning text-dark'; // Almost full
        }

        const row = `
            <tr>
                <td>${batch.batch_name}</td>
                <td>${batch.course_name}</td>
                <td>${startDate}</td>
                <td>
                    <span class="badge ${seatsColor}">${seatsStatus}</span>
                </td>
            </tr>
        `;
        batchStatusTable.insertAdjacentHTML('beforeend', row);
    });
}


// --- Initialization ---
document.addEventListener('DOMContentLoaded', loadDashboardData);