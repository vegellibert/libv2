<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$db = getDBConnection();

// Validar y sanitizar el ID del libro
$book = null;
$bookId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($bookId) {
    try {
        $stmt = $db->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $result = $stmt->get_result();
        $book = $result->fetch_assoc();
        
        if (!$book) {
            throw new Exception("Libro no encontrado");
        }
        
        // Obtener autores del libro
        $stmt = $db->prepare("SELECT a.id, a.name FROM authors a 
                            JOIN book_authors ba ON a.id = ba.author_id 
                            WHERE ba.book_id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $bookAuthors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Obtener categorías del libro
        $stmt = $db->prepare("SELECT c.id, c.name FROM categories c 
                            JOIN book_categories bc ON c.id = bc.category_id 
                            WHERE bc.book_id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $bookCategories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Obtener materiales del libro
        $stmt = $db->prepare("SELECT m.id, m.name FROM accompanying_materials m 
                            JOIN book_materials bm ON m.id = bm.material_id 
                            WHERE bm.book_id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $bookMaterials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error al obtener datos del libro: " . $e->getMessage());
        $_SESSION['error_message'] = "Error al cargar los datos del libro";
        header("Location: index.php");
        exit();
    }
}

// Obtener listas para selects y checkboxes
try {
    $authors = $db->query("SELECT id, name FROM authors ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $publishers = $db->query("SELECT id, name FROM publishers ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $publicationPlaces = $db->query("SELECT id, name FROM publication_places ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $materials = $db->query("SELECT id, name FROM accompanying_materials ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $locations = $db->query("SELECT id, name FROM locations ORDER BY name")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener listas para formulario: " . $e->getMessage());
    $_SESSION['error_message'] = "Error al cargar datos del formulario";
    header("Location: index.php");
    exit();
}
?>

<?php require_once '../../includes/header.php'; ?>

<!-- Modal para mensajes -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalTitle">Mensaje</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="messageModalBody">
                <!-- Mensaje se insertará aquí -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="card-title"><?= $bookId ? 'Editar Libro' : 'Nuevo Libro' ?></h2>
        
        <form id="bookForm" method="POST" action="guardar.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $bookId ?>">
            
            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="title" class="form-label">Título *</label>
                    <input type="text" class="form-control" id="title" name="title" required 
                           value="<?= htmlspecialchars($book['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label for="isbn" class="form-label">ISBN</label>
                    <input type="text" class="form-control" id="isbn" name="isbn" 
                           value="<?= htmlspecialchars($book['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="authors" class="form-label">Autor(es) *</label>
                    <select id="authors" name="authors[]" class="form-select select2-multiple" multiple required>
                        <?php foreach ($authors as $author): ?>
                        <option value="<?= $author['id'] ?>" 
                            <?= in_array($author, $bookAuthors ?? []) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($author['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                            data-bs-toggle="modal" data-bs-target="#newAuthorModal">
                        <i class="bi bi-plus"></i> Nuevo Autor
                    </button>
                </div>
                <div class="col-md-6">
                    <label for="publisher" class="form-label">Editorial</label>
                    <select id="publisher" name="publisher_id" class="form-select select2-single">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($publishers as $publisher): ?>
                        <option value="<?= $publisher['id'] ?>" 
                            <?= ($book['publisher_id'] ?? null) == $publisher['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($publisher['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                            data-bs-toggle="modal" data-bs-target="#newPublisherModal">
                        <i class="bi bi-plus"></i> Nueva Editorial
                    </button>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="volume" class="form-label">Volumen</label>
                    <input type="text" class="form-control" id="volume" name="volume" 
                           value="<?= htmlspecialchars($book['volume'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label for="edition_year" class="form-label">Año de edición</label>
                    <input type="number" class="form-control" id="edition_year" name="edition_year" 
                           min="1900" max="<?= date('Y') ?>" 
                           value="<?= htmlspecialchars($book['edition_year'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label for="edition" class="form-label">Edición</label>
                    <input type="text" class="form-control" id="edition" name="edition" 
                           value="<?= htmlspecialchars($book['edition'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="publication_place" class="form-label">Lugar de publicación</label>
                    <select id="publication_place" name="publication_place_id" class="form-select select2-single">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($publicationPlaces as $place): ?>
                        <option value="<?= $place['id'] ?>" 
                            <?= ($book['publication_place_id'] ?? null) == $place['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($place['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                            data-bs-toggle="modal" data-bs-target="#newPlaceModal">
                        <i class="bi bi-plus"></i> Nuevo Lugar
                    </button>
                </div>
                <div class="col-md-6">
                    <label for="pages" class="form-label">Número de páginas</label>
                    <input type="number" class="form-control" id="pages" name="pages" min="1" 
                           value="<?= htmlspecialchars($book['pages'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="series" class="form-label">Serie</label>
                    <input type="text" class="form-control" id="series" name="series" 
                           value="<?= htmlspecialchars($book['series'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                    <label for="codification" class="form-label">Codificación (Obra)</label>
                    <input type="text" class="form-control" id="codification" name="codification" 
                           value="<?= htmlspecialchars($book['codification'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Categorías</label>
                <div class="row row-cols-2 row-cols-md-3 g-2">
                    <?php foreach ($categories as $category): ?>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="categories[]" 
                                   id="category_<?= $category['id'] ?>" value="<?= $category['id'] ?>"
                                   <?= in_array($category, $bookCategories ?? []) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="category_<?= $category['id'] ?>">
                                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                        data-bs-toggle="modal" data-bs-target="#newCategoryModal">
                    <i class="bi bi-plus"></i> Nueva Categoría
                </button>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Material de acompañamiento</label>
                <div class="row row-cols-2 row-cols-md-3 g-2">
                    <?php foreach ($materials as $material): ?>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="materials[]" 
                                   id="material_<?= $material['id'] ?>" value="<?= $material['id'] ?>"
                                   <?= in_array($material, $bookMaterials ?? []) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="material_<?= $material['id'] ?>">
                                <?= htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8') ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                        data-bs-toggle="modal" data-bs-target="#newMaterialModal">
                    <i class="bi bi-plus"></i> Nuevo Material
                </button>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($book['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="cover_image" class="form-label">Portada</label>
                <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
                <?php if ($bookId && !empty($book['cover_image_path'])): ?>
                <div class="mt-2">
                    <img src="<?= '../../public/uploads/covers/' . htmlspecialchars(basename($book['cover_image_path']), ENT_QUOTES, 'UTF-8') ?>" 
     class="img-thumbnail" style="max-height: 150px;">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_cover" name="remove_cover">
                        <label class="form-check-label" for="remove_cover">Eliminar portada actual</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para nuevo autor -->
<div class="modal fade" id="newAuthorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Autor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newAuthorForm">
                    <div class="mb-3">
                        <label for="authorName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="authorName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveNewAuthor()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nueva editorial -->
<div class="modal fade" id="newPublisherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Editorial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newPublisherForm">
                    <div class="mb-3">
                        <label for="publisherName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="publisherName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveNewPublisher()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nuevo lugar -->
<div class="modal fade" id="newPlaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Lugar de Publicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newPlaceForm">
                    <div class="mb-3">
                        <label for="placeName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="placeName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveNewPlace()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nueva categoría -->
<div class="modal fade" id="newCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCategoryForm">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="categoryName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveNewCategory()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nuevo material -->
<div class="modal fade" id="newMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Material de Acompañamiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newMaterialForm">
                    <div class="mb-3">
                        <label for="materialName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="materialName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveNewMaterial()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="/libv2/public/assets/js/main.js"></script>
<script>
// Función para mostrar mensajes en modal
function showMessage(title, message, isSuccess = false) {
    const modal = new bootstrap.Modal(document.getElementById('messageModal'));
    const modalTitle = document.getElementById('messageModalTitle');
    const modalBody = document.getElementById('messageModalBody');
    
    modalTitle.textContent = title;
    modalBody.innerHTML = message;
    
    // Cambiar color según el tipo de mensaje
    const header = modal._element.querySelector('.modal-header');
    header.className = isSuccess ? 'modal-header bg-success text-white' : 'modal-header bg-danger text-white';
    
    modal.show();
}

// Función genérica mejorada para crear nuevas entidades
async function createNewEntity(entityType, name, endpoint) {
    const modalId = `new${entityType.replace(/\s/g, '')}Modal`;
    const nameFieldId = `${entityType.toLowerCase().replace(/\s/g, '')}Name`;
    const selectId = endpoint.includes('autor') ? 'authors' : 
                    endpoint.includes('editorial') ? 'publisher' : 
                    endpoint.includes('lugar') ? 'publication_place' : null;

    try {
        const response = await fetch(`../../templates/api/${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });

        const data = await response.json();

        if (data.success) {
            // Cerrar el modal de creación
            bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
            document.getElementById(nameFieldId).value = '';
            
            // Actualizar el select correspondiente
            if (selectId) {
                const select = document.getElementById(selectId);
                const option = new Option(name, data.id, true, true);
                
                if (select.multiple) {
                    select.add(option);
                } else {
                    // Para selects simples, reemplazar las opciones
                    select.innerHTML = '';
                    if (selectId === 'publisher' || selectId === 'publication_place') {
                        select.appendChild(new Option('Seleccionar...', ''));
                    }
                    select.appendChild(option);
                }
                
                // Trigger change para Select2
                $(`#${selectId}`).trigger('change');
            }

            showMessage('Éxito', `${entityType} creado correctamente`, true);
            return data;
        } else {
            throw new Error(data.message || `Error al crear ${entityType}`);
        }
    } catch (error) {
        console.error(`Error al crear ${entityType}:`, error);
        showMessage('Error', error.message || `Error al comunicarse con el servidor para crear ${entityType}`);
        return { success: false, message: error.message };
    }
}

// Funciones específicas para cada modal
async function saveNewAuthor() {
    const name = document.getElementById('authorName').value.trim();
    if (!name) {
        showMessage('Error', 'El nombre del autor es requerido');
        return;
    }
    await createNewEntity('Autor', name, 'crear_autor.php');
}

async function saveNewPublisher() {
    const name = document.getElementById('publisherName').value.trim();
    if (!name) {
        showMessage('Error', 'El nombre de la editorial es requerido');
        return;
    }
    await createNewEntity('Editorial', name, 'crear_editorial.php');
}

async function saveNewPlace() {
    const name = document.getElementById('placeName').value.trim();
    if (!name) {
        showMessage('Error', 'El nombre del lugar es requerido');
        return;
    }
    await createNewEntity('Lugar de publicación', name, 'crear_lugar.php');
}

async function saveNewCategory() {
    const name = document.getElementById('categoryName').value.trim();
    if (!name) {
        showMessage('Error', 'El nombre de la categoría es requerido');
        return;
    }
    const result = await createNewEntity('Categoría', name, 'crear_categoria.php');
    
    if (result.success) {
        // Agregar nuevo checkbox
        const container = document.querySelector('.row-cols-md-3');
        const col = document.createElement('div');
        col.className = 'col';
        
        const checkDiv = document.createElement('div');
        checkDiv.className = 'form-check';
        
        const input = document.createElement('input');
        input.className = 'form-check-input';
        input.type = 'checkbox';
        input.name = 'categories[]';
        input.id = 'category_' + result.id;
        input.value = result.id;
        input.checked = true;
        
        const label = document.createElement('label');
        label.className = 'form-check-label';
        label.htmlFor = 'category_' + result.id;
        label.textContent = name;
        
        checkDiv.appendChild(input);
        checkDiv.appendChild(label);
        col.appendChild(checkDiv);
        container.appendChild(col);
    }
}

async function saveNewMaterial() {
    const name = document.getElementById('materialName').value.trim();
    if (!name) {
        showMessage('Error', 'El nombre del material es requerido');
        return;
    }
    const result = await createNewEntity('Material', name, 'crear_material.php');
    
    if (result.success) {
        // Agregar nuevo checkbox
        const container = document.querySelectorAll('.row-cols-md-3')[1];
        const col = document.createElement('div');
        col.className = 'col';
        
        const checkDiv = document.createElement('div');
        checkDiv.className = 'form-check';
        
        const input = document.createElement('input');
        input.className = 'form-check-input';
        input.type = 'checkbox';
        input.name = 'materials[]';
        input.id = 'material_' + result.id;
        input.value = result.id;
        input.checked = true;
        
        const label = document.createElement('label');
        label.className = 'form-check-label';
        label.htmlFor = 'material_' + result.id;
        label.textContent = name;
        
        checkDiv.appendChild(input);
        checkDiv.appendChild(label);
        col.appendChild(checkDiv);
        container.appendChild(col);
    }
}

// Inicializar Select2 cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Configuración común para Select2
    const select2Config = {
        placeholder: 'Seleccione o busque...',
        allowClear: true,
        width: '100%',
        language: {
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    };

    // Inicializar Select2 para autores (múltiple)
    $('#authors').select2({
        ...select2Config,
        tags: true,
        createTag: function(params) {
            return {
                id: params.term,
                text: params.term,
                newTag: true
            };
        }
    });

    // Inicializar Select2 para editorial (simple)
    $('#publisher').select2(select2Config);
    
    // Inicializar Select2 para lugar de publicación (simple)
    $('#publication_place').select2(select2Config);

    // Manejar creación de nuevo autor desde Select2
    $('#authors').on('select2:select', function(e) {
        const data = e.params.data;
        if (data.newTag) {
            createNewEntity('Autor', data.text, 'crear_autor.php')
                .then(response => {
                    if (response.success) {
                        // Actualizar el Select2 con la nueva opción
                        const $select = $(this);
                        const newOption = new Option(data.text, response.id, true, true);
                        $select.append(newOption).trigger('change');
                    }
                });
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>