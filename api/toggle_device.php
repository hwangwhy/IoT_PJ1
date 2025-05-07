<?php
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

    // Update device status in database immediately
    $sql = "UPDATE devices SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $status, $device_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }

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

    // If delay is requested, use sleep
    if ($delay > 0) {
        sleep($delay);
    }

    // Publish MQTT message using phpMQTT
    try {
        $mqtt = new \Bluerhinos\phpMQTT('192.168.1.159', 2003, 'php-backend');
        if ($mqtt->connect(true, NULL, 'hoanghuy', 'hoanghuy')) {
            $topic = $device['mqtt_topic'] ?: 'home/light1';
            
            // Map device name to LED name
            $ledName = 'LED1'; // Default LED name
            
            // Extract LED number from device name if it follows the pattern "Light1", "Light2", etc.
            if (preg_match('/Light(\d+)/', $device['name'], $matches)) {
                $ledName = 'LED' . $matches[1];
            } else if ($device['name'] == 'Light') {
                $ledName = 'LED1'; // Default for just "Light"
            }
            
            // Format the message exactly like the working command
            $payload = "{'" . $ledName . "':'" . ($status ? 'ON' : 'OFF') . "'}";
            error_log("Publishing MQTT message - Topic: " . $topic . ", Payload: " . $payload . ", Device: " . $device['name']);
            $mqtt->publish($topic, $payload, 0);
            $mqtt->close();
        } else {
            error_log("MQTT connection failed");
        }
    } catch (Exception $e) {
        error_log("MQTT publish failed: " . $e->getMessage());
        // Don't throw here, as the device status was already updated
    }

    echo json_encode(["success" => true]);

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