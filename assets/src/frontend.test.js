import {
	initializeTryOnRoots,
	invalidateForVariation,
	setTryOnState,
} from './frontend';
import axe from 'axe-core';

const NativeImage = window.Image;
const nativeGetContext = window.HTMLCanvasElement.prototype.getContext;
const nativeToBlob = window.HTMLCanvasElement.prototype.toBlob;
const nativeScrollTo = window.scrollTo;
const nativeMediaDevices = navigator.mediaDevices;
let encodedType;
let encodedQuality;
let encodedWidth;
let encodedHeight;
let renderedCanvas;
let sourceWidth;
let sourceHeight;

/**
 * Select a file and wait for the native image optimization promise chain.
 *
 * @param {HTMLInputElement} input File input.
 * @param {File}             image Image to select.
 */
async function selectImage( input, image ) {
	Object.defineProperty( input, 'files', {
		configurable: true,
		value: [ image ],
	} );
	input.dispatchEvent( new Event( 'change' ) );

	for ( let step = 0; step < 8; step++ ) {
		await Promise.resolve();
	}
}

function fixture() {
	document.body.innerHTML = `
		<button type="button" data-sea-tryon-open data-product-id="7">Open</button>
		<div data-sea-tryon-root data-product-id="7" data-experience-mode="person" hidden>
			<div data-sea-tryon-backdrop>
				<div role="dialog" aria-modal="true" aria-labelledby="try-on-title" tabindex="-1">
					<h2 id="try-on-title">Virtual Try-On</h2>
					<button type="button" data-sea-tryon-close>Close</button>
					<div data-sea-tryon-login hidden><a data-sea-tryon-login-link href="#">Log in</a></div>
					<div data-sea-tryon-workflow>
					<div data-sea-tryon-sources>
						<input type="file" data-sea-tryon-file>
						<button type="button" data-sea-tryon-camera-open>Take a photo</button>
					</div>
					<div data-sea-tryon-camera hidden>
						<video data-sea-tryon-camera-video></video>
						<button type="button" data-sea-tryon-camera-capture>Capture photo</button>
						<button type="button" data-sea-tryon-camera-cancel>Cancel camera</button>
					</div>
					<p data-sea-tryon-upload-error hidden></p>
					<div data-sea-tryon-preview hidden><img data-sea-tryon-preview-image><p data-sea-tryon-file-name></p><button type="button" data-sea-tryon-remove>Remove</button></div>
					<input type="checkbox" data-sea-tryon-consent>
					<button type="button" data-sea-tryon-generate disabled>Generate</button>
					<button type="button" data-sea-tryon-error-retry hidden>Retry</button>
					<button type="button" data-sea-tryon-close>Cancel</button>
					<p data-sea-tryon-status></p>
					<div data-sea-tryon-result hidden>
						<img data-sea-tryon-result-image>
						<button type="button" data-sea-tryon-zoom disabled>Zoom</button>
						<a data-sea-tryon-download>Download</a>
						<button type="button" data-sea-tryon-try-again>Try again</button>
						<button type="button" data-sea-tryon-delete>Delete</button>
					</div>
					</div>
				</div>
			</div>
		</div>`;

	initializeTryOnRoots();

	return {
		trigger: document.querySelector( '[data-sea-tryon-open]' ),
		root: document.querySelector( '[data-sea-tryon-root]' ),
		dialog: document.querySelector( '[role="dialog"]' ),
		close: document.querySelector( '[data-sea-tryon-close]' ),
		file: document.querySelector( '[data-sea-tryon-file]' ),
		cameraOpen: document.querySelector( '[data-sea-tryon-camera-open]' ),
		camera: document.querySelector( '[data-sea-tryon-camera]' ),
		cameraVideo: document.querySelector( '[data-sea-tryon-camera-video]' ),
		cameraCapture: document.querySelector(
			'[data-sea-tryon-camera-capture]'
		),
		cameraCancel: document.querySelector(
			'[data-sea-tryon-camera-cancel]'
		),
		preview: document.querySelector( '[data-sea-tryon-preview]' ),
		remove: document.querySelector( '[data-sea-tryon-remove]' ),
		consent: document.querySelector( '[data-sea-tryon-consent]' ),
		generate: document.querySelector( '[data-sea-tryon-generate]' ),
		result: document.querySelector( '[data-sea-tryon-result]' ),
		resultImage: document.querySelector( '[data-sea-tryon-result-image]' ),
		zoom: document.querySelector( '[data-sea-tryon-zoom]' ),
		tryAgain: document.querySelector( '[data-sea-tryon-try-again]' ),
		login: document.querySelector( '[data-sea-tryon-login]' ),
		workflow: document.querySelector( '[data-sea-tryon-workflow]' ),
	};
}

describe( 'Virtual Try-On modal shell', () => {
	beforeEach( () => {
		window.scrollTo = jest.fn();
		window.URL.createObjectURL = jest.fn( () => 'blob:preview' );
		window.URL.revokeObjectURL = jest.fn();
		encodedType = '';
		encodedQuality = 0;
		encodedWidth = 0;
		encodedHeight = 0;
		renderedCanvas = null;
		sourceWidth = 2400;
		sourceHeight = 1800;
		window.Image = class MockImage {
			constructor() {
				this.naturalWidth = sourceWidth;
				this.naturalHeight = sourceHeight;
			}

			set src( value ) {
				this.source = value;
				Promise.resolve().then( () => this.onload?.() );
			}
		};
		window.HTMLCanvasElement.prototype.getContext = jest.fn( function () {
			renderedCanvas = this;

			return {
				drawImage: jest.fn(),
				fillRect: jest.fn(),
				fillStyle: '',
				imageSmoothingEnabled: false,
				imageSmoothingQuality: 'low',
			};
		} );
		window.HTMLCanvasElement.prototype.toBlob = jest.fn(
			( callback, type, quality ) => {
				encodedType = type;
				encodedQuality = quality;
				encodedWidth = renderedCanvas.width;
				encodedHeight = renderedCanvas.height;
				callback( new Blob( [ 'jpeg' ], { type } ) );
			}
		);
	} );

	afterEach( () => {
		const openDialog = document.querySelector(
			'[data-sea-tryon-root]:not([hidden]) [role="dialog"]'
		);
		openDialog?.dispatchEvent(
			new window.KeyboardEvent( 'keydown', {
				key: 'Escape',
				bubbles: true,
			} )
		);
		document.body.innerHTML = '';
		document.body.removeAttribute( 'style' );
		document.documentElement.removeAttribute( 'style' );
		document.documentElement.classList.remove( 'sea-tryon-modal-open' );
		document.body.classList.remove( 'sea-tryon-modal-open' );
		Object.defineProperty( window, 'scrollX', {
			configurable: true,
			value: 0,
		} );
		Object.defineProperty( window, 'scrollY', {
			configurable: true,
			value: 0,
		} );
		Object.defineProperty( window, 'innerWidth', {
			configurable: true,
			value: 1024,
		} );
		delete document.documentElement.clientWidth;
		delete window.SeaTryOnConfig;
		delete window.fetch;
		delete window.PhotoSwipe;
		delete window.PhotoSwipeUI_Default;
		Object.defineProperty( navigator, 'mediaDevices', {
			configurable: true,
			value: nativeMediaDevices,
		} );
		window.Image = NativeImage;
		window.HTMLCanvasElement.prototype.getContext = nativeGetContext;
		window.HTMLCanvasElement.prototype.toBlob = nativeToBlob;
		window.scrollTo = nativeScrollTo;
		document.body.classList.remove( 'sea-tryon-lightbox-open' );
	} );

	it( 'opens, closes with Escape, and restores trigger focus', () => {
		const { trigger, root, dialog } = fixture();
		trigger.focus();
		trigger.click();

		expect( root.hidden ).toBe( false );
		expect( document.activeElement ).toBe( dialog );
		expect(
			document.documentElement.classList.contains(
				'sea-tryon-modal-open'
			)
		).toBe( true );
		expect(
			document.body.classList.contains( 'sea-tryon-modal-open' )
		).toBe( true );

		dialog.dispatchEvent(
			new window.KeyboardEvent( 'keydown', {
				key: 'Escape',
				bubbles: true,
			} )
		);

		expect( root.hidden ).toBe( true );
		expect( document.activeElement ).toBe( trigger );
		expect(
			document.documentElement.classList.contains(
				'sea-tryon-modal-open'
			)
		).toBe( false );
		expect(
			document.body.classList.contains( 'sea-tryon-modal-open' )
		).toBe( false );
		expect( window.scrollTo ).toHaveBeenCalledWith( 0, 0 );
	} );

	it( 'freezes the page, compensates for the scrollbar, and restores its position and styles', () => {
		Object.defineProperty( window, 'scrollX', {
			configurable: true,
			value: 12,
		} );
		Object.defineProperty( window, 'scrollY', {
			configurable: true,
			value: 420,
		} );
		Object.defineProperty( window, 'innerWidth', {
			configurable: true,
			value: 1200,
		} );
		Object.defineProperty( document.documentElement, 'clientWidth', {
			configurable: true,
			value: 1180,
		} );
		document.documentElement.style.overflow = 'auto';
		document.documentElement.style.scrollBehavior = 'smooth';
		document.body.style.position = 'relative';
		document.body.style.overflow = 'visible';
		document.body.style.paddingRight = '5px';

		const { trigger, close } = fixture();
		trigger.click();

		expect( document.documentElement.style.overflow ).toBe( 'hidden' );
		expect( document.body.style.position ).toBe( 'fixed' );
		expect( document.body.style.top ).toBe( '-420px' );
		expect( document.body.style.left ).toBe( '-12px' );
		expect( document.body.style.paddingRight ).toBe( '25px' );

		close.click();

		expect( document.documentElement.style.overflow ).toBe( 'auto' );
		expect( document.documentElement.style.scrollBehavior ).toBe(
			'smooth'
		);
		expect( document.body.style.position ).toBe( 'relative' );
		expect( document.body.style.overflow ).toBe( 'visible' );
		expect( document.body.style.paddingRight ).toBe( '5px' );
		expect( window.scrollTo ).toHaveBeenCalledWith( 12, 420 );
	} );

	it( 'traps reverse Tab and does not duplicate initialization', () => {
		const { trigger, root, dialog } = fixture();
		const ready = jest.fn();
		root.addEventListener( 'sea-tryon:ready', ready );
		initializeTryOnRoots();
		trigger.click();

		dialog.dispatchEvent(
			new window.KeyboardEvent( 'keydown', {
				key: 'Tab',
				shiftKey: true,
				bubbles: true,
			} )
		);

		expect( document.activeElement.textContent ).toBe( 'Cancel' );
		expect( ready ).not.toHaveBeenCalled();
	} );

	it( 'resizes to 2000px wide and uploads a JPEG encoded at 90%', async () => {
		const { file, consent, generate, root } = fixture();
		const requested = jest.fn( ( event ) => event.preventDefault() );
		root.addEventListener( 'sea-tryon:generate-requested', requested );
		const image = new File( [ 'image' ], 'person.png', {
			type: 'image/png',
		} );
		await selectImage( file, image );
		expect( generate.disabled ).toBe( true );
		expect( encodedWidth ).toBe( 2000 );
		expect( encodedHeight ).toBe( 1500 );
		expect( encodedType ).toBe( 'image/jpeg' );
		expect( encodedQuality ).toBe( 0.9 );

		consent.checked = true;
		consent.dispatchEvent( new Event( 'change' ) );
		expect( generate.disabled ).toBe( false );
		generate.click();
		const upload = requested.mock.calls[ 0 ][ 0 ].detail.file;
		expect( upload.name ).toBe( 'person.jpg' );
		expect( upload.type ).toBe( 'image/jpeg' );

		setTryOnState( root, 'processing', 'Generating your preview.' );
		expect( generate.disabled ).toBe( true );
	} );

	it( 'does not upscale an image narrower than 2000px', async () => {
		sourceWidth = 1200;
		sourceHeight = 2400;
		const { file } = fixture();
		const image = new File( [ 'image' ], 'portrait.webp', {
			type: 'image/webp',
		} );

		await selectImage( file, image );

		expect( encodedWidth ).toBe( 1200 );
		expect( encodedHeight ).toBe( 2400 );
		expect( encodedType ).toBe( 'image/jpeg' );
		expect( encodedQuality ).toBe( 0.9 );
	} );

	it( 'captures a camera frame and reuses the normal image pipeline', async () => {
		const track = { stop: jest.fn() };
		const stream = { getTracks: jest.fn( () => [ track ] ) };
		const getUserMedia = jest.fn().mockResolvedValue( stream );
		Object.defineProperty( navigator, 'mediaDevices', {
			configurable: true,
			value: { getUserMedia },
		} );
		const {
			trigger,
			root,
			cameraOpen,
			camera,
			cameraVideo,
			cameraCapture,
			consent,
			generate,
		} = fixture();
		cameraVideo.play = jest.fn().mockResolvedValue();
		cameraVideo.pause = jest.fn();
		Object.defineProperty( cameraVideo, 'videoWidth', { value: 1280 } );
		Object.defineProperty( cameraVideo, 'videoHeight', { value: 960 } );
		const requested = jest.fn( ( event ) => event.preventDefault() );
		root.addEventListener( 'sea-tryon:generate-requested', requested );

		trigger.click();
		cameraOpen.click();
		for ( let step = 0; step < 4; step++ ) {
			await Promise.resolve();
		}

		expect( getUserMedia ).toHaveBeenCalledWith( {
			audio: false,
			video: {
				facingMode: { ideal: 'user' },
				width: { ideal: 1920 },
				height: { ideal: 1080 },
			},
		} );
		expect( camera.hidden ).toBe( false );
		expect( cameraVideo.srcObject ).toBe( stream );

		cameraCapture.click();
		for ( let step = 0; step < 12; step++ ) {
			await Promise.resolve();
		}
		expect( track.stop ).toHaveBeenCalledTimes( 1 );
		expect( camera.hidden ).toBe( true );

		consent.checked = true;
		consent.dispatchEvent( new Event( 'change' ) );
		generate.click();
		const captured = requested.mock.calls[ 0 ][ 0 ].detail.file;
		expect( captured.name ).toMatch( /^camera-photo-\d+\.jpg$/ );
		expect( captured.type ).toBe( 'image/jpeg' );
	} );

	it( 'stops the camera when the modal closes', async () => {
		const track = { stop: jest.fn() };
		Object.defineProperty( navigator, 'mediaDevices', {
			configurable: true,
			value: {
				getUserMedia: jest.fn().mockResolvedValue( {
					getTracks: () => [ track ],
				} ),
			},
		} );
		const { trigger, close, cameraOpen, cameraVideo } = fixture();
		cameraVideo.play = jest.fn().mockResolvedValue();
		cameraVideo.pause = jest.fn();

		trigger.click();
		cameraOpen.click();
		for ( let step = 0; step < 4; step++ ) {
			await Promise.resolve();
		}
		close.click();

		expect( track.stop ).toHaveBeenCalledTimes( 1 );
		expect( cameraVideo.srcObject ).toBeNull();
	} );

	it( 'opens the photo picker from Change photo after generation succeeds', async () => {
		const { file, preview, remove, result, resultImage, root } = fixture();
		const image = new File( [ 'image' ], 'person.jpg', {
			type: 'image/jpeg',
		} );
		await selectImage( file, image );
		result.hidden = false;
		resultImage.src = 'blob:result';
		setTryOnState( root, 'succeeded' );
		const openPicker = jest.spyOn( file, 'click' );

		expect( remove.disabled ).toBe( false );
		remove.click();

		expect( openPicker ).toHaveBeenCalledTimes( 1 );
		expect( root.dataset.state ).toBe( 'idle' );
		expect( root.dataset.hasFile ).toBe( 'false' );
		expect( preview.hidden ).toBe( true );
		expect( result.hidden ).toBe( true );
		expect( file.disabled ).toBe( false );
	} );

	it( 'invalidates an existing selection after a variation change', () => {
		const { root } = fixture();
		const invalidated = jest.fn();
		root.addEventListener( 'sea-tryon:variation-invalidated', invalidated );

		invalidateForVariation( root, 45 );

		expect( root.dataset.variationId ).toBe( '45' );
		expect( invalidated ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'opens the generated result with WooCommerce PhotoSwipe', () => {
		const { result, resultImage, zoom } = fixture();
		const listeners = {};
		const lightbox = {
			listen: jest.fn( ( event, callback ) => {
				listeners[ event ] = callback;
			} ),
			init: jest.fn(),
		};
		document.body.insertAdjacentHTML(
			'beforeend',
			'<div class="pswp"></div>'
		);
		window.PhotoSwipe = jest.fn( () => lightbox );
		window.PhotoSwipeUI_Default = jest.fn();
		result.hidden = false;
		resultImage.src = 'blob:preview';
		Object.defineProperty( resultImage, 'naturalWidth', { value: 1600 } );
		Object.defineProperty( resultImage, 'naturalHeight', { value: 1200 } );
		zoom.disabled = false;
		zoom.click();

		expect( window.PhotoSwipe ).toHaveBeenCalledWith(
			document.querySelector( '.pswp' ),
			window.PhotoSwipeUI_Default,
			[
				expect.objectContaining( {
					src: expect.stringContaining( 'blob:preview' ),
					w: 1600,
					h: 1200,
				} ),
			],
			expect.objectContaining( { history: false, shareEl: false } )
		);
		expect( lightbox.init ).toHaveBeenCalledTimes( 1 );
		expect(
			document.body.classList.contains( 'sea-tryon-lightbox-open' )
		).toBe( true );

		listeners.destroy();
		expect(
			document.body.classList.contains( 'sea-tryon-lightbox-open' )
		).toBe( false );
		expect( document.activeElement ).toBe( zoom );
	} );

	it( 'creates a job and loads the authenticated private result', async () => {
		window.SeaTryOnConfig = {
			productId: 7,
			restRoot: 'https://store.example/wp-json/sea-tryon/v1/',
			authMode: 'logged-in',
			nonce: 'rest-nonce',
			tokens: {},
		};
		window.fetch = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					id: 'a'.repeat( 32 ),
					status: 'succeeded',
					result_url:
						'https://store.example/wp-json/sea-tryon/v1/jobs/result',
				} ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				blob: async () => new Blob( [ 'png' ], { type: 'image/png' } ),
			} );

		const { file, consent, generate, result, resultImage, root, tryAgain } =
			fixture();
		const image = new File( [ 'image' ], 'person.jpg', {
			type: 'image/jpeg',
		} );
		await selectImage( file, image );
		consent.checked = true;
		consent.dispatchEvent( new Event( 'change' ) );
		generate.click();

		for ( let step = 0; step < 8; step++ ) {
			await Promise.resolve();
		}

		expect( window.fetch ).toHaveBeenCalledTimes( 2 );
		expect( window.fetch.mock.calls[ 0 ][ 1 ].headers ).toEqual( {
			'X-WP-Nonce': 'rest-nonce',
		} );
		expect( window.fetch.mock.calls[ 0 ][ 1 ].body ).toBeInstanceOf(
			FormData
		);
		const uploadedImage =
			window.fetch.mock.calls[ 0 ][ 1 ].body.get( 'image' );
		expect( uploadedImage.name ).toBe( 'person.jpg' );
		expect( uploadedImage.type ).toBe( 'image/jpeg' );
		expect( result.hidden ).toBe( false );
		expect( resultImage.src ).toContain( 'blob:preview' );
		expect( root.dataset.state ).toBe( 'succeeded' );
		expect( generate.disabled ).toBe( true );
		expect( file.disabled ).toBe( true );
		expect(
			root.querySelector( '[role="dialog"]' ).getAttribute( 'aria-busy' )
		).toBe( 'false' );

		let resolveRetry;
		window.fetch.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveRetry = resolve;
				} )
		);
		tryAgain.click();
		await Promise.resolve();
		expect( result.hidden ).toBe( false );
		expect( resultImage.src ).toContain( 'blob:preview' );
		expect( root.dataset.state ).toBe( 'submitting' );

		resolveRetry( {
			ok: true,
			json: async () => ( {
				id: 'b'.repeat( 32 ),
				status: 'failed',
				error: { message: 'Please try another image.' },
			} ),
		} );
		for ( let step = 0; step < 6; step++ ) {
			await Promise.resolve();
		}
		expect( result.hidden ).toBe( false );
		expect( root.dataset.state ).toBe( 'failed' );
	} );

	it( 'shows a REST failure only in the inline upload error', async () => {
		window.SeaTryOnConfig = {
			productId: 7,
			restRoot: 'https://store.example/wp-json/sea-tryon/v1/',
			authMode: 'logged-in',
			nonce: 'rest-nonce',
			tokens: {},
		};
		window.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( {
				code: 'configuration_error',
				message:
					'Virtual Try-On is temporarily unavailable. Please contact the store.',
			} ),
		} );

		const { file, consent, generate, root } = fixture();
		const image = new File( [ 'image' ], 'person.jpg', {
			type: 'image/jpeg',
		} );
		await selectImage( file, image );
		consent.checked = true;
		consent.dispatchEvent( new Event( 'change' ) );
		generate.click();

		for ( let step = 0; step < 8; step++ ) {
			await Promise.resolve();
		}

		const inlineError = root.querySelector(
			'[data-sea-tryon-upload-error]'
		);
		const status = root.querySelector( '[data-sea-tryon-status]' );
		expect( inlineError.hidden ).toBe( false );
		expect( inlineError.textContent ).toBe(
			'Virtual Try-On is temporarily unavailable. Please contact the store.'
		);
		expect( status.textContent ).toBe( '' );
	} );

	it( 'pauses polling in a hidden tab and backs off from two seconds', async () => {
		jest.useFakeTimers();
		let hidden = true;
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			get: () => hidden,
		} );
		window.SeaTryOnConfig = {
			productId: 7,
			restRoot: 'https://store.example/wp-json/sea-tryon/v1/',
			authMode: 'logged-in',
			nonce: 'rest-nonce',
			tokens: {},
		};
		window.fetch = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					id: 'a'.repeat( 32 ),
					status: 'queued',
				} ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					id: 'a'.repeat( 32 ),
					status: 'processing',
				} ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					id: 'a'.repeat( 32 ),
					status: 'succeeded',
					result_url:
						'https://store.example/wp-json/sea-tryon/v1/jobs/result',
				} ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				blob: async () => new Blob( [ 'png' ], { type: 'image/png' } ),
			} );

		const { file, consent, generate, result } = fixture();
		const image = new File( [ 'image' ], 'person.jpg', {
			type: 'image/jpeg',
		} );
		await selectImage( file, image );
		consent.checked = true;
		consent.dispatchEvent( new Event( 'change' ) );
		generate.click();
		await Promise.resolve();
		await Promise.resolve();
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );

		await jest.advanceTimersByTimeAsync( 10000 );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );

		hidden = false;
		document.dispatchEvent( new Event( 'visibilitychange' ) );
		await jest.advanceTimersByTimeAsync( 1999 );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		await jest.advanceTimersByTimeAsync( 1 );
		expect( window.fetch ).toHaveBeenCalledTimes( 2 );

		await jest.advanceTimersByTimeAsync( 3999 );
		expect( window.fetch ).toHaveBeenCalledTimes( 2 );
		await jest.advanceTimersByTimeAsync( 1 );
		for ( let step = 0; step < 8; step++ ) {
			await Promise.resolve();
		}
		expect( window.fetch ).toHaveBeenCalledTimes( 4 );
		expect( result.hidden ).toBe( false );

		jest.useRealTimers();
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			value: false,
		} );
	} );

	it( 'shows a login action without exposing the upload workflow', () => {
		window.SeaTryOnConfig = {
			productId: 7,
			restRoot: 'https://store.example/wp-json/sea-tryon/v1/',
			authMode: 'required',
			loginUrl: 'https://store.example/my-account/',
		};

		const { login, workflow } = fixture();
		expect( login.hidden ).toBe( false );
		expect( workflow.hidden ).toBe( true );
		expect( login.querySelector( 'a' ).href ).toBe(
			'https://store.example/my-account/'
		);
	} );

	it( 'has no automated accessibility violations in the open dialog', async () => {
		window.SeaTryOnConfig = {
			productId: 7,
			restRoot: 'https://store.example/wp-json/sea-tryon/v1/',
			authMode: 'required',
			loginUrl: 'https://store.example/my-account/',
		};
		const { trigger } = fixture();
		trigger.click();

		const results = await axe.run( document.body, {
			rules: { 'color-contrast': { enabled: false } },
		} );
		expect( results.violations ).toEqual( [] );
	} );
} );
