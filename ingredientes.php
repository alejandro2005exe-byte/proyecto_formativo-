<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('ingredientes');
$verAcciones = puede('ingredientes', 'editar') || puede('ingredientes', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('ingredientes', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Ingrediente', 'id_ingrediente', $id, 'ingredientes.php', 'ingredientes');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('ingredientes', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Ingrediente WHERE id_ingrediente = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('ingredientes', intval($_POST['id_ingrediente'] ?? 0) > 0 ? 'editar' : 'crear');
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $cantidad = floatval($_POST['cantidad']);
    $unidad = $conexion->real_escape_string($_POST['unidad']);
    $precio = floatval($_POST['precio']);
    $id = intval($_POST['id_ingrediente']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Ingrediente SET nombre='$nombre', cantidad_actual=$cantidad, unidad_medida='$unidad', precio_unitario=$precio WHERE id_ingrediente=$id");
        } else {
            $conexion->query("INSERT INTO Ingrediente (codigo, nombre, cantidad_actual, unidad_medida, precio_unitario) VALUES ('" . codigoTemporal() . "', '$nombre', $cantidad, '$unidad', $precio)");
            $codNuevo = asignarCodigo($conexion, 'Ingrediente', 'codigo', 'id_ingrediente', 'ING', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'ingredientes', ($id > 0 ? "id=$id " : "$codNuevo ") . (string)($_POST['nombre'] ?? ''));
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('ingredientes.php', 'error=guardar');
    }
    ir('ingredientes.php');
}

$lista = $conexion->query("SELECT * FROM Ingrediente ORDER BY id_ingrediente DESC");
$titulo = 'Ingredientes';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Ingredientes (Materia Prima)</h2>
    <?php if (puede('ingredientes', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir ingrediente</button>
    <?php } ?>
</div>

<?php if (puede('ingredientes', 'crear') || puede('ingredientes', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar ingrediente' : 'Nuevo ingrediente'; ?></h2>
    <form method="POST" action="ingredientes.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_ingrediente" value="<?php echo $editando['id_ingrediente'] ?? 0; ?>">

        <?php if ($editando) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo h($editando['nombre'] ?? ''); ?>" required>

        <label>Cantidad actual:</label>
        <input type="number" step="0.01" min="0" name="cantidad" value="<?php echo h($editando['cantidad_actual'] ?? '0'); ?>" required>

        <label>Unidad de medida (libras, unidades, sachets):</label>
        <input type="text" name="unidad" value="<?php echo h($editando['unidad_medida'] ?? ''); ?>" required>

        <label>Precio unitario (Bs):</label>
        <input type="number" step="0.01" min="0" name="precio" value="<?php echo h($editando['precio_unitario'] ?? ''); ?>" required>

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
        <tr><th>Codigo</th><th>Nombre</th><th>Cantidad actual</th><th>Unidad</th><th>Precio Unitario</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['nombre']); ?></td>
            <td><?php echo h($fila['cantidad_actual']); ?></td>
            <td><?php echo h($fila['unidad_medida']); ?></td>
            <td><?php echo h($fila['precio_unitario']); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('ingredientes', 'editar')) { ?><a href="ingredientes.php?editar=<?php echo $fila['id_ingrediente']; ?>">Editar</a><?php } ?>
                <?php if (puede('ingredientes', 'eliminar')) { echo botonEliminar('ingredientes.php', $fila['id_ingrediente'], 'Deseas eliminar este ingrediente?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>