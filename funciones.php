<?php
// Hace que los errores de MySQL se puedan atrapar con try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Asegura que las tildes y las enies se vean bien
if (isset($conexion) && $conexion instanceof mysqli) {
    $conexion->set_charset('utf8mb4');
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Redirige a una pagina. 'ok=1' dispara la lluvia de estrellas (solo al terminar una accion)
function ir($pagina, $parametro = 'ok=1') {
    header('Location: ' . $pagina . '?' . $parametro);
    exit;
}

// Elimina un registro dentro de una transaccion (COMMIT / ROLLBACK) y lo anota en la bitacora
function eliminarRegistro($conexion, $tabla, $colId, $id, $pagina, $modulo) {
    $id = intval($id);
    $conexion->begin_transaction();
    try {
        $fila = $conexion->query("SELECT * FROM $tabla WHERE $colId = $id")->fetch_assoc();
        $conexion->query("DELETE FROM $tabla WHERE $colId = $id");
        if ($conexion->affected_rows > 0) {
            bitacora($conexion, 'ELIMINAR', $modulo, json_encode($fila, JSON_UNESCAPED_UNICODE));
        }
        $conexion->commit();
    } catch (mysqli_sql_exception $e) {
        $conexion->rollback();
        ir($pagina, 'error=eliminar');
    }
    ir($pagina);
}

// Boton "Eliminar": formulario POST con token de seguridad (ya no se borra con un simple enlace)
function botonEliminar($pagina, $id, $mensaje) {
    return '<form method="POST" action="' . h($pagina) . '" class="form-linea" onsubmit="return confirm(\'' . h(addslashes($mensaje)) . '\');">'
        . campoCsrf()
        . '<input type="hidden" name="eliminar" value="' . intval($id) . '">'
        . '<button type="submit" class="enlace-accion">Eliminar</button></form>';
}

// Codigo automatico: CLI-001, ING-001, PRO-001 ... (se arma con el id del registro)
function codigoTemporal() {
    return 'TMP' . uniqid();
}

function asignarCodigo($conexion, $tabla, $colCodigo, $colId, $prefijo, $id) {
    $codigo = $prefijo . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
    $conexion->query("UPDATE $tabla SET $colCodigo='$codigo' WHERE $colId=$id");
    return $codigo;
}

// Carnet: solo numeros (1234567) o numeros con complemento (12345-A, 1234567-1A)
// Devuelve el valor listo para el SQL, 'NULL' si esta vacio, o false si es invalido
function limpiarCarnet($conexion, $texto) {
    $texto = strtoupper(trim($texto));
    if ($texto === '') return 'NULL';
    if (!preg_match('/^[0-9]{4,10}(-[0-9A-Z]{1,3})?$/', $texto)) return false;
    return "'" . $conexion->real_escape_string($texto) . "'";
}

// Lluvia de estrellas + cartel "¡Eso es todo, amigos!": solo al terminar una accion o al salir
function lluviaYTelon($quitarTelon = true) {
    echo '<div class="lluvia-estrellas" id="lluvia">';
    for ($i = 0; $i < 24; $i++) {
        $izq = rand(1, 97); $tam = rand(34, 60); $dur = rand(40, 60) / 10; $ret = rand(0, 15) / 10;
        echo '<span class="estrella" style="left: ' . $izq . '%; font-size: ' . $tam . 'px; animation-duration: ' . $dur . 's; animation-delay: ' . $ret . 's; animation-iteration-count: 1; animation-fill-mode: both;">⭐</span>';
    }
    echo '</div>';
    echo '<div class="telon-final" id="telon"><div class="telon-pill">¡Eso es todo, amigos!</div></div>';
    echo '<script>setTimeout(function () { var l = document.getElementById("lluvia"); if (l) l.remove();'
        . ($quitarTelon ? ' var t = document.getElementById("telon"); if (t) t.remove();' : '')
        . ' }, 8000);</script>';
}