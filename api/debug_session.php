<?php
// Start session for state management
session_start();

header('Content-Type: application/json');

// Debug endpoint to view session state
$debug_info = [
    'session_id' => session_id(),
    'device_states' => isset($_SESSION['device_states']) ? $_SESSION['device_states'] : [],
    'last_toggle_time' => isset($_SESSION['last_toggle_time']) ? $_SESSION['last_toggle_time'] : [],
    'current_time' => time(),
    'session_data' => $_SESSION
];

// Calculate time since last toggle for each device
if (isset($_SESSION['last_toggle_time'])) {
    $current_time = time();
    foreach ($_SESSION['last_toggle_time'] as $device_id => $toggle_time) {
        $debug_info['time_since_toggle'][$device_id] = $current_time - $toggle_time;
    }
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?> 