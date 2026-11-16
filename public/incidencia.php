<?php

session_start();

if (!isset($_SESSION['usuario_id'], $_SESSION['rol'])) {
    header('Location: login.php', true, 303);
    exit;
}

require_once __DIR__ . '/../includes/funciones.php';

$idEntrada = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');
$id = is_string($idEntrada)
    ? filter_var($idEntrada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($id === false) {
    http_response_code(400);
    exit('El identificador de incidencia no es válido.');
}

$estados = [
    'abierta' => 'Abierta',
    'en_curso' => 'En curso',
    'resuelta' => 'Resuelta',
];
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
$error = '';
$errores = [];
$incidencia = null;
$historial = [];
$estadoNuevo = isset($_POST['estado']) && is_string($_POST['estado']) ? $_POST['estado'] : '';
$comentario = isset($_POST['comentario']) && is_string($_POST['comentario']) ? $_POST['comentario'] : '';

try {
    require __DIR__ . '/../includes/db.php';

    $consulta = $db->prepare(
        'SELECT i.id, i.equipo_id, e.codigo AS equipo_codigo, e.descripcion AS equipo_descripcion,
                i.usuario_id, u.nombre AS usuario_nombre, i.categoria, i.prioridad,
                i.descripcion, i.estado, i.creada_en, i.actualizada_en
         FROM incidencias i
         INNER JOIN equipos e ON e.id = i.equipo_id
         INNER JOIN usuarios u ON u.id = i.usuario_id
         WHERE i.id = :id'
    );
    $consulta->execute(['id' => $id]);
    $incidencia = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$incidencia) {
        http_response_code(404);
        $error = 'No se encontró la incidencia solicitada.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf($_POST['csrf'] ?? null)) {
            http_response_code(403);
            $error = 'No se pudo procesar la acción. Recarga la página e inténtalo de nuevo.';
        } else {
            $accion = isset($_POST['accion']) && is_string($_POST['accion']) ? $_POST['accion'] : '';

            if ($accion === 'borrar') {
                if ($_SESSION['rol'] !== 'profesor') {
                    http_response_code(403);
                    $error = 'Solo el profesorado puede borrar incidencias.';
                } else {
                    $consulta = $db->prepare('DELETE FROM incidencias WHERE id = :id');
                    $consulta->execute(['id' => $id]);
                    if ($consulta->rowCount() !== 1) {
                        http_response_code(404);
                        $error = 'La incidencia ya no existe.';
                    } else {
                        $_SESSION['aviso'] = 'La incidencia se ha borrado.';
                        header('Location: incidencias.php', true, 303);
                        exit;
                    }
                }
            } elseif ($accion === 'actualizar_estado') {
                if ($_SESSION['rol'] !== 'profesor') {
                    http_response_code(403);
                    $error = 'Solo el profesorado puede cambiar el estado.';
                } else {
                    if (!array_key_exists($estadoNuevo, $estados)) {
                        $errores['estado'] = 'Selecciona un estado válido.';
                    }
                    if (trim($comentario) === '') {
                        $errores['comentario'] = 'Añade un comentario al cambio de estado.';
                    }

                    if (!$errores) {
                        try {
                            $db->beginTransaction();
                            $consulta = $db->prepare('SELECT estado FROM incidencias WHERE id = :id FOR UPDATE');
                            $consulta->execute(['id' => $id]);
                            $estadoAnterior = $consulta->fetchColumn();

                            if ($estadoAnterior === false) {
                                $db->rollBack();
                                http_response_code(404);
                                $error = 'La incidencia ya no existe.';
                                $incidencia = null;
                            } else {
                                $consulta = $db->prepare(
                                    'UPDATE incidencias SET estado = :estado WHERE id = :id'
                                );
                                $consulta->execute(['estado' => $estadoNuevo, 'id' => $id]);

                                $consulta = $db->prepare(
                                    'INSERT INTO historial
                                        (incidencia_id, usuario_id, estado_anterior, estado_nuevo, comentario)
                                     VALUES (:incidencia_id, :usuario_id, :estado_anterior, :estado_nuevo, :comentario)'
                                );
                                $consulta->execute([
                                    'incidencia_id' => $id,
                                    'usuario_id' => (int) $_SESSION['usuario_id'],
                                    'estado_anterior' => $estadoAnterior,
                                    'estado_nuevo' => $estadoNuevo,
                                    'comentario' => trim($comentario),
                                ]);
                                $db->commit();

                                $_SESSION['aviso'] = 'El estado se ha actualizado y añadido al historial.';
                                header('Location: incidencia.php?id=' . (int) $id, true, 303);
                                exit;
                            }
                        } catch (PDOException $excepcion) {
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }
                            throw $excepcion;
                        }
                    }
                }
            } else {
                http_response_code(400);
                $error = 'La acción solicitada no es válida.';
            }
        }
    }

    if ($incidencia) {
        $consulta = $db->prepare(
            'SELECT h.estado_anterior, h.estado_nuevo, h.comentario, h.creado_en,
                    u.nombre AS usuario_nombre
             FROM historial h
             INNER JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.incidencia_id = :id
             ORDER BY h.creado_en ASC, h.id ASC'
        );
        $consulta->execute(['id' => $id]);
        $historial = $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $excepcion) {
    error_log('Error al consultar o actualizar una incidencia: ' . $excepcion->getMessage());
    http_response_code(500);
    $error = 'No se pudo completar la operación. Inténtalo más tarde.';
}

$aviso = isset($_SESSION['aviso']) && is_string($_SESSION['aviso']) ? $_SESSION['aviso'] : '';
unset($_SESSION['aviso']);
$tokenCsrf = obtenerTokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de incidencia | AulaFix</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="css/incidencias.css">
    <link rel="stylesheet" href="css/avisos.css">
    <script src="js/avisos.js" defer></script>
</head>
<body>
    <header class="cabecera">
        <span class="marca">AulaFix</span>
        <span class="cabecera-descripcion">Gestión de incidencias</span>
    </header>
    <main>
        <nav class="navegacion" aria-label="Navegación principal">
            <a href="incidencias.php">Incidencias</a>
            <a href="nueva.php">Nueva incidencia</a>
        </nav>
        <h1>Detalle de incidencia</h1>

        <?php if ($aviso !== ''): ?>
            <p data-aviso role="status"><?= h($aviso) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p role="alert"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($incidencia): ?>
            <dl class="detalle-incidencia">
                <dt>Número</dt><dd><?= (int) $incidencia['id'] ?></dd>
                <dt>Equipo</dt>
                <dd><?= h($incidencia['equipo_codigo']) ?><?= $incidencia['equipo_descripcion'] ? ' - ' . h($incidencia['equipo_descripcion']) : '' ?></dd>
                <dt>Categoría</dt><dd><?= h($categorias[$incidencia['categoria']] ?? $incidencia['categoria']) ?></dd>
                <dt>Prioridad</dt><dd><?= h($prioridades[$incidencia['prioridad']] ?? $incidencia['prioridad']) ?></dd>
                <dt>Estado</dt><dd><?= h($estados[$incidencia['estado']] ?? $incidencia['estado']) ?></dd>
                <dt>Descripción</dt><dd class="descripcion-incidencia"><?= nl2br(h($incidencia['descripcion'])) ?></dd>
                <dt>Creada por</dt><dd><?= h($incidencia['usuario_nombre']) ?></dd>
                <dt>Fecha de creación</dt><dd><?= h($incidencia['creada_en']) ?></dd>
                <dt>Última actualización</dt><dd><?= h($incidencia['actualizada_en']) ?></dd>
            </dl>

            <section aria-labelledby="titulo-historial">
                <h2 id="titulo-historial">Historial</h2>
                <?php if (!$historial): ?>
                    <p role="status">Todavía no hay cambios de estado registrados.</p>
                <?php else: ?>
                    <ol class="historial">
                        <?php foreach ($historial as $evento): ?>
                            <li>
                                <p>
                                    <?= h($estados[$evento['estado_anterior']] ?? $evento['estado_anterior'] ?? 'Creada') ?>
                                    a <?= h($estados[$evento['estado_nuevo']] ?? $evento['estado_nuevo']) ?>
                                    por <?= h($evento['usuario_nombre']) ?>, <?= h($evento['creado_en']) ?>
                                </p>
                                <?php if ($evento['comentario'] !== null && $evento['comentario'] !== ''): ?>
                                    <p class="comentario-historial"><?= nl2br(h($evento['comentario'])) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <?php if ($_SESSION['rol'] === 'profesor'): ?>
                <section aria-labelledby="titulo-actualizar">
                    <h2 id="titulo-actualizar">Actualizar estado</h2>
                    <?php if ($errores): ?>
                        <div role="alert">
                            <ul>
                                <?php foreach ($errores as $mensaje): ?>
                                    <li><?= h($mensaje) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="incidencia.php?id=<?= (int) $id ?>">
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="csrf" value="<?= h($tokenCsrf) ?>">
                        <input type="hidden" name="accion" value="actualizar_estado">
                        <p>
                            <label for="estado">Nuevo estado</label>
                            <select id="estado" name="estado" required
                                <?= isset($errores['estado']) ? 'aria-invalid="true" aria-describedby="error-estado"' : '' ?>>
                                <?php foreach ($estados as $valor => $etiqueta): ?>
                                    <option value="<?= h($valor) ?>" <?= $estadoNuevo === $valor || ($estadoNuevo === '' && $incidencia['estado'] === $valor) ? 'selected' : '' ?>>
                                        <?= h($etiqueta) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errores['estado'])): ?>
                                <span class="error-campo" id="error-estado"><?= h($errores['estado']) ?></span>
                            <?php endif; ?>
                        </p>
                        <p>
                            <label for="comentario">Comentario</label>
                            <textarea id="comentario" name="comentario" rows="4" required
                                <?= isset($errores['comentario']) ? 'aria-invalid="true" aria-describedby="error-comentario"' : '' ?>><?= h($comentario) ?></textarea>
                            <?php if (isset($errores['comentario'])): ?>
                                <span class="error-campo" id="error-comentario"><?= h($errores['comentario']) ?></span>
                            <?php endif; ?>
                        </p>
                        <button type="submit">Guardar cambio</button>
                    </form>
                </section>
                <form method="post" action="incidencia.php?id=<?= (int) $id ?>" class="formulario-borrar">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="csrf" value="<?= h($tokenCsrf) ?>">
                    <input type="hidden" name="accion" value="borrar">
                    <button type="submit">Borrar incidencia</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <footer class="pie">
        <p>AulaFix, gestión de incidencias</p>
    </footer>
</body>
</html>
