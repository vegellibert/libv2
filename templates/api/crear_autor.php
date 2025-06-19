<?php
// templates/api/crear_autor.php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'message' => 'Error desconocido', 'id' => null];

try {
    // Solo aceptar POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido', 405);
    }

    // Obtener y validar datos
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('Datos JSON inválidos', 400);
    }

    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        throw new InvalidArgumentException('El nombre del autor es requerido', 400);
    }

    // Procesar en base de datos
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO authors (name) VALUES (?)");
    $stmt->bind_param("s", $name);

    if ($stmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Autor creado exitosamente',
            'id' => $db->insert_id
        ];
    } else {
        throw new RuntimeException('Error al guardar en base de datos: ' . $stmt->error);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
} catch (RuntimeException $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error interno del servidor';
    error_log("Error en crear_autor: " . $e->getMessage());
}

echo json_encode($response);