<?php
require 'db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';
$field = isset($_GET['field']) ? $_GET['field'] : '';
$validFields = ["id", "name", "action", "timestamp"]; // Danh sách cột hợp lệ
$limit = 10;
$offset = ($page - 1) * $limit;

// Câu lệnh SQL chính lấy dữ liệu
$sql = "SELECT history.id, devices.name, history.action, history.timestamp 
        FROM history 
        JOIN devices ON history.device_id = devices.id 
        WHERE 1=1";

// Câu lệnh SQL đếm tổng số bản ghi
$sql_total = "SELECT COUNT(*) as total FROM history 
              JOIN devices ON history.device_id = devices.id 
              WHERE 1=1";

$params = [];
$total_params = [];  // Biến riêng cho tổng số trang
$types = "";
$total_types = "";  // Biến riêng cho tổng số trang

// Nếu có từ khóa tìm kiếm (tìm trong ID, tên thiết bị, hành động)
if (!empty($keyword) && in_array($field, $validFields)) {
    if ($field === "id") {
        if (!is_numeric($keyword)) {  // Kiểm tra nếu ID không phải số
            die(json_encode(["error" => "ID không hợp lệ"])); 
        }
        
        $sql .= " AND history.id = ?";
        $sql_total .= " AND history.id = ?";

        array_push($params, (int)$keyword);
        array_push($total_params, (int)$keyword);

        $types .= "i";
        $total_types .= "i";
    } else {
        $sql .= " AND $field LIKE ?";
        $sql_total .= " AND $field LIKE ?";

        $likeKeyword = "%$keyword%";
        array_push($params, $likeKeyword);
        array_push($total_params, $likeKeyword);

        $types .= "s";
        $total_types .= "s";
    }
}



// Nếu có ngày bắt đầu
if (!empty($startDate)) {
    $sql .= " AND history.timestamp >= ?";
    $sql_total .= " AND history.timestamp >= ?";

    array_push($params, $startDate . " 00:00:00");
    array_push($total_params, $startDate . " 00:00:00");

    $types .= "s";
    $total_types .= "s";
}

// Nếu có ngày kết thúc
if (!empty($endDate)) {
    $sql .= " AND history.timestamp <= ?";
    $sql_total .= " AND history.timestamp <= ?";

    array_push($params, $endDate . " 23:59:59");
    array_push($total_params, $endDate . " 23:59:59");

    $types .= "s";
    $total_types .= "s";
}

// Sắp xếp và phân trang
$sql .= " ORDER BY history.id ASC LIMIT ?, ?";
array_push($params, $offset, $limit);
$types .= "ii";

// Thực thi truy vấn lấy dữ liệu
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

// Thực thi truy vấn lấy tổng số trang
$stmt_total = $conn->prepare($sql_total);
if (!empty($total_params)) {
    $stmt_total->bind_param($total_types, ...$total_params);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_rows = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Trả về JSON
echo json_encode(["history" => $history, "total_pages" => $total_pages]);

$stmt->close();
$stmt_total->close();
$conn->close();
?>
