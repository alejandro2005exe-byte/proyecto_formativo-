// Registro del service worker y boton "Instalar app" (Chrome/Edge en Android y PC)
(function () {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        });
    }

    var evento = null;
    function mostrarBoton() {
        var b = document.getElementById('btnInstalar');
        if (b && evento) b.hidden = false;
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        evento = e;
        mostrarBoton();
    });

    window.addEventListener('appinstalled', function () {
        evento = null;
        var b = document.getElementById('btnInstalar');
        if (b) b.hidden = true;
    });

    document.addEventListener('DOMContentLoaded', function () {
        var b = document.getElementById('btnInstalar');
        if (!b) return;
        b.addEventListener('click', function () {
            if (!evento) return;
            evento.prompt();
            evento.userChoice.then(function () {
                evento = null;
                b.hidden = true;
            });
        });
        mostrarBoton();
    });
})();
