<?php
require_once '../../../includes/auth_check.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

$db = getDBConnection();

$response = ['success' => false, 'message' => '', 'id' => null];

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');

if (empty($name)) {
    $response['message'] = 'El nombre del lugar es obligatorio';
    echo json_encode($response);
    exit;
}

try {
    // Verificar si el lugar ya existe
    $stmt = $db->prepare("SELECT id FROM publication_places WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $response['message'] = 'El lugar ya existe';
        echo json_encode($response);
        exit;
    }

    // Crear nuevo lugar
    $stmt = $db->prepare("INSERT INTO publication_places (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Lugar creado correctamente';
        $response['id'] = $db->insert_id;
        
        // Registrar en el log de auditoría
        $userId = $_SESSION['user_id'];
        $action = 'crear';
        $entity = 'publication_place';
        $entityId = $response['id'];
        
        $stmt = $db->prepare("INSERT INTO audit_logs 
                             (user_id, action, entity, entity_id, createdAt) 
                             VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issi", $userId, $action, $entity, $entityId);
        $stmt->execute();
    } else {
        throw new Exception('Error al crear el lugar: ' . $stmt->error);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>