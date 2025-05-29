<?php
include 'db.php'; // Kết nối MySQL

header('Content-Type: application/json');

$sql_chart = "SELECT timestamp, temperature, humidity, light, wind_speed FROM sensors ORDER BY id ASC LIMIT 10";
$result = mysqli_query($conn, $sql_chart);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}



echo json_encode($data);
?>