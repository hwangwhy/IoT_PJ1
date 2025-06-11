<?php
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session for state management
session_start();

// Set headers
header('Content-Type: application/json');

try {
    require 'db.php';

    // Check if phpMQTT.php exists before requiring it
    $mqttPath = __DIR__ . '/../phpMqtt/phpMQTT.php';
    if (!file_exists($mqttPath)) {
        throw new Exception("MQTT library not found at: " . $mqttPath);
    }

    require_once $mqttPath;

    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        throw new Exception("Invalid request method");
    }

    if (!isset($_POST['device_id']) || !isset($_POST['status'])) {
        throw new Exception("Missing required parameters");
    }

    $device_id = intval($_POST['device_id']);
    $status = intval($_POST['status']); // 0 or 1
    $delay = isset($_POST['delay']) ? intval($_POST['delay']) : 0; // Delay in seconds, default 0

    // Fetch device details to get mqtt_topic and name
    $sql = "SELECT name, mqtt_topic FROM devices WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $device_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $device = $result->fetch_assoc();

    if (!$device) {
        throw new Exception("Thiết bị không tồn tại");
    }

    // Map device name to LED name
    $ledName = 'LED1'; // Default LED name
    
    // Extract LED number from device name if it follows the pattern "Light1", "Light2", etc.
    if (preg_match('/Light(\d+)/', $device['name'], $matches)) {
        $ledName = 'LED' . $matches[1];
    } else if ($device['name'] == 'Light') {
        $ledName = 'LED1'; // Default for just "Light"
    }

    // Publish MQTT message and wait for response
    $mqtt_success = false;
    $response_received = false;
    $response_topic = 'home/response'; // Topic where ESP sends response
    
    try {
        // Use correct namespace as confirmed working in test
        $mqtt = new \Bluerhinos\phpMQTT('192.168.192.101', 2003, 'php-backend-' . uniqid());
        
        if ($mqtt->connect(true, NULL, 'hoanghuy', 'hoanghuy')) {
            $topic = $device['mqtt_topic'] ?: 'home/light1';
            
            // Subscribe to response topic first
            $mqtt->subscribe([$response_topic => ["qos" => 0, "function" => function($topic, $msg) use (&$response_received, &$mqtt_success, $ledName, $status) {
                error_log("Received MQTT response: Topic=$topic, Message=$msg");
                
                // Parse response message
                $response_data = json_decode($msg, true);
                if ($response_data) {
                    // Check if response matches our request
                    $expected_status = $status ? 'ON' : 'OFF';
                    if (isset($response_data[$ledName]) && $response_data[$ledName] === $expected_status) {
                        $mqtt_success = true;
                        error_log("ESP confirmed: $ledName is $expected_status");
                    } else {
                        error_log("ESP response mismatch: Expected $ledName=$expected_status, got " . json_encode($response_data));
                    }
                } else {
                    error_log("Invalid JSON response from ESP: $msg");
                }
                $response_received = true;
            }]]);
            
            // Format the message exactly like the working command
            $payload = "{'" . $ledName . "':'" . ($status ? 'ON' : 'OFF') . "'}";
            error_log("Publishing MQTT message - Topic: " . $topic . ", Payload: " . $payload . ", Device: " . $device['name']);
            
            // Send the command
            $mqtt->publish($topic, $payload, 0);
            
            // Wait for response with timeout
            $timeout = 10; // 10 seconds timeout
            $start_time = time();
            
            while (!$response_received && (time() - $start_time) < $timeout) {
                $mqtt->proc(false); // Process incoming messages
                usleep(100000); // Sleep 100ms
            }
            
            $mqtt->close();
            
            if (!$response_received) {
                throw new Exception("Timeout waiting for ESP response");
            }
            
            if (!$mqtt_success) {
                throw new Exception("ESP failed to execute command or returned error");
            }
            
        } else {
            throw new Exception("MQTT connection failed");
        }
    } catch (Exception $e) {
        error_log("MQTT operation failed: " . $e->getMessage());
        throw new Exception("Không thể kết nối với thiết bị: " . $e->getMessage());
    }

    // Only update database if ESP confirmed success
    $sql = "UPDATE devices SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $status, $device_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }

    // Initialize session arrays if not exists
    if (!isset($_SESSION['device_states'])) {
        $_SESSION['device_states'] = [];
    }
    if (!isset($_SESSION['last_toggle_time'])) {
        $_SESSION['last_toggle_time'] = [];
    }

    // Record the toggle time and state in session
    $_SESSION['last_toggle_time'][$device_id] = time();
    $_SESSION['device_states'][$device_id] = $status;

    // Log to history
    $action = $status ? 'Bật' : 'Tắt';
    $stmt = $conn->prepare("INSERT INTO history (device_id, action, timestamp) VALUES (?, ?, NOW())");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("is", $device_id, $action);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }

    echo json_encode([
        "success" => true,
        "device_id" => $device_id,
        "status" => $status,
        "timestamp" => time(),
        "message" => "ESP confirmed: " . $ledName . " is " . ($status ? 'ON' : 'OFF')
    ]);

} catch (Exception $e) {
    error_log("Error in toggle_device.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Fatal error in toggle_device.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => "Internal server error: " . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>