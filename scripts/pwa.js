(() => {
  let deferredInstallPrompt = null;
  let installBanner = null;
  let updateToast = null;
  let activeRegistration = null;

  function isPublicAuthRoute() {
    const path = String(window.location.pathname || "").toLowerCase();
    return /\/(login|reset_password|verify_email|companies)\.php$/.test(path);
  }

  async function cleanupServiceWorkersAndCaches() {
    if (!("serviceWorker" in navigator)) return;

    try {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map((registration) => registration.unregister()));
    } catch (_error) {
      // Ignore SW cleanup errors.
    }

    if (!("caches" in window)) return;

    try {
      const cacheKeys = await caches.keys();
      await Promise.all(cacheKeys.map((key) => caches.delete(key)));
    } catch (_error) {
      // Ignore cache cleanup errors.
    }
  }

  function createInstallBanner() {
    if (installBanner) return installBanner;

    installBanner = document.createElement("div");
    installBanner.className = "pwa-install-banner";
    installBanner.innerHTML = `
      <div class="pwa-banner-copy">
        <strong>Install TradeMeter</strong>
        <span>Open it like an app from your home screen.</span>
      </div>
      <div class="pwa-banner-actions">
        <button type="button" class="btn btn-sm btn-primary" data-action="install">Install</button>
        <button type="button" class="btn btn-sm btn-light" data-action="dismiss">Not now</button>
      </div>
    `;

    installBanner.querySelector('[data-action="install"]').addEventListener("click", async () => {
      if (!deferredInstallPrompt) return;
      deferredInstallPrompt.prompt();
      const result = await deferredInstallPrompt.userChoice.catch(() => null);
      if (result?.outcome === "accepted") {
        hideInstallBanner();
      }
      deferredInstallPrompt = null;
    });

    installBanner.querySelector('[data-action="dismiss"]').addEventListener("click", () => {
      hideInstallBanner();
      try {
        localStorage.setItem("trademeter-install-dismissed", String(Date.now()));
      } catch (_error) {}
    });

    document.body.appendChild(installBanner);
    requestAnimationFrame(() => installBanner.classList.add("is-visible"));
    return installBanner;
  }

  function hideInstallBanner() {
    if (!installBanner) return;
    installBanner.classList.remove("is-visible");
  }

  function wasInstallDismissedRecently() {
    try {
      const raw = localStorage.getItem("trademeter-install-dismissed");
      if (!raw) return false;
      const dismissedAt = Number(raw);
      const sevenDays = 7 * 24 * 60 * 60 * 1000;
      return Number.isFinite(dismissedAt) && (Date.now() - dismissedAt) < sevenDays;
    } catch (_error) {
      return false;
    }
  }

  function createUpdateToast(registration) {
    if (updateToast) {
      updateToast.classList.add("is-visible");
      return;
    }

    updateToast = document.createElement("div");
    updateToast.className = "pwa-update-toast";
    updateToast.innerHTML = `
      <div class="pwa-toast-copy">
        <strong>Update ready</strong>
        <span>A newer version of TradeMeter is available.</span>
      </div>
      <div class="pwa-toast-actions">
        <button type="button" class="btn btn-sm btn-primary" data-action="refresh">Refresh</button>
        <button type="button" class="btn btn-sm btn-light" data-action="later">Later</button>
      </div>
    `;

    updateToast.querySelector('[data-action="refresh"]').addEventListener("click", () => {
      if (registration.waiting) {
        registration.waiting.postMessage({ type: "SKIP_WAITING" });
      }
    });

    updateToast.querySelector('[data-action="later"]').addEventListener("click", () => {
      updateToast.classList.remove("is-visible");
    });

    document.body.appendChild(updateToast);
    requestAnimationFrame(() => updateToast.classList.add("is-visible"));
  }

  function watchForWaitingServiceWorker(registration) {
    if (!registration) return;

    if (registration.waiting) {
      createUpdateToast(registration);
      return;
    }

    registration.addEventListener("updatefound", () => {
      const installingWorker = registration.installing;
      if (!installingWorker) return;

      installingWorker.addEventListener("statechange", () => {
        if (installingWorker.state === "installed" && navigator.serviceWorker.controller) {
          createUpdateToast(registration);
        }
      });
    });
  }

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;

    if (!wasInstallDismissedRecently()) {
      createInstallBanner();
    }
  });

  window.addEventListener("appinstalled", () => {
    deferredInstallPrompt = null;
    hideInstallBanner();
    try {
      localStorage.removeItem("trademeter-install-dismissed");
    } catch (_error) {}
  });

  if (isPublicAuthRoute()) {
    cleanupServiceWorkersAndCaches();
    return;
  }

  if ("serviceWorker" in navigator) {
    window.addEventListener("load", async () => {
      try {
        activeRegistration = await navigator.serviceWorker.register("/TradeMeter/sw.js");
        watchForWaitingServiceWorker(activeRegistration);

        navigator.serviceWorker.addEventListener("controllerchange", () => {
          window.location.reload();
        });
      } catch (error) {
        console.error("Service worker registration failed:", error);
      }
    });
  }
})();
