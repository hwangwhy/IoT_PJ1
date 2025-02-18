<?php
require 'db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';
$limit = 10;
$offset = ($page - 1) * $limit;

// Debug xem dữ liệu nhận đúng không
error_log("🔍 GET PARAMS: page=$page, keyword=$keyword, startDate=$startDate, endDate=$endDate");

$sql = "SELECT history.id, devices.name, history.action, history.timestamp 
        FROM history 
        JOIN devices ON history.device_id = devices.id 
        WHERE 1=1";

$params = [];
$types = "";

// Nếu có từ khóa tìm kiếm
if (!empty($keyword)) {
    $sql .= " AND devices.name LIKE ?";
    $params[] = "%$keyword%";
    $types .= "s";
}

// Nếu có ngày bắt đầu
if (!empty($startDate)) {
    $sql .= " AND history.timestamp >= ?";
    $params[] = $startDate . " 00:00:00";
    $types .= "s";
}

// Nếu có ngày kết thúc
if (!empty($endDate)) {
    $sql .= " AND history.timestamp <= ?";
    $params[] = $endDate . " 23:59:59";
    $types .= "s";
}

$sql .= " ORDER BY history.id ASC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

// Tính tổng số trang
$sql_total = "SELECT COUNT(*) as total FROM history WHERE 1=1";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_rows = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Debug xem API có trả về dữ liệu không
error_log("🔍 Query: " . $sql);
error_log("🔍 Tổng số trang: " . $total_pages);

echo json_encode(["history" => $history, "total_pages" => $total_pages]);
?>
 