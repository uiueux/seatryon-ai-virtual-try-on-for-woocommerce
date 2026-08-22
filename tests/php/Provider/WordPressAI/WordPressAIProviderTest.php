<?php
/**
 * WordPress AI provider contract tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Provider\WordPressAI;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Image\ValidatedImage;
use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
use SeaTryOn\Provider\WordPressAI\WordPressAIImage;
use SeaTryOn\Provider\WordPressAI\WordPressAIProvider;
use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

final class WordPressAIProviderTest extends TestCase {
	public function test_validates_inputs_and_persists_the_generated_image_privately(): void {
		$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
		$scope    = str_repeat( 'a', 32 );
		$storage  = new WordPressAIStorage( $scope, $png );
		$client   = new RecordingWordPressAIClient( $png );
		$provider = new WordPressAIProvider( $storage, $client, new ImageValidator( 1024, 64, 4096, false ) );

		$result = $provider->generate(
			new ProviderRequest(
				str_repeat( 'b', 32 ),
				$scope . '/customer.png',
				$scope . '/product.png',
				'Keep both subjects accurate.',
				ExperienceType::from_string( ExperienceType::CLOTHING ),
				'auto',
				'auto'
			)
		);

		self::assertSame( 'Keep both subjects accurate.', $client->prompt );
		self::assertCount( 2, $client->images );
		self::assertSame( $scope . '/result.png', $result->result_reference() );
		self::assertSame( 'image/png', $result->mime_type() );
		self::assertSame( $png, $storage->written );
	}
}

final class RecordingWordPressAIClient implements WordPressAIClientInterface {
	/** @var string */ private $result;
	/** @var string */ public $prompt = '';
	/** @var ValidatedImage[] */ public $images = array();
	public function __construct( string $result ) { $this->result = $result; }
	public function supports_image_editing(): bool { return true; }
	/** @param ValidatedImage[] $images Images. */
	public function generate_image( string $prompt, array $images ): WordPressAIImage {
		$this->prompt = $prompt;
		$this->images = $images;
		return new WordPressAIImage( $this->result, 'image/png' );
	}
}

final class WordPressAIStorage implements TemporaryStorageInterface {
	/** @var string */ private $scope;
	/** @var string */ private $input;
	/** @var string */ public $written = '';
	public function __construct( string $scope, string $input ) { $this->scope = $scope; $this->input = $input; }
	public function create_scope(): string { return $this->scope; }
	public function write( string $scope_id, string $role, string $contents, string $extension ): string { $this->written = $contents; return $scope_id . '/' . $role . '.' . $extension; }
	public function read( string $storage_id ): string { unset( $storage_id ); return $this->input; }
	public function absolute_path( string $storage_id ): string { return $storage_id; }
	public function delete( string $storage_id ): bool { unset( $storage_id ); return true; }
	public function delete_scope( string $scope_id ): bool { unset( $scope_id ); return true; }
	public function cleanup_expired(): int { return 0; }
	public function root_path(): string { return 'private'; }
}
