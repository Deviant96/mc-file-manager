<?php
/**
 * Advanced search hook surface (Pro). Free build uses filename scan only.
 *
 * @package MCFM
 */

namespace MCFM\Service;

defined( 'ABSPATH' ) || exit;

class AdvancedSearchService {

	/**
	 * Allow Pro extensions to replace or augment search results.
	 *
	 * @param array<int,array<string,mixed>> $results
	 * @return array<int,array<string,mixed>>
	 */
	public function augment( string $query, string $scope, string $base_relative, array $results ): array {
		$filtered = apply_filters( 'mcfm_advanced_search', $results, $query, $scope, $base_relative );
		return is_array( $filtered ) ? $filtered : $results;
	}
}
