<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('gastos');
$verAcciones = puede('gastos', 'editar') || puede('gastos', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('gastos', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Gasto', 'id_gasto', $id, 'gastos.php', 'gastos');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('gastos', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Gasto WHERE id_gasto = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('gastos', intval($_POST['id_gasto'] ?? 0) > 0 ? 'editar' : 'crear');
    $concepto = $conexion->real_escape_string($_POST['concepto']);
    $monto = floatval($_POST['monto']);
    $id = intval($_POST['id_gasto']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Gasto SET concepto='$concepto', monto=$monto WHERE id_gasto=$id");
        } else {
            $conexion->query("INSERT INTO Gasto (codigo, concepto, monto, fecha) VALUES ('" . codigoTemporal() . "', '$concepto', $monto, CURDATE())");
            $codNuevo = asignarCodigo($conexion, 'Gasto', 'codigo', 'id_gasto', 'GAS', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'gastos', ($id > 0 ? "id=$id " : "$codNuevo ") . (string)($_POST['concepto'] ?? '') . ' monto=' . $monto);
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('gastos.php', 'error=guardar');
    }
    ir('gastos.php');
}

$lista = $conexion->query("SELECT * FROM Gasto ORDER BY fecha DESC, id_gasto DESC");
$totalGastos = $conexion->query("SELECT IFNULL(SUM(monto),0) AS t FROM Gasto")->fetch_assoc()['t'];
$titulo = 'Gastos';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Gastos (costos fijos)</h2>
    <?php if (puede('gastos', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir gasto</button>
    <?php } ?>
</div>
<p>Servilletas, gas, luz, fletes, ayuda, etc. Estos gastos se usan en la pestaña Finanzas para el punto de equilibrio.</p>

<?php if (puede('gastos', 'crear') || puede('gastos', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar gasto' : 'Nuevo gasto'; ?></h2>
    <form method="POST" action="gastos.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_gasto" value="<?php echo $editando['id_gasto'] ?? 0; ?>">

        <?php if (!empty($editando['codigo'])) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Concepto:</label>
        <input type="text" name="concepto" value="<?php echo h($editando['concepto'] ?? ''); ?>" required>

        <label>Monto (Bs):</label>
        <input type="number" step="0.01" min="0" name="monto" value="<?php echo h($editando['monto'] ?? ''); ?>" required>

        <label>Fecha (no se puede editar):</label>
        <input type="date" value="<?php echo h($editando['fecha'] ?? date('Y-m-d')); ?>" disabled>

        <div class="botones-modal">
            <button type="submit"><?php echo $editando ? 'Actualizar' : 'Guardar'; ?></button>
            <button type="button" class="boton-cancelar" onclick="cerrarModal()">Cancelar</button>
        </div>
    </form>
</div>
</dialog>
<?php } ?>

<div class="seccion-tabla">
<table>
    <thead>
        <tr><th>Codigo</th><th>Fecha</th><th>Concepto</th><th>Monto (Bs)</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['fecha']); ?></td>
            <td><?php echo h($fila['concepto']); ?></td>
            <td><?php echo number_format($fila['monto'], 2); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('gastos', 'editar')) { ?><a href="gastos.php?editar=<?php echo $fila['id_gasto']; ?>">Editar</a><?php } ?>
                <?php if (puede('gastos', 'eliminar')) { echo botonEliminar('gastos.php', $fila['id_gasto'], 'Deseas eliminar este gasto?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
    <tfoot>
        <tr><td colspan="3">TOTAL</td><td colspan="2"><?php echo number_format($totalGastos, 2); ?> Bs</td></tr>
    </tfoot>
</table>
</div>

<?php include 'pie.php'; ?>