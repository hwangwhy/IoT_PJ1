<?php
require_once 'db.php';

$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$field = isset($_GET['field']) ? $_GET['field'] : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';

$validFields = ['id', 'temperature', 'humidity', 'light', 'wind_speed', 'timestamp'];
$sqlFilter = " WHERE 1=1";
$params = [];
$types = "";

foreach ($_GET as $key => $value) {
    if (in_array($key, $validFields) && !empty($value)) {
        $cleanedValue = cleanKeyword($key, $value);
        
        if ($key === 'id') {
            $sqlFilter .= " AND $key = ?";
            $params[] = (int)$cleanedValue;
            $types .= "i";
        } else {
            $sqlFilter .= " AND $key = ?";
            $params[] = $cleanedValue;
            $types .= "s";
        }
    }
}

function cleanKeyword($field, $keyword) {
    if ($field === 'temperature') {
        return str_replace("°C", "", $keyword);
    } elseif ($field === 'light') {
        return str_replace(" lx", "", $keyword);
    } elseif ($field === 'humidity') {
        return str_replace("%", "", $keyword);
    } elseif ($field === 'wind_speed') {
        return str_replace(" m/s", "", $keyword);
    }
    return $keyword;
}

if (!empty($keyword) && in_array($field, $validFields)) {
    $cleanedKeyword = cleanKeyword($field, $keyword);

    if ($field === 'id') {
        $sqlFilter .= " AND id = ?";
        $params[] = $cleanedKeyword;
        $types .= "i";
    } else {
        $sqlFilter .= " AND $field LIKE ?";
        $params[] = "%$cleanedKeyword%";
        $types .= "s";
    }
}

if (!empty($startDate)) {
    $sqlFilter .= " AND timestamp >= ?";
    $params[] = $startDate . " 00:00:00";
    $types .= "s";
}

if (!empty($endDate)) {
    $sqlFilter .= " AND timestamp <= ?";
    $params[] = $endDate . " 23:59:59";
    $types .= "s";
}

$totalQuery = "SELECT COUNT(*) AS total FROM sensors" . $sqlFilter;
$stmt_total = $conn->prepare($totalQuery);
if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$totalResult = $stmt_total->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $limit);

$sql = "SELECT * FROM sensors" . $sqlFilter . " ORDER BY timestamp ASC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = $result->fetch_all(MYSQLI_ASSOC);

$latestQuery = "SELECT * FROM sensors ORDER BY timestamp DESC LIMIT 1";
$latestResult = $conn->query($latestQuery);
$latest = $latestResult->fetch_assoc();

echo json_encode([
    "data" => $data,
    "latest" => $latest,
    "totalPages" => $totalPages,
    "currentPage" => $page,
    "pageSize" => $limit
]);
?>