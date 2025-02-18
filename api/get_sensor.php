<?php
require_once 'db.php';

$limit = 10; // Số bản ghi trên mỗi trang
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Truy vấn dữ liệu cảm biến với phân trang (sắp xếp mới nhất trước)
$sql = "SELECT * FROM (SELECT * FROM sensors ORDER BY id ASC) AS ordered_data LIMIT $limit OFFSET $offset";
$data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Lấy dữ liệu mới nhất (dùng LIMIT 1 để tối ưu)
$latestQuery = "SELECT * FROM sensors ORDER BY timestamp DESC, id DESC LIMIT 1";
$latestResult = $conn->query($latestQuery);
$latest = $latestResult->fetch_assoc();

// Tính tổng số trang
$totalQuery = "SELECT COUNT(*) AS total FROM sensors";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $limit);

// Trả về JSON chuẩn
echo json_encode([
    "latest" => $latest ?: null, // Trả về null nếu không có dữ liệu
    "data" => $data,
    "totalPages" => $totalPages,
    "currentPage" => $page
]);
?>
