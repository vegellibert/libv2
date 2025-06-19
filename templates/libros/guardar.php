<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

// Inicializar respuesta
$response = ['success' => false, 'message' => ''];

try {
    // Obtener datos del formulario
    $bookId = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $isbn = trim($_POST['isbn'] ?? '');
    $volume = trim($_POST['volume'] ?? '');
    $editionYear = !empty($_POST['edition_year']) ? (int)$_POST['edition_year'] : null;
    $edition = trim($_POST['edition'] ?? '');
    $pages = !empty($_POST['pages']) ? (int)$_POST['pages'] : null;
    $publisherId = !empty($_POST['publisher_id']) ? (int)$_POST['publisher_id'] : null;
    $publicationPlaceId = !empty($_POST['publication_place_id']) ? (int)$_POST['publication_place_id'] : null;
    $series = trim($_POST['series'] ?? '');
    $codification = trim($_POST['codification'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $authors = $_POST['authors'] ?? [];
    $categories = $_POST['categories'] ?? [];
    $materials = $_POST['materials'] ?? [];
    $removeCover = isset($_POST['remove_cover']);

    // Validaciones básicas
    if (empty($title)) {
        throw new Exception('El título del libro es obligatorio');
    }

    // Manejar la imagen de portada
    $coverImagePath = null;
    if ($bookId && !$removeCover) {
        // Mantener la imagen existente si no se está eliminando
        $stmt = $db->prepare("SELECT cover_image_path FROM books WHERE id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingBook = $result->fetch_assoc();
        $coverImagePath = $existingBook['cover_image_path'];
    }

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../public/uploads/covers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileExt = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('cover_') . '.' . strtolower($fileExt);
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array(strtolower($fileExt), $allowedTypes)) {
            throw new Exception('Solo se permiten imágenes JPG, PNG o GIF');
        }

        $destination = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $destination)) {
            // Eliminar la imagen anterior si existe
            if ($coverImagePath && file_exists('../../public/uploads/covers/' . basename($coverImagePath))) {
                unlink('../../public/uploads/covers/' . basename($coverImagePath));
            }
            $coverImagePath = 'uploads/covers/' . $fileName;
        } else {
            throw new Exception('Error al subir la imagen de portada');
        }
    } elseif ($removeCover && $coverImagePath) {
        // Eliminar la imagen si se marcó la opción
        if (file_exists('../../public/' . $coverImagePath)) {
            unlink('../../public/' . $coverImagePath);
        }
        $coverImagePath = null;
    }

    // Iniciar transacción
    $db->begin_transaction();

    try {
        // Insertar o actualizar el libro
        if ($bookId) {
            $stmt = $db->prepare("UPDATE books SET 
                                title = ?, isbn = ?, volume = ?, edition_year = ?, 
                                edition = ?, pages = ?, publisher_id = ?, 
                                publication_place_id = ?, series = ?, codification = ?, 
                                description = ?, cover_image_path = ? 
                                WHERE id = ?");
            $stmt->bind_param("sssisiiissssi", 
                $title, $isbn, $volume, $editionYear, $edition, $pages, 
                $publisherId, $publicationPlaceId, $series, $codification, 
                $description, $coverImagePath, $bookId);
        } else {
            $stmt = $db->prepare("INSERT INTO books 
                                (title, isbn, volume, edition_year, edition, pages, 
                                publisher_id, publication_place_id, series, codification, 
                                description, cover_image_path) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisiiissss", 
                $title, $isbn, $volume, $editionYear, $edition, $pages, 
                $publisherId, $publicationPlaceId, $series, $codification, 
                $description, $coverImagePath);
        }

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar el libro: ' . $stmt->error);
        }

        $bookId = $bookId ?: $db->insert_id;

        // Manejar relaciones muchos-a-muchos (autores)
        $db->query("DELETE FROM book_authors WHERE book_id = $bookId");
        if (!empty($authors)) {
            $stmt = $db->prepare("INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)");
            foreach ($authors as $authorId) {
                $stmt->bind_param("ii", $bookId, $authorId);
                $stmt->execute();
            }
        }

        // Manejar relaciones muchos-a-muchos (categorías)
        $db->query("DELETE FROM book_categories WHERE book_id = $bookId");
        if (!empty($categories)) {
            $stmt = $db->prepare("INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)");
            foreach ($categories as $categoryId) {
                $stmt->bind_param("ii", $bookId, $categoryId);
                $stmt->execute();
            }
        }

        // Manejar relaciones muchos-a-muchos (materiales)
        $db->query("DELETE FROM book_materials WHERE book_id = $bookId");
        if (!empty($materials)) {
            $stmt = $db->prepare("INSERT INTO book_materials (book_id, material_id) VALUES (?, ?)");
            foreach ($materials as $materialId) {
                $stmt->bind_param("ii", $bookId, $materialId);
                $stmt->execute();
            }
        }

        $db->commit();
        $response['success'] = true;
        $response['message'] = 'Libro guardado correctamente';
        $response['id'] = $bookId;
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