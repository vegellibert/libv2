<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

$response = ['success' => false, 'message' => ''];

try {
    // Validar datos de entrada
    $bookId = $_POST['book_id'] ?? null;
    $borrowerName = trim($_POST['borrower_name'] ?? '');
    $loanDate = $_POST['loan_date'] ?? '';

    if (empty($bookId) || empty($borrowerName) || empty($loanDate)) {
        throw new Exception('Todos los campos son obligatorios');
    }

    // Convertir fechas
    $loanDate = date('Y-m-d', strtotime($loanDate));
    if ($loanDate === false) {
        throw new Exception('Fecha de préstamo inválida');
    }

    // Iniciar transacción
    $db->begin_transaction();

    try {
        // 1. Buscar un ejemplar disponible del libro
        $stmt = $db->prepare("SELECT id FROM book_items 
                             WHERE book_id = ? AND status = 'disponible' 
                             LIMIT 1 FOR UPDATE");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('No hay ejemplares disponibles de este libro');
        }
        
        $bookItem = $result->fetch_assoc();
        $bookItemId = $bookItem['id'];

        // 2. Actualizar el estado del ejemplar
        $stmt = $db->prepare("UPDATE book_items SET status = 'prestado' 
                             WHERE id = ?");
        $stmt->bind_param("i", $bookItemId);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar el estado del ejemplar');
        }

        // 3. Registrar el préstamo
        $stmt = $db->prepare("INSERT INTO prestamos 
                             (book_item_id, borrower_name, loan_date, status) 
                             VALUES (?, ?, ?, 'prestado')");
        $stmt->bind_param("iss", $bookItemId, $borrowerName, $loanDate);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar el préstamo');
        }

        // 4. Registrar en el log de auditoría
        $userId = $_SESSION['user_id'];
        $action = 'prestar';
        $entity = 'book';
        $entityId = $bookId;
        
        $stmt = $db->prepare("INSERT INTO audit_logs 
                             (user_id, action, entity, entity_id, createdAt) 
                             VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issi", $userId, $action, $entity, $entityId);
        $stmt->execute();

        $db->commit();
        
        $response['success'] = true;
        $response['message'] = 'Préstamo registrado correctamente';
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