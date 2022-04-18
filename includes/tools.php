<?php
/**
 * Tool functions
 *
 * @package   Contextual_Related_Posts
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Function to create an excerpt for the post.
 *
 * @since 1.6
 * @since 3.0.0 Added $more_link_text parameter.
 *
 * @param int|WP_Post $post           Post ID or WP_Post instance.
 * @param int|string  $excerpt_length Length of the excerpt in words.
 * @param bool        $use_excerpt    Use excerpt instead of content.
 * @param string      $more_link_text Content for when there is more text. Default is null.
 * @return string Excerpt
 */
function crp_excerpt( $post, $excerpt_length = 0, $use_excerpt = true, $more_link_text = '' ) {
	$content = '';

	$post = get_post( $post );
	if ( empty( $post ) ) {
		return '';
	}
	if ( $use_excerpt ) {
		$content = $post->post_excerpt;
	}
	if ( empty( $content ) ) {
		$content = $post->post_content;
	}

	$output = wp_strip_all_tags( strip_shortcodes( $content ) );

	/**
	 * Filters excerpt generated by CRP before it is trimmed.
	 *
	 * @since 2.3.0
	 * @since 2.9.0 Added $content parameter
	 * @since 3.0.0 Changed second parameter to WP_Post instance instead of ID.
	 *
	 * @param string  $output         Formatted excerpt.
	 * @param WP_Post $post           Source Post instance.
	 * @param int     $excerpt_length Length of the excerpt.
	 * @param boolean $use_excerpt    Use the excerpt?
	 * @param string  $content        Content that is used to create the excerpt.
	 */
	$output = apply_filters( 'crp_excerpt_pre_trim', $output, $post, $excerpt_length, $use_excerpt, $content );

	if ( 0 === (int) $excerpt_length || CRP_MAX_WORDS < (int) $excerpt_length ) {
		$excerpt_length = CRP_MAX_WORDS;
	}

	/**
	 * Filters the Read More text of the CRP excerpt.
	 *
	 * @since 3.0.0
	 *
	 * @param string  $more_link_text    Read More text.
	 * @param WP_Post $post              Source Post instance.
	 */
	$more_link_text = apply_filters( 'crp_excerpt_more_link_text', $more_link_text, $post );

	if ( null === $more_link_text ) {
		$more_link_text = sprintf(
			'<span aria-label="%1$s">%2$s</span>',
			sprintf(
				/* translators: %s: Post title. */
				__( 'Continue reading %s', 'contextual-related-posts' ),
				the_title_attribute(
					array(
						'echo' => false,
						'post' => $post,
					)
				)
			),
			__( '(more&hellip;)', 'contextual-related-posts' )
		);
	}

	if ( ! empty( $more_link_text ) ) {
		$more_link_element = ' <a href="' . get_permalink( $post ) . "#more-{$post->ID}\" class=\"crp_read_more_link\">$more_link_text</a>";
	} else {
		$more_link_element = '';
	}

	/**
	 * Filters the Read More link text of the CRP excerpt.
	 *
	 * @since 3.0.0
	 *
	 * @param string  $more_link_element Read More link element.
	 * @param string  $more_link_text    Read More text.
	 * @param WP_Post $post              Source Post instance.
	 */
	$more_link_element = apply_filters( 'crp_excerpt_more_link', $more_link_element, $more_link_text, $post );

	if ( $excerpt_length > 0 ) {
		$more_link_element = empty( $more_link_element ) ? null : $more_link_element;

		$output = wp_trim_words( $output, $excerpt_length, $more_link_element );
	}

	if ( post_password_required( $post ) ) {
		$output = __( 'There is no excerpt because this is a protected post.', 'contextual-related-posts' );
	}

	/**
	 * Filters excerpt generated by CRP.
	 *
	 * @since 1.9
	 * @since 3.0.0 Changed second parameter to WP_Post instance instead of ID.
	 *
	 * @param string  $output         Formatted excerpt.
	 * @param WP_Post $post           Source Post instance.
	 * @param int     $excerpt_length Length of the excerpt.
	 * @param boolean $use_excerpt    Use the excerpt?
	 */
	return apply_filters( 'crp_excerpt', $output, $post, $excerpt_length, $use_excerpt );
}


/**
 * Truncate a string to a certain length.
 *
 * @since 2.4.0
 *
 * @param  string $string      String to truncate.
 * @param  int    $count       Maximum number of characters to take.
 * @param  string $more        What to append if $string needs to be trimmed.
 * @param  bool   $break_words Optionally choose to break words.
 * @return string Truncated string.
 */
function crp_trim_char( $string, $count = 60, $more = '&hellip;', $break_words = false ) {

	$string = wp_strip_all_tags( $string, true );
	$count  = absint( $count );

	if ( $count <= 0 ) {
		return $string;
	}

	if ( mb_strlen( $string ) > $count && $count > 0 ) {
		if ( ! $break_words ) {
			$string = preg_replace( '/\s+?(\S+)?$/u', '', mb_substr( $string, 0, $count + 1 ) );
		}

		$string = mb_substr( $string, 0, $count ) . $more;
	}

	/**
	 * Filters truncated string.
	 *
	 * @since 2.4.0
	 *
	 * @param string $string String to truncate.
	 * @param int $count Maximum number of characters to take.
	 * @param string $more What to append if $string needs to be trimmed.
	 * @param bool $break_words Optionally choose to break words.
	 */
	return apply_filters( 'crp_trim_char', $string, $count, $more, $break_words );
}

/**
 * Create the FULLTEXT index.
 *
 * @since   2.2.1
 */
function crp_create_index() {
	global $wpdb;

	$wpdb->hide_errors();

	if ( ! $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} where Key_name = 'crp_related'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "ALTER TABLE {$wpdb->posts} ADD FULLTEXT crp_related (post_title, post_content);" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	if ( ! $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} where Key_name = 'crp_related_title'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "ALTER TABLE {$wpdb->posts} ADD FULLTEXT crp_related_title (post_title);" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	$wpdb->show_errors();

}


/**
 * Delete the FULLTEXT index.
 *
 * @since   2.2.1
 */
function crp_delete_index() {
	global $wpdb;

	$wpdb->hide_errors();

	if ( $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} where Key_name = 'crp_related'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "ALTER TABLE {$wpdb->posts} DROP INDEX crp_related" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	if ( $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} where Key_name = 'crp_related_title'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "ALTER TABLE {$wpdb->posts} DROP INDEX crp_related_title" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	if ( $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} where Key_name = 'crp_related_content'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "ALTER TABLE {$wpdb->posts} DROP INDEX crp_related_content" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	$wpdb->show_errors();

}


/**
 * Get the table schema for the posts table.
 *
 * @since   2.5.0
 */
function crp_posts_table_engine() {
	global $wpdb;

	$engine = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"
		SELECT engine FROM INFORMATION_SCHEMA.TABLES
		WHERE table_schema=DATABASE()
		AND table_name = '{$wpdb->posts}'
		"
	);

	return $engine;
}

/**
 * Convert a string to CSV.
 *
 * @since 2.9.0
 *
 * @param array  $array Input string.
 * @param string $delimiter Delimiter.
 * @param string $enclosure Enclosure.
 * @param string $terminator Terminating string.
 * @return string CSV string.
 */
function crp_str_putcsv( $array, $delimiter = ',', $enclosure = '"', $terminator = "\n" ) {
	// First convert associative array to numeric indexed array.
	$work_array = array();
	foreach ( $array as $key => $value ) {
		$work_array[] = $value;
	}

	$string     = '';
	$array_size = count( $work_array );

	for ( $i = 0; $i < $array_size; $i++ ) {
		// Nested array, process nest item.
		if ( is_array( $work_array[ $i ] ) ) {
			$string .= crp_str_putcsv( $work_array[ $i ], $delimiter, $enclosure, $terminator );
		} else {
			switch ( gettype( $work_array[ $i ] ) ) {
				// Manually set some strings.
				case 'NULL':
					$sp_format = '';
					break;
				case 'boolean':
					$sp_format = ( true === $work_array[ $i ] ) ? 'true' : 'false';
					break;
				// Make sure sprintf has a good datatype to work with.
				case 'integer':
					$sp_format = '%i';
					break;
				case 'double':
					$sp_format = '%0.2f';
					break;
				case 'string':
					$sp_format        = '%s';
					$work_array[ $i ] = str_replace( "$enclosure", "$enclosure$enclosure", $work_array[ $i ] );
					break;
				// Unknown or invalid items for a csv - note: the datatype of array is already handled above, assuming the data is nested.
				case 'object':
				case 'resource':
				default:
					$sp_format = '';
					break;
			}
			$string .= sprintf( '%2$s' . $sp_format . '%2$s', $work_array[ $i ], $enclosure );
			$string .= ( $i < ( $array_size - 1 ) ) ? $delimiter : $terminator;
		}
	}

	return $string;
}

/**
 * Get the primary term for a given post.
 *
 * @since 3.2.0
 *
 * @param int|WP_Post $post       Post ID or WP_Post object.
 * @param string      $term       Term name.
 * @param bool        $return_all Whether to return all categories.
 * @return array Primary term object at `primary` and array of term
 *               objects at `all` if $return_all is true.
 */
function crp_get_primary_term( $post, $term = 'category', $return_all = false ) {
	$return = array(
		'primary' => '',
		'all'     => array(),
	);

	$post = get_post( $post );
	if ( empty( $post ) ) {
		return $return;
	}

	// Yoast primary term.
	if ( class_exists( 'WPSEO_Primary_Term' ) ) {
		$wpseo_primary_term = new WPSEO_Primary_Term( $term, $post->ID );
		$primary_term       = $wpseo_primary_term->get_primary_term();
		$primary_term       = get_term( $wpseo_primary_term->get_primary_term() );

		if ( ! is_wp_error( $primary_term ) ) {
			$return['primary'] = $primary_term;
		}
	}

	// Rank Math SEO primary term.
	if ( class_exists( 'RankMath' ) ) {
		$primary_term = get_term( get_post_meta( $post->ID, "rank_math_primary_{$term}", true ) );
		if ( ! is_wp_error( $primary_term ) ) {
			$return['primary'] = $primary_term;
		}
	}

	// The SEO Framework primary term.
	if ( function_exists( 'the_seo_framework' ) ) {
		$primary_term = get_term( get_post_meta( $post->ID, "_primary_term_{$term}", true ) );
		if ( ! is_wp_error( $primary_term ) ) {
			$return['primary'] = $primary_term;
		}
	}

	// SEOPress primary term.
	if ( function_exists( 'seopress_init' ) ) {
		$primary_term = get_term( get_post_meta( $post->ID, '_seopress_robots_primary_cat', true ) );
		if ( ! is_wp_error( $primary_term ) ) {
			$return['primary'] = $primary_term;
		}
	}

	if ( empty( $return['primary'] ) || $return_all ) {
		$categories = get_the_terms( $post, $term );

		if ( ! empty( $categories ) ) {
			if ( empty( $return['primary'] ) ) {
				$return['primary'] = $categories[0];
			}
			$return['all'] = ( $return_all ) ? $categories : array();
		}
	}

	/**
	 * Filters the primary category/term for the given post.
	 *
	 * @since 3.2.0
	 *
	 * @param array       $return Primary term object at `primary` and optionally
	 *                            array of term objects at `all`.
	 * @param int|WP_Post $post   Post ID or WP_Post object.
	 * @param string      $term   Term name.
	 */
	return apply_filters( 'crp_get_primary_term', $return, $post, $term );
}
