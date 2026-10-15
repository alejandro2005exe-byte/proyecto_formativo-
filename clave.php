<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('cuenta');

$mensaje = "";
$u = usuarioActual();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $actual = (string)($_POST['actual'] ?? '');
    $nueva = (string)($_POST['nueva'] ?? '');
    $repite = (string)($_POST['repite'] ?? '');

    $st = $conexion->prepare("SELECT clave_hash FROM usuario WHERE id_usuario = ?");
    $st->bind_param('i', $u['id_usuario']);
    $st->execute();
    $fila = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$fila || !password_verify($actual, $fila['clave_hash'])) {
        $mensaje = "La contrasena actual no es correcta.";
    } elseif (strlen($nueva) < 8) {
        $mensaje = "La contrasena nueva debe tener al menos 8 caracteres.";
    } elseif ($nueva !== $repite) {
        $mensaje = "La contrasena nueva y su repeticion no coinciden.";
    } elseif ($nueva === $actual) {
        $mensaje = "La contrasena nueva debe ser distinta de la actual.";
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $conexion->begin_transaction();
        try {
            $up = $conexion->prepare("UPDATE usuario SET clave_hash = ? WHERE id_usuario = ?");
            $up->bind_param('si', $hash, $u['id_usuario']);
            $up->execute();
            $up->close();
            bitacora($conexion, 'EDITAR', 'sesion', 'Cambio de contrasena');
            $conexion->commit();
        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
            ir('clave.php', 'error=guardar');
        }
        ir('clave.php');
    }
}

$titulo = 'Mi cuenta';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Mi cuenta</h2>
</div>

<?php if (isset($_GET['ok'])) { ?><div class="mensaje">Contrasena actualizada correctamente.</div><?php } ?>

<p><b>Usuario:</b> <?php echo h($u['usuario']); ?> &nbsp;|&nbsp; <b>Nombre:</b> <?php echo h($u['nombre']); ?> &nbsp;|&nbsp; <b>Nivel:</b> <?php echo h($u['rol']); ?></p>

<form method="POST" action="clave.php" style="max-width: 520px;">
    <?php echo campoCsrf(); ?>
    <label for="actual">Contrasena actual:</label>
    <input type="password" id="actual" name="actual" required autocomplete="current-password">

    <label for="nueva">Contrasena nueva (minimo 8 caracteres):</label>
    <input type="password" id="nueva" name="nueva" required minlength="8" autocomplete="new-password">

    <label for="repite">Repite la contrasena nueva:</label>
    <input type="password" id="repite" name="repite" required minlength="8" autocomplete="new-password">

    <button type="submit">Cambiar contrasena</button>
</form>

<?php include 'pie.php'; ?>
