<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('proveedores');
$verAcciones = puede('proveedores', 'editar') || puede('proveedores', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('proveedores', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Proveedor', 'id_proveedor', $id, 'proveedores.php', 'proveedores');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('proveedores', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Proveedor WHERE id_proveedor = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('proveedores', intval($_POST['id_proveedor'] ?? 0) > 0 ? 'editar' : 'crear');
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $telefono = $conexion->real_escape_string($_POST['telefono']);
    $direccion = $conexion->real_escape_string($_POST['direccion']);
    $id = intval($_POST['id_proveedor']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Proveedor SET nombre='$nombre', telefono='$telefono', direccion='$direccion' WHERE id_proveedor=$id");
        } else {
            $conexion->query("INSERT INTO Proveedor (codigo, nombre, telefono, direccion) VALUES ('" . codigoTemporal() . "', '$nombre', '$telefono', '$direccion')");
            $codNuevo = asignarCodigo($conexion, 'Proveedor', 'codigo', 'id_proveedor', 'PRV', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'proveedores', ($id > 0 ? "id=$id " : "$codNuevo ") . (string)($_POST['nombre'] ?? ''));
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('proveedores.php', 'error=guardar');
    }
    ir('proveedores.php');
}

$lista = $conexion->query("SELECT * FROM Proveedor ORDER BY id_proveedor DESC");
$titulo = 'Proveedores';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Proveedores</h2>
    <?php if (puede('proveedores', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir proveedor</button>
    <?php } ?>
</div>

<?php if (puede('proveedores', 'crear') || puede('proveedores', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar proveedor' : 'Nuevo proveedor'; ?></h2>
    <form method="POST" action="proveedores.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_proveedor" value="<?php echo $editando['id_proveedor'] ?? 0; ?>">

        <?php if (!empty($editando['codigo'])) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo h($editando['nombre'] ?? ''); ?>" required>

        <label>Telefono:</label>
        <input type="text" name="telefono" value="<?php echo h($editando['telefono'] ?? ''); ?>">

        <label>Direccion:</label>
        <input type="text" name="direccion" value="<?php echo h($editando['direccion'] ?? ''); ?>">

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
        <tr><th>Codigo</th><th>Nombre</th><th>Telefono</th><th>Direccion</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['nombre']); ?></td>
            <td><?php echo h($fila['telefono']); ?></td>
            <td><?php echo h($fila['direccion']); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('proveedores', 'editar')) { ?><a href="proveedores.php?editar=<?php echo $fila['id_proveedor']; ?>">Editar</a><?php } ?>
                <?php if (puede('proveedores', 'eliminar')) { echo botonEliminar('proveedores.php', $fila['id_proveedor'], 'Deseas eliminar este proveedor?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>