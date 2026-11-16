<?php

session_start();

$nombre = '';
$correo = '';
$errores = [];
$registrado = !empty($_SESSION['registro_completado']);
unset($_SESSION['registro_completado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
        ? trim($_POST['nombre'])
        : '';
    $correo = isset($_POST['correo']) && is_string($_POST['correo'])
        ? trim($_POST['correo'])
        : '';
    $contrasena = isset($_POST['contrasena']) && is_string($_POST['contrasena'])
        ? $_POST['contrasena']
        : '';
    $repeticion = isset($_POST['repeticion']) && is_string($_POST['repeticion'])
        ? $_POST['repeticion']
        : '';

    $longitudNombre = preg_match_all('/./us', $nombre);
    if ($longitudNombre === false || $longitudNombre < 2 || $longitudNombre > 80) {
        $errores[] = 'El nombre debe tener entre 2 y 80 caracteres.';
    }

    if (strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Introduce un correo electrónico válido de hasta 150 caracteres.';
    }

    $longitudContrasena = preg_match_all('/./us', $contrasena);
    if (
        $longitudContrasena === false
        || $longitudContrasena < 10
        || preg_match('/\p{L}/u', $contrasena) !== 1
        || preg_match('/\p{N}/u', $contrasena) !== 1
    ) {
        $errores[] = 'La contraseña debe tener al menos 10 caracteres, con letras y números.';
    }

    if ($contrasena !== $repeticion) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        try {
            require __DIR__ . '/../includes/db.php';

            $consulta = $db->prepare('SELECT id FROM usuarios WHERE email = :email');
            $consulta->execute(['email' => $correo]);

            if ($consulta->fetch(PDO::FETCH_ASSOC)) {
                $errores[] = 'Ya existe una cuenta con ese correo.';
            } elseif (!defined('PASSWORD_ARGON2ID')) {
                error_log('PHP no dispone de soporte para Argon2id.');
                $errores[] = 'No se pudo completar el registro. Inténtalo más tarde.';
            } else {
                $hash = password_hash($contrasena, PASSWORD_ARGON2ID);

                if ($hash === false) {
                    error_log('No se pudo generar el hash de la contraseña.');
                    $errores[] = 'No se pudo completar el registro. Inténtalo más tarde.';
                } else {
                    $consulta = $db->prepare(
                        'INSERT INTO usuarios (nombre, email, clave_hash, rol)
                         VALUES (:nombre, :email, :clave_hash, :rol)'
                    );
                    $consulta->execute([
                        'nombre' => $nombre,
                        'email' => $correo,
                        'clave_hash' => $hash,
                        'rol' => 'alumno',
                    ]);

                    $_SESSION['registro_completado'] = true;
                    header('Location: register.php');
                    exit;
                }
            }
        } catch (PDOException $excepcion) {
            if (isset($excepcion->errorInfo[1]) && (int) $excepcion->errorInfo[1] === 1062) {
                $errores[] = 'Ya existe una cuenta con ese correo.';
            } else {
                error_log('Error de base de datos al registrar usuario: ' . $excepcion->getMessage());
                $errores[] = 'No se pudo completar el registro. Inténtalo más tarde.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="css/avisos.css">
    <script type="module" src="js/avisos.js"></script>
</head>
<body>
    <header class="cabecera">
        <span class="marca">AulaFix</span>
        <span class="cabecera-descripcion">Gestión de incidencias</span>
    </header>
    <main class="acceso">
        <h1>Crear cuenta</h1>

        <?php if ($registrado): ?>
            <p data-aviso role="status">Cuenta creada. Ya puedes iniciar sesión.</p>
            <p>Ya puedes iniciar sesión con tu nueva cuenta.</p>
        <?php else: ?>
            <?php if ($errores): ?>
                <div data-aviso role="alert">
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <p>
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" minlength="2" maxlength="80"
                        autocomplete="name" required value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">
                </p>
                <p>
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" maxlength="150"
                        autocomplete="email" required value="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>">
                </p>
                <p>
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" minlength="10"
                        autocomplete="new-password" aria-describedby="ayuda-contrasena" required>
                    <span id="ayuda-contrasena">Al menos 10 caracteres, con letras y números.</span>
                </p>
                <p>
                    <label for="repeticion">Repite la contraseña</label>
                    <input type="password" id="repeticion" name="repeticion" minlength="10"
                        autocomplete="new-password" required>
                </p>
                <button type="submit">Crear cuenta</button>
            </form>
        <?php endif; ?>

        <p><a href="login.php">Iniciar sesión</a></p>
    </main>
</body>
</html>
