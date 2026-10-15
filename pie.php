</div>

<?php if (isset($_GET['ok'])) { lluviaYTelon(); } ?>

<script src="sonido-looney.js" defer></script>
<script src="pwa.js" defer></script>
<script>
var modal = document.getElementById('modal');
function abrirModal() { if (modal) modal.showModal(); }
function cerrarModal() { if (modal) modal.close(); }
if (modal) {
    modal.addEventListener('close', function () {
        if (modal.dataset.abrir === '1') window.location.href = window.location.pathname;
    });
    if (modal.dataset.abrir === '1') modal.showModal();
}
// Limpia ?ok=1 / ?error=... de la barra para que al refrescar no se repita
if (window.location.search.indexOf('ok=') !== -1 || window.location.search.indexOf('error=') !== -1) {
    history.replaceState(null, '', window.location.pathname);
}
</script>
</body>
</html>