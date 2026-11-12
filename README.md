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

## Estilos

Las páginas comparten `public/css/estilos.css` para la base visual y
`public/css/formularios.css` para los controles. Para aplicar los mismos estilos
en páginas nuevas, enlaza ambos archivos desde el directorio `public`. La clase
`acceso` limita el ancho de las páginas de acceso y registro. Los colores se
adaptan a la preferencia clara u oscura del sistema.

La fuente Roboto se sirve localmente desde `public/fonts`; su licencia SIL Open
Font License está en `public/fonts/OFL.txt`.

Los mensajes de las acciones se muestran como avisos en la esquina inferior
derecha. Cada aviso se puede cerrar y permanece visible hasta hacerlo. Sin
JavaScript, los mensajes siguen apareciendo dentro de la página.
Los avisos tienen una entrada breve y los controles indican la interacción
con transiciones; estas animaciones se desactivan si el sistema solicita
movimiento reducido.