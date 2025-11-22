<?php
// /api/v1/helpers/scoring_helper.php

if (!defined('SCORE_POINTS')) {
    define('SCORE_POINTS', ['Low' => 25, 'Medium' => 50, 'High' => 100]);
}
if (!defined('MAX_SCORE_PER_FIELD')) {
    define('MAX_SCORE_PER_FIELD', 100);
}

function getScoreValue($level) { 
    return SCORE_POINTS[$level] ?? 0; 
}

function getScoringConfiguration($pdo, $academy_id) {
    $config = [];
    try {
        $stmt = $pdo->prepare("SELECT field_key, is_score_field, scoring_rules FROM custom_fields WHERE is_score_field = TRUE AND academy_id = ?");
        $stmt->execute([$academy_id]);
        while ($row = $stmt->fetch()) { $config[$row['field_key']] = $row; }

        $stmt_sys = $pdo->prepare("SELECT field_key, is_score_field, scoring_rules FROM system_field_config WHERE is_score_field = TRUE AND academy_id = ?");
        $stmt_sys->execute([$academy_id]);
        while ($row = $stmt_sys->fetch()) { $config[$row['field_key']] = $row; }
    } catch (PDOException $e) {}
    
    $default_scoring_rules = '{"High": "ANY"}'; 
    $default_keys = ['course_interested_id', 'lead_source', 'qualification', 'work_experience'];
    foreach ($default_keys as $key) {
        if (!isset($config[$key])) { 
            $config[$key] = ['field_key' => $key, 'is_score_field' => 1, 'scoring_rules' => $default_scoring_rules]; 
        }
    }
    return $config;
}

function calculateLeadScore($pdo, $student_id, $academy_id, $data = []) {
    $stmt = $pdo->prepare("SELECT s.course_interested_id, s.lead_source, s.qualification, s.work_experience, s.custom_data, c.standard_fee 
                           FROM students s LEFT JOIN courses c ON s.course_interested_id = c.course_id 
                           WHERE s.student_id = ? AND s.academy_id = ?");
    $stmt->execute([$student_id, $academy_id]);
    $student = $stmt->fetch();
    
    if (!$student) return 0;
    
    $fields_to_check = [
        'course_interested_id' => $data['course_interested_id'] ?? $student['course_interested_id'],
        'lead_source' => $data['lead_source'] ?? $student['lead_source'],
        'qualification' => $data['qualification'] ?? $student['qualification'],
        'work_experience' => $data['work_experience'] ?? $student['work_experience'],
    ];
    
    $custom_data = json_decode($student['custom_data'] ?? '{}', true);
    if (!is_array($custom_data)) $custom_data = [];

    $scoring_config = getScoringConfiguration($pdo, $academy_id);
    $total_score_obtained = 0;
    $total_max_possible = 0;
    $all_scoring_fields = array_merge($fields_to_check, $custom_data);

    foreach ($scoring_config as $config) {
        $field_key = $config['field_key'];
        $student_value = $all_scoring_fields[$field_key] ?? null;
        
        if ($config['is_score_field'] == 1) {
            $total_max_possible += MAX_SCORE_PER_FIELD; 
            if (!empty($student_value)) {
                $rules_json = $config['scoring_rules'] ?? null;
                $rules = $rules_json ? json_decode($rules_json, true) : [];
                $student_value_lower = strtolower((string)$student_value);
                $matched_level = null;
                $base_score_applied = false;

                foreach (['High', 'Medium', 'Low'] as $level) {
                    if (isset($rules[$level])) {
                        $configured_values = array_map('strtolower', array_map('trim', explode(',', $rules[$level])));
                        if (in_array($student_value_lower, $configured_values) || in_array('any', $configured_values)) {
                             $matched_level = $level;
                             break; 
                        }
                    }
                }
                if ($matched_level) {
                    $total_score_obtained += getScoreValue($matched_level);
                    $base_score_applied = true;
                } else if (isset($rules['default'])) {
                     $total_score_obtained += getScoreValue($rules['default']);
                     $base_score_applied = true;
                }
                if ($field_key === 'course_interested_id' && isset($student['standard_fee']) && $student['standard_fee'] > 30000 && $base_score_applied) {
                     $total_score_obtained += 10;
                }
            }
        }
    }

    $final_score = ($total_max_possible === 0) ? 0 : round(($total_score_obtained / $total_max_possible) * 100);
    $final_score = min(100, max(0, $final_score)); 

    $update_stmt = $pdo->prepare("UPDATE students SET lead_score = ? WHERE student_id = ?");
    $update_stmt->execute([$final_score, $student_id]);

    return $final_score;
}
?>