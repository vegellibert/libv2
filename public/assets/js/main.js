/**
 * LIBV2 - Sistema de Gestión de Librería
 * Archivo principal de JavaScript - Consolidación de toda la interactividad
 */

// Configuración base para API
const API_BASE_URL = '/libv2/templates/api/';

// Esperar a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes comunes
    initSelect2();
    initTooltips();
    initToasts();
    initModals();
    initForms();
    initDataTables();
    initSidebarToggle();

    // Event listeners para botones específicos de entidades (Autores)
    const saveAuthorBtn = document.getElementById('saveNewAuthorBtn');
    if (saveAuthorBtn) {
        saveAuthorBtn.addEventListener('click', saveNewAuthor);
    }
});

---

## Funciones de Utilidad y Componentes

---

/**
 * Inicializar Select2 para elementos de selección avanzada
 */
function initSelect2() {
    // Configuración común para todos los Select2
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
        },
        escapeMarkup: function(markup) {
            return markup;
        }
    };

    // Inicializar Select2 para autores con búsqueda AJAX
    if (document.querySelector('#authors')) {
        $('#authors').select2({
            ...select2Config,
            ajax: {
                url: '../../templates/api/buscar_autor.php',
                dataType: 'json',
                delay: 300,
                data: params => ({ term: params.term }),
                processResults: data => ({
                    results: data.map(item => ({
                        id: item.id,
                        text: item.label
                    }))
                }),
                cache: true
            },
            minimumInputLength: 2,
            tags: true,
            createTag: params => ({
                id: params.term,
                text: params.term,
                newTag: true
            })
        }).on('select2:select', function(e) {
            handleNewEntityCreation(e, 'Autor', 'crear_autor.php');
        });
    }

    // Inicializar Select2 para editoriales con búsqueda AJAX
    if (document.querySelector('#publisher')) {
        $('#publisher').select2({
            ...select2Config,
            ajax: {
                url: '../../templates/api/buscar_editorial.php',
                dataType: 'json',
                delay: 300,
                data: params => ({ term: params.term }),
                processResults: data => ({
                    results: data.map(item => ({
                        id: item.id,
                        text: item.label
                    }))
                }),
                cache: true
            },
            minimumInputLength: 2,
            tags: true,
            createTag: params => ({
                id: params.term,
                text: params.term,
                newTag: true
            })
        }).on('select2:select', function(e) {
            handleNewEntityCreation(e, 'Editorial', 'crear_editorial.php');
        });
    }

    // Inicializar Select2 para lugares de publicación con búsqueda AJAX
    if (document.querySelector('#publication_place')) {
        $('#publication_place').select2({
            ...select2Config,
            ajax: {
                url: '../../templates/api/buscar_lugar.php',
                dataType: 'json',
                delay: 300,
                data: params => ({ term: params.term }),
                processResults: data => ({
                    results: data.map(item => ({
                        id: item.id,
                        text: item.label
                    }))
                }),
                cache: true
            },
            minimumInputLength: 2,
            tags: true,
            createTag: params => ({
                id: params.term,
                text: params.term,
                newTag: true
            })
        }).on('select2:select', function(e) {
            handleNewEntityCreation(e, 'Lugar de publicación', 'crear_lugar.php');
        });
    }
}

/**
 * Manejar la creación de nuevas entidades desde Select2
 */
function handleNewEntityCreation(event, entityType, apiEndpoint) {
    const data = event.params.data;
    if (data.newTag) {
        createNewEntity(entityType, data.text, apiEndpoint)
            .then(response => {
                if (response.success) {
                    // Actualizar el Select2 con la nueva opción
                    const $select = $(event.target);
                    const newOption = new Option(data.text, response.id, true, true);
                    $select.append(newOption).trigger('change');
                }
            })
            .catch(() => {
                // El error ya fue manejado por createNewEntity
                // Es importante que el select2 no se quede con el valor del tag si hubo error
                const $select = $(event.target);
                const currentVal = $select.val();
                if (Array.isArray(currentVal)) {
                    $select.val(currentVal.filter(val => val !== data.id)).trigger('change');
                } else if (currentVal === data.id) {
                    $select.val(null).trigger('change');
                }
            });
    }
}

/**
 * Inicializar tooltips de Bootstrap
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover'
        });
    });
}

/**
 * Inicializar el sistema de notificaciones Toast
 */
function initToasts() {
    // Crear contenedor de toasts si no existe
    if (!document.getElementById('toastContainer')) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1100';
        document.body.appendChild(container);
    }
}

/**
 * Mostrar notificación Toast
 */
function showToast(title, message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = `toast-${Date.now()}`;
    const icon = {
        'success': 'bi-check-circle-fill',
        'danger': 'bi-exclamation-triangle-fill',
        'warning': 'bi-exclamation-circle-fill',
        'info': 'bi-info-circle-fill'
    }[type] || 'bi-info-circle-fill';

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icon} me-2"></i>
                    <strong>${title}</strong><br>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();

    // Eliminar el toast del DOM después de ocultarse
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

/**
 * Inicializar modales y sus comportamientos
 */
function initModals() {
    // Modal para préstamo de libros
    if (document.getElementById('loanModal')) {
        document.getElementById('loanForm').addEventListener('submit', handleLoanFormSubmit);
    }

    // Modal para devolución de libros
    if (document.getElementById('returnModal')) {
        document.getElementById('returnForm').addEventListener('submit', handleReturnFormSubmit);
    }

    // Modal para ver portada de libro
    if (document.getElementById('coverModal')) {
        // La función showCoverModal se define globalmente para acceso desde HTML
        window.showCoverModal = function(imagePath, title) {
            const modal = new bootstrap.Modal(document.getElementById('coverModal'));
            document.getElementById('coverModalTitle').textContent = title || 'Portada del libro';
            document.getElementById('coverModalImage').src = `../../public/uploads/covers/${imagePath.split('/').pop()}`;
            modal.show();
        };
    }
}

/**
 * Manejar envío del formulario de préstamo
 */
function handleLoanFormSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    // Mostrar estado de carga
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Éxito', data.message, 'success');
            // Cerrar modal y recargar después de 1.5 segundos
            bootstrap.Modal.getInstance(form.closest('.modal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error', data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        showToast('Error', 'Error de conexión con el servidor', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
    });
}

/**
 * Manejar envío del formulario de devolución
 */
function handleReturnFormSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    // Mostrar estado de carga
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';

    fetch('devolver_libro.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Éxito', data.message, 'success');
            // Cerrar modal y recargar después de 1.5 segundos
            bootstrap.Modal.getInstance(form.closest('.modal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error', data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        showToast('Error', 'Error de conexión con el servidor', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
    });
}

/**
 * Inicializar comportamientos de formularios
 */
function initForms() {
    // Confirmación antes de eliminar (se sobrescribirá más abajo por deleteEntity)
    // Se mantiene esta función global para compatibilidad si aún se usa directamente en algún HTML,
    // pero la lógica de eliminación con AJAX usará `deleteEntity`.
    window.confirmDelete = function(id, entity = 'registro') {
        if (confirm(`¿Está seguro de que desea eliminar este ${entity}? Esta acción no se puede deshacer.`)) {
            // Considera reemplazar estas llamadas directas con deleteEntity si es posible
            window.location.href = `eliminar.php?id=${id}`;
        }
        return false;
    };

    // Manejar envío de formularios con AJAX
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Añadido para consistencia
                    }
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Éxito', data.message, 'success');
                    if (form.dataset.redirect) {
                        window.location.href = form.dataset.redirect;
                    } else if (form.dataset.reload) {
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    showToast('Error', data.message, 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            } catch (error) {
                showToast('Error', 'Error de conexión con el servidor', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                console.error('Error:', error);
            }
        });
    });
}

/**
 * Inicializar DataTables para tablas
 */
function initDataTables() {
    if (typeof $.fn.DataTable === 'function' && document.querySelector('table.datatable')) {
        $('table.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            responsive: true,
            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            initComplete: function() {
            }
        });
    }
}

/**
 * Inicializar el toggle del sidebar (para menús colapsables)
 */
function initSidebarToggle() {
    const sidebarToggles = document.querySelectorAll('[data-bs-toggle="sidebar"]');
    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const target = this.dataset.bsTarget || '#sidebar';
            document.querySelector(target).classList.toggle('collapsed');
        });
    });
}

---

## Funciones para Operaciones CRUD de Entidades

---

/**
 * Función mejorada para crear entidades
 */
async function createNewEntity(entityType, name, apiEndpoint) {
    showToast('Procesando', `Creando ${entityType}...`, 'info');

    try {
        const response = await fetch(API_BASE_URL + apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ name })
        });

        // Verificar si la respuesta es JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Respuesta no válida del servidor. Se esperaba JSON.');
        }

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || `Error al crear ${entityType}`);
        }

        showToast('Éxito', `${entityType} creado correctamente`, 'success');
        return data;

    } catch (error) {
        showToast('Error', error.message || `Error al crear ${entityType}`, 'danger');
        console.error(`Error al crear ${entityType}:`, error);
        throw error; // Propagar el error para que el Select2 pueda manejarlo si es necesario
    }
}

/**
 * Función para eliminar entidades
 */
async function deleteEntity(entityType, id, apiEndpoint) {
    if (!confirm(`¿Está seguro de eliminar este ${entityType}?`)) {
        return { success: false, message: 'Acción cancelada' };
    }

    showToast('Procesando', `Eliminando ${entityType}...`, 'info');

    try {
        const response = await fetch(API_BASE_URL + apiEndpoint, {
            method: 'POST', // O 'DELETE' si tu backend lo soporta y lo configuraste
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id })
        });

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Respuesta no válida del servidor. Se esperaba JSON.');
        }

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || `Error al eliminar ${entityType}`);
        }

        showToast('Éxito', `${entityType} eliminado correctamente`, 'success');
        return data;

    } catch (error) {
        showToast('Error', error.message || `Error al eliminar ${entityType}`, 'danger');
        console.error(`Error al eliminar ${entityType}:`, error);
        throw error;
    }
}

---

## Funciones Específicas de la Aplicación

---

/**
 * Función para mostrar el modal de préstamo
 */
window.showLoanModal = function(bookId) {
    const modal = new bootstrap.Modal(document.getElementById('loanModal'));
    document.getElementById('loanBookId').value = bookId;
    document.getElementById('loanDate').value = new Date().toISOString().split('T')[0];
    modal.show();
};

/**
 * Función para mostrar el modal de devolución
 */
window.showReturnModal = function(loanId, bookTitle) {
    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    document.getElementById('returnModalLabel').textContent = `Devolver: ${bookTitle}`;
    document.getElementById('returnLoanId').value = loanId;
    document.getElementById('returnDate').value = new Date().toISOString().split('T')[0];
    modal.show();
};

/**
 * Función para guardar un nuevo autor desde el modal
 */
async function saveNewAuthor() {
    const nameInput = document.getElementById('authorName');
    const name = nameInput.value.trim();
    if (!name) {
        showToast('Error', 'El nombre del autor es requerido', 'danger');
        return;
    }

    try {
        const result = await createNewEntity('Autor', name, 'crear_autor.php');
        if (result.success) {
            // Actualizar el select de autores
            const select = $('#authors');
            const option = new Option(name, result.id, true, true);
            select.append(option).trigger('change');
            select.val(result.id).trigger('change');

            // Limpiar input y cerrar modal
            nameInput.value = '';
            bootstrap.Modal.getInstance(document.getElementById('newAuthorModal')).hide();
        }
    } catch (error) {
        // El error ya fue manejado por createNewEntity
    }
}

/**
 * Función para eliminar un autor
 */
async function deleteAuthor(authorId) {
    try {
        const result = await deleteEntity('Autor', authorId, 'eliminar_autor.php');
        if (result.success) {
            // Recargar la lista de autores o actualizar la interfaz
            setTimeout(() => location.reload(), 1500);
        }
    } catch (error) {
        // El error ya fue manejado por deleteEntity
    }
}