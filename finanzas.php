<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('finanzas');

function valor($conexion, $sql) {
    return (float)$conexion->query($sql)->fetch_assoc()['t'];
}

// Ventas brutas = todo lo que se cobro. Ventas netas = brutas menos lo devuelto (vista v_detalle_neto)
$ventasBrutas = valor($conexion, "SELECT IFNULL(SUM(total),0) AS t FROM Pedido_General");
$ventas = valor($conexion, "SELECT IFNULL(SUM(subtotal),0) AS t FROM v_detalle_neto");
$devuelto = $ventasBrutas - $ventas;
$unidadesVendidas = valor($conexion, "SELECT IFNULL(SUM(cantidad),0) AS t FROM v_detalle_neto");

// Costo de lo vendido = cantidad vendida x costo de la receta de cada producto
$costoVendido = valor($conexion, "
    SELECT IFNULL(SUM(d.cantidad * c.costo),0) AS t
    FROM v_detalle_neto d
    INNER JOIN (
        SELECT r.id_producto, SUM(r.cantidad_necesaria * i.precio_unitario) AS costo
        FROM Producto_Receta r
        INNER JOIN Ingrediente i ON r.id_ingrediente = i.id_ingrediente
        GROUP BY r.id_producto
    ) c ON c.id_producto = d.id_producto
");

$compras = valor($conexion, "SELECT IFNULL(SUM(precio_total),0) AS t FROM Abastecimiento");
$gastos = valor($conexion, "SELECT IFNULL(SUM(monto),0) AS t FROM Gasto");
// Perdida por devoluciones: lo devuelto cuyos ingredientes NO volvieron al inventario (se gasto la materia prima)
$perdidaDev = valor($conexion, "
    SELECT IFNULL(SUM(dd.cantidad * c.costo),0) AS t
    FROM detalle_devolucion dd
    INNER JOIN devolucion dv ON dv.id_devolucion = dd.id_devolucion
    INNER JOIN (
        SELECT r.id_producto, SUM(r.cantidad_necesaria * i.precio_unitario) AS costo
        FROM Producto_Receta r
        INNER JOIN Ingrediente i ON r.id_ingrediente = i.id_ingrediente
        GROUP BY r.id_producto
    ) c ON c.id_producto = dd.id_producto
    WHERE dv.reingresa = 0
");
$inversion = $compras + $gastos;

// 1. MARGEN BRUTO
$margenBs = $ventas - $costoVendido;
$margenPct = $ventas > 0 ? $margenBs / $ventas : 0;

// 2. PUNTO DE EQUILIBRIO (en Bs) = costos fijos / margen bruto
$puntoEq = $margenPct > 0 ? $gastos / $margenPct : 0;
$precioPromedio = $unidadesVendidas > 0 ? $ventas / $unidadesVendidas : 0;
$puntoEqUnidades = $precioPromedio > 0 ? ceil($puntoEq / $precioPromedio) : 0;

// 3. RECUPERACION DE LA INVERSION
$utilidad = $margenBs - $gastos - $perdidaDev;
$roi = $inversion > 0 ? $utilidad / $inversion : 0;

$acum = 0; $primera = null; $horaRecup = null; $horaEq = null;
$res = $conexion->query("SELECT pg.fecha, IFNULL(SUM(d.subtotal),0) AS total FROM Pedido_General pg LEFT JOIN v_detalle_neto d ON d.id_pedido = pg.id_pedido GROUP BY pg.id_pedido, pg.fecha ORDER BY pg.fecha ASC");
while ($f = $res->fetch_assoc()) {
    if ($primera === null) $primera = $f['fecha'];
    $acum += $f['total'];
    if ($horaEq === null && $puntoEq > 0 && $acum >= $puntoEq) $horaEq = $f['fecha'];
    if ($horaRecup === null && $inversion > 0 && $acum >= $inversion) $horaRecup = $f['fecha'];
}
$minRecup = $horaRecup ? round((strtotime($horaRecup) - strtotime($primera)) / 60) : null;
$minEq = $horaEq ? round((strtotime($horaEq) - strtotime($primera)) / 60) : null;

$productosMargen = $conexion->query("
    SELECT p.nombre, p.precio, IFNULL(SUM(r.cantidad_necesaria * i.precio_unitario),0) AS costo
    FROM Producto p
    LEFT JOIN Producto_Receta r ON r.id_producto = p.id_producto
    LEFT JOIN Ingrediente i ON i.id_ingrediente = r.id_ingrediente
    GROUP BY p.id_producto, p.nombre, p.precio
    ORDER BY p.nombre
");

$titulo = 'Finanzas';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Finanzas del negocio</h2>
</div>

<div class="bloque-fin">
    <h2>1. Margen bruto</h2>
    <div class="formula">Margen bruto = (Precio de venta − Costo) ÷ Precio de venta</div>
    <div class="tarjetas-stats">
        <div class="stat"><div class="numero"><?php echo number_format($ventas, 2); ?></div><div class="etiqueta">Ventas netas (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($costoVendido, 2); ?></div><div class="etiqueta">Costo de lo vendido (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($margenBs, 2); ?></div><div class="etiqueta">Ganancia bruta (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($margenPct * 100, 1); ?>%</div><div class="etiqueta">Margen bruto</div></div>
    </div>
    <p>Ventas brutas: <b><?php echo number_format($ventasBrutas, 2); ?> Bs</b> − Devoluciones: <b><?php echo number_format($devuelto, 2); ?> Bs</b> = Ventas netas: <b><?php echo number_format($ventas, 2); ?> Bs</b>.</p>

    <table>
        <thead><tr><th>Producto</th><th>Precio</th><th>Costo</th><th>Ganancia por unidad</th><th>Margen bruto</th></tr></thead>
        <tbody>
        <?php while ($p = $productosMargen->fetch_assoc()) {
            $gan = $p['precio'] - $p['costo'];
            $mp = $p['precio'] > 0 ? $gan / $p['precio'] * 100 : 0; ?>
            <tr>
                <td><?php echo h($p['nombre']); ?></td>
                <td><?php echo number_format($p['precio'], 2); ?></td>
                <td><?php echo number_format($p['costo'], 2); ?></td>
                <td><?php echo number_format($gan, 2); ?></td>
                <td class="<?php echo $gan >= 0 ? 'estado-ok' : 'estado-mal'; ?>"><?php echo number_format($mp, 1); ?>%</td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<div class="bloque-fin">
    <h2>2. Punto de equilibrio</h2>
    <div class="formula">Punto de equilibrio = Costos fijos (gastos) ÷ Margen bruto</div>
    <div class="tarjetas-stats">
        <div class="stat"><div class="numero"><?php echo number_format($gastos, 2); ?></div><div class="etiqueta">Costos fijos (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($margenPct * 100, 1); ?>%</div><div class="etiqueta">Margen bruto</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($puntoEq, 2); ?></div><div class="etiqueta">Debes vender (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo $puntoEqUnidades; ?></div><div class="etiqueta">Equivale a (productos)</div></div>
    </div>
    <?php if ($puntoEq > 0 && $ventas >= $puntoEq) { ?>
        <p class="estado-ok">Punto de equilibrio superado: vendiste <?php echo number_format($ventas, 2); ?> Bs y necesitabas <?php echo number_format($puntoEq, 2); ?> Bs.
        <?php if ($horaEq) { ?>Se alcanzó a las <?php echo date('H:i', strtotime($horaEq)); ?> (<?php echo $minEq; ?> min después de la primera venta).<?php } ?></p>
    <?php } else { ?>
        <p class="estado-mal">Todavía no llegas al punto de equilibrio: te faltan <?php echo number_format(max($puntoEq - $ventas, 0), 2); ?> Bs en ventas.</p>
    <?php } ?>
</div>

<div class="bloque-fin">
    <h2>3. Recuperación de la inversión</h2>
    <div class="formula">Inversión = Compras de ingredientes + Gastos &nbsp;|&nbsp; Utilidad = Ventas netas − Costo de lo vendido − Gastos − Pérdida por devoluciones &nbsp;|&nbsp; Rendimiento = Utilidad ÷ Inversión</div>
    <div class="tarjetas-stats">
        <div class="stat"><div class="numero"><?php echo number_format($inversion, 2); ?></div><div class="etiqueta">Inversión total (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($ventas, 2); ?></div><div class="etiqueta">Dinero recuperado (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($utilidad, 2); ?></div><div class="etiqueta">Utilidad neta (Bs)</div></div>
        <div class="stat"><div class="numero"><?php echo number_format($roi * 100, 1); ?>%</div><div class="etiqueta">Rendimiento</div></div>
    </div>
    <p>Compras de ingredientes: <b><?php echo number_format($compras, 2); ?> Bs</b> + Gastos: <b><?php echo number_format($gastos, 2); ?> Bs</b>.</p>
    <p>Pérdida por devoluciones sin reingreso al inventario: <b><?php echo number_format($perdidaDev, 2); ?> Bs</b> (ya descontada de la utilidad).</p>
    <?php if ($horaRecup) { ?>
        <p class="estado-ok">Inversión recuperada a las <?php echo date('H:i', strtotime($horaRecup)); ?>, <?php echo $minRecup; ?> minutos después de la primera venta. Todo lo vendido después es ganancia.</p>
    <?php } else { ?>
        <p class="estado-mal">Todavía no se recupera la inversión: faltan <?php echo number_format(max($inversion - $ventas, 0), 2); ?> Bs.</p>
    <?php } ?>
</div>

<?php include 'pie.php'; ?>