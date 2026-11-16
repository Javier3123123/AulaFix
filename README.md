# AulaFix

## Inicio de sesión

Importa `database/esquema.sql` en MySQL. Puedes crear una cuenta de alumno en
`public/register.php` y acceder desde `public/login.php`. El registro requiere
un nombre de 2 a 80 caracteres, un correo válido no utilizado y una contraseña
de al menos 10 caracteres con letras y números. La contraseña se guarda con
Argon2id; PHP debe disponer de soporte para este algoritmo.

La conexión usa por defecto la base de datos `gestion_incidencias` en
`localhost:3306`, con el usuario `root` y contraseña vacía. Se puede configurar
mediante las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y
`DB_PASSWORD`. PHP necesita la extensión PDO MySQL y sesiones habilitadas.

Al entrar se guarda el identificador, nombre y rol del usuario en la sesión.
La casilla «Recordar mi correo» solo guarda el correo en una cookie durante
30 días; no mantiene la sesión iniciada. Si se desmarca al entrar, se elimina
la cookie. Como todavía no hay una página de destino, el acceso correcto se
confirma en el propio formulario.

Desde esa página se puede cerrar sesión con un formulario POST protegido por
un token CSRF. Al salir se destruye la sesión, pero se conserva la cookie del
correo recordado, si se eligió esa opción.

## Incidencias

Las páginas de gestión están en `public/`: `incidencias.php` muestra el listado
y publica los mismos registros como JSON en el elemento
`<script id="datos-incidencias" type="application/json">`, y `nueva.php`
permite registrar una incidencia asociada a un equipo existente. El listado y
el alta requieren una sesión iniciada.

`incidencia.php?id=...` muestra el detalle y el historial. Solo el profesorado
puede cambiar el estado, y cada cambio con comentario actualiza la incidencia
y añade su entrada al historial dentro de una transacción. Solo el profesorado
puede borrar incidencias; el borrado se envía por POST con protección CSRF.
La base de datos debe tener equipos cargados antes de poder registrar
incidencias.

## Estilos

Las páginas comparten `public/css/estilos.css` para la base visual y
`public/css/formularios.css` para los controles. Para aplicar los mismos estilos
en páginas nuevas, enlaza ambos archivos desde el directorio `public`. La clase
`acceso` limita el ancho de las páginas de acceso y registro. Los colores se
adaptan a la preferencia clara u oscura del sistema.
Las vistas de incidencias también enlazan `public/css/incidencias.css` para las
tablas, el historial y su distribución adaptable.
Se conserva Roboto y la paleta neutra existente para mantener continuidad con
el acceso y el registro; la tabla usa desplazamiento local en pantallas
pequeñas para evitar que desborde el resto de la página.
El panel agrupa los conteos de estado y urgencia para que el profesorado pueda
detectar rápidamente incidencias pendientes.

El panel lee el JSON del elemento `datos-incidencias`. Sus objetos usan las
claves `id`, `equipo`, `categoria`, `prioridad`, `descripcion`, `estado`,
`autor` y `creada_en` (fecha ISO 8601). Si JavaScript no está disponible, queda
visible el listado generado por PHP. Con JavaScript, el panel permite buscar,
filtrar por estado y prioridad y ordenar por equipo, prioridad o fecha; guarda
esas preferencias en el navegador. El formulario de alta cuenta caracteres y
conserva un borrador local hasta que la incidencia se registra correctamente.

La fuente Roboto se sirve localmente desde `public/fonts`; su licencia SIL Open
Font License está en `public/fonts/OFL.txt`.

Los mensajes de las acciones se muestran como avisos en la esquina inferior
derecha. Cada aviso se puede cerrar y permanece visible hasta hacerlo. Sin
JavaScript, los mensajes siguen apareciendo dentro de la página.
Los avisos tienen una entrada breve y los controles indican la interacción
con transiciones; estas animaciones se desactivan si el sistema solicita
movimiento reducido.