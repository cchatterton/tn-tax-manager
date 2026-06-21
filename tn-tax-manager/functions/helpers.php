<?php
/**
 * TN Tax Manager - Helpers
 */

if (!defined('ABSPATH')) exit;

/**
 * Show the tool for logged-in users.
 */
function tn801_ttm_should_show() {
	global $post;

	return is_user_logged_in()
		&& $post
		&& $post->post_type === 'post';
}

/**
 * Get the taxonomy this plugin manages.
 */
function tn801_ttm_get_taxonomy() {
	return get_option(TN801_TTM_TAXONOMY_OPTION, TN801_TTM_DEFAULT_TAXONOMY);
}

/**
 * Get sorted terms for a post.
 */
function tn801_ttm_get_terms($post_id) {
	$taxonomy = tn801_ttm_get_taxonomy();
	$terms = get_the_terms($post_id, $taxonomy);

	if (empty($terms) || is_wp_error($terms)) {
		return array();
	}

	usort($terms, function($a, $b) {
		return strcasecmp($a->name, $b->name);
	});

	return $terms;
}

/**
 * Get readable parent > child term path.
 */
function tn801_ttm_get_term_path($term) {
	$taxonomy = tn801_ttm_get_taxonomy();

	$names  = array($term->name);
	$parent = (int) $term->parent;

	while ($parent) {
		$parent_term = get_term($parent, $taxonomy);

		if (!$parent_term || is_wp_error($parent_term)) {
			break;
		}

		array_unshift($names, $parent_term->name);
		$parent = (int) $parent_term->parent;
	}

	return implode(' > ', $names);
}

/**
 * Get trimmed plain text from a post for AI.
 */
function tn801_ttm_get_post_plain_text($post) {
	$text = wp_strip_all_tags(strip_shortcodes($post->post_content));
	$text = preg_replace('/\s+/', ' ', $text);
	$text = trim($text);

	if (strlen($text) > 6000) {
		$text = substr($text, 0, 6000);
	}

	return $text;
}

function tn801_ttm_get_all_term_names() {
	$taxonomy = tn801_ttm_get_taxonomy();

	$terms = get_terms(array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	));

	if (empty($terms) || is_wp_error($terms)) {
		return array();
	}

	return wp_list_pluck($terms, 'name');
}