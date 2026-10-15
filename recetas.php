<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('recetas');
$verAcciones = puede('recetas', 'editar') || puede('recetas', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('recetas', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Producto_Receta', 'id_receta', $id, 'recetas.php', 'recetas');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('recetas', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Producto_Receta WHERE id_receta = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('recetas', intval($_POST['id_receta'] ?? 0) > 0 ? 'editar' : 'crear');
    $id_producto = intval($_POST['id_producto']);
    $id_ingrediente = intval($_POST['id_ingrediente']);
    $cantidad = floatval($_POST['cantidad_necesaria']);
    $id = intval($_POST['id_receta']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Producto_Receta SET id_producto=$id_producto, id_ingrediente=$id_ingrediente, cantidad_necesaria=$cantidad WHERE id_receta=$id");
        } else {
            $conexion->query("INSERT INTO Producto_Receta (codigo, id_producto, id_ingrediente, cantidad_necesaria) VALUES ('" . codigoTemporal() . "', $id_producto, $id_ingrediente, $cantidad)");
            $codNuevo = asignarCodigo($conexion, 'Producto_Receta', 'codigo', 'id_receta', 'REC', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'recetas', ($id > 0 ? "id=$id " : "$codNuevo ") . 'prod=' . $id_producto . ' ing=' . $id_ingrediente . ' cant=' . $cantidad);
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('recetas.php', 'error=guardar');
    }
    ir('recetas.php');
}

$productos = $conexion->query("SELECT id_producto, codigo, nombre FROM Producto ORDER BY codigo");
$ingredientes = $conexion->query("SELECT id_ingrediente, codigo, nombre, unidad_medida FROM Ingrediente ORDER BY codigo");

$lista = $conexion->query("
    SELECT r.id_receta, r.codigo, CONCAT(p.codigo, ' - ', p.nombre) AS producto, CONCAT(i.codigo, ' - ', i.nombre) AS ingrediente, r.cantidad_necesaria, i.unidad_medida
    FROM Producto_Receta r
    INNER JOIN Producto p ON r.id_producto = p.id_producto
    INNER JOIN Ingrediente i ON r.id_ingrediente = i.id_ingrediente
    ORDER BY p.nombre, i.nombre
");
$titulo = 'Recetas';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Recetas de Productos</h2>
    <?php if (puede('recetas', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir a receta</button>
    <?php } ?>
</div>
<p>Cuanto de cada ingrediente lleva un producto. Se usa para descontar el inventario automaticamente al registrar un pedido.</p>

<?php if (puede('recetas', 'crear') || puede('recetas', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar receta' : 'Nueva linea de receta'; ?></h2>
    <form method="POST" action="recetas.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_receta" value="<?php echo $editando['id_receta'] ?? 0; ?>">

        <?php if (!empty($editando['codigo'])) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo']); ?>" disabled>
        <?php } ?>

        <label>Producto:</label>
        <select name="id_producto" required>
            <option value="">Selecciona un producto</option>
            <?php while ($p = $productos->fetch_assoc()) { ?>
                <option value="<?php echo $p['id_producto']; ?>" <?php echo (isset($editando['id_producto']) && $editando['id_producto'] == $p['id_producto']) ? 'selected' : ''; ?>>
                    <?php echo h($p['codigo'] . ' - ' . $p['nombre']); ?>
                </option>
            <?php } ?>
        </select>

        <label>Ingrediente:</label>
        <select name="id_ingrediente" required>
            <option value="">Selecciona un ingrediente</option>
            <?php while ($i = $ingredientes->fetch_assoc()) { ?>
                <option value="<?php echo $i['id_ingrediente']; ?>" <?php echo (isset($editando['id_ingrediente']) && $editando['id_ingrediente'] == $i['id_ingrediente']) ? 'selected' : ''; ?>>
                    <?php echo h($i['codigo'] . ' - ' . $i['nombre'] . ' (' . $i['unidad_medida'] . ')'); ?>
                </option>
            <?php } ?>
        </select>

        <label>Cantidad necesaria por unidad de producto:</label>
        <input type="number" step="0.01" min="0.01" name="cantidad_necesaria" value="<?php echo h($editando['cantidad_necesaria'] ?? ''); ?>" required>

        <div class="botones-modal">
            <button type="submit"><?php echo $editando ? 'Actualizar' : 'Agregar a la receta'; ?></button>
            <button type="button" class="boton-cancelar" onclick="cerrarModal()">Cancelar</button>
        </div>
    </form>
</div>
</dialog>
<?php } ?>

<div class="seccion-tabla">
<table>
    <thead>
        <tr><th>Codigo</th><th>Producto</th><th>Ingrediente</th><th>Cantidad Necesaria</th><th>Unidad</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo']); ?></td>
            <td><?php echo h($fila['producto']); ?></td>
            <td><?php echo h($fila['ingrediente']); ?></td>
            <td><?php echo h($fila['cantidad_necesaria']); ?></td>
            <td><?php echo h($fila['unidad_medida']); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('recetas', 'editar')) { ?><a href="recetas.php?editar=<?php echo $fila['id_receta']; ?>">Editar</a><?php } ?>
                <?php if (puede('recetas', 'eliminar')) { echo botonEliminar('recetas.php', $fila['id_receta'], 'Deseas eliminar esta linea de receta?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>