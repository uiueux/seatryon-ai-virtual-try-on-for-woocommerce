<?php
/**
 * Dynamic fallback block detection.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Detects the manual block before the WooCommerce action can claim its place.
 */
final class ManualBlockDetector {

	public const BLOCK_NAME = 'sea-tryon/virtual-try-on';

	/**
	 * Cached request result.
	 *
	 * @var bool|null
	 */
	private $cached;

	/** Determine whether the current resolved content contains the fallback. */
	public function current_request_has_block(): bool {
		if ( null !== $this->cached ) {
			return $this->cached;
		}

		$contents = array();

		if ( isset( $GLOBALS['_wp_current_template_content'] ) && is_string( $GLOBALS['_wp_current_template_content'] ) ) {
			$contents[] = $GLOBALS['_wp_current_template_content'];
		}

		$queried_id = get_queried_object_id();

		if ( $queried_id > 0 ) {
			$post = get_post( $queried_id );

			if ( $post instanceof \WP_Post ) {
				$contents[] = $post->post_content;
			}
		}

		foreach ( $contents as $content ) {
			if ( $this->content_has_block( $content ) ) {
				$this->cached = true;
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively inspect serialized blocks, reusable blocks and template parts.
	 *
	 * @param string $content Serialized block content.
	 */
	public function content_has_block( string $content ): bool {
		if ( '' === $content ) {
			return false;
		}

		return $this->blocks_have_target( parse_blocks( $content ), array() );
	}

	/**
	 * Walk parsed blocks, resolving common reusable containers with cycle guards.
	 *
	 * @param array<int,array<string,mixed>> $blocks  Parsed blocks.
	 * @param array<string,bool>             $visited Visited reusable resources.
	 */
	private function blocks_have_target( array $blocks, array $visited ): bool {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';

			if ( self::BLOCK_NAME === $name ) {
				return true;
			}

			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();

			if ( $inner_blocks && $this->blocks_have_target( $inner_blocks, $visited ) ) {
				return true;
			}

			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			if ( 'core/block' === $name && isset( $attrs['ref'] ) ) {
				$reference = 'post:' . absint( $attrs['ref'] );

				if ( ! isset( $visited[ $reference ] ) ) {
					$visited[ $reference ] = true;
					$post                  = get_post( absint( $attrs['ref'] ) );

					if ( $post instanceof \WP_Post && $this->blocks_have_target( parse_blocks( $post->post_content ), $visited ) ) {
						return true;
					}
				}
			}

			if ( 'core/template-part' === $name && isset( $attrs['slug'] ) && is_string( $attrs['slug'] ) ) {
				$theme     = isset( $attrs['theme'] ) && is_string( $attrs['theme'] ) ? $attrs['theme'] : get_stylesheet();
				$reference = 'part:' . $theme . '//' . $attrs['slug'];

				if ( ! isset( $visited[ $reference ] ) ) {
					$visited[ $reference ] = true;
					$template              = get_block_template( $theme . '//' . $attrs['slug'], 'wp_template_part' );

					if ( $template instanceof \WP_Block_Template && $this->blocks_have_target( parse_blocks( $template->content ), $visited ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
