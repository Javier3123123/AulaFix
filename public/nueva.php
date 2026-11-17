<?php

session_start();

// Solo los usuarios con una sesión identificada pueden registrar incidencias.
if (!isset($_SESSION['usuario_id'], $_SESSION['rol'])) {
    header('Location: login.php', true, 303);
    exit;
}

// Estas funciones se usan para la validación CSRF y para escapar contenido HTML.
require_once __DIR__ . '/../includes/funciones.php';

// Se conservan los valores recibidos para repoblar el formulario si la validación falla.
$equipoId = isset($_POST['equipo_id']) && is_string($_POST['equipo_id']) ? trim($_POST['equipo_id']) : '';
$categoria = isset($_POST['categoria']) && is_string($_POST['categoria']) ? $_POST['categoria'] : '';
$prioridad = isset($_POST['prioridad']) && is_string($_POST['prioridad']) ? $_POST['prioridad'] : '';
$descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion']) ? $_POST['descripcion'] : '';
$errores = [];
$errorGeneral = '';
$equipos = [];
// Permite distinguir una consulta correcta sin resultados de un error al cargar equipos.
$equiposCargados = false;
// Las claves de estas listas son los valores que se guardan en la base de datos.
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

// Los equipos se consultan para construir opciones válidas para el formulario.
try {
    require __DIR__ . '/../includes/db.php';
    $consulta = $db->query('SELECT id, codigo, descripcion FROM equipos ORDER BY codigo');
    $equipos = $consulta->fetchAll(PDO::FETCH_ASSOC);
    $equiposCargados = true;
} catch (PDOException $excepcion) {
    // El detalle técnico queda en el registro; la respuesta muestra un mensaje general.
    error_log('Error al cargar equipos para una incidencia: ' . $excepcion->getMessage());
    http_response_code(500);
    $errorGeneral = 'No se pudieron cargar los equipos. Inténtalo más tarde.';
}

// Solo se procesa el envío cuando la lista de equipos se pudo cargar y validar.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorGeneral === '') {
    // El token evita aceptar cambios enviados desde una página ajena al sitio.
    if (!validarTokenCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $errorGeneral = 'No se pudo enviar el formulario. Recarga la página e inténtalo de nuevo.';
    } else {
        // Se comparan identificadores como cadenas para validar el valor exacto del desplegable.
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

        // El patrón cuenta caracteres UTF-8 y también incluye saltos de línea en la longitud.
        $descripcionLimpia = trim($descripcion);
        $longitudDescripcion = preg_match_all('/./us', $descripcionLimpia);
        if ($longitudDescripcion === false || $longitudDescripcion < 10 || $longitudDescripcion > 500) {
            $errores['descripcion'] = 'La descripción debe tener entre 10 y 500 caracteres.';
        }

        if (!$errores) {
            try {
                // La consulta preparada separa los datos recibidos de la instrucción SQL.
                $consulta = $db->prepare(
                    'INSERT INTO incidencias (equipo_id, usuario_id, categoria, prioridad, descripcion)
                     VALUES (:equipo_id, :usuario_id, :categoria, :prioridad, :descripcion)'
                );
                $consulta->execute([
                    'equipo_id' => (int) $equipoId,
                    // El autor siempre se toma de la sesión, no del formulario.
                    'usuario_id' => (int) $_SESSION['usuario_id'],
                    'categoria' => $categoria,
                    'prioridad' => $prioridad,
                    'descripcion' => $descripcionLimpia,
                ]);

                // El listado consume el aviso y usa esta marca para eliminar el borrador del navegador.
                $_SESSION['aviso'] = 'La incidencia se ha creado correctamente.';
                $_SESSION['limpiar_borrador_nueva'] = true;
                header('Location: incidencias.php', true, 303);
                exit;
            } catch (PDOException $excepcion) {
                // No se expone el error de base de datos en la página.
                error_log('Error al guardar una incidencia: ' . $excepcion->getMessage());
                http_response_code(500);
                $errorGeneral = 'No se pudo guardar la incidencia. Inténtalo más tarde.';
            }
        }
    }
}

// El formulario genera el token incluso al cargar la página por primera vez.
$tokenCsrf = obtenerTokenCsrf();
// Un POST fallido conserva sus valores y no se sustituye por un borrador anterior.
$restaurarBorrador = $_SERVER['REQUEST_METHOD'] !== 'POST';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva incidencia | AulaFix</title>
    <!-- Se reutilizan los estilos del sitio; nueva.js controla el contador y el borrador. -->
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="css/incidencias.css">
    <script type="module" src="js/nueva.js"></script>
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

        <!-- Los errores generales anuncian fallos que no pertenecen a un campo concreto. -->
        <?php if ($errorGeneral !== ''): ?>
            <p role="alert"><?= h($errorGeneral) ?></p>
        <?php endif; ?>
        <!-- nueva.js anuncia aquí los cambios al guardar o recuperar el borrador. -->
        <p id="estado-borrador" role="status" hidden></p>

        <!-- Un resultado vacío se trata aparte del fallo de consulta mostrado arriba. -->
        <?php if ($equiposCargados && !$equipos): ?>
            <p role="status">No hay equipos disponibles. Contacta con el profesorado para registrar equipos.</p>
        <?php elseif ($equiposCargados): ?>
            <!-- La bandera indica al módulo cliente si debe restaurar el borrador guardado. -->
            <!-- Los valores recibidos y los datos de la base se escapan antes de mostrarse. -->
            <form method="post" action="nueva.php" data-restaurar-borrador="<?= $restaurarBorrador ? 'true' : 'false' ?>">
                <input type="hidden" name="csrf" value="<?= h($tokenCsrf) ?>">
                <p>
                    <label for="equipo_id">Equipo</label>
                    <select id="equipo_id" name="equipo_id" required
                        <?= isset($errores['equipo_id']) ? 'aria-invalid="true" aria-describedby="error-equipo"' : '' ?>>
                        <option value="">Selecciona un equipo</option>
                        <!-- Las opciones salen de la consulta y se conserva la selección enviada. -->
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
                        <!-- El valor persistido y la etiqueta visible se mantienen separados. -->
                        <?php foreach ($categorias as $valor => $etiqueta): ?>
                            <option value="<?= h($valor) ?>" <?= $categoria === $valor ? 'selected' : '' ?>><?= h($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['categoria'])): ?>
                        <span class="error-campo" id="error-categoria"><?= h($errores['categoria']) ?></span>
                    <?php endif; ?>
                </p>
                <!-- Los radios representan una única prioridad y recuperan la opción enviada. -->
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
                    <!-- Los límites del navegador mejoran la entrada, pero PHP vuelve a validarlos. -->
                    <textarea id="descripcion" name="descripcion" minlength="10" maxlength="500" rows="6" required
                        aria-describedby="ayuda-descripcion contador-descripcion<?= isset($errores['descripcion']) ? ' error-descripcion' : '' ?>"
                        <?= isset($errores['descripcion']) ? 'aria-invalid="true"' : '' ?>><?= h($descripcion) ?></textarea>
                    <span id="ayuda-descripcion">Escribe entre 10 y 500 caracteres.</span>
                    <!-- nueva.js actualiza este contador mientras se escribe. -->
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
