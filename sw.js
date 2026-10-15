// Service worker de Hamburguesas RaySu
// Guarda SOLO archivos estaticos (estilos, scripts, logo, iconos).
// Las paginas PHP NUNCA se guardan: siempre se piden al servidor para no mostrar
// datos viejos ni datos privados de otra sesion. Sin internet se muestra offline.html.
// Si cambias estilo.css, modal.css, app.css o los .js, sube el numero de VERSION.
const VERSION = 'raysu-v1';
const PRECACHE = [
  'offline.html',
  'estilo.css',
  'modal.css',
  'app.css',
  'sonido-looney.js',
  'pwa.js',
  'logo.jpg',
  'manifest.webmanifest',
  'icons/icon-192.png',
  'icons/icon-512.png'
];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(VERSION)
      .then(function (c) { return c.addAll(PRECACHE); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);

  // Paginas: siempre del servidor; si no hay internet, pantalla "sin conexion"
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(function () { return caches.match('offline.html'); }));
    return;
  }

  var mismoOrigen = url.origin === self.location.origin;
  var estatico = mismoOrigen && /\.(css|js|jpg|jpeg|png|svg|ico|webmanifest)$/.test(url.pathname);
  var fuente = url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';
  if (!estatico && !fuente) return;

  // Estatico: responde rapido con lo guardado y actualiza en segundo plano
  e.respondWith(
    caches.open(VERSION).then(function (cache) {
      return cache.match(req).then(function (guardado) {
        var red = fetch(req).then(function (res) {
          if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone());
          return res;
        }).catch(function () { return guardado; });
        return guardado || red;
      });
    })
  );
});
