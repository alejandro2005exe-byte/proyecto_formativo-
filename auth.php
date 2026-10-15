<?php
// =====================================================
// AUTENTICACION: sesion, roles, permisos, CSRF y bitacora
// Se incluye despues de conexion.php y funciones.php
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('RAYSUSESION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Las paginas con datos privados no se guardan en el cache del navegador
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Minutos de inactividad antes de cerrar la sesion (60)
const TIEMPO_INACTIVIDAD = 3600;

if (isset($_SESSION['usuario'])) {
    if (time() - ($_SESSION['ultima_actividad'] ?? time()) > TIEMPO_INACTIVIDAD) {
        unset($_SESSION['usuario']);
        $_SESSION['sesion_expirada'] = true;
    } else {
        $_SESSION['ultima_actividad'] = time();
    }
}

// Lo que puede hacer el nivel EMPLEADO. El SUPERVISOR puede todo.
// Acciones: ver, crear, editar, eliminar, devolver
const PERMISOS_EMPLEADO = [
    'clientes'  => ['ver', 'crear'],
    'pedidos'   => ['ver', 'crear'],
    'recetas'   => ['ver'],
    'productos' => ['ver'],
];

function usuarioActual() {
    return $_SESSION['usuario'] ?? null;
}

function estaLogueado() {
    return usuarioActual() !== null;
}

function esSupervisor() {
    $u = usuarioActual();
    return $u !== null && $u['rol'] === 'Supervisor';
}

// puede('clientes', 'editar')
function puede($modulo, $accion = 'ver') {
    $u = usuarioActual();
    if ($u === null) return false;
    if (in_array($modulo, ['inicio', 'cuenta'], true)) return true;
    if ($u['rol'] === 'Supervisor') return true;
    return in_array($accion, PERMISOS_EMPLEADO[$modulo] ?? [], true);
}

// Pide inicio de sesion y permiso de "ver" en el modulo. Tambien valida el token en los POST.
function exigirAcceso($modulo) {
    if (!estaLogueado()) {
        header('Location: login.php' . (!empty($_SESSION['sesion_expirada']) ? '?e=expirada' : ''));
        exit;
    }
    if (!puede($modulo, 'ver')) {
        ir('index.php', 'error=permiso');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarCsrf();
    }
}

// Pide permiso para una accion concreta (crear, editar, eliminar, devolver)
function exigirPermiso($modulo, $accion) {
    if (!puede($modulo, $accion)) {
        ir('index.php', 'error=permiso');
    }
}

// ----- CSRF: evita que otra pagina mande formularios en nombre del usuario -----
function tokenCsrf() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campoCsrf() {
    return '<input type="hidden" name="csrf" value="' . h(tokenCsrf()) . '">';
}

function verificarCsrf() {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals(tokenCsrf(), $token)) {
        http_response_code(403);
        die('Solicitud no valida (token de seguridad). Vuelve atras, recarga la pagina e intenta de nuevo.');
    }
}

// ----- BITACORA: quien hizo que y cuando (se guarda dentro de la misma transaccion) -----
function bitacora($conexion, $accion, $modulo, $detalle = '') {
    $u = usuarioActual();
    $nombre = $u['usuario'] ?? 'sistema';
    $idUsuario = $u['id_usuario'] ?? null;
    $detalle = mb_substr((string)$detalle, 0, 500);
    $st = $conexion->prepare("INSERT INTO bitacora (id_usuario, usuario, accion, modulo, detalle) VALUES (?, ?, ?, ?, ?)");
    $st->bind_param('issss', $idUsuario, $nombre, $accion, $modulo, $detalle);
    $st->execute();
    $st->close();
}

function cerrarSesion() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
