<?php
/**
 * Administrative notice renderer tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}
}

namespace SeaTryOn\Tests\Admin\Notices {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Admin\Notices\CapabilityCheckerInterface;
	use SeaTryOn\Admin\Notices\DependencyNoticeRegistryInterface;
	use SeaTryOn\Admin\Notices\HealthIssue;
	use SeaTryOn\Admin\Notices\HealthProbeInterface;
	use SeaTryOn\Admin\Notices\NoticeRenderer;

	defined( 'ABSPATH' ) || exit;

	final class NoticeRendererTest extends TestCase {

		public function test_renderer_escapes_dynamic_version_context(): void {
			$renderer = new NoticeRenderer(
				new FixedHealthProbe(
					array(
						new HealthIssue(
							HealthIssue::WOOCOMMERCE_TOO_OLD,
							HealthIssue::AUDIENCE_PLUGIN_MANAGER,
							array(
								'minimum' => '10.9',
								'current' => '<script>alert(1)</script>',
							)
						),
					)
				),
				new FixedCapabilityChecker( true ),
				new FixedDependencyNoticeRegistry( false )
			);

			ob_start();
			$renderer->render();
			$output = (string) ob_get_clean();

			self::assertStringNotContainsString( '<script>', $output );
			self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $output );
			self::assertStringContainsString( 'notice notice-error', $output );
			self::assertStringNotContainsString( 'is-dismissible', $output );
		}

		public function test_unauthorized_user_receives_no_notice(): void {
			$renderer = new NoticeRenderer(
				new FixedHealthProbe(
					array( new HealthIssue( HealthIssue::WORDPRESS_AI_UNAVAILABLE, HealthIssue::AUDIENCE_STORE_MANAGER ) )
				),
				new FixedCapabilityChecker( false ),
				new FixedDependencyNoticeRegistry( false )
			);

			ob_start();
			$renderer->render();
			$output = (string) ob_get_clean();

			self::assertSame( '', $output );
		}

		public function test_existing_dependencies_notice_suppresses_only_duplicate_dependency_issue(): void {
			$renderer = new NoticeRenderer(
				new FixedHealthProbe(
					array(
						new HealthIssue(
							HealthIssue::WOOCOMMERCE_MISSING,
							HealthIssue::AUDIENCE_PLUGIN_MANAGER,
							array( 'minimum' => '10.9' )
						),
						new HealthIssue( HealthIssue::STORAGE_UNAVAILABLE, HealthIssue::AUDIENCE_STORE_MANAGER ),
					)
				),
				new FixedCapabilityChecker( true ),
				new FixedDependencyNoticeRegistry( true )
			);

			ob_start();
			$renderer->render();
			$output = (string) ob_get_clean();

			self::assertStringNotContainsString( 'requires WooCommerce', $output );
			self::assertStringContainsString( 'private temporary storage', $output );
		}
	}

	final class FixedHealthProbe implements HealthProbeInterface {

		/** @var HealthIssue[] */
		private $issues;

		/** @param HealthIssue[] $issues Issues. */
		public function __construct( array $issues ) {
			$this->issues = $issues;
		}

		public function issues(): array {
			return $this->issues;
		}
	}

	final class FixedCapabilityChecker implements CapabilityCheckerInterface {

		/** @var bool */
		private $allowed;

		public function __construct( bool $allowed ) {
			$this->allowed = $allowed;
		}

		public function can_view( HealthIssue $issue ): bool {
			return $this->allowed;
		}
	}

	final class FixedDependencyNoticeRegistry implements DependencyNoticeRegistryInterface {

		/** @var bool */
		private $registered;

		public function __construct( bool $registered ) {
			$this->registered = $registered;
		}

		public function is_registered(): bool {
			return $this->registered;
		}
	}
}
