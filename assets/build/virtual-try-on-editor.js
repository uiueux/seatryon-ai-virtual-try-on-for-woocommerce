/******/ (() => { // webpackBootstrap
/*!********************************************************!*\
  !*** ./blocks/virtual-try-on/virtual-try-on-editor.js ***!
  \********************************************************/
const {
  registerBlockType
} = window.wp.blocks;
const {
  Placeholder
} = window.wp.components;
const {
  useBlockProps
} = window.wp.blockEditor;
const {
  createElement
} = window.wp.element;
const {
  __
} = window.wp.i18n;
registerBlockType('sea-tryon/virtual-try-on', {
  edit: function Edit() {
    return createElement('div', useBlockProps(), createElement(Placeholder, {
      icon: 'visibility',
      label: __('Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce'),
      instructions: __('The button appears here when the current product is eligible. Eligibility is checked on the storefront.', 'seatryon-ai-virtual-try-on-for-woocommerce')
    }));
  },
  save: () => null
});
/******/ })()
;
//# sourceMappingURL=virtual-try-on-editor.js.map