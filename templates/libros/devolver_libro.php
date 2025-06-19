<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

$response = ['success' => false, 'message' => ''];

try {
    // Validar datos de entrada
    $loanId = $_POST['loan_id'] ?? null;
    $returnDate = $_POST['return_date'] ?? date('Y-m-d');

    if (empty($loanId)) {
        throw new Exception('ID de préstamo no proporcionado');
    }

    // Convertir fecha
    $returnDate = date('Y-m-d', strtotime($returnDate));
    if ($returnDate === false) {
        throw new Exception('Fecha de devolución inválida');
    }

    // Iniciar transacción
    $db->begin_transaction();

    try {
        // 1. Obtener información del préstamo
        $stmt = $db->prepare("SELECT book_item_id FROM prestamos 
                             WHERE id = ? AND status = 'prestado' 
                             FOR UPDATE");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Préstamo no encontrado o ya devuelto');
        }
        
        $loan = $result->fetch_assoc();
        $bookItemId = $loan['book_item_id'];

        // 2. Actualizar el estado del ejemplar
        $stmt = $db->prepare("UPDATE book_items SET status = 'disponible' 
                             WHERE id = ?");
        $stmt->bind_param("i", $bookItemId);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar el estado del ejemplar');
        }

        // 3. Registrar la devolución
        $stmt = $db->prepare("UPDATE prestamos 
                             SET return_date = ?, status = 'devuelto' 
                             WHERE id = ?");
        $stmt->bind_param("si", $returnDate, $loanId);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar la devolución');
        }

        // 4. Obtener book_id para el log de auditoría
        $stmt = $db->prepare("SELECT book_id FROM book_items WHERE id = ?");
        $stmt->bind_param("i", $bookItemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bookItem = $result->fetch_assoc();
        $bookId = $bookItem['book_id'];

        // 5. Registrar en el log de auditoría
        $userId = $_SESSION['user_id'];
        $action = 'devolver';
        $entity = 'book';
        $entityId = $bookId;
        
        $stmt = $db->prepare("INSERT INTO audit_logs 
                             (user_id, action, entity, entity_id, createdAt) 
                             VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issi", $userId, $action, $entity, $entityId);
        $stmt->execute();

        $db->commit();
        
        $response['success'] = true;
        $response['message'] = 'Devolución registrada correctamente';
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