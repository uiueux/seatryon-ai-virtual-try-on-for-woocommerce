<?php
/** WordPress 7.0 AI Client symbols used by static analysis. */

namespace {
	function wp_ai_client_prompt(): WP_AI_Client_Prompt_Builder {}

	class WP_AI_Client_Prompt_Builder {
		public function with_text( string $text ): self {}
		public function with_file( $file, ?string $mime_type = null ): self {}
		public function as_output_file_type( $file_type ): self {}
		public function is_supported_for_image_generation(): bool {}
		/** @return \WordPress\AiClient\Files\DTO\File|\WP_Error */
		public function generate_image() {}
	}
}

namespace WordPress\AiClient\Files\DTO {
	class File {
		public function __construct( string $file, ?string $mime_type = null ) {}
		public function isInline(): bool {}
		public function isImage(): bool {}
		public function getBase64Data(): ?string {}
		public function getMimeType(): string {}
	}
}

namespace WordPress\AiClient\Files\Enums {
	class FileTypeEnum {
		public static function inline(): self {}
	}
}
