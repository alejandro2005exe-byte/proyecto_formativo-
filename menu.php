<?php
require_once 'funciones.php';
$paginaActual = basename($_SERVER['PHP_SELF']);
$enlaces = [
    'index.php' => ['Inicio', 'inicio'],
    'clientes.php' => ['Clientes', 'clientes'],
    'empleados.php' => ['Empleados', 'empleados'],
    'proveedores.php' => ['Proveedores', 'proveedores'],
    'ingredientes.php' => ['Ingredientes', 'ingredientes'],
    'productos.php' => ['Productos', 'productos'],
    'recetas.php' => ['Recetas', 'recetas'],
    'abastecimiento.php' => ['Abastecimiento', 'abastecimiento'],
    'gastos.php' => ['Gastos', 'gastos'],
    'pedidos.php' => ['Pedidos', 'pedidos'],
    'finanzas.php' => ['Finanzas', 'finanzas'],
    'reportes.php' => ['Reportes', 'reportes'],
    'clave.php' => ['Mi cuenta', 'cuenta'],
];
$usuarioMenu = usuarioActual();
$mensajesError = [
    'eliminar' => 'No se puede eliminar: este registro se usa en otros datos (pedidos, recetas, abastecimiento...).',
    'guardar' => 'No se pudo guardar. Revisa los datos ingresados (el carnet no puede repetirse).',
    'stock' => 'No se puede eliminar esta compra: ese ingrediente ya se uso en pedidos y el stock quedaria en negativo.',
    'permiso' => 'No tienes permiso para realizar esa accion.',
    'devuelto' => 'Este pedido tiene devoluciones registradas y ya no se puede editar ni eliminar.',
    'carnet' => 'Carnet no valido. Usa solo numeros (1234567) o numeros con complemento (12345-A).',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo h($titulo); ?> - Hamburguesas RaySu</title>
    <link rel="stylesheet" href="estilo.css">
    <link rel="stylesheet" href="modal.css">
    <?php include 'pwa_head.php'; ?>
</head>
<body>

<?php if (!empty($mostrarBanner)) { ?>
<div class="cabecera">
    <div class="espacio-logo">
        <img src="logo.jpg" alt="Logo de la empresa">
    </div>
    <div class="nombre-empresa">
        <h1>HAMBURGUESAS RAYSU</h1>
        <p>Sistema de Gestion de Pedidos e Inventario</p>
    </div>
</div>
<?php } ?>

<nav id="menu-principal">
    <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="nav-enlaces" onclick="var n = document.getElementById('menu-principal'); var a = n.classList.toggle('abierto'); this.setAttribute('aria-expanded', a ? 'true' : 'false');">☰ Menú</button>
    <div class="nav-enlaces" id="nav-enlaces">
    <?php foreach ($enlaces as $archivo => $info) { if (!puede($info[1], 'ver')) continue; ?>
        <a href="<?php echo $archivo; ?>" class="<?php echo $paginaActual == $archivo ? 'activo' : ''; ?>"><?php echo $info[0]; ?></a>
    <?php } ?>
    <a href="salir.php" class="salir" onclick="return confirm('Deseas cerrar sesion?');">Salir</a>
    <?php if ($usuarioMenu) { ?>
        <span class="usuario-chip">👤 <?php echo h($usuarioMenu['nombre']); ?> · <?php echo h($usuarioMenu['rol']); ?></span>
    <?php } ?>
    <button type="button" id="btnInstalar" hidden>📲 Instalar app</button>
    </div>
</nav>

<div class="contenedor">
<?php if (isset($_GET['error']) && isset($mensajesError[$_GET['error']])) { ?>
    <div class="mensaje"><?php echo h($mensajesError[$_GET['error']]); ?></div>
<?php } ?>
<?php if (!empty($mensaje)) { ?>
    <div class="mensaje"><?php echo h($mensaje); ?></div>
<?php } ?>