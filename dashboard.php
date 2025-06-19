<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/auth_check.php';
require_once 'includes/header.php';
?>

<h1 class="mb-4">Dashboard</h1>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Total Libros</h5>
                <?php
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM books");
                $stmt->execute();
                $result = $stmt->get_result();
                $total = $result->fetch_assoc()['total'];
                ?>
                <p class="card-text display-4"><?= $total ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Libros Disponibles</h5>
                <?php
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM book_items WHERE status = 'disponible'");
                $stmt->execute();
                $result = $stmt->get_result();
                $total = $result->fetch_assoc()['total'];
                ?>
                <p class="card-text display-4"><?= $total ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Libros Prestados</h5>
                <?php
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM book_items WHERE status = 'prestado'");
                $stmt->execute();
                $result = $stmt->get_result();
                $total = $result->fetch_assoc()['total'];
                ?>
                <p class="card-text display-4"><?= $total ?></p>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>