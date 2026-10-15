<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('inicio');
$usuarioActual = usuarioActual();

$totalPedidos = $conexion->query("SELECT COUNT(*) AS total FROM Pedido_General WHERE estado <> 'Devuelto'")->fetch_assoc()['total'];
$totalVendido = $conexion->query("SELECT IFNULL(SUM(subtotal),0) AS total FROM v_detalle_neto")->fetch_assoc()['total'];
$totalClientes = $conexion->query("SELECT COUNT(*) AS total FROM Cliente")->fetch_assoc()['total'];

$ingredienteBajo = $conexion->query("SELECT nombre, cantidad_actual, unidad_medida FROM Ingrediente ORDER BY cantidad_actual ASC LIMIT 1")->fetch_assoc();

$productoMasVendido = $conexion->query("
    SELECT p.nombre, SUM(d.cantidad) AS total_vendido
    FROM v_detalle_neto d
    INNER JOIN Producto p ON d.id_producto = p.id_producto
    GROUP BY p.id_producto, p.nombre
    ORDER BY total_vendido DESC
    LIMIT 1
")->fetch_assoc();

$titulo = 'Inicio';
$mostrarBanner = true;
include 'menu.php';
?>

<h2>Panel de Control</h2>
<p class="bienvenida">Bienvenido/a, <?php echo h($usuarioActual['nombre']); ?> (<?php echo h($usuarioActual['rol']); ?>)</p>

<?php if (esSupervisor()) { ?>
<div class="tarjetas-stats">
    <div class="stat">
        <div class="numero"><?php echo $totalPedidos; ?></div>
        <div class="etiqueta">Pedidos Realizados</div>
    </div>
    <div class="stat">
        <div class="numero"><?php echo number_format($totalVendido, 2); ?></div>
        <div class="etiqueta">Total Vendido (Bs)</div>
    </div>
    <div class="stat">
        <div class="numero"><?php echo $totalClientes; ?></div>
        <div class="etiqueta">Clientes Registrados</div>
    </div>
    <div class="stat">
        <div class="numero"><?php echo $ingredienteBajo ? h($ingredienteBajo['cantidad_actual'] . ' ' . $ingredienteBajo['unidad_medida']) : '-'; ?></div>
        <div class="etiqueta"><?php echo $ingredienteBajo ? 'Stock mas bajo: ' . h($ingredienteBajo['nombre']) : 'Sin ingredientes'; ?></div>
    </div>
    <div class="stat">
        <div class="numero"><?php echo $productoMasVendido ? intval($productoMasVendido['total_vendido']) : 0; ?></div>
        <div class="etiqueta"><?php echo $productoMasVendido ? 'Mas vendido: ' . h($productoMasVendido['nombre']) : 'Sin ventas aun'; ?></div>
    </div>
</div>
<?php } ?>

<h2>Accesos Rapidos</h2>
<div class="tarjetas">
    <?php if (puede('clientes', 'ver')) { ?>
    <a href="clientes.php" class="tarjeta"><div class="titulo-tarjeta">Clientes</div><div class="descripcion-tarjeta">Registrar y administrar clientes</div></a>
    <?php } ?>
    <?php if (puede('empleados', 'ver')) { ?>
    <a href="empleados.php" class="tarjeta"><div class="titulo-tarjeta">Empleados</div><div class="descripcion-tarjeta">Personal que atiende pedidos</div></a>
    <?php } ?>
    <?php if (puede('proveedores', 'ver')) { ?>
    <a href="proveedores.php" class="tarjeta"><div class="titulo-tarjeta">Proveedores</div><div class="descripcion-tarjeta">Quienes suministran ingredientes</div></a>
    <?php } ?>
    <?php if (puede('ingredientes', 'ver')) { ?>
    <a href="ingredientes.php" class="tarjeta"><div class="titulo-tarjeta">Ingredientes</div><div class="descripcion-tarjeta">Inventario de materia prima</div></a>
    <?php } ?>
    <?php if (puede('productos', 'ver')) { ?>
    <a href="productos.php" class="tarjeta"><div class="titulo-tarjeta">Productos</div><div class="descripcion-tarjeta">Hamburguesas y limonada a la venta</div></a>
    <?php } ?>
    <?php if (puede('recetas', 'ver')) { ?>
    <a href="recetas.php" class="tarjeta"><div class="titulo-tarjeta">Recetas</div><div class="descripcion-tarjeta">Ingredientes que lleva cada producto</div></a>
    <?php } ?>
    <?php if (puede('abastecimiento', 'ver')) { ?>
    <a href="abastecimiento.php" class="tarjeta"><div class="titulo-tarjeta">Abastecimiento</div><div class="descripcion-tarjeta">Registrar compras de ingredientes</div></a>
    <?php } ?>
    <?php if (puede('gastos', 'ver')) { ?>
    <a href="gastos.php" class="tarjeta"><div class="titulo-tarjeta">Gastos</div><div class="descripcion-tarjeta">Servicios, fletes y otros costos fijos</div></a>
    <?php } ?>
    <?php if (puede('pedidos', 'ver')) { ?>
    <a href="pedidos.php" class="tarjeta"><div class="titulo-tarjeta">Pedidos</div><div class="descripcion-tarjeta">Registrar y ver ventas</div></a>
    <?php } ?>
    <?php if (puede('finanzas', 'ver')) { ?>
    <a href="finanzas.php" class="tarjeta"><div class="titulo-tarjeta">Finanzas</div><div class="descripcion-tarjeta">Margen bruto, equilibrio e inversion</div></a>
    <?php } ?>
    <?php if (puede('reportes', 'ver')) { ?>
    <a href="reportes.php" class="tarjeta"><div class="titulo-tarjeta">Reportes</div><div class="descripcion-tarjeta">Ventas, inventario y compras. Imprimir y Excel</div></a>
    <?php } ?>
</div>

<?php include 'pie.php'; ?>