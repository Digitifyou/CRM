// /assets/js/dashboard-charts.js

const API_DASHBOARD = '/api/v1/dashboard.php';

// --- DOM Elements for KPIs ---
const kpiInquiriesToday = document.getElementById('kpi-inquiries-today');
const kpiInquiriesWeek = document.getElementById('kpi-inquiries-week');
const kpiEnrollments = document.getElementById('kpi-enrollments');
const kpiConversion = document.getElementById('kpi-conversion');
const kpiFees = document.getElementById('kpi-fees');
const funnelLoading = document.getElementById('funnel-loading');

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

    } catch (error) {
        console.error('Error loading dashboard data:', error);
        alert('Failed to load dashboard data. Please check the API.');
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


// --- Initialization ---
document.addEventListener('DOMContentLoaded', loadDashboardData);