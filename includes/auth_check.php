<?php
// FICHERO PORTABLE: /libv2/includes/auth_check.php
declare(strict_types=1);

// 1. Configuración portable de rutas
$rootPath = dirname(__DIR__, 2); // Sube dos niveles desde /includes/ para llegar a /libv2/
define('APP_ROOT', $rootPath);

// 2. Configuración segura de sesión portable
if (session_status() === PHP_SESSION_NONE) {
    $sessionParams = array(
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    );
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($sessionParams);
    } else {
        // Compatibilidad con versiones antiguas
        session_set_cookie_params(
            $sessionParams['lifetime'],
            $sessionParams['path'],
            $sessionParams['domain'],
            $sessionParams['secure'],
            $sessionParams['httponly']
        );
    }
    session_start();
}

// 3. Función portable para redirecciones
function redirectToLogin($reason = null) {
    $loginUrl = '/libv2/login.php'; // Ruta relativa desde el root del dominio
    
    // Construye la URL de redirección portable
    $redirectUrl = $loginUrl . 
                  (isset($_SERVER['REQUEST_URI']) ? 
                  '?redirect=' . urlencode($_SERVER['REQUEST_URI']) : '');
                  
    if ($reason) {
        $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 
                       'reason=' . urlencode($reason);
    }
    
    header('Location: ' . $redirectUrl);
    exit();
}

// 4. Verificación de sesión (portable)
if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    redirectToLogin('invalid_session');
}

// 5. Fingerprint de sesión portable
$fingerprintSecret = defined('SESSION_SECRET') ? SESSION_SECRET : 'default_secret_change_in_production';
$currentFingerprint = hash_hmac('sha256', 
    $_SERVER['HTTP_USER_AGENT'] . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
    $fingerprintSecret
);

if (!isset($_SESSION['fingerprint'])) {
    $_SESSION['fingerprint'] = $currentFingerprint;
} elseif ($_SESSION['fingerprint'] !== $currentFingerprint) {
    session_regenerate_id(true);
    session_unset();
    session_destroy();
    redirectToLogin('session_tampered');
}

// 6. Tiempo de inactividad (30 minutos)
define('MAX_INACTIVITY', 1800);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > MAX_INACTIVITY)) {
    session_unset();
    session_destroy();
    redirectToLogin('session_expired');
}
$_SESSION['last_activity'] = time();

// 7. Headers de seguridad (portables)
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// 8. Verificación de roles (configurable)
$allowedRoles = defined('ALLOWED_ROLES') ? unserialize(ALLOWED_ROLES) : array(1, 2);
if (!in_array($_SESSION['role_id'] ?? 0, $allowedRoles, true)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso no autorizado: Privilegios insuficientes');
}