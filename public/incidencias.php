<?php

session_start();

if (!isset($_SESSION['usuario_id'], $_SESSION['rol'])) {
    header('Location: login.php', true, 303);
    exit;
}

require_once __DIR__ . '/../includes/funciones.php';

$error = '';
$incidencias = [];
$datosPanel = [];
$datosJson = '';

try {
    require __DIR__ . '/../includes/db.php';
    $consulta = $db->query(
        'SELECT i.id, i.equipo_id, e.codigo AS equipo_codigo, e.descripcion AS equipo_descripcion,
                i.usuario_id, u.nombre AS usuario_nombre, i.categoria, i.prioridad,
                i.descripcion, i.estado, i.creada_en, i.actualizada_en
         FROM incidencias i
         INNER JOIN equipos e ON e.id = i.equipo_id
         INNER JOIN usuarios u ON u.id = i.usuario_id
         ORDER BY i.creada_en DESC, i.id DESC'
    );
    $incidencias = $consulta->fetchAll(PDO::FETCH_ASSOC);
    foreach ($incidencias as $incidencia) {
        $fechaCreacion = new DateTimeImmutable($incidencia['creada_en']);
        $datosPanel[] = [
            'id' => (int) $incidencia['id'],
            'equipo' => (string) $incidencia['equipo_codigo'],
            'categoria' => (string) $incidencia['categoria'],
            'prioridad' => (string) $incidencia['prioridad'],
            'descripcion' => (string) $incidencia['descripcion'],
            'estado' => (string) $incidencia['estado'],
            'autor' => (string) $incidencia['usuario_nombre'],
            'creada_en' => $fechaCreacion->format(DateTimeInterface::ATOM),
        ];
    }

    $datosJson = json_encode(
        $datosPanel,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    if ($datosJson === false) {
        error_log('No se pudieron convertir las incidencias a JSON: ' . json_last_error_msg());
        throw new RuntimeException('No se pudieron preparar los datos del panel.');
    }
} catch (PDOException $excepcion) {
    error_log('Error al listar incidencias: ' . $excepcion->getMessage());
    http_response_code(500);
    $error = 'No se pudieron cargar las incidencias. Inténtalo más tarde.';
} catch (Exception $excepcion) {
    error_log('Error al preparar los datos del panel: ' . $excepcion->getMessage());
    http_response_code(500);
    $error = 'No se pudieron preparar los datos del panel. Inténtalo más tarde.';
}

$aviso = isset($_SESSION['aviso']) && is_string($_SESSION['aviso']) ? $_SESSION['aviso'] : '';
unset($_SESSION['aviso']);
$limpiarBorrador = !empty($_SESSION['limpiar_borrador_nueva']);
unset($_SESSION['limpiar_borrador_nueva']);

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
$estados = [
    'abierta' => 'Abierta',
    'en_curso' => 'En curso',
    'resuelta' => 'Resuelta',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incidencias | AulaFix</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="css/incidencias.css">
    <link rel="stylesheet" href="css/avisos.css">
    <script src="js/avisos.js" defer></script>
    <script src="js/main.js" defer></script>
</head>
<body data-borrar-borrador="<?= $limpiarBorrador ? 'true' : 'false' ?>">
    <header class="cabecera">
        <span class="marca">AulaFix</span>
        <span class="cabecera-descripcion">Gestión de incidencias</span>
    </header>
    <main>
        <nav class="navegacion" aria-label="Navegación principal">
            <a href="incidencias.php" aria-current="page">Incidencias</a>
            <a href="nueva.php">Nueva incidencia</a>
        </nav>
        <h1>Incidencias</h1>

        <?php if ($aviso !== ''): ?>
            <p data-aviso role="status"><?= h($aviso) ?></p>
        <?php endif; ?>

        <div id="vista-servidor">
            <?php if ($error !== ''): ?>
                <p role="alert"><?= h($error) ?></p>
            <?php elseif (!$incidencias): ?>
                <p role="status">Todavía no hay incidencias registradas.</p>
            <?php else: ?>
                <div class="tabla-contenedor" tabindex="0" role="region" aria-label="Listado de incidencias">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">N.º</th>
                                <th scope="col">Equipo</th>
                                <th scope="col">Categoría</th>
                                <th scope="col">Prioridad</th>
                                <th scope="col">Descripción</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Creada</th>
                                <th scope="col">Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $incidencia): ?>
                                <tr>
                                    <td><a href="incidencia.php?id=<?= (int) $incidencia['id'] ?>"><?= (int) $incidencia['id'] ?></a></td>
                                    <td>
                                        <?= h($incidencia['equipo_codigo']) ?>
                                        <?php if ($incidencia['equipo_descripcion'] !== null && $incidencia['equipo_descripcion'] !== ''): ?>
                                            <span class="texto-secundario"><?= h($incidencia['equipo_descripcion']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($categorias[$incidencia['categoria']] ?? $incidencia['categoria']) ?></td>
                                    <td><?= h($prioridades[$incidencia['prioridad']] ?? $incidencia['prioridad']) ?></td>
                                    <td><?= h($incidencia['descripcion']) ?></td>
                                    <td><?= h($estados[$incidencia['estado']] ?? $incidencia['estado']) ?></td>
                                    <td><?= h($incidencia['creada_en']) ?></td>
                                    <td><?= h($incidencia['usuario_nombre']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($error === ''): ?>
            <section id="panel-incidencias" hidden aria-labelledby="titulo-panel">
                <h2 id="titulo-panel">Resumen de incidencias</h2>
                <div class="resumen-incidencias" role="group" aria-label="Resumen por estado">
                    <article>
                        <h3>Abiertas</h3>
                        <p id="total-abiertas">0</p>
                    </article>
                    <article>
                        <h3>En curso</h3>
                        <p id="total-en-curso">0</p>
                    </article>
                    <article>
                        <h3>Resueltas</h3>
                        <p id="total-resueltas">0</p>
                    </article>
                    <article>
                        <h3>Urgentes sin resolver</h3>
                        <p id="total-urgentes">0</p>
                    </article>
                </div>

                <div class="filtros-incidencias">
                    <label for="buscar-incidencia">Buscar por equipo, descripción o autor
                        <input type="search" id="buscar-incidencia" autocomplete="off">
                    </label>
                    <label for="filtrar-prioridad">Prioridad
                        <select id="filtrar-prioridad">
                            <option value="todas">Todas las prioridades</option>
                            <?php foreach ($prioridades as $valor => $etiqueta): ?>
                                <option value="<?= h($valor) ?>"><?= h($etiqueta) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="pestanas-incidencias" role="tablist" aria-label="Filtrar por estado">
                    <button type="button" id="pestana-todas" role="tab" aria-selected="true"
                        aria-controls="panel-resultados" tabindex="0" data-estado="todas">Todas</button>
                    <button type="button" id="pestana-abiertas" role="tab" aria-selected="false"
                        aria-controls="panel-resultados" tabindex="-1" data-estado="abierta">Abiertas</button>
                    <button type="button" id="pestana-en-curso" role="tab" aria-selected="false"
                        aria-controls="panel-resultados" tabindex="-1" data-estado="en_curso">En curso</button>
                    <button type="button" id="pestana-resueltas" role="tab" aria-selected="false"
                        aria-controls="panel-resultados" tabindex="-1" data-estado="resuelta">Resueltas</button>
                </div>

                <section id="panel-resultados" role="tabpanel" tabindex="0" aria-labelledby="pestana-todas">
                    <p id="contador-incidencias" aria-live="polite" aria-atomic="true"></p>
                    <div class="tabla-contenedor" tabindex="0" role="region" aria-label="Resultados filtrados">
                        <table id="tabla-cliente">
                            <caption>Resultados de incidencias</caption>
                            <thead>
                                <tr>
                                    <th scope="col" aria-sort="none">N.º</th>
                                    <th scope="col" data-campo-orden="equipo" aria-sort="none">
                                        <button type="button" data-orden="equipo" aria-label="Ordenar por equipo">Equipo</button>
                                    </th>
                                    <th scope="col" aria-sort="none">Categoría</th>
                                    <th scope="col" data-campo-orden="prioridad" aria-sort="none">
                                        <button type="button" data-orden="prioridad" aria-label="Ordenar por prioridad">Prioridad</button>
                                    </th>
                                    <th scope="col" aria-sort="none">Descripción</th>
                                    <th scope="col" aria-sort="none">Estado</th>
                                    <th scope="col" data-campo-orden="fecha" aria-sort="descending">
                                        <button type="button" data-orden="fecha" aria-label="Ordenar por fecha, descendente">Fecha</button>
                                    </th>
                                    <th scope="col" aria-sort="none">Autor</th>
                                </tr>
                            </thead>
                            <tbody id="filas-incidencias"></tbody>
                        </table>
                    </div>
                    <p id="sin-resultados" role="status" hidden></p>
                </section>
            </section>
            <script type="application/json" id="datos-incidencias"><?= $datosJson ?></script>
        <?php endif; ?>
        <p id="error-panel-incidencias" role="alert" hidden></p>
    </main>
    <footer class="pie">
        <p>AulaFix, gestión de incidencias</p>
    </footer>
</body>
</html>
