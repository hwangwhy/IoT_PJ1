<?php
require_once 'db.php';

$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';

// Điều kiện lọc dữ liệu
$sqlFilter = " WHERE 1=1";
$params = [];
$types = "";

// Nếu có từ khóa
if (!empty($keyword)) {
    $sqlFilter .= " AND (id LIKE ? OR temperature LIKE ? OR humidity LIKE ? OR light LIKE ? OR timestamp LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $types .= "sssss";
}

// Nếu có ngày bắt đầu
if (!empty($startDate)) {
    $sqlFilter .= " AND timestamp >= ?";
    $params[] = $startDate . " 00:00:00";
    $types .= "s";
}

// Nếu có ngày kết thúc
if (!empty($endDate)) {
    $sqlFilter .= " AND timestamp <= ?";
    $params[] = $endDate . " 23:59:59";
    $types .= "s";
}

// Lấy tổng số bản ghi (DÙNG CÙNG ĐIỀU KIỆN LỌC)
$totalQuery = "SELECT COUNT(*) AS total FROM sensors" . $sqlFilter;
$stmt_total = $conn->prepare($totalQuery);
if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$totalResult = $stmt_total->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $limit);

// Truy vấn dữ liệu có phân trang
$sql = "SELECT * FROM sensors" . $sqlFilter . " ORDER BY timestamp ASC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = $result->fetch_all(MYSQLI_ASSOC);

// Lấy dữ liệu mới nhất
$latestQuery = "SELECT * FROM sensors ORDER BY timestamp DESC LIMIT 1";
$latestResult = $conn->query($latestQuery);
$latest = $latestResult->fetch_assoc();

// Trả về JSON
echo json_encode([
    "data" => $data,
    "latest" => $latest,  // Thêm dữ liệu mới nhất
    "totalPages" => $totalPages,
    "currentPage" => $page
]);
?>
