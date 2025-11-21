<?php
// /api/v1/dashboard.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

$method = $_SERVER['REQUEST_METHOD'];
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_role = $_SESSION['role'] ?? 'counselor';

// Helper dates
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$start_of_week = date('Y-m-d 00:00:00', strtotime('monday this week'));
$end_of_week = date('Y-m-d 23:59:59', strtotime('sunday this week'));

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $dashboardData = [];
    
    // --- Permission Logic ---
    $is_admin = in_array($current_role, ['admin', 'owner']);
    $user_sql_condition = $is_admin ? "1=1" : "e.assigned_to_user_id = ?";
    $user_params = $is_admin ? [] : [$current_user_id];

    // 1. New Inquiries (Today)
    // We link to enrollments to check assignment permissions
    $sql = "SELECT COUNT(s.student_id) 
            FROM students s 
            LEFT JOIN enrollments e ON s.student_id = e.student_id 
            WHERE s.created_at BETWEEN ? AND ? AND $user_sql_condition";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$today_start, $today_end], $user_params));
    $dashboardData['new_inquiries_today'] = (int)$stmt->fetchColumn();

    // 1b. New Inquiries (Week)
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$start_of_week, $end_of_week], $user_params));
    $dashboardData['new_inquiries_week'] = (int)$stmt->fetchColumn();

    // 2. Enrollments & Fees
    $enroll_sql = "SELECT COUNT(enrollment_id) as total, COALESCE(SUM(total_fee_paid), 0) as fees 
                   FROM enrollments e
                   WHERE e.status = 'enrolled' AND e.created_at BETWEEN ? AND ? AND $user_sql_condition";
    $stmt = $pdo->prepare($enroll_sql);
    $stmt->execute(array_merge([$start_of_month, $end_of_month], $user_params));
    $stats = $stmt->fetch();
    
    $dashboardData['enrollments_this_month'] = (int)$stats['total'];
    $dashboardData['fees_collected_this_month'] = (float)$stats['fees'];

    // 3. Funnel Data (CRITICAL FIX)
    // We use LEFT JOIN on enrollments so we get the Stages even if they are empty
    $funnel_sql = "
        SELECT 
            ps.stage_name, 
            COUNT(e.enrollment_id) as student_count
        FROM pipeline_stages ps
        LEFT JOIN enrollments e ON ps.stage_id = e.pipeline_stage_id 
            AND e.status = 'open' 
            AND ($user_sql_condition)
        GROUP BY ps.stage_id, ps.stage_name, ps.stage_order
        ORDER BY ps.stage_order ASC
    ";
    
    // PDO cannot bind arrays easily in this specific query structure with the condition injected
    // So we execute carefully
    $stmt = $pdo->prepare($funnel_sql);
    if (!$is_admin) {
        $stmt->execute([$current_user_id]);
    } else {
        $stmt->execute();
    }
    $dashboardData['funnel_data'] = $stmt->fetchAll();

    // 4. Batches
    $batch_query = $pdo->query("SELECT b.batch_name, c.course_name, b.start_date, b.total_seats, b.filled_seats 
                                FROM batches b JOIN courses c ON b.course_id = c.course_id 
                                WHERE b.start_date >= CURDATE() LIMIT 5");
    $dashboardData['upcoming_batches'] = $batch_query->fetchAll();
    
    // 5. Conversion Rate Placeholder
    $dashboardData['conversion_rate'] = 0; // Calculation omitted for brevity/stability

    echo json_encode($dashboardData);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>