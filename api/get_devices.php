<?php
// Start session for state management
session_start();

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nhận dữ liệu từ body request
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['name']) && isset($data['status'])) {
        $device_name = $conn->real_escape_string($data['name']);  
        $status = intval($data['status']);
        // Lấy mqtt_topic từ dữ liệu, mặc định là chuỗi rỗng nếu không có
        $mqtt_topic = isset($data['mqtt_topic']) ? $conn->real_escape_string($data['mqtt_topic']) : '';
        // Lấy pin từ dữ liệu, mặc định là 0 nếu không có
        $pin = isset($data['pin']) ? intval($data['pin']) : 0;

        $sql = "INSERT INTO devices (name, status, mqtt_topic, pin) VALUES ('$device_name', '$status', '$mqtt_topic', '$pin')";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(["message" => "Thiết bị đã được thêm thành công"]);
        } else {
            echo json_encode(["error" => "Lỗi: " . $conn->error]);
        }
    } else {
        echo json_encode(["error" => "Thiếu thông tin thiết bị"]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Xóa thiết bị dựa trên id
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $id = intval($data['id']);

        $sql = "DELETE FROM devices WHERE id = $id";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(["message" => "Thiết bị đã được xóa thành công"]);
        } else {
            echo json_encode(["error" => "Lỗi: " . $conn->error]);
        }
    } else {
        echo json_encode(["error" => "Thiếu ID thiết bị để xóa"]);
    }
} else {
    // Mặc định GET: Lấy danh sách thiết bị với session-based state management
    $sql = "SELECT * FROM devices";
    $result = $conn->query($sql);

    $devices = [];
    $current_time = time();
    $COOLDOWN_SECONDS = 5; // 5 seconds cooldown

    while ($row = $result->fetch_assoc()) {
        $device_id = $row['id'];
        
        // Check if device was recently toggled in session
        $use_session_state = false;
        if (isset($_SESSION['last_toggle_time'][$device_id]) && 
            isset($_SESSION['device_states'][$device_id])) {
            
            $time_since_toggle = $current_time - $_SESSION['last_toggle_time'][$device_id];
            
            // If toggled within cooldown period, use session state
            if ($time_since_toggle < $COOLDOWN_SECONDS) {
                $use_session_state = true;
                $row['status'] = $_SESSION['device_states'][$device_id];
                $row['source'] = 'session'; // For debugging
                $row['time_since_toggle'] = $time_since_toggle;
            } else {
                // Cooldown expired, clean up session
                unset($_SESSION['last_toggle_time'][$device_id]);
                unset($_SESSION['device_states'][$device_id]);
                $row['source'] = 'database'; // For debugging
            }
        } else {
            $row['source'] = 'database'; // For debugging
        }
        
        $devices[] = $row;
    }

    echo json_encode($devices);
}

$conn->close();
?>