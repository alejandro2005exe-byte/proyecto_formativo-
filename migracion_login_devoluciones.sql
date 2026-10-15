-- =====================================================================
-- MIGRACION: login con dos niveles, devoluciones de pedidos y bitacora
-- Hamburguesas RaySu
--
-- COMO USARLA (phpMyAdmin):
--   1. Selecciona la base de datos SistemaPedidos (la que ya tiene tus datos).
--   2. Pestaña "Importar" -> elige este archivo -> Continuar.
--   Tus datos actuales NO se borran. Solo se agregan tablas nuevas.
-- =====================================================================


-- ---------- Usuarios del sistema (login) ----------
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(30) NOT NULL,
  `clave_hash` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `rol` enum('Supervisor','Empleado') NOT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `id_empleado` (`id_empleado`),
  CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contraseñas iniciales (CAMBIALAS en "Mi cuenta" la primera vez que entres):
--   raysa -> Raysa#2026      susy -> Susy#2026
INSERT INTO `usuario` (`usuario`, `clave_hash`, `nombre`, `rol`, `id_empleado`) VALUES
('raysa', '$2y$10$lmv7ei1/POJgXXTepoYP1.tgPKxWiX2Uylux6iiNzIYfKnoNtroQu', 'Raysa Cordova', 'Supervisor', 2),
('susy',  '$2y$10$sMVRWQ1tOoeGMnxk85OXF.awDNiD8DIbWdm4KYDGg82MRqOvHcM0m', 'Susy Illanes',  'Empleado',   1);

-- ---------- Devoluciones de pedidos ----------
CREATE TABLE `devolucion` (
  `id_devolucion` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo` varchar(200) NOT NULL,
  `reingresa` tinyint(1) NOT NULL DEFAULT 0,
  `monto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_devolucion`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `devolucion_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedido_general` (`id_pedido`),
  CONSTRAINT `devolucion_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `detalle_devolucion` (
  `id_detalle_dev` int(11) NOT NULL AUTO_INCREMENT,
  `id_devolucion` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle_dev`),
  KEY `id_devolucion` (`id_devolucion`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_devolucion_ibfk_1` FOREIGN KEY (`id_devolucion`) REFERENCES `devolucion` (`id_devolucion`),
  CONSTRAINT `detalle_devolucion_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Bitacora de movimientos (quien creo, edito, elimino, devolvio, entro...) ----------
CREATE TABLE `bitacora` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) DEFAULT NULL,
  `usuario` varchar(30) NOT NULL,
  `accion` varchar(20) NOT NULL,
  `modulo` varchar(30) NOT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_bitacora`),
  KEY `fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- Vistas: ventas NETAS (lo vendido menos lo devuelto) ----------
-- Los reportes y Finanzas leen estas vistas, asi las devoluciones se descuentan solas.
CREATE OR REPLACE VIEW `v_devuelto` AS
SELECT dv.id_pedido, dd.id_producto, SUM(dd.cantidad) AS cantidad_devuelta
FROM `devolucion` dv
INNER JOIN `detalle_devolucion` dd ON dd.id_devolucion = dv.id_devolucion
GROUP BY dv.id_pedido, dd.id_producto;

CREATE OR REPLACE VIEW `v_detalle_neto` AS
SELECT d.id_detalle, d.id_pedido, d.id_producto,
       d.cantidad - IFNULL(x.cantidad_devuelta, 0) AS cantidad,
       d.precio_unitario,
       (d.cantidad - IFNULL(x.cantidad_devuelta, 0)) * d.precio_unitario AS subtotal
FROM `detalle_pedido` d
LEFT JOIN `v_devuelto` x ON x.id_pedido = d.id_pedido AND x.id_producto = d.id_producto
WHERE d.cantidad - IFNULL(x.cantidad_devuelta, 0) > 0;
