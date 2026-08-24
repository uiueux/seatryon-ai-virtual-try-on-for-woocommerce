/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/src/frontend.scss"
/*!**********************************!*\
  !*** ./assets/src/frontend.scss ***!
  \**********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	const __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		const cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		const module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			const e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter/value functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			if(Array.isArray(definition)) {
/******/ 				var i = 0;
/******/ 				while(i < definition.length) {
/******/ 					var key = definition[i++];
/******/ 					var binding = definition[i++];
/******/ 					if(!__webpack_require__.o(exports, key)) {
/******/ 						if(binding === 0) {
/******/ 							Object.defineProperty(exports, key, { enumerable: true, value: definition[i++] });
/******/ 						} else {
/******/ 							Object.defineProperty(exports, key, { enumerable: true, get: binding });
/******/ 						}
/******/ 					} else if(binding === 0) { i++; }
/******/ 				}
/******/ 			} else {
/******/ 				for(var key in definition) {
/******/ 					if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 						Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.hasOwn(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
let __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!********************************!*\
  !*** ./assets/src/frontend.js ***!
  \********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   initializeTryOnRoots: () => (/* binding */ initializeTryOnRoots),
/* harmony export */   invalidateForVariation: () => (/* binding */ invalidateForVariation),
/* harmony export */   setTryOnState: () => (/* binding */ setTryOnState)
/* harmony export */ });
/* harmony import */ var _frontend_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./frontend.scss */ "./assets/src/frontend.scss");

const ROOT_SELECTOR = '[data-sea-tryon-root]';
const READY_ATTRIBUTE = 'data-sea-tryon-ready';
const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_IMAGE_WIDTH = 2000;
const JPEG_QUALITY = 0.9;
const POLL_INITIAL_INTERVAL_MS = 2000;
const POLL_MAX_INTERVAL_MS = 10000;
const MAX_POLL_DURATION_MS = 5 * 60 * 1000;
const roots = new WeakMap();
const messages = {
  invalidType: window.wp?.i18n?.__('Please upload a valid JPEG, PNG, or WebP image.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Please upload a valid JPEG, PNG, or WebP image.',
  fileTooLarge: window.wp?.i18n?.__('This image is too large. Please choose an image under 10 MB.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'This image is too large. Please choose an image under 10 MB.',
  imageSelected: window.wp?.i18n?.__('Image selected. Review the consent notice to continue.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Image selected. Review the consent notice to continue.',
  optimizing: window.wp?.i18n?.__('Optimizing your image…', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Optimizing your image…',
  optimizationFailed: window.wp?.i18n?.__('We could not optimize this image. Please choose another image.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'We could not optimize this image. Please choose another image.',
  variationChanged: window.wp?.i18n?.__('The selected variation changed. Please choose your image again.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'The selected variation changed. Please choose your image again.',
  imageRemoved: window.wp?.i18n?.__('Image removed.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Image removed.',
  cameraRequesting: window.wp?.i18n?.__('Requesting camera access…', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Requesting camera access…',
  cameraReady: window.wp?.i18n?.__('Camera ready. Position the photo and press Capture photo.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Camera ready. Position the photo and press Capture photo.',
  cameraUnsupported: window.wp?.i18n?.__('Camera capture is not supported in this browser. Please upload a photo instead.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Camera capture is not supported in this browser. Please upload a photo instead.',
  cameraPermissionDenied: window.wp?.i18n?.__('Camera access was blocked. Allow camera permission or upload a photo instead.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Camera access was blocked. Allow camera permission or upload a photo instead.',
  cameraFailed: window.wp?.i18n?.__('We could not access the camera. Please try again or upload a photo instead.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'We could not access the camera. Please try again or upload a photo instead.',
  captureFailed: window.wp?.i18n?.__('We could not capture this photo. Please try again.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'We could not capture this photo. Please try again.',
  submitting: window.wp?.i18n?.__('Uploading your image securely…', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Uploading your image securely…',
  processing: window.wp?.i18n?.__('Generating your preview. This may take a moment…', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Generating your preview. This may take a moment…',
  succeeded: window.wp?.i18n?.__('Your preview is ready.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'Your preview is ready.',
  genericError: window.wp?.i18n?.__('We could not generate the preview. Please try again.', 'seatryon-ai-virtual-try-on-for-woocommerce') || 'We could not generate the preview. Please try again.'
};

/** Create a cryptographically random, URL-safe idempotency key. */
function createIdempotencyKey() {
  const bytes = new Uint8Array(24);
  window.crypto.getRandomValues(bytes);
  return Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
}

/**
 * Return the localized configuration for this single-product root.
 *
 * @param {Object} state Root UI state.
 */
function getConfig(state) {
  const config = window.SeaTryOnConfig;
  return config && Number(config.productId) === Number(state.root.dataset.productId) ? config : null;
}

/**
 * Build authentication headers without placing credentials in URLs.
 *
 * @param {Object} config Runtime configuration.
 * @param {string} action REST action name.
 */
function authHeaders(config, action) {
  if (config.authMode === 'logged-in') {
    return {
      'X-WP-Nonce': config.nonce
    };
  }
  return config.tokens?.[action] ? {
    'X-Sea-TryOn-Token': config.tokens[action]
  } : {};
}

/**
 * Refresh short-lived guest action tokens through the HttpOnly session.
 *
 * @param {Object}      config Runtime configuration.
 * @param {AbortSignal} signal Request abort signal.
 */
async function refreshGuestTokens(config, signal) {
  if (config.authMode !== 'guest') {
    return;
  }
  const response = await window.fetch(config.guestTokenUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      product_id: Number(config.productId)
    }),
    signal
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.message || messages.genericError);
  }
  config.tokens = payload;
}

/**
 * Parse a JSON REST response and keep only its public message.
 *
 * @param {Response} response Fetch response.
 */
async function readJsonResponse(response) {
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload.message || messages.genericError);
    error.code = payload.code || 'generation_failed';
    throw error;
  }
  return payload;
}

/**
 * Revoke a generated result object URL.
 *
 * @param {Object} state Root UI state.
 */
function clearResult(state) {
  if (state.resultUrl) {
    URL.revokeObjectURL(state.resultUrl);
    state.resultUrl = '';
  }
  if (state.resultImage) {
    state.resultImage.removeAttribute('src');
  }
  if (state.download) {
    state.download.removeAttribute('href');
  }
  if (state.result) {
    state.result.hidden = true;
  }
  if (state.zoom) {
    state.zoom.disabled = true;
  }
}

/**
 * Open the generated result with WooCommerce's PhotoSwipe viewer.
 *
 * @param {Object} state Root UI state.
 */
function openResultLightbox(state) {
  const src = state.resultImage.currentSrc || state.resultImage.src;
  if (!src) {
    return;
  }
  const template = document.querySelector('#photoswipe-fullscreen-dialog.pswp') || document.querySelector('.pswp');
  const PhotoSwipe = window.PhotoSwipe;
  const PhotoSwipeUI = window.PhotoSwipeUI_Default;
  if (!template || typeof PhotoSwipe !== 'function' || typeof PhotoSwipeUI !== 'function') {
    window.open(src, '_blank', 'noopener,noreferrer');
    return;
  }
  const rect = state.resultImage.getBoundingClientRect();
  const options = {
    ...(window.wc_single_product_params?.photoswipe_options || {}),
    index: 0,
    history: false,
    shareEl: false,
    closeOnScroll: false,
    getThumbBoundsFn: () => ({
      x: rect.left + window.pageXOffset,
      y: rect.top + window.pageYOffset,
      w: rect.width
    })
  };
  const items = [{
    src,
    w: state.resultImage.naturalWidth || 1200,
    h: state.resultImage.naturalHeight || 1200,
    title: state.resultImage.alt
  }];
  const lightbox = new PhotoSwipe(template, PhotoSwipeUI, items, options);
  document.body.classList.add('sea-tryon-lightbox-open');
  lightbox.listen('destroy', () => {
    document.body.classList.remove('sea-tryon-lightbox-open');
    if (state.zoom.isConnected) {
      state.zoom.focus();
    }
  });
  lightbox.init();
}

/**
 * Render a safe public failure and allow a new attempt.
 *
 * @param {Object} state Root UI state.
 * @param {Error}  error Public REST or client error.
 */
function showFailure(state, error) {
  const message = error?.message || messages.genericError;
  setTryOnState(state.root, 'failed');
  setStatus(state, '');
  state.error.textContent = message;
  state.error.hidden = false;
  if (state.retry) {
    state.retry.hidden = false;
    state.retry.focus();
  }
}

/**
 * Fetch and display an authenticated result without leaking its private URL.
 *
 * @param {Object} state  Root UI state.
 * @param {Object} job    Terminal job resource.
 * @param {Object} config Runtime configuration.
 */
async function loadResult(state, job, config) {
  const response = await window.fetch(job.result_url, {
    credentials: 'same-origin',
    headers: authHeaders(config, 'result'),
    signal: state.controller.signal
  });
  if (!response.ok) {
    await readJsonResponse(response);
  }
  const blob = await response.blob();
  if (!ALLOWED_TYPES.includes(blob.type)) {
    throw new Error(messages.genericError);
  }
  const previousResultJobId = state.resultJobId;
  clearResult(state);
  state.resultUrl = URL.createObjectURL(blob);
  state.resultJobId = job.id;
  state.resultImage.src = state.resultUrl;
  state.download.href = state.resultUrl;
  state.download.download = `virtual-try-on-${job.id}.${blob.type === 'image/jpeg' ? 'jpg' : 'png'}`;
  state.result.hidden = false;
  setTryOnState(state.root, 'succeeded', messages.succeeded);
  if (previousResultJobId && previousResultJobId !== job.id) {
    deleteJob(state, previousResultJobId, true);
  }
}

/**
 * Poll a job until it reaches a terminal state.
 *
 * @param {Object} state  Root UI state.
 * @param {Object} job    Initial job resource.
 * @param {Object} config Runtime configuration.
 */
async function pollJob(state, job, config) {
  let started = Date.now();
  let interval = POLL_INITIAL_INTERVAL_MS;
  let current = job;
  while (Date.now() - started < MAX_POLL_DURATION_MS) {
    if (current.status === 'succeeded') {
      await loadResult(state, current, config);
      return;
    }
    if (current.status === 'failed' || current.status === 'expired') {
      throw new Error(current.error?.message || messages.genericError);
    }
    if (document.hidden) {
      const hiddenAt = Date.now();
      await new Promise((resolve, reject) => {
        const onVisibilityChange = () => {
          if (!document.hidden) {
            cleanup();
            resolve();
          }
        };
        const onAbort = () => {
          cleanup();
          reject(new DOMException('Aborted', 'AbortError'));
        };
        const cleanup = () => {
          document.removeEventListener('visibilitychange', onVisibilityChange);
          state.controller.signal.removeEventListener('abort', onAbort);
        };
        document.addEventListener('visibilitychange', onVisibilityChange);
        state.controller.signal.addEventListener('abort', onAbort, {
          once: true
        });
      });
      started += Date.now() - hiddenAt;
    }
    await new Promise(resolve => window.setTimeout(resolve, interval));
    const response = await window.fetch(`${config.restRoot}jobs/${encodeURIComponent(current.id)}`, {
      credentials: 'same-origin',
      headers: authHeaders(config, 'status'),
      signal: state.controller.signal
    });
    current = await readJsonResponse(response);
    interval = Math.min(POLL_MAX_INTERVAL_MS, interval * 2);
  }
  throw new Error(messages.genericError);
}

/**
 * Submit a local file and start the asynchronous result flow.
 *
 * @param {Object} state Root UI state.
 */
async function submitJob(state) {
  const config = getConfig(state);
  if (!config || config.authMode === 'required' || !state.uploadFile) {
    return;
  }
  state.controller?.abort();
  state.controller = new AbortController();
  state.retry.hidden = true;
  state.error.hidden = true;
  state.error.textContent = '';
  setTryOnState(state.root, 'submitting', messages.submitting);
  try {
    await refreshGuestTokens(config, state.controller.signal);
    const data = new FormData();
    data.append('product_id', String(config.productId));
    if (Number(state.root.dataset.variationId) > 0) {
      data.append('variation_id', state.root.dataset.variationId);
    }
    data.append('consent', 'true');
    if (!state.idempotencyKey) {
      state.idempotencyKey = createIdempotencyKey();
    }
    data.append('idempotency_key', state.idempotencyKey);
    data.append('image', state.uploadFile);
    const response = await window.fetch(`${config.restRoot}jobs`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: authHeaders(config, 'create'),
      body: data,
      signal: state.controller.signal
    });
    const job = await readJsonResponse(response);
    state.idempotencyKey = '';
    state.jobId = job.id;
    setTryOnState(state.root, 'processing', messages.processing);
    await pollJob(state, job, config);
  } catch (error) {
    if (error.name !== 'AbortError') {
      showFailure(state, error);
    }
  }
}

/**
 * Delete a server-side job and all of its private files.
 *
 * @param {Object}  state  Root UI state.
 * @param {string}  jobId  Job to delete; defaults to current job.
 * @param {boolean} silent Whether to suppress deletion feedback.
 */
async function deleteJob(state, jobId = state.jobId, silent = false) {
  const config = getConfig(state);
  if (!config || !jobId) {
    return;
  }
  try {
    await refreshGuestTokens(config);
    const response = await window.fetch(`${config.restRoot}jobs/${encodeURIComponent(jobId)}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: authHeaders(config, 'delete')
    });
    if (!response.ok) {
      await readJsonResponse(response);
    }
    if (state.jobId === jobId) {
      state.jobId = '';
    }
    if (state.resultJobId === jobId) {
      state.resultJobId = '';
      clearResult(state);
    }
    if (!silent) {
      setTryOnState(state.root, 'idle', '');
    }
  } catch (error) {
    if (!silent) {
      showFailure(state, error);
    }
  }
}

/**
 * Return focusable, currently available controls inside a dialog.
 *
 * @param {HTMLElement} dialog Dialog element.
 */
function getFocusableElements(dialog) {
  return Array.from(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(element => !element.closest('[hidden]'));
}

/**
 * Announce a non-sensitive UI status.
 *
 * @param {Object} state   Root UI state.
 * @param {string} message Message to announce.
 */
function setStatus(state, message) {
  state.status.textContent = message;
}

/**
 * Keep the generate button synchronized with upload, consent and busy state.
 *
 * @param {Object} state Root UI state.
 */
function updateGenerateState(state) {
  const completed = state.root.dataset.state === 'succeeded';
  state.generate.disabled = !state.uploadFile || !state.consent.checked || state.busy || state.optimizing || completed;
  state.file.disabled = state.busy || state.optimizing || completed;
  if (state.cameraOpen) {
    state.cameraOpen.disabled = state.busy || state.optimizing || state.cameraOpening || completed;
  }
  if (state.cameraCapture) {
    state.cameraCapture.disabled = state.busy || state.cameraOpening || state.cameraCapturing;
  }
  state.consent.disabled = state.busy || state.optimizing || completed;
  if (state.remove) {
    state.remove.disabled = state.busy || state.optimizing;
  }
}

/**
 * Return compact, human-readable metadata for the selected image.
 *
 * @param {File} file Selected image file.
 * @return {string} File type and size.
 */
function formatFileMeta(file) {
  const type = (file.type.split('/')[1] || 'image').replace('jpeg', 'JPG').replace('png', 'PNG').replace('webp', 'WebP');
  const size = `${(file.size / (1024 * 1024)).toFixed(1)} MB`;
  return `${type} · ${size}`;
}

/**
 * Decode a local image file using the browser's native image decoder.
 *
 * @param {File} file Local image file.
 * @return {Promise<HTMLImageElement>} Decoded image.
 */
function decodeLocalImage(file) {
  return new Promise((resolve, reject) => {
    const sourceUrl = URL.createObjectURL(file);
    const image = new window.Image();
    image.onload = () => {
      URL.revokeObjectURL(sourceUrl);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(sourceUrl);
      reject(new Error(messages.optimizationFailed));
    };
    image.src = sourceUrl;
  });
}

/**
 * Resize an image to a maximum width of 2,000 pixels and encode it as JPEG.
 *
 * @param {File} file Valid local JPEG, PNG, or WebP image.
 * @return {Promise<File>} JPEG upload file encoded at 90% quality.
 */
async function optimizeImageForUpload(file) {
  const image = await decodeLocalImage(file);
  const sourceWidth = image.naturalWidth || image.width;
  const sourceHeight = image.naturalHeight || image.height;
  if (sourceWidth < 1 || sourceHeight < 1) {
    throw new Error(messages.optimizationFailed);
  }
  const targetWidth = Math.min(sourceWidth, MAX_IMAGE_WIDTH);
  const targetHeight = Math.max(1, Math.round(sourceHeight * (targetWidth / sourceWidth)));
  const canvas = document.createElement('canvas');
  canvas.width = targetWidth;
  canvas.height = targetHeight;
  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error(messages.optimizationFailed);
  }
  context.imageSmoothingEnabled = true;
  context.imageSmoothingQuality = 'high';
  context.fillStyle = '#fff';
  context.fillRect(0, 0, targetWidth, targetHeight);
  context.drawImage(image, 0, 0, targetWidth, targetHeight);
  const blob = await new Promise((resolve, reject) => {
    canvas.toBlob(output => {
      if (output) {
        resolve(output);
      } else {
        reject(new Error(messages.optimizationFailed));
      }
    }, 'image/jpeg', JPEG_QUALITY);
  });
  const basename = file.name.replace(/\.[^/.]+$/, '') || 'try-on-photo';
  canvas.width = 1;
  canvas.height = 1;
  return new File([blob], `${basename}.jpg`, {
    type: 'image/jpeg',
    lastModified: file.lastModified
  });
}

/**
 * Revoke the local preview URL and clear upload state.
 *
 * @param {Object} state   Root UI state.
 * @param {string} message Optional status message.
 */
function clearFile(state, message = '') {
  stopCamera(state);
  ++state.fileSelectionId;
  state.uploadFile = null;
  state.optimizing = false;
  if (state.previewUrl) {
    URL.revokeObjectURL(state.previewUrl);
    state.previewUrl = '';
  }
  state.file.value = '';
  state.fileName.textContent = '';
  if (state.fileMeta) {
    state.fileMeta.textContent = '';
  }
  state.previewImage.removeAttribute('src');
  state.preview.hidden = true;
  state.root.dataset.hasFile = 'false';
  state.error.hidden = true;
  state.error.textContent = '';
  if (state.retry) {
    state.retry.hidden = true;
  }
  updateGenerateState(state);
  if (message) {
    setStatus(state, message);
  }
}

/**
 * Reset completed or failed output before opening the photo picker again.
 *
 * @param {Object} state Root UI state.
 */
function changePhoto(state) {
  if (state.busy || state.optimizing) {
    return;
  }
  state.controller?.abort();
  state.idempotencyKey = '';
  state.jobId = '';
  clearResult(state);
  setTryOnState(state.root, 'idle');
  clearFile(state, messages.imageRemoved);
  state.file.click();
}
let pageScrollLock = null;

/**
 * Freeze the storefront at its current coordinates while the modal is open.
 *
 * @return {void}
 */
function lockPageScroll() {
  if (pageScrollLock || !document.body) {
    return;
  }
  const html = document.documentElement;
  const body = document.body;
  const scrollX = window.scrollX || window.pageXOffset || 0;
  const scrollY = window.scrollY || window.pageYOffset || 0;
  const scrollbarWidth = Math.max(0, window.innerWidth - html.clientWidth);
  const computedPadding = Number.parseFloat(window.getComputedStyle(body).paddingRight);
  pageScrollLock = {
    scrollX,
    scrollY,
    htmlOverflow: html.style.overflow,
    htmlOverscrollBehavior: html.style.overscrollBehavior,
    htmlScrollBehavior: html.style.scrollBehavior,
    bodyPosition: body.style.position,
    bodyTop: body.style.top,
    bodyLeft: body.style.left,
    bodyRight: body.style.right,
    bodyWidth: body.style.width,
    bodyBoxSizing: body.style.boxSizing,
    bodyOverflow: body.style.overflow,
    bodyOverscrollBehavior: body.style.overscrollBehavior,
    bodyPaddingRight: body.style.paddingRight
  };
  html.classList.add('sea-tryon-modal-open');
  body.classList.add('sea-tryon-modal-open');
  html.style.overflow = 'hidden';
  html.style.overscrollBehavior = 'none';
  body.style.position = 'fixed';
  body.style.top = `-${scrollY}px`;
  body.style.left = `-${scrollX}px`;
  body.style.right = '0';
  body.style.width = '100%';
  body.style.boxSizing = 'border-box';
  body.style.overflow = 'hidden';
  body.style.overscrollBehavior = 'none';
  if (scrollbarWidth > 0) {
    body.style.paddingRight = `${(Number.isFinite(computedPadding) ? computedPadding : 0) + scrollbarWidth}px`;
  }
}

/**
 * Restore storefront scrolling and the exact styles that existed before lock.
 *
 * @return {void}
 */
function unlockPageScroll() {
  if (!pageScrollLock || !document.body) {
    return;
  }
  const html = document.documentElement;
  const body = document.body;
  const saved = pageScrollLock;
  pageScrollLock = null;
  html.classList.remove('sea-tryon-modal-open');
  body.classList.remove('sea-tryon-modal-open');
  html.style.overflow = saved.htmlOverflow;
  html.style.overscrollBehavior = saved.htmlOverscrollBehavior;
  body.style.position = saved.bodyPosition;
  body.style.top = saved.bodyTop;
  body.style.left = saved.bodyLeft;
  body.style.right = saved.bodyRight;
  body.style.width = saved.bodyWidth;
  body.style.boxSizing = saved.bodyBoxSizing;
  body.style.overflow = saved.bodyOverflow;
  body.style.overscrollBehavior = saved.bodyOverscrollBehavior;
  body.style.paddingRight = saved.bodyPaddingRight;
  html.style.scrollBehavior = 'auto';
  window.scrollTo(saved.scrollX, saved.scrollY);
  html.style.scrollBehavior = saved.htmlScrollBehavior;
}

/**
 * Close a dialog and restore focus to the trigger that opened it.
 *
 * @param {Object} state Root UI state.
 */
function closeDialog(state) {
  if (state.root.hidden) {
    return;
  }
  stopCamera(state);
  state.root.hidden = true;
  unlockPageScroll();
  if (state.trigger?.isConnected) {
    state.trigger.focus();
  }
  state.root.dispatchEvent(new CustomEvent('sea-tryon:closed', {
    bubbles: true
  }));
}

/**
 * Open a dialog and move focus into it.
 *
 * @param {Object}      state   Root UI state.
 * @param {HTMLElement} trigger Trigger that opened the dialog.
 */
function openDialog(state, trigger) {
  state.trigger = trigger;
  state.root.hidden = false;
  lockPageScroll();
  state.dialog.focus();
  state.root.dispatchEvent(new CustomEvent('sea-tryon:opened', {
    bubbles: true
  }));
}

/**
 * Trap Tab/Shift+Tab and support Escape.
 *
 * @param {KeyboardEvent} event Keyboard event.
 * @param {Object}        state Root UI state.
 */
function handleDialogKeydown(event, state) {
  if (event.key === 'Escape') {
    event.preventDefault();
    closeDialog(state);
    return;
  }
  if (event.key !== 'Tab') {
    return;
  }
  const focusable = getFocusableElements(state.dialog);
  if (focusable.length === 0) {
    event.preventDefault();
    state.dialog.focus();
    return;
  }
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  const activeElement = state.dialog.ownerDocument.activeElement;
  if (event.shiftKey && (activeElement === first || activeElement === state.dialog)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

/**
 * Stop all active camera tracks and restore the image source choices.
 *
 * @param {Object}  state        Root UI state.
 * @param {boolean} restoreFocus Whether to focus the camera button.
 */
function stopCamera(state, restoreFocus = false) {
  if (!state.camera) {
    return;
  }
  ++state.cameraRequestId;
  state.cameraStream?.getTracks().forEach(track => track.stop());
  if (state.cameraStream || state.cameraVideo.srcObject) {
    state.cameraVideo.pause?.();
  }
  state.cameraStream = null;
  state.cameraOpening = false;
  state.cameraCapturing = false;
  state.cameraVideo.srcObject = null;
  state.camera.hidden = true;
  state.sources.hidden = false;
  updateGenerateState(state);
  if (restoreFocus && state.cameraOpen.isConnected) {
    state.cameraOpen.focus();
  }
}

/**
 * Show a camera failure without hiding the upload fallback.
 *
 * @param {Object} state   Root UI state.
 * @param {string} message Public failure message.
 */
function showCameraFailure(state, message) {
  stopCamera(state);
  setStatus(state, '');
  state.error.textContent = message;
  state.error.hidden = false;
}

/**
 * Open the device camera inside the modal.
 *
 * @param {Object} state Root UI state.
 */
async function openCamera(state) {
  if (state.busy || state.optimizing || state.cameraOpening) {
    return;
  }
  if (!navigator.mediaDevices?.getUserMedia) {
    showCameraFailure(state, messages.cameraUnsupported);
    return;
  }
  const requestId = ++state.cameraRequestId;
  state.cameraOpening = true;
  state.error.hidden = true;
  state.error.textContent = '';
  setStatus(state, messages.cameraRequesting);
  updateGenerateState(state);
  try {
    const facingMode = state.root.dataset.experienceMode === 'scene' ? 'environment' : 'user';
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: {
        facingMode: {
          ideal: facingMode
        },
        width: {
          ideal: 1920
        },
        height: {
          ideal: 1080
        }
      }
    });
    if (requestId !== state.cameraRequestId || state.root.hidden) {
      stream.getTracks().forEach(track => track.stop());
      return;
    }
    state.cameraStream = stream;
    state.cameraOpening = false;
    state.cameraVideo.srcObject = stream;
    state.sources.hidden = true;
    state.camera.hidden = false;
    await state.cameraVideo.play?.();
    setStatus(state, messages.cameraReady);
    updateGenerateState(state);
    state.cameraCapture.focus();
  } catch (error) {
    if (requestId !== state.cameraRequestId) {
      return;
    }
    const denied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
    showCameraFailure(state, denied ? messages.cameraPermissionDenied : messages.cameraFailed);
  }
}

/**
 * Capture the current video frame and pass it through normal image processing.
 *
 * @param {Object} state Root UI state.
 */
async function captureCameraPhoto(state) {
  if (state.cameraCapturing) {
    return;
  }
  const width = state.cameraVideo.videoWidth;
  const height = state.cameraVideo.videoHeight;
  if (!state.cameraStream || width < 1 || height < 1) {
    state.error.textContent = messages.captureFailed;
    state.error.hidden = false;
    return;
  }
  state.cameraCapturing = true;
  updateGenerateState(state);
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext('2d');
  if (!context) {
    showCameraFailure(state, messages.captureFailed);
    return;
  }
  context.drawImage(state.cameraVideo, 0, 0, width, height);
  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
  canvas.width = 1;
  canvas.height = 1;
  if (!blob) {
    showCameraFailure(state, messages.captureFailed);
    return;
  }
  const file = new File([blob], `camera-photo-${Date.now()}.jpg`, {
    type: 'image/jpeg',
    lastModified: Date.now()
  });
  stopCamera(state);
  await handleSelectedFile(state, file);
}

/**
 * Validate and preview a local file without uploading it.
 *
 * @param {Object} state Root UI state.
 * @param {File}   file  Image selected from storage or captured by camera.
 */
async function handleSelectedFile(state, file) {
  const selectionId = ++state.fileSelectionId;
  if (!file) {
    clearFile(state);
    return;
  }
  let error = '';
  if (!ALLOWED_TYPES.includes(file.type)) {
    error = messages.invalidType;
  } else if (file.size > Math.min(MAX_FILE_BYTES, Number(getConfig(state)?.maxUploadBytes) || MAX_FILE_BYTES)) {
    error = messages.fileTooLarge;
  }
  if (error) {
    clearFile(state);
    setStatus(state, '');
    state.error.textContent = error;
    state.error.hidden = false;
    return;
  }
  state.uploadFile = null;
  state.optimizing = true;
  state.root.dataset.hasFile = 'false';
  state.error.hidden = true;
  state.error.textContent = '';
  setStatus(state, messages.optimizing);
  updateGenerateState(state);
  let uploadFile;
  try {
    uploadFile = await optimizeImageForUpload(file);
  } catch (optimizationError) {
    if (selectionId !== state.fileSelectionId) {
      return;
    }
    const failureMessage = optimizationError?.message || messages.optimizationFailed;
    clearFile(state);
    setStatus(state, '');
    state.error.textContent = failureMessage;
    state.error.hidden = false;
    return;
  }
  if (selectionId !== state.fileSelectionId) {
    return;
  }
  const maxUploadBytes = Math.min(MAX_FILE_BYTES, Number(getConfig(state)?.maxUploadBytes) || MAX_FILE_BYTES);
  if (uploadFile.size > maxUploadBytes) {
    clearFile(state);
    setStatus(state, '');
    state.error.textContent = messages.fileTooLarge;
    state.error.hidden = false;
    return;
  }
  if (state.previewUrl) {
    URL.revokeObjectURL(state.previewUrl);
  }
  state.uploadFile = uploadFile;
  state.optimizing = false;
  state.previewUrl = URL.createObjectURL(uploadFile);
  state.previewImage.src = state.previewUrl;
  state.fileName.textContent = uploadFile.name;
  if (state.fileMeta) {
    state.fileMeta.textContent = formatFileMeta(uploadFile);
  }
  state.preview.hidden = false;
  state.root.dataset.hasFile = 'true';
  state.error.hidden = true;
  state.error.textContent = '';
  state.retry.hidden = true;
  setTryOnState(state.root, 'idle');
  setStatus(state, messages.imageSelected);
  updateGenerateState(state);
}

/**
 * Pass the file input selection into the shared image pipeline.
 *
 * @param {Object} state Root UI state.
 */
async function handleFileChange(state) {
  const [file] = state.file.files || [];
  await handleSelectedFile(state, file);
}

/**
 * Invalidate the local input when a variable product selection changes.
 *
 * @param {HTMLElement}   root        Modal root.
 * @param {number|string} variationId Selected variation ID, or zero.
 * @return {void}
 */
function invalidateForVariation(root, variationId) {
  const state = roots.get(root);
  if (!state) {
    return;
  }
  const normalized = String(variationId || '0');
  if (state.root.dataset.variationId === normalized) {
    return;
  }
  state.root.dataset.variationId = normalized;
  state.controller?.abort();
  const staleJobs = [...new Set([state.jobId, state.resultJobId].filter(Boolean))];
  for (const staleJob of staleJobs) {
    deleteJob(state, staleJob, true);
  }
  state.jobId = '';
  state.resultJobId = '';
  state.idempotencyKey = '';
  clearResult(state);
  clearFile(state, messages.variationChanged);
  state.root.dispatchEvent(new CustomEvent('sea-tryon:variation-invalidated', {
    bubbles: true,
    detail: {
      variationId: normalized
    }
  }));
}

/**
 * Set a testable client state without coupling the shell to the M5 REST API.
 *
 * @param {HTMLElement} root      Modal root.
 * @param {string}      nextState New stable state name.
 * @param {string}      message   Optional live-region message.
 */
function setTryOnState(root, nextState, message = '') {
  const state = roots.get(root);
  if (!state) {
    return;
  }
  state.busy = nextState === 'submitting' || nextState === 'processing';
  state.root.dataset.state = nextState;
  state.dialog.setAttribute('aria-busy', state.busy ? 'true' : 'false');
  if (state.progress) {
    state.progress.setAttribute('aria-hidden', state.busy ? 'false' : 'true');
  }
  updateGenerateState(state);
  if (message) {
    setStatus(state, message);
  }
  state.root.dispatchEvent(new CustomEvent('sea-tryon:state-changed', {
    bubbles: true,
    detail: {
      state: nextState
    }
  }));
}

/**
 * Attach the accessible shell behavior to one server-rendered root.
 *
 * @param {HTMLElement} root Modal root.
 */
function initializeRoot(root) {
  if (root.hasAttribute(READY_ATTRIBUTE)) {
    return;
  }
  const state = {
    root,
    dialog: root.querySelector('[role="dialog"]'),
    backdrop: root.querySelector('[data-sea-tryon-backdrop]'),
    file: root.querySelector('[data-sea-tryon-file]'),
    sources: root.querySelector('[data-sea-tryon-sources]'),
    cameraOpen: root.querySelector('[data-sea-tryon-camera-open]'),
    camera: root.querySelector('[data-sea-tryon-camera]'),
    cameraVideo: root.querySelector('[data-sea-tryon-camera-video]'),
    cameraCapture: root.querySelector('[data-sea-tryon-camera-capture]'),
    cameraCancel: root.querySelector('[data-sea-tryon-camera-cancel]'),
    preview: root.querySelector('[data-sea-tryon-preview]'),
    previewImage: root.querySelector('[data-sea-tryon-preview-image]'),
    fileName: root.querySelector('[data-sea-tryon-file-name]'),
    fileMeta: root.querySelector('[data-sea-tryon-file-meta]'),
    remove: root.querySelector('[data-sea-tryon-remove]'),
    error: root.querySelector('[data-sea-tryon-upload-error]'),
    consent: root.querySelector('[data-sea-tryon-consent]'),
    generate: root.querySelector('[data-sea-tryon-generate]'),
    status: root.querySelector('[data-sea-tryon-status]'),
    progress: root.querySelector('[data-sea-tryon-progress]'),
    login: root.querySelector('[data-sea-tryon-login]'),
    loginLink: root.querySelector('[data-sea-tryon-login-link]'),
    workflow: root.querySelector('[data-sea-tryon-workflow]'),
    retry: root.querySelector('[data-sea-tryon-error-retry]'),
    result: root.querySelector('[data-sea-tryon-result]'),
    resultImage: root.querySelector('[data-sea-tryon-result-image]'),
    zoom: root.querySelector('[data-sea-tryon-zoom]'),
    download: root.querySelector('[data-sea-tryon-download]'),
    previewUrl: '',
    resultUrl: '',
    jobId: '',
    resultJobId: '',
    idempotencyKey: '',
    uploadFile: null,
    fileSelectionId: 0,
    optimizing: false,
    cameraStream: null,
    cameraOpening: false,
    cameraCapturing: false,
    cameraRequestId: 0,
    controller: null,
    trigger: null,
    busy: false
  };
  const requiredElements = [state.dialog, state.backdrop, state.file, state.sources, state.cameraOpen, state.camera, state.cameraVideo, state.cameraCapture, state.cameraCancel, state.preview, state.previewImage, state.fileName, state.remove, state.error, state.consent, state.generate, state.status, state.login, state.loginLink, state.workflow, state.retry, state.result, state.resultImage, state.zoom, state.download];
  if (requiredElements.some(value => value === null)) {
    return;
  }
  roots.set(root, state);
  root.setAttribute(READY_ATTRIBUTE, 'true');
  root.dataset.state = 'idle';
  root.dataset.variationId = '0';
  root.dataset.hasFile = 'false';
  const config = getConfig(state);
  if (config?.authMode === 'required') {
    state.login.hidden = false;
    state.loginLink.href = config.loginUrl;
    state.workflow.hidden = true;
  }
  root.addEventListener('keydown', event => handleDialogKeydown(event, state));
  root.addEventListener('click', event => {
    const close = event.target.closest('[data-sea-tryon-close]');
    if (close || event.target === state.backdrop) {
      closeDialog(state);
    }
  });
  state.file.addEventListener('change', () => handleFileChange(state));
  state.cameraOpen.addEventListener('click', () => openCamera(state));
  state.cameraCapture.addEventListener('click', () => captureCameraPhoto(state));
  state.cameraCancel.addEventListener('click', () => {
    stopCamera(state, true);
    setStatus(state, '');
  });
  state.consent.addEventListener('change', () => updateGenerateState(state));
  state.remove.addEventListener('click', () => changePhoto(state));
  state.generate.addEventListener('click', () => {
    if (state.generate.disabled) {
      return;
    }
    const event = new CustomEvent('sea-tryon:generate-requested', {
      bubbles: true,
      cancelable: true,
      detail: {
        productId: root.dataset.productId,
        variationId: root.dataset.variationId,
        file: state.uploadFile
      }
    });
    root.dispatchEvent(event);
    if (!event.defaultPrevented) {
      submitJob(state);
    }
  });
  state.retry?.addEventListener('click', () => submitJob(state));
  state.resultImage.addEventListener('load', () => {
    state.zoom.disabled = !state.resultUrl;
  });
  state.zoom.addEventListener('click', () => openResultLightbox(state));
  root.querySelector('[data-sea-tryon-try-again]')?.addEventListener('click', () => {
    state.idempotencyKey = '';
    submitJob(state);
  });
  root.querySelector('[data-sea-tryon-delete]')?.addEventListener('click', () => deleteJob(state, state.resultJobId || state.jobId));
  root.dispatchEvent(new CustomEvent('sea-tryon:ready', {
    bubbles: true
  }));
}

/** Initialize every newly rendered modal root without duplicate listeners. */
function initializeTryOnRoots() {
  document.querySelectorAll(ROOT_SELECTOR).forEach(initializeRoot);
}

/** Open triggers use delegation so block and hook markup share one listener. */
document.addEventListener('click', event => {
  const trigger = event.target.closest('[data-sea-tryon-open]');
  if (!trigger) {
    return;
  }
  const productId = String(trigger.dataset.productId || '').replace(/[^0-9]/g, '');
  const selector = `${ROOT_SELECTOR}[data-product-id="${productId}"]`;
  const root = document.querySelector(selector);
  const state = root ? roots.get(root) : null;
  if (state) {
    openDialog(state, trigger);
  }
});

/** Observe WooCommerce's public variation form events without guessing M5 data. */
if (window.jQuery) {
  window.jQuery(document).on('found_variation.seaTryOn', 'form.variations_form', (event, variation) => {
    const productId = event.currentTarget.dataset.product_id;
    const normalized = String(productId || '').replace(/[^0-9]/g, '');
    const root = document.querySelector(`${ROOT_SELECTOR}[data-product-id="${normalized}"]`);
    if (root) {
      invalidateForVariation(root, variation?.variation_id || 0);
    }
  });
  window.jQuery(document).on('reset_data.seaTryOn hide_variation.seaTryOn', 'form.variations_form', event => {
    const productId = event.currentTarget.dataset.product_id;
    const normalized = String(productId || '').replace(/[^0-9]/g, '');
    const root = document.querySelector(`${ROOT_SELECTOR}[data-product-id="${normalized}"]`);
    if (root) {
      invalidateForVariation(root, 0);
    }
  });
}
window.SeaTryOnUI = Object.freeze({
  initialize: initializeTryOnRoots,
  invalidateForVariation,
  setState: setTryOnState
});
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeTryOnRoots, {
    once: true
  });
} else {
  initializeTryOnRoots();
}
})();

/******/ })()
;
//# sourceMappingURL=frontend.js.map