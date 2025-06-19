<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

// Manejar búsqueda
$search = $_GET['search'] ?? '';

// Consulta base
$query = "SELECT a.id, a.name, 
          COUNT(ba.book_id) as book_count,
          GROUP_CONCAT(DISTINCT b.title ORDER BY b.title SEPARATOR ', ') as books
          FROM authors a
          LEFT JOIN book_authors ba ON a.id = ba.author_id
          LEFT JOIN books b ON ba.book_id = b.id
          WHERE a.name LIKE CONCAT('%', ?, '%')
          GROUP BY a.id
          ORDER BY a.name";

$stmt = $db->prepare($query);
$stmt->bind_param("s", $search);
$stmt->execute();
$authors = $stmt->get_result();
?>

<?php require_once '../../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Autores</h1>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAuthorModal">
            <i class="bi bi-plus-circle"></i> Nuevo Autor
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Buscar autores..." 
                       value="<?= htmlspecialchars($search) ?>">
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
                <th>ID</th>
                <th>Nombre</th>
                <th>Libros</th>
                <th>Cantidad</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($author = $authors->fetch_assoc()): ?>
            <tr>
                <td><?= $author['id'] ?></td>
                <td><?= htmlspecialchars($author['name']) ?></td>
                <td>
                    <?php if (!empty($author['books'])): ?>
                    <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($author['books']) ?>">
                        <?= substr(htmlspecialchars($author['books']), 0, 50) ?><?= strlen($author['books']) > 50 ? '...' : '' ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">Sin libros registrados</span>
                    <?php endif; ?>
                </td>
                <td><?= $author['book_count'] ?></td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-warning" onclick="editAuthor(<?= $author['id'] ?>, '<?= htmlspecialchars($author['name']) ?>')"
                                data-bs-toggle="tooltip" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteAuthor(<?= $author['id'] ?>)"
                                data-bs-toggle="tooltip" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Modal para nuevo autor -->
<div class="modal fade" id="newAuthorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Autor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="authorForm" method="POST" action="../../templates/api/crear_autor.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="authorName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="authorName" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para editar autor -->
<div class="modal fade" id="editAuthorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Autor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAuthorForm" method="POST" action="../../templates/api/actualizar_autor.php">
                <input type="hidden" id="editAuthorId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editAuthorName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="editAuthorName" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para editar autor
function editAuthor(id, name) {
    document.getElementById('editAuthorId').value = id;
    document.getElementById('editAuthorName').value = name;
    const modal = new bootstrap.Modal(document.getElementById('editAuthorModal'));
    modal.show();
}

// Función para confirmar eliminación de autor
function confirmDeleteAuthor(id) {
    if (confirm('¿Estás seguro de eliminar este autor? Esta acción no se puede deshacer.')) {
        fetch(`../../templates/api/eliminar_autor.php?id=${id}`, {
            method: 'DELETE',
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Éxito', data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', data.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Error', 'Error al eliminar el autor', 'danger');
            console.error('Error:', error);
        });
    }
}

// Manejar el formulario de creación de autor
document.getElementById('authorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Éxito', data.message, 'success');
            const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
            modal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error', data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        showToast('Error', 'Error al guardar el autor', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
    });
});

// Manejar el formulario de edición de autor
document.getElementById('editAuthorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Éxito', data.message, 'success');
            const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
            modal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error', data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        showToast('Error', 'Error al actualizar el autor', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>