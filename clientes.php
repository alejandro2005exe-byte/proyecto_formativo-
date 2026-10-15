<?php
require 'conexion.php';
require 'funciones.php';
require 'auth.php';
exigirAcceso('clientes');
$verAcciones = puede('clientes', 'editar') || puede('clientes', 'eliminar');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    exigirPermiso('clientes', 'eliminar');
    $id = intval($_POST['eliminar']);
    eliminarRegistro($conexion, 'Cliente', 'id_cliente', $id, 'clientes.php', 'clientes');
}

$editando = null;
if (isset($_GET['editar'])) {
    exigirPermiso('clientes', 'editar');
    $id = intval($_GET['editar']);
    $editando = $conexion->query("SELECT * FROM Cliente WHERE id_cliente = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    exigirPermiso('clientes', intval($_POST['id_cliente'] ?? 0) > 0 ? 'editar' : 'crear');
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $genero = ($_POST['genero'] == 'Femenino') ? 'Femenino' : 'Masculino';
    $direccion = $conexion->real_escape_string($_POST['direccion']);
    $id = intval($_POST['id_cliente']);

    $codNuevo = '';
    $conexion->begin_transaction();
    try {
        if ($id > 0) {
            $conexion->query("UPDATE Cliente SET nombre='$nombre', genero='$genero', direccion='$direccion' WHERE id_cliente=$id");
        } else {
            $conexion->query("INSERT INTO Cliente (codigo_cliente, nombre, genero, direccion) VALUES ('" . codigoTemporal() . "', '$nombre', '$genero', '$direccion')");
            $codNuevo = asignarCodigo($conexion, 'Cliente', 'codigo_cliente', 'id_cliente', 'CLI', $conexion->insert_id);
        }
        bitacora($conexion, $id > 0 ? 'EDITAR' : 'CREAR', 'clientes', ($id > 0 ? "id=$id " : "$codNuevo ") . (string)($_POST['nombre'] ?? ''));
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir('clientes.php', 'error=guardar');
    }
    ir('clientes.php');
}

$lista = $conexion->query("SELECT * FROM Cliente ORDER BY id_cliente DESC");
$titulo = 'Clientes';
include 'menu.php';
?>

<div class="barra-superior">
    <h2>Clientes</h2>
    <?php if (puede('clientes', 'crear')) { ?>
    <button type="button" class="boton-anadir" onclick="abrirModal()">+ Añadir cliente</button>
    <?php } ?>
</div>

<?php if (puede('clientes', 'crear') || puede('clientes', 'editar')) { ?>
<dialog id="modal" class="modal" data-abrir="<?php echo $editando ? 1 : 0; ?>">
<div class="modal-contenido">
    <h2><?php echo $editando ? 'Editar cliente' : 'Nuevo cliente'; ?></h2>
    <form method="POST" action="clientes.php">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="id_cliente" value="<?php echo $editando['id_cliente'] ?? 0; ?>">

        <?php if ($editando) { ?>
        <label>Codigo (automatico):</label>
        <input type="text" value="<?php echo h($editando['codigo_cliente']); ?>" disabled>
        <?php } ?>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo h($editando['nombre'] ?? ''); ?>" required>

        <label>Genero:</label>
        <select name="genero" required>
            <option value="">Selecciona el genero</option>
            <option value="Masculino" <?php echo (($editando['genero'] ?? '') == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
            <option value="Femenino" <?php echo (($editando['genero'] ?? '') == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
        </select>

        <label>Curso (ej: 3ro A) o tipo (Profesor/a, Padre de familia):</label>
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
        <tr><th>Codigo</th><th>Nombre</th><th>Genero</th><th>Curso / Tipo</th><?php if ($verAcciones) { ?><th>Acciones</th><?php } ?></tr>
    </thead>
    <tbody>
        <?php while ($fila = $lista->fetch_assoc()) { ?>
        <tr>
            <td><?php echo h($fila['codigo_cliente']); ?></td>
            <td><?php echo h($fila['nombre']); ?></td>
            <td><?php echo h($fila['genero']); ?></td>
            <td><?php echo h($fila['direccion']); ?></td>
            <?php if ($verAcciones) { ?>
            <td class="acciones">
                <?php if (puede('clientes', 'editar')) { ?><a href="clientes.php?editar=<?php echo $fila['id_cliente']; ?>">Editar</a><?php } ?>
                <?php if (puede('clientes', 'eliminar')) { echo botonEliminar('clientes.php', $fila['id_cliente'], 'Deseas eliminar este cliente?'); } ?>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php include 'pie.php'; ?>