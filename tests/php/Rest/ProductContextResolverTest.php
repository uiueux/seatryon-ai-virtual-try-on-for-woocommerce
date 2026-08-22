<?php
/**
 * Server-side product context tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! class_exists( 'WC_Product', false ) ) {
		class WC_Product {}
	}

	class SeaTryOnResolverProduct extends WC_Product {
		/** @var int */ private $id;
		/** @var string */ private $type;
		/** @var int */ private $image_id;
		/** @var array<string,string> */ private $metadata;
		/** @var bool */ private $purchasable;
		public function __construct( int $id, string $type, int $image_id, array $metadata = array(), bool $purchasable = true ) {
			$this->id = $id; $this->type = $type; $this->image_id = $image_id; $this->metadata = $metadata; $this->purchasable = $purchasable;
		}
		public function get_meta( string $key, bool $single = true ): string { unset( $single ); return isset( $this->metadata[ $key ] ) ? $this->metadata[ $key ] : ''; }
		public function is_purchasable(): bool { return $this->purchasable; }
		/** @param string|string[] $type Product type. */
		public function is_type( $type ): bool { return is_array( $type ) ? in_array( $this->type, $type, true ) : $this->type === $type; }
		public function get_image_id( string $context = 'view' ): int { unset( $context ); return $this->image_id; }
		public function get_id(): int { return $this->id; }
	}

	if ( ! class_exists( 'WC_Product_Variation', false ) ) {
		class WC_Product_Variation extends SeaTryOnResolverProduct {
			/** @var int */ private $parent_id;
			public function __construct( int $id, int $parent_id, int $image_id, bool $purchasable = true ) {
				parent::__construct( $id, 'variation', $image_id, array(), $purchasable );
				$this->parent_id = $parent_id;
			}
			public function get_parent_id(): int { return $this->parent_id; }
		}
	}
}

namespace SeaTryOn\Rest {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $text, string $domain ): string { unset( $domain ); return $text; }
	}
	/** @return mixed */
	function wc_get_product( int $id ) { return isset( $GLOBALS['sea_tryon_resolver_products'][ $id ] ) ? $GLOBALS['sea_tryon_resolver_products'][ $id ] : false; }
	function wp_attachment_is_image( int $id ): bool { return isset( $GLOBALS['sea_tryon_resolver_paths'][ $id ] ); }
	/** @param mixed $value Value to normalize. */
	function absint( $value ): int { return abs( (int) $value ); }
	/** @return string|false */
	function get_attached_file( int $id ) { return isset( $GLOBALS['sea_tryon_resolver_paths'][ $id ] ) ? $GLOBALS['sea_tryon_resolver_paths'][ $id ] : false; }
}

namespace SeaTryOn\Tests\Rest {
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Admin\Product\ProductFields;
	use SeaTryOn\Prompt\PromptComposer;
	use SeaTryOn\Image\ValidatedImage;
	use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
	use SeaTryOn\Provider\WordPressAI\WordPressAIImage;
	use SeaTryOn\Rest\RestException;
	use SeaTryOn\Rest\WordPressProductContextResolver;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SettingsRepository;
	use SeaTryOn\Storage\TemporaryStorageInterface;
	use SeaTryOn\Upload\ImageProcessorInterface;
	use SeaTryOn\Upload\NormalizedImage;

	final class ProductContextResolverTest extends TestCase {
		/** @var ResolverStorage */ private $storage;
		/** @var ResolverImageProcessor */ private $images;
		/** @var WordPressProductContextResolver */ private $resolver;

		protected function setUp(): void {
			$this->storage = new ResolverStorage();
			$this->images  = new ResolverImageProcessor();
			$settings      = new SettingsRepository( new ResolverOptions() );
			$this->resolver = new WordPressProductContextResolver( $settings, new PromptComposer(), $this->images, $this->storage, null, new ResolverWordPressAIClient() );
			$GLOBALS['sea_tryon_resolver_products'] = array();
			$GLOBALS['sea_tryon_resolver_paths']    = array();
		}

		public function test_variable_product_requires_selected_variation(): void {
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'variable', 20, $this->metadata() ), 'parent.jpg', 20 );
			$this->expectException( RestException::class );
			$this->resolver->resolve( 10, null, str_repeat( 'a', 32 ) );
		}

		public function test_simple_product_rejects_variation(): void {
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'simple', 20, $this->metadata() ), 'parent.jpg', 20 );
			$this->expectException( RestException::class );
			$this->resolver->resolve( 10, 11, str_repeat( 'a', 32 ) );
		}

		public function test_cross_product_variation_is_rejected(): void {
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'variable', 20, $this->metadata() ), 'parent.jpg', 20 );
			$GLOBALS['sea_tryon_resolver_products'][11] = new \WC_Product_Variation( 11, 99, 21 );
			$this->expectException( RestException::class );
			$this->resolver->resolve( 10, 11, str_repeat( 'a', 32 ) );
		}

		public function test_selected_variation_image_is_written_into_customer_scope(): void {
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'variable', 20, $this->metadata() ), 'parent.jpg', 20 );
			$GLOBALS['sea_tryon_resolver_products'][11] = new \WC_Product_Variation( 11, 10, 21 );
			$GLOBALS['sea_tryon_resolver_paths'][21]    = __FILE__;
			$scope   = str_repeat( 'a', 32 );
			$context = $this->resolver->resolve( 10, 11, $scope );

			$this->assertSame( __FILE__, $this->images->last_path );
			$this->assertSame( $scope, $this->storage->last_scope );
			$this->assertSame( $scope . '/product.png', $context->product_image_reference() );
		}

		public function test_optional_tryon_image_overrides_the_product_and_variation_images(): void {
			$metadata                                       = $this->metadata();
			$metadata[ ProductFields::META_PRODUCT_IMAGE_ID ] = '30';
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'variable', 20, $metadata ), 'parent.jpg', 20 );
			$GLOBALS['sea_tryon_resolver_products'][11] = new \WC_Product_Variation( 11, 10, 21 );
			$GLOBALS['sea_tryon_resolver_paths'][21]    = __FILE__;
			$GLOBALS['sea_tryon_resolver_paths'][30]    = dirname( __DIR__ ) . '/bootstrap.php';

			$this->resolver->resolve( 10, 11, str_repeat( 'a', 32 ) );

			self::assertSame( dirname( __DIR__ ) . '/bootstrap.php', $this->images->last_path );
		}

		public function test_missing_optional_tryon_image_falls_back_to_the_product_image(): void {
			$metadata                                       = $this->metadata();
			$metadata[ ProductFields::META_PRODUCT_IMAGE_ID ] = '30';
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'simple', 20, $metadata ), 'parent.jpg', 20 );

			$this->resolver->resolve( 10, null, str_repeat( 'a', 32 ) );

			self::assertSame( __FILE__, $this->images->last_path );
		}

		public function test_empty_optional_prompt_uses_experience_template(): void {
			$metadata                              = $this->metadata();
			$metadata[ ProductFields::META_PROMPT ] = '';
			$this->install_product( new \SeaTryOnResolverProduct( 10, 'simple', 20, $metadata ), 'parent.jpg', 20 );

			$context = $this->resolver->resolve( 10, null, str_repeat( 'a', 32 ) );

			self::assertStringContainsString( 'Place the selected furniture naturally', $context->prompt() );
			self::assertStringNotContainsString( 'Product-specific direction:', $context->prompt() );
		}

		/** @return array<string,string> */
		private function metadata(): array {
			return array( ProductFields::META_ENABLED => 'yes', ProductFields::META_PROMPT => 'Keep the product accurate.', ProductFields::META_EXPERIENCE_TYPE => 'furniture' );
		}

		private function install_product( \SeaTryOnResolverProduct $product, string $path, int $image_id ): void {
			$GLOBALS['sea_tryon_resolver_products'][ $product->get_id() ] = $product;
			$GLOBALS['sea_tryon_resolver_paths'][ $image_id ]             = __FILE__;
			unset( $path );
		}
	}

	final class ResolverOptions implements OptionsStoreInterface {
		/** @param mixed $default Default. @return mixed */
		public function get( string $name, $default = false ) {
			$values = array( SettingsRepository::OPTION_ENABLED => 'yes', SettingsRepository::OPTION_PROVIDER => 'openai' );
			return isset( $values[ $name ] ) ? $values[ $name ] : $default;
		}
		/** @param mixed $value Value. */ public function update( string $name, $value, bool $autoload = false ): bool { return true; }
		public function add( string $name, string $value, bool $autoload = false ): bool { return true; }
		public function delete( string $name ): bool { return true; }
	}

	final class ResolverWordPressAIClient implements WordPressAIClientInterface {
		public function supports_image_editing(): bool { return true; }
		/** @param ValidatedImage[] $images Images. */
		public function generate_image( string $prompt, array $images ): WordPressAIImage {
			unset( $prompt, $images );
			return new WordPressAIImage( 'image-bytes', 'image/png' );
		}
	}

	final class ResolverImageProcessor implements ImageProcessorInterface {
		/** @var string */ public $last_path = '';
		public function normalize( string $path, string $original_name, int $maximum_bytes ): NormalizedImage {
			unset( $original_name, $maximum_bytes ); $this->last_path = $path;
			return new NormalizedImage( 'png', 'image/png', 'png', 512, 512 );
		}
	}

	final class ResolverStorage implements TemporaryStorageInterface {
		/** @var string */ public $last_scope = '';
		public function create_scope(): string { return str_repeat( 'a', 32 ); }
		public function write( string $scope_id, string $role, string $contents, string $extension ): string { unset( $contents ); $this->last_scope = $scope_id; return $scope_id . '/' . $role . '.' . $extension; }
		public function read( string $storage_id ): string { return ''; }
		public function absolute_path( string $storage_id ): string { return ''; }
		public function delete( string $storage_id ): bool { return true; }
		public function delete_scope( string $scope_id ): bool { return true; }
		public function cleanup_expired(): int { return 0; }
		public function root_path(): string { return 'private'; }
	}
}
