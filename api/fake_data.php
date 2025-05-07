<?php
include 'db.php'; // Kết nối đến database

while (true) {
    // Tạo giá trị ngẫu nhiên
    $temperature = rand(19, 40);  // Nhiệt độ từ 19°C đến 40°C
    $humidity = rand(30, 90);     // Độ ẩm từ 30% đến 90%
    $light = rand(100, 1000);     // Cường độ ánh sáng từ 100 lx đến 1000 lx
    $wind_speed = rand(0, 90);   // Tốc độ gió từ 0 m/s đến 100 m/s

    // Lưu vào database
    $sql = "INSERT INTO sensors (temperature, humidity, light, wind_speed) VALUES ('$temperature', '$humidity', '$light', '$wind_speed')";
    $conn->query($sql);

    echo "Thêm dữ liệu: $temperature°C, $humidity%, $light lx, $wind_speed m/s\n";

    // Chờ 2 giây trước khi tiếp tục
    sleep(2);
}
?>