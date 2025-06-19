<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

// Obtener parámetros de filtrado
$search = $_GET['search'] ?? '';
$authorId = $_GET['author_id'] ?? '';
$isbn = $_GET['isbn'] ?? '';
$hasCover = $_GET['has_cover'] ?? '';
$status = $_GET['status'] ?? '';

// Construir consulta base
$query = "SELECT 
            b.id, 
            b.title, 
            b.isbn, 
            b.cover_image_path,
            GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as authors,
            p.name as publisher,
            pp.name as publication_place,
            b.edition_year,
            COUNT(DISTINCT bi.id) as total_copies,
            SUM(CASE WHEN bi.status = 'disponible' THEN 1 ELSE 0 END) as available_copies,
            GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories
          FROM books b
          LEFT JOIN book_authors ba ON b.id = ba.book_id
          LEFT JOIN authors a ON ba.author_id = a.id
          LEFT JOIN publishers p ON b.publisher_id = p.id
          LEFT JOIN publication_places pp ON b.publication_place_id = pp.id
          LEFT JOIN book_categories bc ON b.id = bc.book_id
          LEFT JOIN categories c ON bc.category_id = c.id
          LEFT JOIN book_items bi ON b.id = bi.book_id
          WHERE 1=1";

$params = [];
$types = '';

// Aplicar filtros
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
$books = $stmt->get_result();

// Obtener autores para el filtro
$authors = $db->query("SELECT id, name FROM authors ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<?php require_once '../../includes/header.php'; ?>

<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0">Reportes de Libros</h2>
            <div>
                <a href="exportar_csv.php?<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-sm btn-light">
                    <i class="bi bi-download"></i> Exportar CSV
                </a>
                <button onclick="window.print()" class="btn btn-sm btn-light ms-2">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <label for="search" class="form-label">Buscar (título/descripción)</label>
                <input type="text" id="search" name="search" class="form-control" 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label for="author_id" class="form-label">Filtrar por autor</label>
                <select id="author_id" name="author_id" class="form-select">
                    <option value="">Todos los autores</option>
                    <?php foreach ($authors as $author): ?>
                    <option value="<?= $author['id'] ?>" 
                        <?= $authorId == $author['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($author['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="isbn" class="form-label">ISBN</label>
                <input type="text" id="isbn" name="isbn" class="form-control" 
                       value="<?= htmlspecialchars($isbn) ?>">
            </div>
            <div class="col-md-2">
                <label for="has_cover" class="form-label">Portada</label>
                <select id="has_cover" name="has_cover" class="form-select">
                    <option value="">Todas</option>
                    <option value="1" <?= $hasCover === '1' ? 'selected' : '' ?>>Con portada</option>
                    <option value="0" <?= $hasCover === '0' ? 'selected' : '' ?>>Sin portada</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Estado</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="disponible" <?= $status === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                    <option value="prestado" <?= $status === 'prestado' ? 'selected' : '' ?>>Prestado</option>
                    <option value="mantenimiento" <?= $status === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a href="index.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Título</th>
                        <th>Autores</th>
                        <th>ISBN</th>
                        <th>Editorial</th>
                        <th>Año</th>
                        <th>Categorías</th>
                        <th>Ejemplares</th>
                        <th>Disponibles</th>
                        <th>Portada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($book = $books->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($book['title']) ?></td>
                        <td><?= htmlspecialchars($book['authors'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($book['publisher'] ?? 'N/A') ?></td>
                        <td><?= $book['edition_year'] ? htmlspecialchars($book['edition_year']) : 'N/A' ?></td>
                        <td><?= htmlspecialchars($book['categories'] ?? 'N/A') ?></td>
                        <td class="text-center"><?= $book['total_copies'] ?></td>
                        <td class="text-center"><?= $book['available_copies'] ?></td>
                        <td class="text-center">
                            <?php if ($book['cover_image_path']): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                            <i class="bi bi-x-circle-fill text-danger"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($books->num_rows === 0): ?>
        <div class="alert alert-warning mt-3">No se encontraron libros con los criterios de búsqueda seleccionados.</div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .card, .card * {
        visibility: visible;
    }
    .card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
    }
    .btn {
        display: none !important;
    }
    table {
        width: 100% !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>