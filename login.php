<?php
// FICHERO CORREGIDO: /libv2/login.php
declare(strict_types=1);

// 1. Configuración inicial
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Inicializar variables para evitar warnings
$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_success']);

// 3. Configuración de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/libv2/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// 4. CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 5. Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    // Validación CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido';
    } else {
        require_once __DIR__ . '/config/database.php';
        
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Usuario y contraseña son requeridos';
        } else {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT id, username, password, role_id FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    if (password_verify($password, $user['password'])) {
                        session_regenerate_id(true);
                        
                        $_SESSION = [
                            'user_id' => (int)$user['id'],
                            'username' => htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'),
                            'role_id' => (int)$user['role_id'],
                            'ip' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                            'last_activity' => time()
                        ];
                        
                        header('Location: dashboard.php');
                        exit();
                    }
                }
                
                $error = 'Credenciales inválidas';
                usleep(random_int(500000, 2000000));
                
            } catch (Exception $e) {
                error_log('Login error: ' . $e->getMessage());
                $error = 'Error del sistema. Por favor intente más tarde.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Biblioteca</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        .login-body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border-radius: 15px;
            border: none;
        }
        .login-logo {
            height: 180px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="login-body">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <img src="/libv2/public/assets/images/logo.jpeg" alt="Logo" class="login-logo">
                            <h2 class="mt-3">Iniciar Sesión</h2>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form method="POST" class="login-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       required autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mt-3">Ingresar</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="/libv2/forgot-password.php" class="text-muted">¿Olvidó su contraseña?</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>