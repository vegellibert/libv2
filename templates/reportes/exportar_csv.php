<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

// Obtener parámetros de filtrado (los mismos que en index.php)
$search = $_GET['search'] ?? '';
$authorId = $_GET['author_id'] ?? '';
$isbn = $_GET['isbn'] ?? '';
$hasCover = $_GET['has_cover'] ?? '';
$status = $_GET['status'] ?? '';

// Construir consulta base (similar a index.php pero con más campos para el CSV)
$query = "SELECT 
            b.title as 'Título',
            GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as 'Autores',
            b.isbn as 'ISBN',
            p.name as 'Editorial',
            pp.name as 'Lugar de publicación',
            b.edition_year as 'Año de edición',
            b.edition as 'Edición',
            b.pages as 'Páginas',
            b.series as 'Serie',
            b.codification as 'Codificación',
            GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as 'Categorías',
            GROUP_CONCAT(DISTINCT am.name SEPARATOR ', ') as 'Materiales',
            COUNT(DISTINCT bi.id) as 'Total ejemplares',
            SUM(CASE WHEN bi.status = 'disponible' THEN 1 ELSE 0 END) as 'Ejemplares disponibles',
            CASE WHEN b.cover_image_path IS NOT NULL THEN 'Sí' ELSE 'No' END as 'Tiene portada'
          FROM books b
          LEFT JOIN book_authors ba ON b.id = ba.book_id
          LEFT JOIN authors a ON ba.author_id = a.id
          LEFT JOIN publishers p ON b.publisher_id = p.id
          LEFT JOIN publication_places pp ON b.publication_place_id = pp.id
          LEFT JOIN book_categories bc ON b.id = bc.book_id
          LEFT JOIN categories c ON bc.category_id = c.id
          LEFT JOIN book_materials bm ON b.id = bm.book_id
          LEFT JOIN accompanying_materials am ON bm.material_id = am.id
          LEFT JOIN book_items bi ON b.id = bi.book_id
          WHERE 1=1";

$params = [];
$types = '';

// Aplicar filtros (igual que en index.php)
if (!empty($search)) {
    $query .= " AND (b.title LIKE CONCAT('%', ?, '%') OR b.description LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (!empty($authorId)) {
    $query .= " AND a.id = ?";
    $params[] = $authorId;
    $types .= 'i';
}

if (!empty($isbn)) {
    $query .= " AND b.isbn LIKE CONCAT('%', ?, '%')";
    $params[] = $isbn;
    $types .= 's';
}

if ($hasCover === '1') {
    $query .= " AND b.cover_image_path IS NOT NULL";
} elseif ($hasCover === '0') {
    $query .= " AND b.cover_image_path IS NULL";
}

if (!empty($status)) {
    $query .= " AND bi.status = ?";
    $params[] = $status;
    $types .= 's';
}

$query .= " GROUP BY b.id ORDER BY b.title";

// Preparar y ejecutar consulta
$stmt = $db->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Configurar headers para descarga CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_libros_' . date('Y-m-d') . '.csv');

// Crear archivo de salida
$output = fopen('php://output', 'w');

// Escribir encabezados
if ($result->num_rows > 0) {
    $firstRow = $result->fetch_assoc();
    fputcsv($output, array_keys($firstRow));
    
    // Volver a ejecutar la consulta para obtener todos los datos
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Escribir datos
    while ($row = $result->fetch_assoc()) {
        // Limpiar valores para CSV
        $cleanedRow = array_map(function($value) {
            // Eliminar saltos de línea y comas innecesarias
            $value = str_replace(["\r", "\n"], ' ', $value);
            $value = preg_replace('/,+/', ',', $value);
            return $value;
        }, $row);
        
        fputcsv($output, $cleanedRow);
    }
} else {
    fputcsv($output, ['No se encontraron registros con los filtros seleccionados']);
}

fclose($output);
exit;