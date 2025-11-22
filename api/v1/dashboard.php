<?php
// /api/v1/dashboard.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

if (!defined('ACADEMY_ID')) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}
$academy_id = ACADEMY_ID;

$method = $_SERVER['REQUEST_METHOD'];
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_role = $_SESSION['role'] ?? 'counselor';

// Helper Dates
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$start_of_week = date('Y-m-d 00:00:00', strtotime('monday this week'));
$end_of_week = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

if ($method !== 'GET') { http_response_code(405); exit; }

try {
    $dashboardData = [];
    $is_admin = in_array($current_role, ['admin', 'owner']);
    $perm_sql = $is_admin ? "1=1" : "e.assigned_to_user_id = ?";
    $perm_params = $is_admin ? [] : [$current_user_id];

    // 1. New Inquiries (Today)
    $sql = "SELECT COUNT(s.student_id) FROM students s LEFT JOIN enrollments e ON s.student_id = e.student_id 
            WHERE s.academy_id = ? AND s.created_at BETWEEN ? AND ? AND $perm_sql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$academy_id, $today_start, $today_end], $perm_params));
    $dashboardData['new_inquiries_today'] = (int)$stmt->fetchColumn();

    // 1b. New Inquiries (Week)
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$academy_id, $start_of_week, $end_of_week], $perm_params));
    $dashboardData['new_inquiries_week'] = (int)$stmt->fetchColumn();

    // 2. Enrollments & Fees (This Month)
    $enroll_sql = "SELECT COUNT(enrollment_id) as total, COALESCE(SUM(total_fee_paid), 0) as fees 
                   FROM enrollments e
                   WHERE e.academy_id = ? AND e.status = 'enrolled' AND e.created_at BETWEEN ? AND ? AND $perm_sql";
    $stmt = $pdo->prepare($enroll_sql);
    $stmt->execute(array_merge([$academy_id, $start_of_month, $end_of_month], $perm_params));
    $stats = $stmt->fetch();
    $dashboardData['enrollments_this_month'] = (int)$stats['total'];
    $dashboardData['fees_collected_this_month'] = (float)$stats['fees'];

    // 3. Funnel Data (CRITICAL FIX: Support Defaults OR Custom Stages)
    // First, check if academy has custom stages
    $check_custom = $pdo->prepare("SELECT COUNT(*) FROM pipeline_stages WHERE academy_id = ?");
    $check_custom->execute([$academy_id]);
    $has_custom = $check_custom->fetchColumn() > 0;
    $stage_academy_target = $has_custom ? $academy_id : 0;

    $funnel_sql = "
        SELECT ps.stage_name, COUNT(e.enrollment_id) as student_count
        FROM pipeline_stages ps
        LEFT JOIN enrollments e ON ps.stage_id = e.pipeline_stage_id 
            AND e.status = 'open' 
            AND e.academy_id = ? 
            AND ($perm_sql)
        WHERE ps.academy_id = ?
        GROUP BY ps.stage_id, ps.stage_name, ps.stage_order
        ORDER BY ps.stage_order ASC
    ";
    
    $f_params = array_merge([$academy_id], $perm_params, [$stage_academy_target]);
    $stmt = $pdo->prepare($funnel_sql);
    $stmt->execute($f_params);
    $dashboardData['funnel_data'] = $stmt->fetchAll();

    // 4. Batches
    $batch_query = $pdo->prepare("SELECT b.batch_name, c.course_name, b.start_date, b.total_seats, b.filled_seats 
                                FROM batches b JOIN courses c ON b.course_id = c.course_id 
                                WHERE b.academy_id = ? AND b.start_date >= CURDATE() LIMIT 5");
    $batch_query->execute([$academy_id]);
    $dashboardData['upcoming_batches'] = $batch_query->fetchAll();
    
    $dashboardData['conversion_rate'] = 0; 

    echo json_encode($dashboardData);

} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>