const STATIC_CACHE = "trademeter-static-v2";
const PAGE_CACHE = "trademeter-pages-v2";
const IMAGE_CACHE = "trademeter-images-v2";
const OFFLINE_URL = "/TradeMeter/offline.html";

const CORE_ASSETS = [
  "/TradeMeter/",
  "/TradeMeter/index.php",
  "/TradeMeter/login.php",
  "/TradeMeter/offline.html",
  "/TradeMeter/manifest.webmanifest",
  "/TradeMeter/styles/styles.css",
  "/TradeMeter/scripts/App.js",
  "/TradeMeter/scripts/api.js",
  "/TradeMeter/scripts/pwa.js",
  "/TradeMeter/assets/vendor/css/bootstrap-4.6.2.min.css",
  "/TradeMeter/assets/vendor/css/fontawesome-5.15.4-all.min.css",
  "/TradeMeter/assets/vendor/js/jquery-3.6.0.min.js",
  "/TradeMeter/assets/vendor/js/popper-1.16.1.min.js",
  "/TradeMeter/assets/vendor/js/bootstrap-4.6.2.min.js",
  "/TradeMeter/Images/companyDP/icon-192.png",
  "/TradeMeter/Images/companyDP/icon-512.png"
];

const ALL_CACHES = [STATIC_CACHE, PAGE_CACHE, IMAGE_CACHE];

function isApiRequest(url) {
  return /\/TradeMeter\/api[A-Za-z0-9_-]*\.php$/i.test(url.pathname);
}

function isStaticAsset(url) {
  return /\.(?:css|js|woff2?|ttf|eot)$/i.test(url.pathname);
}

function isImageAsset(url) {
  return /\.(?:png|jpg|jpeg|gif|webp|svg|ico)$/i.test(url.pathname);
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      if (response && response.status === 200) {
        cache.put(request, response.clone()).catch(() => {});
      }
      return response;
    })
    .catch(() => cached);

  return cached || networkPromise;
}

async function networkFirstPage(request) {
  const cache = await caches.open(PAGE_CACHE);

  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
      cache.put(request, response.clone()).catch(() => {});
    }
    return response;
  } catch (_error) {
    const cached = await cache.match(request);
    return cached || caches.match(OFFLINE_URL);
  }
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(CORE_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((key) => !ALL_CACHES.includes(key)).map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== "GET") {
    return;
  }

  if (isApiRequest(url)) {
    event.respondWith(fetch(request));
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(networkFirstPage(request));
    return;
  }

  if (url.origin === self.location.origin) {
    if (isStaticAsset(url)) {
      event.respondWith(staleWhileRevalidate(request, STATIC_CACHE));
      return;
    }

    if (isImageAsset(url)) {
      event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE));
      return;
    }

    event.respondWith(staleWhileRevalidate(request, PAGE_CACHE));
  }
});
