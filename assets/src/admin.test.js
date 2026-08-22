import {
	initializeProductImageSelectors,
	initializeSeaAIConnectionTests,
} from './admin';

describe( 'Try-On product image selector', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		delete window.wp;
	} );

	it( 'selects, replaces, and clears an optional Media Library image', () => {
		document.body.innerHTML = `
			<span
				data-sea-tryon-product-image
				data-select-label="Select image"
				data-change-label="Change image"
				data-media-title="Select a Try-On Product Image"
				data-media-button="Use this image"
			>
				<input type="hidden" value="">
				<span data-sea-tryon-image-preview></span>
				<button type="button" data-sea-tryon-image-select>Select image</button>
				<button type="button" data-sea-tryon-image-remove hidden>Remove image</button>
			</span>
		`;

		const handlers = {};
		const attachment = {
			get: jest.fn( ( key ) => {
				const values = {
					id: 41,
					sizes: {
						thumbnail: { url: 'https://example.test/thumb.jpg' },
					},
					url: 'https://example.test/full.jpg',
				};
				return values[ key ];
			} ),
		};
		const frame = {
			on: jest.fn( ( event, callback ) => {
				handlers[ event ] = callback;
			} ),
			open: jest.fn(),
			state: jest.fn( () => ( {
				get: () => ( { first: () => attachment } ),
			} ) ),
		};
		const media = jest.fn( () => frame );
		window.wp = { media };

		initializeProductImageSelectors();
		initializeProductImageSelectors();

		const root = document.querySelector( '[data-sea-tryon-product-image]' );
		const input = root.querySelector( 'input' );
		const selectButton = root.querySelector(
			'[data-sea-tryon-image-select]'
		);
		const removeButton = root.querySelector(
			'[data-sea-tryon-image-remove]'
		);

		selectButton.click();
		expect( media ).toHaveBeenCalledTimes( 1 );
		expect( media ).toHaveBeenCalledWith(
			expect.objectContaining( {
				library: { type: 'image' },
				multiple: false,
				title: 'Select a Try-On Product Image',
			} )
		);
		expect( frame.open ).toHaveBeenCalledTimes( 1 );

		handlers.select();
		expect( input.value ).toBe( '41' );
		expect( root.querySelector( 'img' ).src ).toBe(
			'https://example.test/thumb.jpg'
		);
		expect( selectButton.textContent ).toBe( 'Change image' );
		expect( removeButton.hidden ).toBe( false );

		selectButton.click();
		expect( media ).toHaveBeenCalledTimes( 1 );
		expect( frame.open ).toHaveBeenCalledTimes( 2 );

		removeButton.click();
		expect( input.value ).toBe( '' );
		expect( root.querySelector( 'img' ) ).toBeNull();
		expect( selectButton.textContent ).toBe( 'Select image' );
		expect( removeButton.hidden ).toBe( true );
	} );
} );

describe( 'SeaAI connection test', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		delete window.sea_tryon_seaai_connection;
		delete window.fetch;
	} );

	it( 'tests the current unsaved URL and masked key without duplicating the component', async () => {
		document.body.innerHTML = `
			<input id="sea_tryon_seaai_base_url" value="https://gateway.example/wp-json/seaai/v1">
			<input type="password" data-sea-tryon-seaai-key="true" value="************">
		`;
		window.sea_tryon_seaai_connection = {
			ajaxUrl: 'https://shop.example/wp-admin/admin-ajax.php',
			action: 'sea_tryon_test_seaai_connection',
			nonce: 'test-nonce',
			messages: {
				button: 'Test connection',
				testing: 'Testing connection…',
				failed: 'Connection failed.',
			},
		};
		window.fetch = jest.fn().mockResolvedValue( {
			json: jest.fn().mockResolvedValue( {
				success: true,
				data: { message: 'Connection successful.' },
			} ),
		} );

		initializeSeaAIConnectionTests();
		initializeSeaAIConnectionTests();

		const button = document.querySelector(
			'.sea-tryon-seaai-test__button'
		);
		button.click();
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(
			document.querySelectorAll( '.sea-tryon-seaai-test' )
		).toHaveLength( 1 );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		const request = window.fetch.mock.calls[ 0 ][ 1 ];
		const body = new URLSearchParams( request.body );
		expect( body.get( 'base_url' ) ).toBe(
			'https://gateway.example/wp-json/seaai/v1'
		);
		expect( body.get( 'api_key' ) ).toBe( '************' );
		expect( body.get( 'nonce' ) ).toBe( 'test-nonce' );
		expect(
			document.querySelector( '.sea-tryon-seaai-test__status' )
				.textContent
		).toBe( 'Connection successful.' );
		expect(
			document.querySelector( '.sea-tryon-seaai-test__status' ).dataset
				.state
		).toBe( 'success' );
		expect( button.disabled ).toBe( false );
	} );
} );
