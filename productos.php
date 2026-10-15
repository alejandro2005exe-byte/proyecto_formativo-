<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('productos');
$verAcciones = puede('productos', 'editar') || puede('productos', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('productos', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Producto', 'id_producto', $id, 'productos.php', 'productos');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('productos', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Producto WHERE id_producto = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('productos', intval($_POST['id_producto'] ?? 0) > 0 ? 'editar' : 'crear');
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $descripcion = $conexion->real_escape_string($_POST['descripcion']);
    $precio = floatval($_POST['precio']);
    $id = intval($_POST['id_producto']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Producto SET nombre='$nombre', descripcion='$descripcion', precio=$precio WHERE id_producto=$id");
        } else {
            $conexion->query("INSERT INTO Producto (codigo, nombre, descripcion, precio) VALUES ('" . codigoTemporal() . "', '$nombre', '$descripcion', $precio)");
            $codNuevo = asignarCodigo($conexion, 'Producto', 'codigo', 'id_producto', 'PRO', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'productos', ($id > 0 ? "id=$id " : "$codNuevo ") . (string)($_POST['nombre'] ?? ''));
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('productos.php', 'error=guardar');
    }
    ir('productos.php');
}

$lista = $conexion->query("SELECT * FROM Producto ORDER BY id_producto DESC");
$titulo = 'Productos';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Productos</h2>
    <?php if (puede('productos', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir producto</button>
    <?php } ?>
</div>

<?php if (puede('productos', 'crear') || puede('productos', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar producto' : 'Nuevo producto'; ?></h2>
    <form method="POST" action="productos.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_producto" value="<?php echo $editando['id_producto'] ?? 0; ?>">

        <?php if ($editando) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo h($editando['nombre'] ?? ''); ?>" required>

        <label>Descripcion:</label>
        <input type="text" name="descripcion" value="<?php echo h($editando['descripcion'] ?? ''); ?>">

        <label>Precio (Bs):</label>
        <input type="number" step="0.01" min="0" name="precio" value="<?php echo h($editando['precio'] ?? ''); ?>" required>

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
        <tr><th>Codigo</th><th>Nombre</th><th>Descripcion</th><th>Precio (Bs)</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['nombre']); ?></td>
            <td><?php echo h($fila['descripcion']); ?></td>
            <td><?php echo h($fila['precio']); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('productos', 'editar')) { ?><a href="productos.php?editar=<?php echo $fila['id_producto']; ?>">Editar</a><?php } ?>
                <?php if (puede('productos', 'eliminar')) { echo botonEliminar('productos.php', $fila['id_producto'], 'Deseas eliminar este producto?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>