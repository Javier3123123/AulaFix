<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

session_start();

// checkea CSRF token, si es invalido de alguna manera (o hashes no coinciden) se bloquea el cierre de sesión.
if (
    !isset($_SESSION['usuario_id'], $_SESSION['csrf_logout'], $_POST['csrf'])
    || !is_string($_SESSION['csrf_logout'])
    || !is_string($_POST['csrf'])
    || !hash_equals($_SESSION['csrf_logout'], $_POST['csrf'])
) {
    http_response_code(403);
    exit('No se pudo cerrar la sesión: token CSRF inválido.');
}

$_SESSION = [];
if (!session_destroy()) {
    error_log('No se pudo destruir la sesión al cerrar sesión.');
    http_response_code(500);
    exit('No se pudo cerrar la sesión. Inténtalo de nuevo.');
}

if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    if (!setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $parametros['path'],
        'domain' => $parametros['domain'],
        'secure' => $parametros['secure'],
        'httponly' => $parametros['httponly'],
        'samesite' => $parametros['samesite'],
    ])) {
        error_log('No se pudo eliminar la cookie de sesión.');
        http_response_code(500);
        exit('Se cerró la sesión, pero no se pudo eliminar la cookie de sesión.');
    }
}

session_id('');
if (!session_start()) {
    error_log('No se pudo iniciar una sesión para confirmar el cierre.');
    http_response_code(500);
    exit('Se cerró la sesión, pero no se pudo mostrar la confirmación.');
}
$_SESSION['aviso'] = 'Has cerrado sesión.';

header('Location: login.php', true, 303);
exit;
