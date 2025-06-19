<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

$response = ['success' => false, 'message' => ''];

if (!isset($_GET['id'])) {
    $response['message'] = 'ID de libro no proporcionado';
    echo json_encode($response);
    exit;
}

$bookId = (int)$_GET['id'];

try {
    // Verificar si el libro existe
    $stmt = $db->prepare("SELECT cover_image_path FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Libro no encontrado');
    }
    
    $book = $result->fetch_assoc();
    
    // Iniciar transacción
    $db->begin_transaction();
    
    try {
        // Eliminar relaciones muchos-a-muchos primero
        $db->query("DELETE FROM book_authors WHERE book_id = $bookId");
        $db->query("DELETE FROM book_categories WHERE book_id = $bookId");
        $db->query("DELETE FROM book_materials WHERE book_id = $bookId");
        
        // Eliminar ejemplares del libro
        $db->query("DELETE FROM book_items WHERE book_id = $bookId");
        
        // Finalmente, eliminar el libro
        $stmt = $db->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $bookId);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al eliminar el libro: ' . $stmt->error);
        }
        
        // Eliminar la imagen de portada si existe
        if ($book['cover_image_path'] && file_exists('../../public/' . $book['cover_image_path'])) {
            unlink('../../public/' . $book['cover_image_path']);
        }
        
        $db->commit();
        $response['success'] = true;
        $response['message'] = 'Libro eliminado correctamente';
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>