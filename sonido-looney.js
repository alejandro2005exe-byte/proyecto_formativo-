/* =====================================================
   SONIDO LOONEY - sin archivos de audio (se sintetiza)
   Uso: <script src="sonido-looney.js" defer></script>
   ===================================================== */
(function () {
    // ----- CONFIGURACION -----
    // Si tienes tus propios audios (con derechos de uso), ponlos aquí.
    // Si los dejas en null, se usan los sonidos sintetizados.
    const ARCHIVO_ENTRADA = null; // ej: "audio/entrada.mp3"
    const ARCHIVO_SALIDA  = null; // ej: "audio/salida.mp3"
    const RETRASO_SALIDA  = 750;  // ms que se espera antes de cambiar de página

    let ctx = null;
    let silenciado = sessionStorage.getItem('looneySilencio') === '1';

    function getCtx() {
        if (!ctx) {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return null;
            ctx = new AC();
        }
        return ctx;
    }

    // Nota con envolvente, estilo "metales de dibujo animado"
    function nota(freq, inicio, dur, tipo = 'sawtooth', vol = 0.18, freqFin = null) {
        const c = getCtx();
        const t0 = c.currentTime + inicio;
        const osc = c.createOscillator();
        const gan = c.createGain();
        const filtro = c.createBiquadFilter();
        osc.type = tipo;
        osc.frequency.setValueAtTime(freq, t0);
        if (freqFin) osc.frequency.exponentialRampToValueAtTime(freqFin, t0 + dur);
        filtro.type = 'lowpass';
        filtro.frequency.setValueAtTime(3200, t0);
        gan.gain.setValueAtTime(0.0001, t0);
        gan.gain.exponentialRampToValueAtTime(vol, t0 + 0.02);
        gan.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        osc.connect(filtro).connect(gan).connect(c.destination);
        osc.start(t0);
        osc.stop(t0 + dur + 0.05);
    }

    // Fanfarria de ENTRADA: arpegio ascendente + acorde final
    function fanfarriaEntrada() {
        [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => nota(f, i * 0.12, 0.18));
        [523.25, 659.25, 783.99, 1046.5].forEach(f => nota(f, 0.55, 0.7, 'sawtooth', 0.1));
        nota(2093, 0.55, 0.5, 'triangle', 0.06); // brillo
    }

    // Sonido de SALIDA: trombón que cae + "bonk" final
    function sonidoSalida() {
        nota(659, 0.00, 0.22, 'sawtooth', 0.16, 600);
        nota(523, 0.22, 0.22, 'sawtooth', 0.16, 480);
        nota(392, 0.44, 0.45, 'sawtooth', 0.18, 150);
        nota(110, 0.62, 0.12, 'square', 0.22, 60);   // bonk
    }

    // "Boing" para clics en el menú
    function boing() {
        nota(180, 0, 0.35, 'sine', 0.3, 520);
        nota(520, 0.18, 0.25, 'sine', 0.2, 240);
    }

    // Reproduce un archivo o, si no hay, la versión sintetizada
    function reproducir(archivo, sintetizado) {
        if (silenciado) return;
        if (archivo) {
            const a = new Audio(archivo);
            a.play().catch(() => {});
            return;
        }
        const c = getCtx();
        if (!c) return;
        const lanzar = () => sintetizado();
        if (c.state === 'suspended') {
            c.resume().then(lanzar).catch(() => {});
        } else {
            lanzar();
        }
    }

    // ----- ENTRADA: al cargar o recargar -----
    // Los navegadores bloquean audio sin interacción previa, así que
    // se intenta ya; si no deja, suena en el primer clic/tecla/toque.
    let yaSono = false;
    function intentarEntrada() {
        if (yaSono) return;
        const c = getCtx();
        if (!ARCHIVO_ENTRADA && c && c.state === 'suspended') return; // bloqueado
        yaSono = true;
        reproducir(ARCHIVO_ENTRADA, fanfarriaEntrada);
    }
    window.addEventListener('load', () => setTimeout(intentarEntrada, 300));
    ['pointerdown', 'keydown', 'touchstart'].forEach(ev =>
        window.addEventListener(ev, function once() {
            const c = getCtx();
            if (c && c.state === 'suspended') c.resume();
            if (!yaSono) { yaSono = true; reproducir(ARCHIVO_ENTRADA, fanfarriaEntrada); }
            window.removeEventListener(ev, once);
        }, { passive: true })
    );

    // ----- CAMBIO DE PESTAÑA DEL NAVEGADOR: al volver, suena -----
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && yaSono) {
            reproducir(null, boing);
        }
    });

    // ----- SALIDA: al hacer clic en un enlace interno -----
    document.addEventListener('click', e => {
        if (e.defaultPrevented) return; // respeta el "Cancelar" de confirm()
        const a = e.target.closest('a[href]');
        if (!a || silenciado) return;
        const url = new URL(a.href, location.href);
        const interno = url.origin === location.origin;
        const normal = !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0;
        if (!interno || !normal || a.target === '_blank' || a.hasAttribute('download')) return;
        if (url.pathname === location.pathname && url.hash) return; // ancla en la misma página

        e.preventDefault();
        document.body.style.transition = 'filter .6s, transform .6s';
        document.body.style.filter = 'brightness(.4)';
        reproducir(ARCHIVO_SALIDA, sonidoSalida);
        setTimeout(() => { location.href = a.href; }, RETRASO_SALIDA);
    });

    // ----- BOTON DE SILENCIO (esquina inferior derecha) -----
    function crearBoton() {
        const b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Activar o silenciar sonidos');
        b.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:300;margin:0;' +
            'width:54px;height:54px;padding:0;border-radius:50%;font-size:24px;';
        const pintar = () => { b.textContent = silenciado ? '🔇' : '🔊'; };
        pintar();
        b.addEventListener('click', ev => {
            ev.stopPropagation();
            silenciado = !silenciado;
            sessionStorage.setItem('looneySilencio', silenciado ? '1' : '0');
            pintar();
            if (!silenciado) reproducir(null, boing);
        });
        document.body.appendChild(b);
    }
    if (document.body) crearBoton();
    else document.addEventListener('DOMContentLoaded', crearBoton);
})();
