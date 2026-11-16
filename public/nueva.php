<?php

session_start();

if (!isset($_SESSION['usuario_id'], $_SESSION['rol'])) {
    header('Location: login.php', true, 303);
    exit;
}

require_once __DIR__ . '/../includes/funciones.php';

$equipoId = isset($_POST['equipo_id']) && is_string($_POST['equipo_id']) ? trim($_POST['equipo_id']) : '';
$categoria = isset($_POST['categoria']) && is_string($_POST['categoria']) ? $_POST['categoria'] : '';
$prioridad = isset($_POST['prioridad']) && is_string($_POST['prioridad']) ? $_POST['prioridad'] : '';
$descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion']) ? $_POST['descripcion'] : '';
$errores = [];
$errorGeneral = '';
$equipos = [];
$equiposCargados = false;
$categorias = [
    'hardware' => 'Hardware',
    'software' => 'Software',
    'red' => 'Red',
    'otro' => 'Otro',
];
$prioridades = [
    'baja' => 'Baja',
    'media' => 'Media',
    'alta' => 'Alta',
];

try {
    require __DIR__ . '/../includes/db.php';
    $consulta = $db->query('SELECT id, codigo, descripcion FROM equipos ORDER BY codigo');
    $equipos = $consulta->fetchAll(PDO::FETCH_ASSOC);
    $equiposCargados = true;
} catch (PDOException $excepcion) {
    error_log('Error al cargar equipos para una incidencia: ' . $excepcion->getMessage());
    http_response_code(500);
    $errorGeneral = 'No se pudieron cargar los equipos. Inténtalo más tarde.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorGeneral === '') {
    if (!validarTokenCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $errorGeneral = 'No se pudo enviar el formulario. Recarga la página e inténtalo de nuevo.';
    } else {
        $idsEquipo = array_map(static function ($equipo) {
            return (string) $equipo['id'];
        }, $equipos);

        if (!in_array($equipoId, $idsEquipo, true)) {
            $errores['equipo_id'] = 'Selecciona un equipo válido.';
        }

        if (!array_key_exists($categoria, $categorias)) {
            $errores['categoria'] = 'Selecciona una categoría.';
        }

        if (!array_key_exists($prioridad, $prioridades)) {
            $errores['prioridad'] = 'Selecciona una prioridad.';
        }

        $descripcionLimpia = trim($descripcion);
        $longitudDescripcion = preg_match_all('/./us', $descripcionLimpia);
        if ($longitudDescripcion === false || $longitudDescripcion < 10 || $longitudDescripcion > 500) {
            $errores['descripcion'] = 'La descripción debe tener entre 10 y 500 caracteres.';
        }

        if (!$errores) {
            try {
                $consulta = $db->prepare(
                    'INSERT INTO incidencias (equipo_id, usuario_id, categoria, prioridad, descripcion)
                     VALUES (:equipo_id, :usuario_id, :categoria, :prioridad, :descripcion)'
                );
                $consulta->execute([
                    'equipo_id' => (int) $equipoId,
                    'usuario_id' => (int) $_SESSION['usuario_id'],
                    'categoria' => $categoria,
                    'prioridad' => $prioridad,
                    'descripcion' => $descripcionLimpia,
                ]);

                $_SESSION['aviso'] = 'La incidencia se ha creado correctamente.';
                $_SESSION['limpiar_borrador_nueva'] = true;
                header('Location: incidencias.php', true, 303);
                exit;
            } catch (PDOException $excepcion) {
                error_log('Error al guardar una incidencia: ' . $excepcion->getMessage());
                http_response_code(500);
                $errorGeneral = 'No se pudo guardar la incidencia. Inténtalo más tarde.';
            }
        }
    }
}

$tokenCsrf = obtenerTokenCsrf();
$restaurarBorrador = $_SERVER['REQUEST_METHOD'] !== 'POST';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva incidencia | AulaFix</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="css/incidencias.css">
    <script src="js/nueva.js" defer></script>
</head>
<body>
    <header class="cabecera">
        <span class="marca">AulaFix</span>
        <span class="cabecera-descripcion">Gestión de incidencias</span>
    </header>
    <main class="formulario-incidencia">
        <nav class="navegacion" aria-label="Navegación principal">
            <a href="incidencias.php">Incidencias</a>
            <a href="nueva.php" aria-current="page">Nueva incidencia</a>
        </nav>
        <h1>Nueva incidencia</h1>

        <?php if ($errorGeneral !== ''): ?>
            <p role="alert"><?= h($errorGeneral) ?></p>
        <?php endif; ?>
        <p id="estado-borrador" role="status" hidden></p>

        <?php if ($equiposCargados && !$equipos): ?>
            <p role="status">No hay equipos disponibles. Contacta con el profesorado para registrar equipos.</p>
        <?php elseif ($equiposCargados): ?>
            <form method="post" action="nueva.php" data-restaurar-borrador="<?= $restaurarBorrador ? 'true' : 'false' ?>">
                <input type="hidden" name="csrf" value="<?= h($tokenCsrf) ?>">
                <p>
                    <label for="equipo_id">Equipo</label>
                    <select id="equipo_id" name="equipo_id" required
                        <?= isset($errores['equipo_id']) ? 'aria-invalid="true" aria-describedby="error-equipo"' : '' ?>>
                        <option value="">Selecciona un equipo</option>
                        <?php foreach ($equipos as $equipo): ?>
                            <option value="<?= (int) $equipo['id'] ?>" <?= $equipoId === (string) $equipo['id'] ? 'selected' : '' ?>>
                                <?= h($equipo['codigo']) ?><?= $equipo['descripcion'] ? ' - ' . h($equipo['descripcion']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['equipo_id'])): ?>
                        <span class="error-campo" id="error-equipo"><?= h($errores['equipo_id']) ?></span>
                    <?php endif; ?>
                </p>
                <p>
                    <label for="categoria">Categoría</label>
                    <select id="categoria" name="categoria" required
                        <?= isset($errores['categoria']) ? 'aria-invalid="true" aria-describedby="error-categoria"' : '' ?>>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($categorias as $valor => $etiqueta): ?>
                            <option value="<?= h($valor) ?>" <?= $categoria === $valor ? 'selected' : '' ?>><?= h($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['categoria'])): ?>
                        <span class="error-campo" id="error-categoria"><?= h($errores['categoria']) ?></span>
                    <?php endif; ?>
                </p>
                <fieldset class="grupo-opciones" <?= isset($errores['prioridad']) ? 'aria-describedby="error-prioridad"' : '' ?>>
                    <legend>Prioridad</legend>
                    <?php foreach ($prioridades as $valor => $etiqueta): ?>
                        <label class="opcion" for="prioridad-<?= h($valor) ?>">
                            <input type="radio" id="prioridad-<?= h($valor) ?>" name="prioridad" value="<?= h($valor) ?>"
                                <?= $prioridad === $valor ? 'checked' : '' ?> required>
                            <?= h($etiqueta) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (isset($errores['prioridad'])): ?>
                        <span class="error-campo" id="error-prioridad"><?= h($errores['prioridad']) ?></span>
                    <?php endif; ?>
                </fieldset>
                <p>
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" minlength="10" maxlength="500" rows="6" required
                        aria-describedby="ayuda-descripcion contador-descripcion<?= isset($errores['descripcion']) ? ' error-descripcion' : '' ?>"
                        <?= isset($errores['descripcion']) ? 'aria-invalid="true"' : '' ?>><?= h($descripcion) ?></textarea>
                    <span id="ayuda-descripcion">Escribe entre 10 y 500 caracteres.</span>
                    <output id="contador-descripcion" for="descripcion">0 de 500 caracteres</output>
                    <?php if (isset($errores['descripcion'])): ?>
                        <span class="error-campo" id="error-descripcion"><?= h($errores['descripcion']) ?></span>
                    <?php endif; ?>
                </p>
                <button type="submit">Guardar incidencia</button>
            </form>
        <?php endif; ?>
    </main>
    <footer class="pie">
        <p>AulaFix, gestión de incidencias</p>
    </footer>
</body>
</html>
