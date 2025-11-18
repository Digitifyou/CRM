<?php
// /api/v1/dashboard.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

// Helper: Get start/end of the current month
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

// Helper: Get start/end of the current week (Monday to Sunday)
$start_of_week = date('Y-m-d 00:00:00', strtotime('monday this week'));
$end_of_week = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');


if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $dashboardData = [];

    // --- KPI 1: New Inquiries (Today/This Week) ---
    $stmt_today = $pdo->prepare("SELECT COUNT(student_id) FROM students WHERE created_at BETWEEN ? AND ?");
    $stmt_today->execute([$today_start, $today_end]);
    $dashboardData['new_inquiries_today'] = (int)$stmt_today->fetchColumn();
    
    $stmt_week = $pdo->prepare("SELECT COUNT(student_id) FROM students WHERE created_at BETWEEN ? AND ?");
    $stmt_week->execute([$start_of_week, $end_of_week]);
    $dashboardData['new_inquiries_week'] = (int)$stmt_week->fetchColumn();

    // --- KPI 2: Total Enrollments (This Month) & Fees Collected ---
    // Note: We define 'enrolled' as status='enrolled'
    $stmt_enrollments = $pdo->prepare("
        SELECT 
            COUNT(enrollment_id) as total_enrollments, 
            COALESCE(SUM(total_fee_paid), 0) as total_fees_paid 
        FROM enrollments 
        WHERE status = 'enrolled' AND created_at BETWEEN ? AND ?
    ");
    $stmt_enrollments->execute([$start_of_month, $end_of_month]);
    $monthlyStats = $stmt_enrollments->fetch();
    
    $dashboardData['enrollments_this_month'] = (int)$monthlyStats['total_enrollments'];
    $dashboardData['fees_collected_this_month'] = (float)$monthlyStats['total_fees_paid'];

    // --- Admissions Funnel Data ---
    $funnel_query = $pdo->query("
        SELECT 
            ps.stage_name, 
            ps.stage_order, 
            COUNT(e.enrollment_id) AS student_count
        FROM pipeline_stages ps
        LEFT JOIN enrollments e ON ps.stage_id = e.pipeline_stage_id AND e.status = 'open'
        GROUP BY ps.stage_id, ps.stage_name, ps.stage_order
        ORDER BY ps.stage_order ASC
    ");
    $funnel_data = $funnel_query->fetchAll();
    
    $total_open_inquiries = array_sum(array_column($funnel_data, 'student_count'));

    // --- KPI 3: Inquiry-to-Enrollment % (Conversion Rate) ---
    // Simple calculation: (Monthly Enrollments) / (Total Monthly Leads in Stage 1)
    // For MVP, we'll use a simplified version: (Monthly Enrollments) / (Total Open Inquiries)
    $stmt_total_inquiries = $pdo->prepare("SELECT COUNT(student_id) FROM students WHERE created_at BETWEEN ? AND ?");
    $stmt_total_inquiries->execute([$start_of_month, $end_of_month]);
    $total_monthly_leads = (int)$stmt_total_inquiries->fetchColumn();

    $conversion_rate = 0;
    if ($total_monthly_leads > 0) {
        $conversion_rate = ($dashboardData['enrollments_this_month'] / $total_monthly_leads) * 100;
    }

    $dashboardData['conversion_rate'] = round($conversion_rate, 2);
    $dashboardData['funnel_data'] = $funnel_data;
    
    // --- Batch Status (NEW) ---
    $batch_query = $pdo->query("
        SELECT 
            b.batch_name,
            b.start_date,
            b.total_seats,
            b.filled_seats,
            c.course_name
        FROM batches b
        JOIN courses c ON b.course_id = c.course_id
        WHERE b.start_date >= CURDATE()
        ORDER BY b.start_date ASC
        LIMIT 5
    ");
    $dashboardData['upcoming_batches'] = $batch_query->fetchAll();


    echo json_encode($dashboardData);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>