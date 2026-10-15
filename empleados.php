<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('empleados');
$verAcciones = puede('empleados', 'editar') || puede('empleados', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('empleados', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Empleado', 'id_empleado', $id, 'empleados.php', 'empleados');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('empleados', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Empleado WHERE id_empleado = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('empleados', intval($_POST['id_empleado'] ?? 0) > 0 ? 'editar' : 'crear');
    $carnet = limpiarCarnet($conexion, $_POST['carnet']);
    if ($carnet === false) ir('empleados.php', 'error=carnet');
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $cargo = $conexion->real_escape_string($_POST['cargo']);
    $telefono = $conexion->real_escape_string($_POST['telefono']);
    $id = intval($_POST['id_empleado']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Empleado SET nombre='$nombre', carnet=$carnet, cargo='$cargo', telefono='$telefono' WHERE id_empleado=$id");
        } else {
            $conexion->query("INSERT INTO Empleado (codigo, nombre, carnet, cargo, telefono) VALUES ('" . codigoTemporal() . "', '$nombre', $carnet, '$cargo', '$telefono')");
            $codNuevo = asignarCodigo($conexion, 'Empleado', 'codigo', 'id_empleado', 'EMP', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'empleados', ($id > 0 ? "id=$id " : "$codNuevo ") . (string)($_POST['nombre'] ?? ''));
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('empleados.php', 'error=guardar');
    }
    ir('empleados.php');
}

$lista = $conexion->query("SELECT * FROM Empleado ORDER BY id_empleado DESC");
$titulo = 'Empleados';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Empleados</h2>
    <?php if (puede('empleados', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir empleado</button>
    <?php } ?>
</div>

<?php if (puede('empleados', 'crear') || puede('empleados', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar empleado' : 'Nuevo empleado'; ?></h2>
    <form method="POST" action="empleados.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_empleado" value="<?php echo $editando['id_empleado'] ?? 0; ?>">

        <?php if (!empty($editando['codigo'])) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo h($editando['nombre'] ?? ''); ?>" required>

        <label>Carnet de identidad:</label>
        <input type="text" name="carnet" value="<?php echo h($editando['carnet'] ?? ''); ?>" placeholder="Ej: 1234567 o 12345-A" pattern="[0-9]{4,10}(-[0-9A-Za-z]{1,3})?" title="Solo numeros, o numeros con complemento. Ej: 12345-A">

        <label>Cargo:</label>
        <input type="text" name="cargo" value="<?php echo h($editando['cargo'] ?? ''); ?>" required>

        <label>Telefono:</label>
        <input type="text" name="telefono" value="<?php echo h($editando['telefono'] ?? ''); ?>">

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
        <tr><th>Codigo</th><th>Nombre</th><th>Carnet</th><th>Cargo</th><th>Telefono</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['nombre']); ?></td>
            <td><?php echo h($fila['carnet']); ?></td>
            <td><?php echo h($fila['cargo']); ?></td>
            <td><?php echo h($fila['telefono']); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('empleados', 'editar')) { ?><a href="empleados.php?editar=<?php echo $fila['id_empleado']; ?>">Editar</a><?php } ?>
                <?php if (puede('empleados', 'eliminar')) { echo botonEliminar('empleados.php', $fila['id_empleado'], 'Deseas eliminar este empleado?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>