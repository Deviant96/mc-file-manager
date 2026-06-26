<?php
/**
 * Preview service. Classifies files and provides text/image preview payloads.
 * V1 supports images and text only.
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Filesystem\FilesystemService;
use MCFM\Security\PathResolver;

defined( 'ABSPATH' ) || exit;

class PreviewService {

	private const IMAGE_EXT = array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif' );

	private const TEXT_EXT = array(
		'txt', 'md', 'markdown', 'php', 'js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx', 'vue', 'css', 'scss', 'sass', 'less',
		'html', 'htm', 'xml', 'json', 'yml', 'yaml', 'ini', 'conf', 'env', 'log', 'sql', 'sh', 'bash', 'py', 'rb',
		'go', 'rs', 'java', 'c', 'cpp', 'h', 'hpp', 'cs', 'pl', 'lua', 'twig', 'blade', 'htaccess', 'gitignore',
		'csv', 'tsv', 'svg',
	);

	private FilesystemService $fs;
	private PathResolver $resolver;

	public function __construct( FilesystemService $fs, PathResolver $resolver ) {
		$this->fs       = $fs;
		$this->resolver = $resolver;
	}

	public function classify( string $name ): string {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			$base = strtolower( ltrim( basename( $name ), '.' ) );
			if ( in_array( $base, self::TEXT_EXT, true ) ) {
				return 'text';
			}
		}
		if ( in_array( $ext, self::IMAGE_EXT, true ) ) {
			return 'image';
		}
		if ( in_array( $ext, self::TEXT_EXT, true ) ) {
			return 'text';
		}
		return 'binary';
	}

	public function is_text( string $name ): bool {
		return 'text' === $this->classify( $name );
	}

	public function is_image( string $name ): bool {
		return 'image' === $this->classify( $name );
	}

	/**
	 * Map an extension to a Monaco language id.
	 */
	public function monaco_language( string $name ): string {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$map = array(
			'js'   => 'javascript',
			'mjs'  => 'javascript',
			'cjs'  => 'javascript',
			'ts'   => 'typescript',
			'jsx'  => 'javascript',
			'tsx'  => 'typescript',
			'vue'  => 'html',
			'php'  => 'php',
			'css'  => 'css',
			'scss' => 'scss',
			'less' => 'less',
			'html' => 'html',
			'htm'  => 'html',
			'xml'  => 'xml',
			'json' => 'json',
			'yml'  => 'yaml',
			'yaml' => 'yaml',
			'md'   => 'markdown',
			'sql'  => 'sql',
			'sh'   => 'shell',
			'bash' => 'shell',
			'py'   => 'python',
			'rb'   => 'ruby',
			'go'   => 'go',
			'rs'   => 'rust',
			'java' => 'java',
			'c'    => 'c',
			'cpp'  => 'cpp',
			'cs'   => 'csharp',
			'ini'  => 'ini',
			'conf' => 'ini',
		);
		return $map[ $ext ] ?? 'plaintext';
	}

	/**
	 * Best-effort MIME type for image previews.
	 */
	public function mime_for( string $abs ): string {
		$type = wp_check_filetype( $abs );
		if ( ! empty( $type['type'] ) ) {
			return $type['type'];
		}
		$ext = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
		$map = array(
			'svg' => 'image/svg+xml',
			'ico' => 'image/x-icon',
			'avif' => 'image/avif',
		);
		return $map[ $ext ] ?? 'application/octet-stream';
	}
}
