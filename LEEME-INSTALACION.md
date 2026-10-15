# Hamburguesas RaySu - Version con login, devoluciones y app para celular

## 1. Instalar (XAMPP)
1. Copia TODO el contenido de esta carpeta dentro de tu carpeta del sistema (por ejemplo `htdocs/raysu/`), reemplazando los archivos.
2. **Borra** `inventario.php` y `ventas.php` si todavia los tienes: eran de una version vieja (Pollos Don Coco), usan tablas que no existen y no tienen login.
3. En phpMyAdmin selecciona la base `SistemaPedidos` -> Importar -> `migracion_login_devoluciones.sql`. Tus datos NO se borran; solo se agregan tablas (`usuario`, `devolucion`, `detalle_devolucion`, `bitacora`) y dos vistas.
4. Abre `http://localhost/raysu/` y entra.

| Usuario | Contrasena inicial | Nivel |
|---|---|---|
| raysa | Raysa#2026 | Supervisor |
| susy | Susy#2026 | Empleado |

**Cambia las contrasenas** en el menu "Mi cuenta" la primera vez.

## 2. Que puede hacer cada nivel
- **Supervisor (Raysa):** todo. Ve todos los modulos, crea/edita/elimina, registra devoluciones, ve Finanzas, Reportes y la Bitacora.
- **Empleado (Susy):** registrar clientes, registrar pedidos, ver Productos y Recetas. No puede editar, eliminar ni devolver; no ve Finanzas, Reportes, Gastos, Abastecimiento, Ingredientes, Proveedores ni Empleados. En los pedidos queda registrada ella como quien atiende.

## 3. Devoluciones
- En Pedidos, el Supervisor pulsa **Devolver**: elige cuantas unidades de cada producto (devolucion total o parcial), el motivo y si los ingredientes vuelven al inventario.
- Estados del pedido: `Completado` -> `Devuelto parcial` -> `Devuelto`.
- Un pedido con devoluciones ya no se puede editar ni eliminar (queda el historial intacto).
- Las ventas de Reportes, Inicio y Finanzas ya muestran **ventas netas** (menos lo devuelto). Si los ingredientes no se reingresan, el costo se anota como **perdida por devoluciones** en Finanzas.
- Reporte nuevo **Devoluciones** y reporte **Bitacora de movimientos** (quien creo, edito, elimino, devolvio o entro al sistema).

## 4. Transacciones (COMMIT / ROLLBACK)
Todas las altas, ediciones, eliminaciones y devoluciones se hacen con `begin_transaction()` ... `commit()` y `rollback()` si algo falla. La bitacora se guarda dentro de la misma transaccion, asi nunca queda un cambio sin registro ni un registro sin cambio.

## 5. Excel
Todos los reportes (incluidos Devoluciones y Bitacora) tienen **Descargar Excel** e **Imprimir** (PDF desde el navegador). El Excel es un archivo `.xls`; Excel puede mostrar un aviso de formato al abrirlo, es normal: pulsa "Si".

## 6. Usarlo en el celular (PWA)
- La pagina ya se adapta al celular (menu plegable, tablas con desplazamiento, formularios grandes).
- Para **instalarla como app** el navegador exige **HTTPS** (o `localhost`). Con `http://192.168.x.x` desde el celular se ve bien, pero NO se puede instalar. Opciones: publicarlo en un hosting con HTTPS, o usar un tunel como Cloudflare Tunnel / ngrok.
- Android (Chrome): menu ⋮ -> "Instalar app" (o el boton "📲 Instalar app" del menu). iPhone (Safari): Compartir -> "Agregar a pantalla de inicio".
- Sin internet se muestra una pantalla "Sin conexion"; el sistema necesita conexion para guardar datos.
- Si cambias `estilo.css`, `modal.css`, `app.css` o los `.js`, sube el numero `VERSION` en `sw.js` para que los celulares descarguen la version nueva.

## 7. Seguridad agregada
Contrasenas cifradas (bcrypt), sesion que expira a los 60 min sin uso, bloqueo de 5 min tras 5 intentos fallidos, token anti-CSRF en todos los formularios, eliminar solo por POST (ya no con un enlace), permisos revisados en el servidor (no solo ocultando botones), y `.htaccess` que impide descargar el `.sql`.

## 8. Lista de pruebas sugerida
1. Entrar como `susy`: ver que solo aparecen Clientes, Pedidos, Productos, Recetas y Mi cuenta; registrar un pedido; probar escribir `http://localhost/raysu/finanzas.php` (debe rechazar).
2. Entrar como `raysa`: devolver 1 unidad de un pedido -> estado "Devuelto parcial"; mirar Reportes > Devoluciones y Finanzas.
3. Intentar editar ese pedido (debe avisar que tiene devoluciones).
4. Reportes > Bitacora: deben aparecer los movimientos de ambos.
5. Abrir desde el celular y probar el menu plegable.
