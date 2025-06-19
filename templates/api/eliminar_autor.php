<?php
// templates/api/eliminar_autor.php (versión básica)
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'message' => ''];

try {
    // Solo aceptar DELETE
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new RuntimeException('Método no permitido', 405);
    }

    // Obtener ID
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if ($id <= 0) {
        throw new InvalidArgumentException('ID de autor inválido', 400);
    }

    // Verificar si tiene libros asociados
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM book_authors WHERE author_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    if ($stmt->get_result()->fetch_row()[0] > 0) {
        throw new RuntimeException('El autor tiene libros asociados y no puede ser eliminado', 409);
    }

    // Eliminar
    $stmt = $db->prepare("DELETE FROM authors WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Autor eliminado correctamente'];
    } else {
        throw new RuntimeException('Error al eliminar autor', 500);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
} catch (RuntimeException $e) {
    http_response_code($e->getCode());
    $response['message'] = $e->getMessage();
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error interno del servidor';
    error_log("Error en eliminar_autor: " . $e->getMessage());
}

echo json_encode($response);