<?php
require 'db.php';

header('Content-Type: application/json');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$validFields = ["id", "name", "action", "timestamp"]; // Danh sách field hợp lệ

$request_uri = $_SERVER['REQUEST_URI'];
$isLatest = str_ends_with($request_uri, "/latest");

if ($isLatest) {
    $sql = "SELECT h.id, d.name, h.action, h.timestamp 
            FROM history h
            JOIN devices d ON h.device_id = d.id
            JOIN (
                SELECT device_id, MAX(timestamp) AS max_timestamp
                FROM history
                GROUP BY device_id
            ) latest ON h.device_id = latest.device_id AND h.timestamp = latest.max_timestamp
            WHERE 1=1";

    $sql_total = "SELECT COUNT(DISTINCT device_id) as total FROM history";
} else {
    $sql = "SELECT h.id, d.name, h.action, h.timestamp 
            FROM history h
            JOIN devices d ON h.device_id = d.id 
            WHERE 1=1";

    $sql_total = "SELECT COUNT(*) as total FROM history 
                  JOIN devices ON history.device_id = devices.id 
                  WHERE 1=1";
}

$params = [];
$total_params = [];
$types = "";
$total_types = "";

// Handle keyword search with field
if (!empty($_GET['keyword']) && !empty($_GET['field']) && in_array($_GET['field'], $validFields)) {
    $field = $_GET['field'];
    $keyword = $_GET['keyword'];
    
    // Handle different field types appropriately
    if ($field === "id") {
        if (is_numeric($keyword)) {
            $sql .= " AND h.id = ?";
            $sql_total .= " AND history.id = ?";
            $params[] = (int)$keyword;
            $total_params[] = (int)$keyword;
            $types .= "i";
            $total_types .= "i";
        }
    } else if ($field === "name") {
        $sql .= " AND d.name LIKE ?";
        $sql_total .= " AND devices.name LIKE ?";
        $params[] = "%$keyword%";
        $total_params[] = "%$keyword%";
        $types .= "s";
        $total_types .= "s";
    } else if ($field === "action") {
        $sql .= " AND h.action LIKE ?";
        $sql_total .= " AND history.action LIKE ?";
        $params[] = "%$keyword%";
        $total_params[] = "%$keyword%";
        $types .= "s";
        $total_types .= "s";
    } else if ($field === "timestamp") {
        $sql .= " AND h.timestamp LIKE ?";
        $sql_total .= " AND history.timestamp LIKE ?";
        $params[] = "%$keyword%";
        $total_params[] = "%$keyword%";
        $types .= "s";
        $total_types .= "s";
    }
}

// **Lọc theo từng field truyền vào**
foreach ($_GET as $key => $value) {
    if (in_array($key, $validFields) && !empty($value) && $key !== 'field' && $key !== 'keyword') {
        if ($key === "id") {
            if (!is_numeric($value)) {
                die(json_encode(["error" => "ID không hợp lệ"]));
            }
            $sql .= " AND h.id = ?";
            $sql_total .= " AND history.id = ?";
            $params[] = (int)$value;
            $total_params[] = (int)$value;
            $types .= "i";
            $total_types .= "i";
        } else {
            $sql .= " AND h.$key = ?";
            $sql_total .= " AND history.$key = ?";
            $params[] = $value;
            $total_params[] = $value;
            $types .= "s";
            $total_types .= "s";
        }
    }
}

// **Thêm điều kiện ngày tháng nếu có**
if (!empty($_GET['startDate'])) {
    $sql .= " AND h.timestamp >= ?";
    $sql_total .= " AND history.timestamp >= ?";
    $params[] = $_GET['startDate'] . " 00:00:00";
    $total_params[] = $_GET['startDate'] . " 00:00:00";
    $types .= "s";
    $total_types .= "s";
}

if (!empty($_GET['endDate'])) {
    $sql .= " AND h.timestamp <= ?";
    $sql_total .= " AND history.timestamp <= ?";
    $params[] = $_GET['endDate'] . " 23:59:59";
    $total_params[] = $_GET['endDate'] . " 23:59:59";
    $types .= "s";
    $total_types .= "s";
}

// **Thêm phân trang nếu không phải `/latest`**
if (!$isLatest) {
    $sql .= " ORDER BY h.id ASC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
}

// **Thực thi truy vấn**
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

// **Lấy tổng số trang**
$stmt_total = $conn->prepare($sql_total);
if (!empty($total_params)) {
    $stmt_total->bind_param($total_types, ...$total_params);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_rows = $result_total->fetch_assoc()['total'];
$total_pages = $isLatest ? 1 : ceil($total_rows / $limit);

// **Trả về JSON**
echo json_encode([
    "history" => $history, 
    "total_pages" => $total_pages,  
    "currentPage" => $page, 
    "pageSize" => $limit,
    "debug" => [
        "sql" => $sql,
        "keyword" => $_GET['keyword'] ?? '',
        "field" => $_GET['field'] ?? ''
    ]
]);

$stmt->close();
$stmt_total->close();
$conn->close();
?>
