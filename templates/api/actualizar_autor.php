<?php
require_once '../../../includes/auth_check.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

$db = getDBConnection();

$response = ['success' => false, 'message' => ''];

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$name = trim($data['name'] ?? '');

if (empty($id) || empty($name)) {
    $response['message'] = 'Datos incompletos';
    echo json_encode($response);
    exit;
}

try {
    // Verificar si el nombre ya existe en otro autor
    $stmt = $db->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $response['message'] = 'Ya existe un autor con ese nombre';
        echo json_encode($response);
        exit;
    }

    // Actualizar el autor
    $stmt = $db->prepare("UPDATE authors SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Autor actualizado correctamente';
        
        // Registrar en el log de auditoría
        $userId = $_SESSION['user_id'];
        $action = 'actualizar';
        $entity = 'author';
        $entityId = $id;
        
        $stmt = $db->prepare("INSERT INTO audit_logs 
                             (user_id, action, entity, entity_id, createdAt) 
                             VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issi", $userId, $action, $entity, $entityId);
        $stmt->execute();
    } else {
        throw new Exception('Error al actualizar el autor: ' . $stmt->error);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>