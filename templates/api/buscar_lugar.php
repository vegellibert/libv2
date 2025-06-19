<?php
require_once '../../../includes/auth_check.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

$db = getDBConnection();

$term = $_GET['term'] ?? '';

if (empty($term)) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, name FROM publication_places WHERE name LIKE CONCAT('%', ?, '%') ORDER BY name LIMIT 10");
$stmt->bind_param("s", $term);
$stmt->execute();
$result = $stmt->get_result();

$places = [];
while ($row = $result->fetch_assoc()) {
    $places[] = [
        'id' => $row['id'],
        'label' => $row['name'],
        'value' => $row['name']
    ];
}

echo json_encode($places);
?>