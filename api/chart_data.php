<?php
include 'db.php'; // Kết nối MySQL

header('Content-Type: application/json');

$sql_chart = "SELECT timestamp, temperature, humidity, light FROM sensors ORDER BY id DESC LIMIT 10";
$result = mysqli_query($conn, $sql_chart);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

// Đảo ngược thứ tự để hiển thị từ cũ đến mới trên biểu đồ
$data = array_reverse($data);

echo json_encode($data);
?>