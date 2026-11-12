<?php

session_start();

$error = '';
$correo = isset($_COOKIE['correo_recordado']) && is_string($_COOKIE['correo_recordado'])
    ? $_COOKIE['correo_recordado']
    : '';
$recordar = $correo !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = isset($_POST['correo']) && is_string($_POST['correo'])
        ? trim($_POST['correo'])
        : '';
    $contrasena = isset($_POST['contrasena']) && is_string($_POST['contrasena'])
        ? $_POST['contrasena']
        : '';
    $recordar = isset($_POST['recordar']);

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $contrasena === '') {
        $error = 'Introduce un correo y una contraseña válidos.';
    } else {
        try {
            require __DIR__ . '/../includes/db.php';

            $consulta = $db->prepare( // anti sql injection (prepared statement)
                'SELECT id, nombre, email, clave_hash, rol FROM usuarios WHERE email = :email'
            );
            $consulta->execute(['email' => $correo]);
            $usuario = $consulta->fetch(PDO::FETCH_ASSOC); // devuelve conjunto de resultados como array asociativo.

            if (!$usuario || !password_verify($contrasena, $usuario['clave_hash'])) { // si password no coincide con hash de DB:
                $error = 'Correo o contraseña incorrectos.';
            } elseif (!session_regenerate_id(true)) {
                error_log('No se pudo regenerar el identificador de sesión.');
                $error = 'No se pudo iniciar sesión. Inténtalo de nuevo.';
            } else {
                $opcionesCookie = [
                    'expires' => $recordar ? time() + 30 * 24 * 60 * 60 : time() - 3600, // 30 dias de duración cookie
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ];

                if (!setcookie('correo_recordado', $recordar ? $usuario['email'] : '', $opcionesCookie)) {
                    error_log('No se pudo actualizar la cookie del correo recordado.');
                    $error = 'No se pudo iniciar sesión. Inténtalo de nuevo.';
                } else {
                    unset($_SESSION['csrf_logout']);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre'] = $usuario['nombre'];
                    $_SESSION['rol'] = $usuario['rol'];
                    $_SESSION['aviso'] = 'Has iniciado sesión.';
                    header('Location: login.php');
                    exit;
                }
            }
        } catch (PDOException $excepcion) {
            error_log('Error de base de datos al iniciar sesión: ' . $excepcion->getMessage());
            $error = 'No se pudo conectar con la base de datos. Inténtalo más tarde.';
        }
    }
}

if (isset($_SESSION['usuario_id'], $_SESSION['nombre']) && !isset($_SESSION['csrf_logout'])) {
    $_SESSION['csrf_logout'] = bin2hex(random_bytes(32));
}
$aviso = $_SESSION['aviso'] ?? '';
unset($_SESSION['aviso']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="css/avisos.css">
    <script src="js/avisos.js" defer></script>
</head>
<body>
    <header class="cabecera">
        <span class="marca">AulaFix</span>
        <span class="cabecera-descripcion">Gestión de incidencias</span>
    </header>
    <main class="acceso">
    <h1>Iniciar sesión</h1>

    <?php if ($aviso !== ''): ?>
        <p data-aviso role="status"><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['usuario_id'], $_SESSION['nombre'])): ?>
        <p>Has iniciado sesión como <?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') ?>.</p>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_logout'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Cerrar sesión</button>
        </form>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <p data-aviso role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="post" action="login.php">
            <p>
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" maxlength="150" required
                    value="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>">
            </p>
            <p>
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </p>
            <p>
                <label class="opcion" for="recordar">
                    <input type="checkbox" id="recordar" name="recordar" value="1" <?= $recordar ? 'checked' : '' ?>>
                    Recordar mi correo
                </label>
            </p>
            <button type="submit">Entrar</button>
        </form>
        <p><a href="register.php">Crear cuenta</a></p>
    <?php endif; ?>
    </main>
</body>
</html>