<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('reportes');

$tipos = [
    'resumen'    => 'Resumen de ventas',
    'producto'   => 'Ventas por producto',
    'turno'      => 'Ventas por dia y turno',
    'hora'       => 'Ventas por hora',
    'cliente'    => 'Ventas por cliente',
    'genero'     => 'Ventas por genero',
    'empleado'   => 'Ventas por empleado',
    'inventario' => 'Inventario actual',
    'compras'    => 'Compras de ingredientes',
    'gastos'     => 'Gastos',
    'devoluciones' => 'Devoluciones de pedidos',
    'bitacora'   => 'Bitacora de movimientos',
];

function fechaValida($f) {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m)) return '';
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $f : '';
}
function filtroFecha($col, $desde, $hasta) {
    $w = '';
    if ($desde) $w .= " AND DATE($col) >= '$desde'";
    if ($hasta) $w .= " AND DATE($col) <= '$hasta'";
    return $w;
}
function q($conexion, $sql) {
    return $conexion->query($sql)->fetch_assoc();
}
function fmt($v, $tipo) {
    switch ($tipo) {
        case 'dinero': case 'decimal': return number_format((float)$v, 2);
        case 'entero': return number_format((float)$v, 0);
        case 'pct': return number_format((float)$v, 1) . '%';
        default: return h($v);
    }
}
function pivotSql($productos) {
    $s = '';
    foreach ($productos as $p) {
        $id = intval($p['id_producto']);
        $s .= ", SUM(CASE WHEN d.id_producto = $id THEN d.cantidad ELSE 0 END) AS p$id";
    }
    return $s;
}
function colsProductos($productos) {
    $c = [];
    foreach ($productos as $p) $c[] = ['p' . $p['id_producto'], $p['nombre'], 'entero', true];
    return $c;
}

$r = $_GET['r'] ?? 'resumen';
if (!isset($tipos[$r])) $r = 'resumen';
$desde = fechaValida($_GET['desde'] ?? '');
$hasta = fechaValida($_GET['hasta'] ?? '');
if ($desde && $hasta && $desde > $hasta) { $tmp = $desde; $desde = $hasta; $hasta = $tmp; }

$productos = $conexion->query("SELECT id_producto, nombre FROM Producto ORDER BY codigo")->fetch_all(MYSQLI_ASSOC);
$pv = pivotSql($productos);
$cp = colsProductos($productos);
$costoSub = "SELECT r.id_producto, SUM(r.cantidad_necesaria * i.precio_unitario) AS costo FROM Producto_Receta r INNER JOIN Ingrediente i ON r.id_ingrediente = i.id_ingrediente GROUP BY r.id_producto";
$wTodos = filtroFecha('pg.fecha', $desde, $hasta);                  // todos los pedidos del periodo
$wPed = $wTodos . " AND pg.estado <> 'Devuelto'";                 // sin los pedidos devueltos por completo

$cols = []; $rows = []; $nota = ''; $sinTotal = false;

switch ($r) {
    case 'resumen':
        $a = q($conexion, "SELECT COUNT(*) AS pedidos, COUNT(DISTINCT pg.id_cliente) AS clientes, MIN(pg.fecha) AS primera, MAX(pg.fecha) AS ultima FROM Pedido_General pg WHERE 1=1 $wPed");
        $b = q($conexion, "SELECT IFNULL(SUM(pg.total),0) AS brutas FROM Pedido_General pg WHERE 1=1 $wTodos");
        $nd = q($conexion, "SELECT COUNT(*) AS n FROM devolucion dv INNER JOIN Pedido_General pg ON pg.id_pedido = dv.id_pedido WHERE 1=1 $wTodos");
        $u = q($conexion, "SELECT IFNULL(SUM(d.cantidad),0) AS unidades, IFNULL(SUM(d.subtotal),0) AS ventas FROM v_detalle_neto d INNER JOIN Pedido_General pg ON pg.id_pedido = d.id_pedido WHERE 1=1 $wPed");
        $cc = q($conexion, "SELECT IFNULL(SUM(d.cantidad * c.costo),0) AS costo FROM v_detalle_neto d INNER JOIN Pedido_General pg ON pg.id_pedido = d.id_pedido INNER JOIN ($costoSub) c ON c.id_producto = d.id_producto WHERE 1=1 $wPed");
        $ventas = (float)$u['ventas']; $brutas = (float)$b['brutas']; $devuelto = $brutas - $ventas;
        $costo = (float)$cc['costo']; $gan = $ventas - $costo;
        $rows = [
            ['concepto' => 'Pedidos realizados (sin los devueltos por completo)', 'valor' => fmt($a['pedidos'], 'entero')],
            ['concepto' => 'Clientes que compraron', 'valor' => fmt($a['clientes'], 'entero')],
            ['concepto' => 'Unidades vendidas netas (todos los productos)', 'valor' => fmt($u['unidades'], 'entero')],
            ['concepto' => 'Ventas brutas (Bs)', 'valor' => fmt($brutas, 'dinero')],
            ['concepto' => 'Devoluciones registradas (cantidad)', 'valor' => fmt($nd['n'], 'entero')],
            ['concepto' => 'Monto devuelto (Bs)', 'valor' => fmt($devuelto, 'dinero')],
            ['concepto' => 'Ventas netas (Bs)', 'valor' => fmt($ventas, 'dinero')],
            ['concepto' => 'Ticket promedio por pedido (Bs)', 'valor' => fmt($a['pedidos'] > 0 ? $ventas / $a['pedidos'] : 0, 'dinero')],
            ['concepto' => 'Costo de lo vendido (Bs)', 'valor' => fmt($costo, 'dinero')],
            ['concepto' => 'Ganancia bruta (Bs)', 'valor' => fmt($gan, 'dinero')],
            ['concepto' => 'Margen bruto', 'valor' => fmt($ventas > 0 ? $gan / $ventas * 100 : 0, 'pct')],
            ['concepto' => 'Primera venta', 'valor' => h($a['primera'] ?? '-')],
            ['concepto' => 'Ultima venta', 'valor' => h($a['ultima'] ?? '-')],
        ];
        $cols = [['concepto', 'Concepto', 'raw'], ['valor', 'Valor', 'raw']];
        $sinTotal = true;
        $nota = 'Las ventas ya descuentan las devoluciones. Para la ganancia neta, la inversion, la perdida por devoluciones y el punto de equilibrio mira la pestaña Finanzas.';
        break;

    case 'producto':
        $res = $conexion->query("SELECT p.codigo, p.nombre, IFNULL(v.unidades,0) AS unidades, IFNULL(v.ventas,0) AS ventas, IFNULL(c.costo,0) AS costo_unitario
            FROM Producto p
            LEFT JOIN (SELECT d.id_producto, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS ventas FROM v_detalle_neto d INNER JOIN Pedido_General pg ON pg.id_pedido = d.id_pedido WHERE 1=1 $wPed GROUP BY d.id_producto) v ON v.id_producto = p.id_producto
            LEFT JOIN ($costoSub) c ON c.id_producto = p.id_producto
            ORDER BY p.codigo");
        while ($f = $res->fetch_assoc()) {
            $f['costo_total'] = $f['unidades'] * $f['costo_unitario'];
            $f['ganancia'] = $f['ventas'] - $f['costo_total'];
            $f['margen'] = $f['ventas'] > 0 ? $f['ganancia'] / $f['ventas'] * 100 : 0;
            $rows[] = $f;
        }
        $cols = [['codigo', 'Codigo', 'texto'], ['nombre', 'Producto', 'texto'], ['unidades', 'Unidades vendidas', 'entero', true], ['ventas', 'Ventas (Bs)', 'dinero', true], ['costo_unitario', 'Costo unitario (Bs)', 'dinero'], ['costo_total', 'Costo total (Bs)', 'dinero', true], ['ganancia', 'Ganancia bruta (Bs)', 'dinero', true], ['margen', 'Margen bruto', 'pct']];
        break;

    case 'turno':
        $res = $conexion->query("SELECT DATE(pg.fecha) AS dia, IF(HOUR(pg.fecha) < 14, 1, 2) AS turno_n, COUNT(DISTINCT pg.id_pedido) AS pedidos, MIN(TIME(pg.fecha)) AS primera, MAX(TIME(pg.fecha)) AS ultima $pv, SUM(d.subtotal) AS ventas
            FROM Pedido_General pg INNER JOIN v_detalle_neto d ON d.id_pedido = pg.id_pedido
            WHERE 1=1 $wPed GROUP BY DATE(pg.fecha), IF(HOUR(pg.fecha) < 14, 1, 2) ORDER BY dia, turno_n");
        while ($f = $res->fetch_assoc()) { $f['turno'] = ($f['turno_n'] == 1) ? 'Mañana (antes de las 14:00)' : 'Tarde (desde las 14:00)'; $rows[] = $f; }
        $cols = array_merge([['dia', 'Fecha', 'texto'], ['turno', 'Turno', 'texto'], ['pedidos', 'Pedidos', 'entero', true], ['primera', 'Primera venta', 'texto'], ['ultima', 'Ultima venta', 'texto']], $cp, [['ventas', 'Ventas (Bs)', 'dinero', true]]);
        break;

    case 'hora':
        $res = $conexion->query("SELECT HOUR(pg.fecha) AS h, COUNT(DISTINCT pg.id_pedido) AS pedidos $pv, SUM(d.subtotal) AS ventas
            FROM Pedido_General pg INNER JOIN v_detalle_neto d ON d.id_pedido = pg.id_pedido
            WHERE 1=1 $wPed GROUP BY HOUR(pg.fecha) ORDER BY h");
        while ($f = $res->fetch_assoc()) { $f['hora'] = sprintf('%02d:00 a %02d:59', $f['h'], $f['h']); $rows[] = $f; }
        $cols = array_merge([['hora', 'Hora', 'texto'], ['pedidos', 'Pedidos', 'entero', true]], $cp, [['ventas', 'Ventas (Bs)', 'dinero', true]]);
        $nota = 'Si el periodo tiene varios dias, las horas se suman.';
        break;

    case 'cliente':
        $res = $conexion->query("SELECT c.codigo_cliente, c.nombre, c.genero, c.direccion, COUNT(DISTINCT pg.id_pedido) AS pedidos $pv, SUM(d.subtotal) AS total
            FROM Cliente c INNER JOIN Pedido_General pg ON pg.id_cliente = c.id_cliente INNER JOIN v_detalle_neto d ON d.id_pedido = pg.id_pedido
            WHERE 1=1 $wPed GROUP BY c.id_cliente, c.codigo_cliente, c.nombre, c.genero, c.direccion ORDER BY total DESC, c.codigo_cliente");
        while ($f = $res->fetch_assoc()) $rows[] = $f;
        $cols = array_merge([['codigo_cliente', 'Codigo', 'texto'], ['nombre', 'Cliente', 'texto'], ['genero', 'Genero', 'texto'], ['direccion', 'Curso / Tipo', 'texto'], ['pedidos', 'Pedidos', 'entero', true]], $cp, [['total', 'Total comprado (Bs)', 'dinero', true]]);
        $nota = 'Ordenado de quien mas compro a quien menos.';
        break;

    case 'genero':
        $res = $conexion->query("SELECT c.genero, COUNT(DISTINCT c.id_cliente) AS clientes, COUNT(DISTINCT pg.id_pedido) AS pedidos $pv, SUM(d.subtotal) AS ventas
            FROM Cliente c INNER JOIN Pedido_General pg ON pg.id_cliente = c.id_cliente INNER JOIN v_detalle_neto d ON d.id_pedido = pg.id_pedido
            WHERE 1=1 $wPed GROUP BY c.genero ORDER BY c.genero");
        while ($f = $res->fetch_assoc()) $rows[] = $f;
        $cols = array_merge([['genero', 'Genero', 'texto'], ['clientes', 'Clientes', 'entero', true], ['pedidos', 'Pedidos', 'entero', true]], $cp, [['ventas', 'Ventas (Bs)', 'dinero', true]]);
        break;

    case 'empleado':
        $res = $conexion->query("SELECT e.codigo, e.nombre, e.cargo, COUNT(DISTINCT pg.id_pedido) AS pedidos $pv, IFNULL(SUM(d.subtotal),0) AS ventas
            FROM Empleado e
            LEFT JOIN Pedido_General pg ON pg.id_empleado = e.id_empleado $wPed
            LEFT JOIN v_detalle_neto d ON d.id_pedido = pg.id_pedido
            GROUP BY e.id_empleado, e.codigo, e.nombre, e.cargo ORDER BY e.codigo");
        while ($f = $res->fetch_assoc()) $rows[] = $f;
        $cols = array_merge([['codigo', 'Codigo', 'texto'], ['nombre', 'Empleado', 'texto'], ['cargo', 'Cargo', 'texto'], ['pedidos', 'Pedidos atendidos', 'entero', true]], $cp, [['ventas', 'Ventas (Bs)', 'dinero', true]]);
        break;

    case 'inventario':
        $res = $conexion->query("SELECT i.codigo, i.nombre, i.cantidad_actual, i.unidad_medida, i.precio_unitario, IFNULL(c.comprado,0) AS comprado
            FROM Ingrediente i
            LEFT JOIN (SELECT id_ingrediente, SUM(cantidad) AS comprado FROM Abastecimiento GROUP BY id_ingrediente) c ON c.id_ingrediente = i.id_ingrediente
            ORDER BY i.codigo");
        while ($f = $res->fetch_assoc()) {
            $f['valor'] = $f['cantidad_actual'] * $f['precio_unitario'];
            $f['estado'] = ($f['cantidad_actual'] <= 0) ? 'Sin stock' : 'Disponible';
            $rows[] = $f;
        }
        $cols = [['codigo', 'Codigo', 'texto'], ['nombre', 'Ingrediente', 'texto'], ['comprado', 'Total comprado', 'decimal'], ['cantidad_actual', 'Cantidad actual', 'decimal'], ['unidad_medida', 'Unidad', 'texto'], ['precio_unitario', 'Precio unitario (Bs)', 'dinero'], ['valor', 'Valor en stock (Bs)', 'dinero', true], ['estado', 'Estado', 'texto']];
        $nota = 'Este reporte muestra el stock de hoy y no usa el filtro de fechas.';
        break;

    case 'compras':
        $wc = filtroFecha('a.fecha', $desde, $hasta);
        $res = $conexion->query("SELECT a.codigo, a.fecha, CONCAT(i.codigo, ' - ', i.nombre) AS ingrediente, CONCAT(pr.codigo, ' - ', pr.nombre) AS proveedor, a.cantidad, i.unidad_medida, a.precio_total
            FROM Abastecimiento a INNER JOIN Ingrediente i ON i.id_ingrediente = a.id_ingrediente INNER JOIN Proveedor pr ON pr.id_proveedor = a.id_proveedor
            WHERE 1=1 $wc ORDER BY a.fecha, a.id_abastecimiento");
        while ($f = $res->fetch_assoc()) { $f['unitario'] = $f['cantidad'] > 0 ? $f['precio_total'] / $f['cantidad'] : 0; $rows[] = $f; }
        $cols = [['codigo', 'Codigo', 'texto'], ['fecha', 'Fecha', 'texto'], ['ingrediente', 'Ingrediente', 'texto'], ['proveedor', 'Proveedor', 'texto'], ['cantidad', 'Cantidad', 'decimal'], ['unidad_medida', 'Unidad', 'texto'], ['unitario', 'Precio unitario (Bs)', 'dinero'], ['precio_total', 'Total pagado (Bs)', 'dinero', true]];
        break;

    case 'gastos':
        $wg = filtroFecha('g.fecha', $desde, $hasta);
        $res = $conexion->query("SELECT g.codigo, g.fecha, g.concepto, g.monto FROM Gasto g WHERE 1=1 $wg ORDER BY g.fecha, g.id_gasto");
        while ($f = $res->fetch_assoc()) $rows[] = $f;
        $cols = [['codigo', 'Codigo', 'texto'], ['fecha', 'Fecha', 'texto'], ['concepto', 'Concepto', 'texto'], ['monto', 'Monto (Bs)', 'dinero', true]];
        break;

    case 'devoluciones':
        $wd = filtroFecha('dv.fecha', $desde, $hasta);
        $res = $conexion->query("SELECT dv.codigo, dv.fecha, pg.codigo AS pedido, CONCAT(c.codigo_cliente, ' - ', c.nombre) AS cliente,
                (SELECT GROUP_CONCAT(CONCAT(CAST(dd.cantidad AS UNSIGNED), ' x ', p.nombre) SEPARATOR ', ') FROM detalle_devolucion dd INNER JOIN Producto p ON p.id_producto = dd.id_producto WHERE dd.id_devolucion = dv.id_devolucion) AS productos,
                dv.motivo, IF(dv.reingresa = 1, 'Si', 'No') AS reingreso, u.nombre AS registrado_por, dv.monto
            FROM devolucion dv
            INNER JOIN Pedido_General pg ON pg.id_pedido = dv.id_pedido
            INNER JOIN Cliente c ON c.id_cliente = pg.id_cliente
            INNER JOIN usuario u ON u.id_usuario = dv.id_usuario
            WHERE 1=1 $wd ORDER BY dv.fecha, dv.id_devolucion");
        while ($f = $res->fetch_assoc()) $rows[] = $f;
        $cols = [['codigo', 'Codigo', 'texto'], ['fecha', 'Fecha y hora', 'texto'], ['pedido', 'Pedido', 'texto'], ['cliente', 'Cliente', 'texto'], ['productos', 'Productos devueltos', 'texto'], ['motivo', 'Motivo', 'texto'], ['reingreso', 'Reingreso al inventario', 'texto'], ['registrado_por', 'Registrado por', 'texto'], ['monto', 'Monto devuelto (Bs)', 'dinero', true]];
        $nota = 'Estas devoluciones ya estan descontadas de las ventas en los demas reportes y en Finanzas.';
        break;

    case 'bitacora':
        $wb = filtroFecha('b.fecha', $desde, $hasta);
        $res = $conexion->query("SELECT b.fecha, b.usuario, b.accion, b.modulo, b.detalle FROM bitacora b WHERE 1=1 $wb ORDER BY b.fecha DESC, b.id_bitacora DESC LIMIT 1000");
        while ($f = $res->fetch_assoc()) $rows[] = $f;
        $cols = [['fecha', 'Fecha y hora', 'texto'], ['usuario', 'Usuario', 'texto'], ['accion', 'Accion', 'texto'], ['modulo', 'Modulo', 'texto'], ['detalle', 'Detalle', 'texto']];
        $nota = 'Muestra los ultimos 1000 movimientos: quien ingreso, creo, edito, elimino o registro devoluciones.';
        break;
}

// Fila de TOTAL
$total = null;
if (!$sinTotal && count($rows) > 0) {
    $hay = false;
    foreach ($cols as $c) if (!empty($c[3])) $hay = true;
    if ($hay) {
        $total = [];
        foreach ($cols as $c) {
            $k = $c[0];
            if (!empty($c[3])) { $s = 0; foreach ($rows as $f) $s += (float)$f[$k]; $total[$k] = $s; }
            else $total[$k] = '';
        }
        $total[$cols[0][0]] = 'TOTAL';
        if ($r === 'producto') $total['margen'] = $total['ventas'] > 0 ? $total['ganancia'] / $total['ventas'] * 100 : 0;
    }
}

$periodo = ($desde || $hasta) ? 'Periodo: del ' . ($desde ?: 'inicio') . ' al ' . ($hasta ?: 'final') : 'Periodo: todas las fechas';
$numericos = ['entero', 'dinero', 'decimal', 'pct'];

// ---------- Descargar a Excel ----------
if (($_GET['export'] ?? '') === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $r . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
    echo '<h3>Hamburguesas RaySu - ' . h($tipos[$r]) . '</h3><p>' . h($periodo) . '</p>';
    echo '<table border="1"><tr>';
    foreach ($cols as $c) echo '<th>' . h($c[1]) . '</th>';
    echo '</tr>';
    $filasXls = $rows;
    if ($total) $filasXls[] = $total;
    foreach ($filasXls as $f) {
        echo '<tr>';
        foreach ($cols as $c) {
            $v = $f[$c[0]] ?? '';
            if (in_array($c[2], $numericos) && $v !== '' && $v !== 'TOTAL') echo '<td x:num="' . round((float)$v, 4) . '">' . fmt($v, $c[2]) . '</td>';
            elseif ($c[2] === 'raw') echo '<td>' . $v . '</td>';
            else echo '<td>' . h($v) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

$titulo = 'Reportes';
include 'menu.php';
$params = http_build_query(['r' => $r, 'desde' => $desde, 'hasta' => $hasta, 'export' => 'xls']);
?>

<div class="barra-superior">
    <h2>Reportes</h2>
</div>

<form class="filtros" method="GET" action="reportes.php">
    <div>
        <label>Reporte:</label>
        <select name="r" onchange="this.form.submit()">
            <?php foreach ($tipos as $clave => $nombre) { ?>
                <option value="<?php echo $clave; ?>" <?php echo $clave === $r ? 'selected' : ''; ?>><?php echo h($nombre); ?></option>
            <?php } ?>
        </select>
    </div>
    <div>
        <label>Desde:</label>
        <input type="date" name="desde" value="<?php echo h($desde); ?>">
    </div>
    <div>
        <label>Hasta:</label>
        <input type="date" name="hasta" value="<?php echo h($hasta); ?>">
    </div>
    <button type="submit">Ver reporte</button>
    <button type="button" onclick="window.print()">Imprimir</button>
    <a class="boton" href="reportes.php?<?php echo h($params); ?>">Descargar Excel</a>
</form>
<p class="pista">El filtro de fechas se aplica a la fecha de cada registro (ventas, compras o gastos). Dejalo vacio para ver todo.</p>

<div class="solo-impresion"><h1>Hamburguesas RaySu</h1></div>
<div class="encabezado-reporte">
    <h2><?php echo h($tipos[$r]); ?></h2>
    <p><?php echo h($periodo); ?></p>
</div>

<div class="seccion-tabla" style="margin-top: 0;">
<table>
    <thead>
        <tr><?php foreach ($cols as $c) { ?><th><?php echo h($c[1]); ?></th><?php } ?></tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0) { ?>
            <tr><td colspan="<?php echo count($cols); ?>">No hay registros en este periodo.</td></tr>
        <?php } ?>
        <?php foreach ($rows as $f) { ?>
        <tr>
            <?php foreach ($cols as $c) { ?>
                <td><?php echo ($c[2] === 'raw') ? $f[$c[0]] : fmt($f[$c[0]] ?? '', $c[2]); ?></td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
    <?php if ($total) { ?>
    <tfoot>
        <tr>
            <?php foreach ($cols as $c) { $v = $total[$c[0]]; ?>
                <td><?php echo ($v === '' || $v === 'TOTAL') ? h($v) : fmt($v, $c[2]); ?></td>
            <?php } ?>
        </tr>
    </tfoot>
    <?php } ?>
</table>
</div>
<?php if ($nota) { ?><p class="pista"><?php echo h($nota); ?></p><?php } ?>

<?php include 'pie.php'; ?>