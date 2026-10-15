<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('abastecimiento');

$mensaje = "";
$abrir = false;

// ELIMINAR: se resta del ingrediente lo que esa compra habia sumado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('abastecimiento', 'eliminar');
    $id = intval($_POST['eliminar']);
    $registro = $conexion->query("SELECT * FROM Abastecimiento WHERE id_abastecimiento = $id")->fetch_assoc();
    if ($registro) {
        $ing = $conexion->query("SELECT cantidad_actual FROM Ingrediente WHERE id_ingrediente = " . $registro['id_ingrediente'])->fetch_assoc();
        if (round($ing['cantidad_actual'] - $registro['cantidad'], 2) < 0) ir('abastecimiento.php', 'error=stock');
        $conexion->begin_transaction();
        try {
            $conexion->query("UPDATE Ingrediente SET cantidad_actual = cantidad_actual - " . $registro['cantidad'] . " WHERE id_ingrediente = " . $registro['id_ingrediente']);
            $conexion->query("DELETE FROM Abastecimiento WHERE id_abastecimiento = $id");
            bitacora($conexion, 'ELIMINAR', 'abastecimiento', json_encode($registro, JSON_UNESCAPED_UNICODE));
            $conexion->commit();
        } catch (Exception $e) {
            $conexion->rollback();
            ir('abastecimiento.php', 'error=eliminar');
        }
    }
    ir('abastecimiento.php');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('abastecimiento', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Abastecimiento WHERE id_abastecimiento = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('abastecimiento', intval($_POST['id_abastecimiento'] ?? 0) > 0 ? 'editar' : 'crear');
    $id_ingrediente = intval($_POST['id_ingrediente']);
    $id_proveedor = intval($_POST['id_proveedor']);
    $cantidad = floatval($_POST['cantidad']);
    $precio_total = floatval($_POST['precio_total']);
    $id = intval($_POST['id_abastecimiento']);

    // Si algo falla, el formulario se vuelve a abrir con lo que se escribio
    $editando = ['id_abastecimiento' => $id, 'id_ingrediente' => $id_ingrediente, 'id_proveedor' => $id_proveedor, 'cantidad' => $cantidad, 'precio_total' => $precio_total];

    if ($cantidad <= 0) {
        $mensaje = "La cantidad debe ser mayor a cero.";
    } else {
        $codNuevo = '';
        $conexion->begin_transaction();
        try {
            if ($id > 0) {
                $anterior = $conexion->query("SELECT * FROM Abastecimiento WHERE id_abastecimiento = $id")->fetch_assoc();
                $conexion->query("UPDATE Ingrediente SET cantidad_actual = cantidad_actual - " . $anterior['cantidad'] . " WHERE id_ingrediente = " . $anterior['id_ingrediente']);
                $conexion->query("UPDATE Abastecimiento SET id_ingrediente=$id_ingrediente, id_proveedor=$id_proveedor, cantidad=$cantidad, precio_total=$precio_total WHERE id_abastecimiento=$id");
                $conexion->query("UPDATE Ingrediente SET cantidad_actual = cantidad_actual + $cantidad WHERE id_ingrediente = $id_ingrediente");
                $neg = $conexion->query("SELECT COUNT(*) AS n FROM Ingrediente WHERE cantidad_actual < 0 AND id_ingrediente IN (" . intval($anterior['id_ingrediente']) . ", $id_ingrediente)")->fetch_assoc();
                if ($neg['n'] > 0) throw new Exception("No se puede guardar: el stock de ese ingrediente quedaria en negativo, porque ya se uso en pedidos.");
            } else {
                $conexion->query("INSERT INTO Abastecimiento (codigo, id_ingrediente, id_proveedor, cantidad, fecha, precio_total) VALUES ('" . codigoTemporal() . "', $id_ingrediente, $id_proveedor, $cantidad, CURDATE(), $precio_total)");
                $codNuevo = asignarCodigo($conexion, 'Abastecimiento', 'codigo', 'id_abastecimiento', 'ABA', $conexion->insert_id);
                $conexion->query("UPDATE Ingrediente SET cantidad_actual = cantidad_actual + $cantidad WHERE id_ingrediente = $id_ingrediente");
            }
            bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'abastecimiento', ($id > 0 ? "id=$id" : $codNuevo) . " ing=$id_ingrediente prov=$id_proveedor cant=$cantidad total=$precio_total");
            $conexion->commit();
            ir('abastecimiento.php');
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = ($e instanceof mysqli_sql_exception) ? "Error al procesar el abastecimiento." : $e->getMessage();
        }
    }
}
$abrir = ($editando !== null);
$esEdicion = ($editando['id_abastecimiento'] ?? 0) > 0;

$ingredientes = $conexion->query("SELECT id_ingrediente, codigo, nombre, cantidad_actual, unidad_medida FROM Ingrediente ORDER BY codigo");
$proveedores = $conexion->query("SELECT id_proveedor, codigo, nombre FROM Proveedor ORDER BY codigo");

$lista = $conexion->query("
    SELECT a.id_abastecimiento, a.codigo, CONCAT(i.codigo, ' - ', i.nombre) AS ingrediente, CONCAT(pr.codigo, ' - ', pr.nombre) AS proveedor, a.cantidad, i.unidad_medida, a.fecha, a.precio_total
    FROM Abastecimiento a
    INNER JOIN Ingrediente i ON a.id_ingrediente = i.id_ingrediente
    INNER JOIN Proveedor pr ON a.id_proveedor = pr.id_proveedor
    ORDER BY a.fecha DESC, a.id_abastecimiento DESC
");
$titulo = 'Abastecimiento';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Abastecimiento de Ingredientes</h2>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Registrar compra</button>
</div>

<dialog id="modal" class="modal" data-abrir="<?php echo $abrir ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $esEdicion ? 'Editar compra' : 'Nueva compra'; ?></h2>
    <form method="POST" action="abastecimiento.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_abastecimiento" value="<?php echo $editando['id_abastecimiento'] ?? 0; ?>">

        <?php if (!empty($editando['codigo'])) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Ingrediente:</label>
        <select name="id_ingrediente" required>
            <option value="">Selecciona un ingrediente</option>
            <?php while ($i = $ingredientes->fetch_assoc()) { ?>
                <option value="<?php echo $i['id_ingrediente']; ?>" <?php echo (isset($editando['id_ingrediente']) && $editando['id_ingrediente'] == $i['id_ingrediente']) ? 'selected' : ''; ?>>
                    <?php echo h($i['codigo'] . ' - ' . $i['nombre'] . ' (Cantidad actual: ' . $i['cantidad_actual'] . ' ' . $i['unidad_medida'] . ')'); ?>
                </option>
            <?php } ?>
        </select>

        <label>Proveedor:</label>
        <select name="id_proveedor" required>
            <option value="">Selecciona un proveedor</option>
            <?php while ($p = $proveedores->fetch_assoc()) { ?>
                <option value="<?php echo $p['id_proveedor']; ?>" <?php echo (isset($editando['id_proveedor']) && $editando['id_proveedor'] == $p['id_proveedor']) ? 'selected' : ''; ?>>
                    <?php echo h($p['codigo'] . ' - ' . $p['nombre']); ?>
                </option>
            <?php } ?>
        </select>

        <label>Cantidad comprada:</label>
        <input type="number" step="0.01" min="0.01" name="cantidad" value="<?php echo h($editando['cantidad'] ?? ''); ?>" required>

        <label>Precio total pagado (Bs):</label>
        <input type="number" step="0.01" min="0" name="precio_total" value="<?php echo h($editando['precio_total'] ?? ''); ?>" required>

        <div class="botones-modal">
            <button type="submit"><?php echo $esEdicion ? 'Actualizar' : 'Registrar'; ?></button>
            <button type="button" class="boton-cancelar" onclick="cerrarModal()">Cancelar</button>
        </div>
    </form>
</div>
</dialog>

<div class="seccion-tabla">
<table>
    <thead>
        <tr><th>Codigo</th><th>Ingrediente</th><th>Proveedor</th><th>Cantidad</th><th>Fecha</th><th>Precio Total</th><th>Acciones</th></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['ingrediente']); ?></td>
            <td><?php echo h($fila['proveedor']); ?></td>
            <td><?php echo h($fila['cantidad'] . ' ' . $fila['unidad_medida']); ?></td>
            <td><?php echo h($fila['fecha']); ?></td>
            <td><?php echo number_format($fila['precio_total'], 2); ?></td>
            <td class="acciones">
                <a href="abastecimiento.php?editar=<?php echo $fila['id_abastecimiento']; ?>">Editar</a>
                <?php echo botonEliminar('abastecimiento.php', $fila['id_abastecimiento'], 'Deseas eliminar esta compra? Se restara el stock que sumo.'); ?>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>