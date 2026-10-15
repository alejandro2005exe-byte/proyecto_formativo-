<?php
// Conexion a la base de datos (XAMPP: usuario root, sin contraseña)
$servidor = "localhost";
$usuario = "root";
$clave = "";
$base = "SistemaPedidos";

mysqli_report(MYSQLI_REPORT_OFF);
$conexion = @new mysqli($servidor, $usuario, $clave, $base);

if ($conexion->connect_error) {
    die("No se pudo conectar a la base de datos. Revisa que MySQL este encendido en XAMPP y que hayas importado sistemapedidos.sql y migracion_login_devoluciones.sql. Detalle: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");