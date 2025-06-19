<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

// Manejar búsqueda/filtrado
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT b.id, b.title, b.isbn, b.cover_image_path, 
          GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as authors,
          COUNT(bi.id) as total_copies,
          SUM(CASE WHEN bi.status = 'disponible' THEN 1 ELSE 0 END) as available_copies,
          GROUP_CONCAT(
              DISTINCT CONCAT(p.id, '||', p.borrower_name, '||', p.loan_date)
              SEPARATOR ';;'
          ) as active_loans
          FROM books b
          LEFT JOIN book_authors ba ON b.id = ba.book_id
          LEFT JOIN authors a ON ba.author_id = a.id
          LEFT JOIN book_items bi ON b.id = bi.book_id
          LEFT JOIN prestamos p ON bi.id = p.book_item_id AND p.status = 'prestado'
          WHERE b.title LIKE CONCAT('%', ?, '%')";

if (!empty($status)) {
    $query .= " AND bi.status = ?";
}

$query .= " GROUP BY b.id ORDER BY b.title";

$stmt = $db->prepare($query);

if (!empty($status)) {
    $stmt->bind_param("ss", $search, $status);
} else {
    $stmt->bind_param("s", $search);
}

$stmt->execute();
$books = $stmt->get_result();
?>

<?php require_once '../../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Libros</h1>
    <div>
        <a href="formulario.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Libro
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Buscar por título..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="disponible" <?= $status === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                    <option value="prestado" <?= $status === 'prestado' ? 'selected' : '' ?>>Prestado</option>
                    <option value="mantenimiento" <?= $status === 'mantenimiento' ? 'selected' : '' ?>>En mantenimiento</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover datatable">
        <thead class="table-dark">
            <tr>
                <th>Portada</th>
                <th>Título</th>
                <th>ISBN</th>
                <th>Autores</th>
                <th>Ejemplares</th>
                <th>Disponibles</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($book = $books->fetch_assoc()): 
                $activeLoans = [];
                if (!empty($book['active_loans'])) {
                    $loans = explode(';;', $book['active_loans']);
                    foreach ($loans as $loan) {
                        if (!empty($loan)) {
                            $loanParts = explode('||', $loan);
                            $activeLoans[] = [
                                'id' => $loanParts[0],
                                'borrower_name' => $loanParts[1],
                                'loan_date' => $loanParts[2]
                            ];
                        }
                    }
                }
            ?>
            <tr>
                <td>
                    <?php if ($book['cover_image_path']): ?>
                    <img src="../../public/uploads/covers/<?= htmlspecialchars(basename($book['cover_image_path'])) ?>" 
                         class="img-thumbnail" style="width: 50px; cursor: pointer;" 
                         onclick="showCoverModal('<?= htmlspecialchars($book['cover_image_path']) ?>', '<?= htmlspecialchars($book['title']) ?>')">
                    <?php else: ?>
                    <span class="text-muted">Sin portada</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($book['title']) ?></td>
                <td><?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($book['authors'] ?? 'N/A') ?></td>
                <td><?= $book['total_copies'] ?></td>
                <td><?= $book['available_copies'] ?></td>
                <td>
                    <div class="btn-group" role="group">
                        <a href="formulario.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button onclick="return confirmDelete(<?= $book['id'] ?>, 'libro')" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                        
                        <?php if ($book['available_copies'] > 0): ?>
                        <button onclick="showLoanModal(<?= $book['id'] ?>)" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Prestar libro">
                            <i class="bi bi-book"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($activeLoans)): ?>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" title="Préstamos activos">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <?php foreach ($activeLoans as $loan): ?>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="showReturnModal(<?= $loan['id'] ?>, '<?= htmlspecialchars($book['title']) ?>')">
                                        <?= htmlspecialchars($loan['borrower_name']) ?> (<?= date('d/m/Y', strtotime($loan['loan_date'])) ?>)
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Modal para ver portada -->
<div class="modal fade" id="coverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="coverModalTitle">Portada del libro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="coverModalImage" src="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<!-- Modal para préstamo -->
<div class="modal fade" id="loanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Prestar libro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="loanForm" method="POST" action="prestar_libro.php">
                <div class="modal-body">
                    <input type="hidden" name="book_id" id="loanBookId">
                    <div class="mb-3">
                        <label for="borrowerName" class="form-label">Nombre del prestatario *</label>
                        <input type="text" class="form-control" id="borrowerName" name="borrower_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="loanDate" class="form-label">Fecha de préstamo *</label>
                        <input type="date" class="form-control" id="loanDate" name="loan_date" required 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar préstamo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para devolución -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnModalLabel">Devolver libro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="returnForm">
                <div class="modal-body">
                    <input type="hidden" name="loan_id" id="returnLoanId">
                    <div class="mb-3">
                        <label for="returnDate" class="form-label">Fecha de devolución *</label>
                        <input type="date" class="form-control" id="returnDate" name="return_date" required 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar devolución</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>