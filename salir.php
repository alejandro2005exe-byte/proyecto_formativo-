<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
if (estaLogueado()) {
    try {
        bitacora($conexion, 'LOGOUT', 'sesion', 'Cierre de sesion');
    } catch (mysqli_sql_exception $e) {
    }
}
cerrarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Hasta luego - Hamburguesas RaySu</title>
    <link rel="stylesheet" href="estilo.css">
    <link rel="stylesheet" href="modal.css">
    <?php include 'pwa_head.php'; ?>
</head>
<body>
<div class="contenedor" style="max-width: 700px; text-align: center; margin-top: 80px; position: relative; z-index: 1;">
    <h2>¡Hasta luego!</h2>
    <p style="font-size: 18px; font-weight: 700;">Cerraste tu sesion en el sistema de Hamburguesas RaySu.<br>Gracias por vender con nosotros.</p>
    <a class="boton" href="login.php">Volver a entrar</a>
    <button type="button" onclick="window.close()">Cerrar pestaña</button>
</div>
<?php lluviaYTelon(false); ?>
<script src="sonido-looney.js" defer></script>
<script src="pwa.js" defer></script>
</body>
</html>
