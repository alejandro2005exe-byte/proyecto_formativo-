<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('pedidos');

$mensaje = "";
$abrir = false;
$abrirDev = false;
$verAcciones = puede('pedidos', 'editar') || puede('pedidos', 'eliminar') || puede('pedidos', 'devolver');
$motivosDev = ['Producto en mal estado', 'Error en el pedido', 'Cliente no satisfecho', 'Pedido cancelado', 'Otro'];

// Devuelve al inventario los ingredientes que un pedido habia consumido
function devolverIngredientes($conexion, $id_pedido) {
    $detalles = $conexion->query("SELECT id_producto, cantidad FROM Detalle_Pedido WHERE id_pedido = $id_pedido");
    while ($d = $detalles->fetch_assoc()) {
        reponerIngredientes($conexion, $d['id_producto'], $d['cantidad']);
    }
}

// Suma al inventario los ingredientes de $cantidad unidades de un producto
function reponerIngredientes($conexion, $id_producto, $cantidad) {
    $receta = $conexion->query("SELECT id_ingrediente, cantidad_necesaria FROM Producto_Receta WHERE id_producto = " . intval($id_producto));
    while ($r = $receta->fetch_assoc()) {
        $devolver = round($r['cantidad_necesaria'] * $cantidad, 2);
        $conexion->query("UPDATE Ingrediente SET cantidad_actual = cantidad_actual + $devolver WHERE id_ingrediente = " . $r['id_ingrediente']);
    }
}

// Datos de un pedido y de cada linea (cuanto se vendio y cuanto ya se devolvio)
function cargarPedidoDevolucion($conexion, $id_pedido) {
    $id_pedido = intval($id_pedido);
    $ped = $conexion->query("SELECT pg.id_pedido, pg.codigo, pg.fecha, pg.estado, pg.total, CONCAT(c.codigo_cliente, ' - ', c.nombre) AS cliente
        FROM Pedido_General pg INNER JOIN Cliente c ON c.id_cliente = pg.id_cliente WHERE pg.id_pedido = $id_pedido")->fetch_assoc();
    if (!$ped) return null;
    $lineas = $conexion->query("SELECT d.id_producto, p.nombre, d.cantidad, d.precio_unitario, IFNULL(v.cantidad_devuelta, 0) AS devuelta
        FROM Detalle_Pedido d
        INNER JOIN Producto p ON p.id_producto = d.id_producto
        LEFT JOIN v_devuelto v ON v.id_pedido = d.id_pedido AND v.id_producto = d.id_producto
        WHERE d.id_pedido = $id_pedido ORDER BY p.codigo")->fetch_all(MYSQLI_ASSOC);
    return ['pedido' => $ped, 'lineas' => $lineas];
}

// ELIMINAR pedido (solo Supervisor): devuelve ingredientes, borra detalle y pedido, todo en una transaccion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('pedidos', 'eliminar');
    $id_pedido = intval($_POST['eliminar']);
    $conexion->begin_transaction();
    try {
        $ped = $conexion->query("SELECT codigo, estado, total FROM Pedido_General WHERE id_pedido = $id_pedido FOR UPDATE")->fetch_assoc();
        if (!$ped) throw new Exception("no existe");
        if ($ped['estado'] !== 'Completado') throw new Exception("devuelto");
        devolverIngredientes($conexion, $id_pedido);
        $conexion->query("DELETE FROM Detalle_Pedido WHERE id_pedido = $id_pedido");
        $conexion->query("DELETE FROM Pedido_General WHERE id_pedido = $id_pedido");
        bitacora($conexion, 'ELIMINAR', 'pedidos', $ped['codigo'] . ' total=' . $ped['total']);
        $conexion->commit();
    } catch (Exception $e) {
        $conexion->rollback();
        ir('pedidos.php', 'error=' . ($e->getMessage() === 'devuelto' ? 'devuelto' : 'eliminar'));
    }
    ir('pedidos.php');
}

// Datos del formulario (vacio, edicion, o lo que se escribio si hubo error)
$form = ['id_pedido' => 0, 'codigo' => '', 'id_cliente' => 0, 'id_empleado' => 0];
$lineasForm = [];
$idDevForm = 0;
$prevDev = [];
$prevMotivo = '';
$prevDetalle = '';
$prevReingresa = 0;

if (isset($_GET['editar'])) {
    exigirPermiso('pedidos', 'editar');
    $id_pedido = intval($_GET['editar']);
    $ped = $conexion->query("SELECT * FROM Pedido_General WHERE id_pedido = $id_pedido")->fetch_assoc();
    if ($ped) {
        if ($ped['estado'] !== 'Completado') ir('pedidos.php', 'error=devuelto');
        $form = ['id_pedido' => $ped['id_pedido'], 'codigo' => $ped['codigo'], 'id_cliente' => $ped['id_cliente'], 'id_empleado' => $ped['id_empleado']];
        $det = $conexion->query("SELECT id_producto, cantidad FROM Detalle_Pedido WHERE id_pedido = $id_pedido");
        while ($d = $det->fetch_assoc()) {
            $lineasForm[] = [intval($d['id_producto']), intval($d['cantidad'])];
        }
        $abrir = true;
    }
}

if (isset($_GET['devolver'])) {
    exigirPermiso('pedidos', 'devolver');
    $idDevForm = intval($_GET['devolver']);
    $abrirDev = true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';

    // ---------- DEVOLUCION (solo Supervisor) ----------
    if ($accion === 'devolver') {
        exigirPermiso('pedidos', 'devolver');
        $idDevForm = intval($_POST['id_pedido'] ?? 0);
        $cants = $_POST['dev_cantidad'] ?? [];
        $prevDev = is_array($cants) ? $cants : [];
        $motivoSel = trim((string)($_POST['motivo'] ?? ''));
        $motivoTxt = trim((string)($_POST['motivo_detalle'] ?? ''));
        $reingresa = isset($_POST['reingresa']) ? 1 : 0;
        $prevMotivo = $motivoSel;
        $prevDetalle = $motivoTxt;
        $prevReingresa = $reingresa;
        $abrirDev = true;

        $conexion->begin_transaction();
        try {
            $ped = $conexion->query("SELECT id_pedido, codigo, estado FROM Pedido_General WHERE id_pedido = $idDevForm FOR UPDATE")->fetch_assoc();
            if (!$ped) throw new Exception("El pedido no existe.");
            if ($ped['estado'] === 'Devuelto') throw new Exception("Este pedido ya fue devuelto por completo.");
            if (!in_array($motivoSel, $motivosDev, true)) throw new Exception("Selecciona el motivo de la devolucion.");
            if ($motivoSel === 'Otro' && $motivoTxt === '') throw new Exception("Escribe el motivo de la devolucion.");
            $motivo = mb_substr($motivoSel . (($motivoTxt !== '') ? ': ' . $motivoTxt : ''), 0, 200);
            $motivoSql = $conexion->real_escape_string($motivo);

            $lineas = $conexion->query("SELECT d.id_producto, p.nombre, d.cantidad, d.precio_unitario, IFNULL(v.cantidad_devuelta, 0) AS devuelta
                FROM Detalle_Pedido d
                INNER JOIN Producto p ON p.id_producto = d.id_producto
                LEFT JOIN v_devuelto v ON v.id_pedido = d.id_pedido AND v.id_producto = d.id_producto
                WHERE d.id_pedido = $idDevForm")->fetch_all(MYSQLI_ASSOC);

            $items = [];
            $monto = 0;
            foreach ($lineas as $l) {
                $q = intval($prevDev[$l['id_producto']] ?? 0);
                $max = intval(round($l['cantidad'] - $l['devuelta']));
                if ($q < 0 || $q > $max) throw new Exception("La cantidad a devolver de " . $l['nombre'] . " no puede ser mayor a " . $max . ".");
                if ($q > 0) {
                    $items[] = [intval($l['id_producto']), $q, $l['precio_unitario']];
                    $monto += $q * $l['precio_unitario'];
                }
            }
            if (empty($items)) throw new Exception("Indica al menos una unidad a devolver.");
            $monto = round($monto, 2);
            $id_usuario = intval(usuarioActual()['id_usuario']);

            $conexion->query("INSERT INTO devolucion (codigo, id_pedido, id_usuario, fecha, motivo, reingresa, monto) VALUES ('" . codigoTemporal() . "', $idDevForm, $id_usuario, NOW(), '$motivoSql', $reingresa, $monto)");
            $id_dev = $conexion->insert_id;
            $codDev = asignarCodigo($conexion, 'devolucion', 'codigo', 'id_devolucion', 'DEV', $id_dev);
            foreach ($items as $it) {
                $sub = round($it[1] * $it[2], 2);
                $conexion->query("INSERT INTO detalle_devolucion (id_devolucion, id_producto, cantidad, precio_unitario, subtotal) VALUES ($id_dev, $it[0], $it[1], $it[2], $sub)");
            }
            if ($reingresa) {
                foreach ($items as $it) reponerIngredientes($conexion, $it[0], $it[1]);
            }

            // Si ya no queda nada por entregar, el pedido pasa a "Devuelto"; si queda algo, a "Devuelto parcial"
            $resta = $conexion->query("SELECT IFNULL(SUM(d.cantidad - IFNULL(v.cantidad_devuelta, 0)), 0) AS n
                FROM Detalle_Pedido d
                LEFT JOIN v_devuelto v ON v.id_pedido = d.id_pedido AND v.id_producto = d.id_producto
                WHERE d.id_pedido = $idDevForm")->fetch_assoc();
            $estado = ((float)$resta['n'] <= 0) ? 'Devuelto' : 'Devuelto parcial';
            $conexion->query("UPDATE Pedido_General SET estado = '$estado' WHERE id_pedido = $idDevForm");

            bitacora($conexion, 'DEVOLVER', 'pedidos', $ped['codigo'] . ' / ' . $codDev . ' / ' . number_format($monto, 2, '.', '') . ' Bs / ' . $motivo . ($reingresa ? ' / con reingreso al inventario' : ' / sin reingreso'));
            $conexion->commit();
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = ($e instanceof mysqli_sql_exception) ? "Error al registrar la devolucion." : $e->getMessage();
        }
        if ($mensaje === "") ir('pedidos.php');

    // ---------- NUEVO PEDIDO / EDICION ----------
    } else {
        $id_pedido = intval($_POST['id_pedido']);
        exigirPermiso('pedidos', $id_pedido > 0 ? 'editar' : 'crear');
        $id_cliente = intval($_POST['id_cliente']);
        // El Empleado siempre queda como quien atiende; solo el Supervisor puede elegir otro
        $id_empleado = esSupervisor() ? intval($_POST['id_empleado'] ?? 0) : intval(usuarioActual()['id_empleado'] ?? 0);
        $form = ['id_pedido' => $id_pedido, 'codigo' => '', 'id_cliente' => $id_cliente, 'id_empleado' => $id_empleado];

        // Junta las lineas (si repiten producto, se suman)
        $pedido = [];
        $prods = $_POST['id_producto'] ?? [];
        $cants = $_POST['cantidad'] ?? [];
        foreach ($prods as $k => $p) {
            $p = intval($p);
            $c = intval($cants[$k] ?? 0);
            if ($p > 0 && $c > 0) $pedido[$p] = ($pedido[$p] ?? 0) + $c;
        }
        foreach ($pedido as $p => $c) $lineasForm[] = [$p, $c];
        $abrir = true;

        if (empty($pedido)) {
            $mensaje = "Agrega al menos un producto con cantidad mayor a cero.";
        } else {
            $esNuevo = ($id_pedido == 0);
            $conexion->begin_transaction();
            try {
                $codigoPedido = '';
                if (!$esNuevo) {
                    // Un pedido con devoluciones ya no se puede editar
                    $est = $conexion->query("SELECT codigo, estado FROM Pedido_General WHERE id_pedido = $id_pedido FOR UPDATE")->fetch_assoc();
                    if (!$est) throw new Exception("El pedido no existe.");
                    if ($est['estado'] !== 'Completado') throw new Exception("Este pedido tiene devoluciones registradas y ya no se puede editar.");
                    $codigoPedido = $est['codigo'];
                    // Primero se devuelve lo que consumia (todo dentro de la transaccion)
                    devolverIngredientes($conexion, $id_pedido);
                }

                $total = 0;
                $detalle = [];
                $necesario = [];
                foreach ($pedido as $id_producto => $cant) {
                    $prod = $conexion->query("SELECT precio FROM Producto WHERE id_producto = $id_producto")->fetch_assoc();
                    if (!$prod) throw new Exception("Producto no encontrado.");
                    $subtotal = $cant * $prod['precio'];
                    $total += $subtotal;
                    $detalle[] = [$id_producto, $cant, $prod['precio'], $subtotal];
                    $receta = $conexion->query("SELECT id_ingrediente, cantidad_necesaria FROM Producto_Receta WHERE id_producto = $id_producto");
                    while ($r = $receta->fetch_assoc()) {
                        $necesario[$r['id_ingrediente']] = ($necesario[$r['id_ingrediente']] ?? 0) + $r['cantidad_necesaria'] * $cant;
                    }
                }

                // Revisar stock de todos los ingredientes juntos
                $faltante = "";
                foreach ($necesario as $id_ing => $cant_nec) {
                    $cant_nec = round($cant_nec, 2);
                    $necesario[$id_ing] = $cant_nec;
                    $ing = $conexion->query("SELECT nombre, cantidad_actual, unidad_medida FROM Ingrediente WHERE id_ingrediente = $id_ing")->fetch_assoc();
                    if ($cant_nec > $ing['cantidad_actual']) {
                        $faltante .= $ing['nombre'] . " (disponible: " . $ing['cantidad_actual'] . " " . $ing['unidad_medida'] . ", se necesita: " . $cant_nec . "). ";
                    }
                }
                if ($faltante != "") throw new Exception("No hay suficiente stock de: " . $faltante);

                if (!$esNuevo) {
                    $conexion->query("UPDATE Pedido_General SET id_cliente=$id_cliente, id_empleado=$id_empleado, total=$total WHERE id_pedido=$id_pedido");
                    $conexion->query("DELETE FROM Detalle_Pedido WHERE id_pedido = $id_pedido");
                } else {
                    $conexion->query("INSERT INTO Pedido_General (codigo, id_cliente, id_empleado, fecha, total, estado) VALUES ('" . codigoTemporal() . "', $id_cliente, $id_empleado, NOW(), $total, 'Completado')");
                    $id_pedido = $conexion->insert_id;
                    $codigoPedido = asignarCodigo($conexion, 'Pedido_General', 'codigo', 'id_pedido', 'PED', $id_pedido);
                }
                foreach ($detalle as $d) {
                    $conexion->query("INSERT INTO Detalle_Pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal) VALUES ($id_pedido, $d[0], $d[1], $d[2], $d[3])");
                }
                foreach ($necesario as $id_ing => $cant_nec) {
                    $conexion->query("UPDATE Ingrediente SET cantidad_actual = cantidad_actual - $cant_nec WHERE id_ingrediente = $id_ing");
                }
                bitacora($conexion, $esNuevo ? 'CREAR' : 'EDITAR', 'pedidos', $codigoPedido . ' total=' . number_format($total, 2, '.', ''));
                $conexion->commit();
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = ($e instanceof mysqli_sql_exception) ? "Error al registrar el pedido." : $e->getMessage();
            }
            if ($mensaje === "") ir('pedidos.php');
        }
    }
}

// Datos del pedido que se va a devolver
$datosDev = null;
if ($abrirDev) {
    $datosDev = cargarPedidoDevolucion($conexion, $idDevForm);
    if (!$datosDev || $datosDev['pedido']['estado'] === 'Devuelto') ir('pedidos.php', 'error=devuelto');
}

$esEdicion = $form['id_pedido'] > 0;
if (empty($lineasForm)) $lineasForm[] = [0, ''];

$clientes = $conexion->query("SELECT id_cliente, codigo_cliente, nombre FROM Cliente ORDER BY codigo_cliente")->fetch_all(MYSQLI_ASSOC);
$empleados = $conexion->query("SELECT id_empleado, codigo, nombre FROM Empleado ORDER BY codigo")->fetch_all(MYSQLI_ASSOC);
$productos = $conexion->query("SELECT id_producto, codigo, nombre, precio FROM Producto ORDER BY codigo")->fetch_all(MYSQLI_ASSOC);

$pedidos = $conexion->query("
    SELECT pg.id_pedido, pg.codigo, CONCAT(c.codigo_cliente, ' - ', c.nombre) AS cliente, CONCAT(e.codigo, ' - ', e.nombre) AS empleado, pg.fecha, pg.total, pg.estado,
           IFNULL((SELECT SUM(dv.monto) FROM devolucion dv WHERE dv.id_pedido = pg.id_pedido), 0) AS devuelto,
           GROUP_CONCAT(CONCAT(CAST(d.cantidad AS UNSIGNED), ' x ', p.nombre) SEPARATOR ', ') AS detalle
    FROM Pedido_General pg
    INNER JOIN Cliente c ON pg.id_cliente = c.id_cliente
    INNER JOIN Empleado e ON pg.id_empleado = e.id_empleado
    LEFT JOIN Detalle_Pedido d ON d.id_pedido = pg.id_pedido
    LEFT JOIN Producto p ON p.id_producto = d.id_producto
    GROUP BY pg.id_pedido, pg.codigo, c.codigo_cliente, c.nombre, e.codigo, e.nombre, pg.fecha, pg.total, pg.estado
    ORDER BY pg.fecha DESC
");

function lineaProducto($productos, $sel, $cant) { ?>
    <div class="linea-producto">
        <div>
            <label>Producto:</label>
            <select name="id_producto[]" required>
                <option value="">Selecciona un producto</option>
                <?php foreach ($productos as $p) { ?>
                    <option value="<?php echo $p['id_producto']; ?>" <?php echo ($sel == $p['id_producto']) ? 'selected' : ''; ?>>
                        <?php echo h($p['codigo'] . ' - ' . $p['nombre'] . ' (' . $p['precio'] . ' Bs)'); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label>Cantidad:</label>
            <input type="number" name="cantidad[]" min="1" step="1" value="<?php echo h($cant); ?>" required>
        </div>
        <button type="button" class="quitar-linea" onclick="quitarLinea(this)" title="Quitar">X</button>
    </div>
<?php }

$titulo = 'Pedidos';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Registro de Pedidos</h2>
    <?php if (puede('pedidos', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Nuevo pedido</button>
    <?php } ?>
</div>

<?php if (puede('pedidos', 'crear') || puede('pedidos', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $abrir ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $esEdicion ? 'Editar pedido ' . ($form['codigo'] != '' ? $form['codigo'] : '#' . $form['id_pedido']) : 'Nuevo pedido'; ?></h2>
    <form method="POST" action="pedidos.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_pedido" value="<?php echo $form['id_pedido']; ?>">
<?php if ($form['codigo'] != '') { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($form['codigo']); ?>" disabled>
        <?php } ?>

        <label>Cliente:</label>
        <select name="id_cliente" required>
            <option value="">Selecciona un cliente</option>
            <?php foreach ($clientes as $c) { ?>
                <option value="<?php echo $c['id_cliente']; ?>" <?php echo ($form['id_cliente'] == $c['id_cliente']) ? 'selected' : ''; ?>>
                    <?php echo h($c['codigo_cliente'] . ' - ' . $c['nombre']); ?>
                </option>
            <?php } ?>
        </select>

        <?php if (esSupervisor()) { ?>
        <label>Empleado que atiende:</label>
        <select name="id_empleado" required>
            <option value="">Selecciona un empleado</option>
            <?php foreach ($empleados as $e) { ?>
                <option value="<?php echo $e['id_empleado']; ?>" <?php echo ($form['id_empleado'] == $e['id_empleado']) ? 'selected' : ''; ?>>
                    <?php echo h($e['codigo'] . ' - ' . $e['nombre']); ?>
                </option>
            <?php } ?>
        </select>
        <?php } else { ?>
        <label>Empleado que atiende:</label>
        <input type="text" value="<?php echo h(usuarioActual()['nombre']); ?>" disabled>
        <?php } ?>

        <div id="lineas">
            <?php foreach ($lineasForm as $l) lineaProducto($productos, $l[0], $l[1]); ?>
        </div>
        <button type="button" class="boton-secundario" onclick="agregarLinea()">+ Otro producto</button>

        <div class="botones-modal">
            <button type="submit"><?php echo $esEdicion ? 'Actualizar pedido' : 'Registrar pedido'; ?></button>
            <button type="button" class="boton-cancelar" onclick="cerrarModal()">Cancelar</button>
        </div>
    </form>
</div>
</dialog>

<template id="plantilla-linea"><?php lineaProducto($productos, 0, ''); ?></template>
<?php } ?>

<?php if ($datosDev) { $pd = $datosDev['pedido']; ?>
<dialog id="modalDev" class="modal" data-abrir="1">
<div class="modal-contenido">
    <h2>Devolucion del pedido <?php echo h($pd['codigo']); ?></h2>
    <div class="resumen-devolucion">
        Cliente: <?php echo h($pd['cliente']); ?><br>
        Fecha: <?php echo h($pd['fecha']); ?> &nbsp;|&nbsp; Total del pedido: <?php echo number_format($pd['total'], 2); ?> Bs
    </div>
    <form method="POST" action="pedidos.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="accion" value="devolver">
        <input type="hidden" name="id_pedido" value="<?php echo intval($pd['id_pedido']); ?>">

        <label>Cuantas unidades se devuelven:</label>
        <?php foreach ($datosDev['lineas'] as $l) {
            $restante = intval(round($l['cantidad'] - $l['devuelta']));
            $valor = $prevDev[$l['id_producto']] ?? 0; ?>
            <div class="linea-devolucion">
                <div class="nombre-prod">
                    <?php echo h($l['nombre']); ?>
                    <small>Vendidas: <?php echo intval($l['cantidad']); ?> | Ya devueltas: <?php echo intval($l['devuelta']); ?> | Se pueden devolver: <?php echo $restante; ?></small>
                </div>
                <input type="number" name="dev_cantidad[<?php echo intval($l['id_producto']); ?>]" min="0" max="<?php echo $restante; ?>" step="1" value="<?php echo h($valor); ?>" <?php echo $restante <= 0 ? 'disabled' : ''; ?>>
            </div>
        <?php } ?>

        <label>Motivo:</label>
        <select name="motivo" required>
            <option value="">Selecciona el motivo</option>
            <?php foreach ($motivosDev as $mo) { ?>
                <option value="<?php echo h($mo); ?>" <?php echo ($prevMotivo === $mo) ? 'selected' : ''; ?>><?php echo h($mo); ?></option>
            <?php } ?>
        </select>

        <label>Detalle del motivo (obligatorio si eliges "Otro"):</label>
        <input type="text" name="motivo_detalle" maxlength="150" value="<?php echo h($prevDetalle); ?>">

        <p style="margin: 14px 0 0;">
            <label style="display: inline;"><input type="checkbox" name="reingresa" value="1" <?php echo $prevReingresa ? 'checked' : ''; ?>>Devolver los ingredientes al inventario</label>
        </p>
        <p class="aviso-reingreso">Marca esto solo si los ingredientes se pueden volver a usar. Si no la marcas, el costo se registra como perdida en Finanzas.</p>

        <div class="botones-modal">
            <button type="submit">Registrar devolucion</button>
            <button type="button" class="boton-cancelar" onclick="cerrarModalDev()">Cancelar</button>
        </div>
    </form>
</div>
</dialog>
<?php } ?>

<div class="seccion-tabla">
<table>
    <thead>
        <tr><th>Codigo</th><th>Cliente</th><th>Empleado</th><th>Fecha</th><th>Detalle</th><th>Total (Bs)</th><th>Devuelto (Bs)</th><th>Estado</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $pedidos->fetch_assoc()) {
            $claseEstado = $fila['estado'] === 'Completado' ? 'estado-completado' : ($fila['estado'] === 'Devuelto' ? 'estado-devuelto' : 'estado-parcial'); ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['cliente']); ?></td>
            <td><?php echo h($fila['empleado']); ?></td>
            <td><?php echo h($fila['fecha']); ?></td>
            <td class="detalle-pedido"><?php echo h($fila['detalle']); ?></td>
            <td><?php echo number_format($fila['total'], 2); ?></td>
            <td><?php echo number_format($fila['devuelto'], 2); ?></td>
            <td><span class="estado-pedido <?php echo $claseEstado; ?>"><?php echo h($fila['estado']); ?></span></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if ($fila['estado'] === 'Completado' && puede('pedidos', 'editar')) { ?><a href="pedidos.php?editar=<?php echo $fila['id_pedido']; ?>">Editar</a><?php } ?>
                <?php if ($fila['estado'] !== 'Devuelto' && puede('pedidos', 'devolver')) { ?><a href="pedidos.php?devolver=<?php echo $fila['id_pedido']; ?>">Devolver</a><?php } ?>
                <?php if ($fila['estado'] === 'Completado' && puede('pedidos', 'eliminar')) { echo botonEliminar('pedidos.php', $fila['id_pedido'], 'Deseas eliminar este pedido? Se devolveran los ingredientes al inventario.'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<script>
function agregarLinea() {
    var t = document.getElementById('plantilla-linea');
    document.getElementById('lineas').appendChild(t.content.cloneNode(true));
}
function quitarLinea(boton) {
    var lineas = document.querySelectorAll('#lineas .linea-producto');
    if (lineas.length > 1) boton.closest('.linea-producto').remove();
}
var modalDev = document.getElementById('modalDev');
function cerrarModalDev() { if (modalDev) modalDev.close(); }
if (modalDev) {
    modalDev.addEventListener('close', function () { window.location.href = window.location.pathname; });
    modalDev.showModal();
}
</script>

<?php include 'pie.php'; ?>
