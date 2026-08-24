/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/src/admin.scss"
/*!*******************************!*\
  !*** ./assets/src/admin.scss ***!
  \*******************************/
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
/*!*****************************!*\
  !*** ./assets/src/admin.js ***!
  \*****************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   initializeAdminRoots: () => (/* binding */ initializeAdminRoots),
/* harmony export */   initializeProductImageSelectors: () => (/* binding */ initializeProductImageSelectors),
/* harmony export */   initializeSeaAIConnectionTests: () => (/* binding */ initializeSeaAIConnectionTests)
/* harmony export */ });
/* harmony import */ var _admin_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./admin.scss */ "./assets/src/admin.scss");

const ADMIN_ROOT_SELECTOR = '[data-sea-tryon-admin]';
const PRODUCT_IMAGE_SELECTOR = '[data-sea-tryon-product-image]';
const SEAAI_KEY_SELECTOR = '[data-sea-tryon-seaai-key="true"]';

/**
 * Initialize Sea Try-On settings roots without assuming a specific admin screen.
 *
 * @return {void}
 */
function initializeAdminRoots() {
  document.querySelectorAll(ADMIN_ROOT_SELECTOR).forEach(root => {
    root.classList.add('is-ready');
  });
}

/**
 * Initialize optional product-image media selectors in the classic editor.
 *
 * @return {void}
 */
function initializeProductImageSelectors() {
  document.querySelectorAll(PRODUCT_IMAGE_SELECTOR).forEach(root => {
    if (root.dataset.seaTryOnInitialized === 'true') {
      return;
    }
    const input = root.querySelector('input[type="hidden"]');
    const preview = root.querySelector('[data-sea-tryon-image-preview]');
    const selectButton = root.querySelector('[data-sea-tryon-image-select]');
    const removeButton = root.querySelector('[data-sea-tryon-image-remove]');
    if (!input || !preview || !selectButton || !removeButton) {
      return;
    }
    root.dataset.seaTryOnInitialized = 'true';
    let mediaFrame;
    selectButton.addEventListener('click', () => {
      if (!window.wp?.media) {
        return;
      }
      if (!mediaFrame) {
        mediaFrame = window.wp.media({
          title: root.dataset.mediaTitle || 'Select a Try-On Product Image',
          button: {
            text: root.dataset.mediaButton || 'Use this image'
          },
          library: {
            type: 'image'
          },
          multiple: false
        });
        mediaFrame.on('select', () => {
          const attachment = mediaFrame.state().get('selection').first();
          const id = attachment?.get('id');
          const sizes = attachment?.get('sizes') || {};
          const url = sizes.thumbnail?.url || attachment?.get('url');
          if (!id || !url) {
            return;
          }
          input.value = String(id);
          preview.replaceChildren();
          const image = document.createElement('img');
          image.src = url;
          image.alt = '';
          image.className = 'sea-tryon-product-image__thumbnail';
          preview.appendChild(image);
          selectButton.textContent = root.dataset.changeLabel || 'Change image';
          removeButton.hidden = false;
        });
      }
      mediaFrame.open();
    });
    removeButton.addEventListener('click', () => {
      input.value = '';
      preview.replaceChildren();
      selectButton.textContent = root.dataset.selectLabel || 'Select image';
      removeButton.hidden = true;
    });
  });
}

/**
 * Add a non-generating SeaAI URL/key test beside the native password field.
 *
 * @return {void}
 */
function initializeSeaAIConnectionTests() {
  document.querySelectorAll(SEAAI_KEY_SELECTOR).forEach(keyInput => {
    if (keyInput.dataset.seaTryOnTestInitialized === 'true') {
      return;
    }
    const config = window.sea_tryon_seaai_connection;
    const urlInput = document.getElementById('sea_tryon_seaai_base_url');
    if (!config?.ajaxUrl || !config?.nonce || !urlInput) {
      return;
    }
    keyInput.dataset.seaTryOnTestInitialized = 'true';
    const getKeyLink = document.createElement('a');
    getKeyLink.className = 'button sea-tryon-seaai-key-link';
    getKeyLink.href = config.getKeyUrl || 'https://theminitech.net/profile/';
    getKeyLink.target = '_blank';
    getKeyLink.rel = 'noopener noreferrer';
    getKeyLink.textContent = config.messages?.getKey || 'Get a key for free';
    const component = document.createElement('span');
    component.className = 'sea-tryon-seaai-test';
    component.dataset.seaTryOnSeaaiTest = 'true';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button sea-tryon-seaai-test__button';
    button.textContent = config.messages?.button || 'Test connection';
    const spinner = document.createElement('span');
    spinner.className = 'spinner sea-tryon-seaai-test__spinner';
    spinner.setAttribute('aria-hidden', 'true');
    const status = document.createElement('p');
    status.className = 'description sea-tryon-seaai-test__status';
    status.id = 'sea-tryon-seaai-test-status';
    status.setAttribute('aria-live', 'polite');
    component.append(button, spinner);
    keyInput.insertAdjacentElement('afterend', getKeyLink);
    getKeyLink.insertAdjacentElement('afterend', component);
    component.insertAdjacentElement('afterend', status);
    const describedBy = keyInput.getAttribute('aria-describedby');
    keyInput.setAttribute('aria-describedby', [describedBy, status.id].filter(Boolean).join(' '));
    button.addEventListener('click', async () => {
      button.disabled = true;
      component.classList.add('is-testing');
      status.dataset.state = 'testing';
      status.textContent = config.messages?.testing || 'Testing connection…';
      const body = new URLSearchParams({
        action: config.action,
        nonce: config.nonce,
        base_url: urlInput.value,
        api_key: keyInput.value
      });
      try {
        const response = await window.fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: body.toString()
        });
        const payload = await response.json();
        const success = payload?.success === true;
        status.dataset.state = success ? 'success' : 'error';
        status.textContent = payload?.data?.message || config.messages?.failed || 'The connection test failed. Please try again.';
      } catch {
        status.dataset.state = 'error';
        status.textContent = config.messages?.failed || 'The connection test failed. Please try again.';
      } finally {
        button.disabled = false;
        component.classList.remove('is-testing');
      }
    });
  });
}
function initializeAdmin() {
  initializeAdminRoots();
  initializeProductImageSelectors();
  initializeSeaAIConnectionTests();
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAdmin, {
    once: true
  });
} else {
  initializeAdmin();
}
})();

/******/ })()
;
//# sourceMappingURL=admin.js.map