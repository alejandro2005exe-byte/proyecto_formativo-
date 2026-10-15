<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';

if (estaLogueado()) {
    header('Location: index.php');
    exit;
}

$error = '';
$aviso = '';
if (($_GET['e'] ?? '') === 'expirada') {
    $aviso = 'Tu sesion se cerro por inactividad. Ingresa de nuevo.';
}
$usuarioEscrito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $usuarioEscrito = trim((string)($_POST['usuario'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');

    $st = $conexion->prepare("SELECT id_usuario, usuario, clave_hash, nombre, rol, id_empleado, activo,
            (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado,
            IFNULL(TIMESTAMPDIFF(MINUTE, NOW(), bloqueado_hasta), 0) + 1 AS min_restantes
        FROM usuario WHERE usuario = ? LIMIT 1");
    $st->bind_param('s', $usuarioEscrito);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();

    if ($u && (int)$u['bloqueado'] === 1) {
        $error = 'Cuenta bloqueada por intentos fallidos. Intenta de nuevo en ' . max(1, (int)$u['min_restantes']) . ' minuto(s).';
    } elseif ($u && (int)$u['activo'] === 1 && password_verify($clave, $u['clave_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id_usuario' => (int)$u['id_usuario'],
            'usuario' => $u['usuario'],
            'nombre' => $u['nombre'],
            'rol' => $u['rol'],
            'id_empleado' => $u['id_empleado'] !== null ? (int)$u['id_empleado'] : null,
        ];
        $_SESSION['ultima_actividad'] = time();
        unset($_SESSION['sesion_expirada']);

        $conexion->begin_transaction();
        try {
            $up = $conexion->prepare("UPDATE usuario SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id_usuario = ?");
            $up->bind_param('i', $u['id_usuario']);
            $up->execute();
            $up->close();
            bitacora($conexion, 'LOGIN', 'sesion', 'Inicio de sesion');
            $conexion->commit();
        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
        }
        header('Location: index.php');
        exit;
    } else {
        if ($u) {
            // 5 intentos fallidos = bloqueo de 5 minutos
            $up = $conexion->prepare("UPDATE usuario SET
                    bloqueado_hasta = IF(intentos_fallidos + 1 >= 5, DATE_ADD(NOW(), INTERVAL 5 MINUTE), bloqueado_hasta),
                    intentos_fallidos = IF(intentos_fallidos + 1 >= 5, 0, intentos_fallidos + 1)
                WHERE id_usuario = ?");
            $up->bind_param('i', $u['id_usuario']);
            $up->execute();
            $up->close();
        }
        $error = 'Usuario o contrasena incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Ingresar - Hamburguesas RaySu</title>
    <link rel="stylesheet" href="estilo.css">
    <link rel="stylesheet" href="modal.css">
    <?php include 'pwa_head.php'; ?>
</head>
<body class="pagina-login">

<div class="contenedor login-caja">
    <div class="espacio-logo"><img src="logo.jpg" alt="Logo de la empresa"></div>
    <h2>Hamburguesas RaySu</h2>
    <p class="subtitulo">Sistema de Gestion de Pedidos e Inventario</p>

    <?php if ($aviso !== '') { ?><div class="mensaje"><?php echo h($aviso); ?></div><?php } ?>
    <?php if ($error !== '') { ?><div class="mensaje"><?php echo h($error); ?></div><?php } ?>

    <form method="POST" action="login.php">
        <?php echo campoCsrf(); ?>
        <label for="usuario">Usuario:</label>
        <input type="text" id="usuario" name="usuario" value="<?php echo h($usuarioEscrito); ?>" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">

        <label for="clave">Contrasena:</label>
        <input type="password" id="clave" name="clave" required autocomplete="current-password">

        <button type="submit">Entrar</button>
    </form>
</div>

<script src="sonido-looney.js" defer></script>
<script src="pwa.js" defer></script>
</body>
</html>
