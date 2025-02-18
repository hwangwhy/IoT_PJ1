<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_id = intval($_POST['device_id']);
    $status = intval($_POST['status']);  // 0 hoặc 1

    // Cập nhật trạng thái thiết bị
    $sql = "UPDATE devices SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $status, $device_id);
    
    if ($stmt->execute()) {
        // Ghi vào lịch sử
        $action = $status ? 'Bật' : 'Tắt';
        $stmt = $conn->prepare("INSERT INTO history (device_id, action, timestamp) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $device_id, $action);
        $stmt->execute();

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Không thể cập nhật thiết bị"]);
    }
}
?>
