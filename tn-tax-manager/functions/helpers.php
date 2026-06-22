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
	return 'category';
}

/**
 * Decode stored term labels for UI, AI prompts, and matching.
 */
function tn801_ttm_decode_term_name($name) {
	return wp_specialchars_decode((string) $name, ENT_QUOTES);
}

function tn801_ttm_get_term_name($term) {
	return tn801_ttm_decode_term_name($term->name ?? '');
}

function tn801_ttm_get_term_name_key($name) {
	return strtolower(tn801_ttm_decode_term_name($name));
}

function tn801_ttm_find_term_by_name($term_name, $taxonomy) {
	$candidates = array_unique(array(
		$term_name,
		tn801_ttm_decode_term_name($term_name),
	));

	foreach ($candidates as $candidate) {
		$term = get_term_by('name', $candidate, $taxonomy);

		if ($term && !is_wp_error($term)) {
			return $term;
		}
	}

	$needle = tn801_ttm_get_term_name_key($term_name);
	$terms = get_terms(array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	));

	if (is_wp_error($terms) || empty($terms)) {
		return false;
	}

	foreach ($terms as $term) {
		if (tn801_ttm_get_term_name_key($term->name) === $needle) {
			return $term;
		}
	}

	return false;
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
		return strcasecmp(tn801_ttm_get_term_name($a), tn801_ttm_get_term_name($b));
	});

	return $terms;
}

/**
 * Get assigned managed taxonomy term names for exclusion checks.
 */
function tn801_ttm_get_assigned_term_names($post_id) {
	return array_map('tn801_ttm_get_term_name', tn801_ttm_get_terms($post_id));
}

/**
 * Get readable parent > child term path.
 */
function tn801_ttm_get_term_path($term) {
	$taxonomy = tn801_ttm_get_taxonomy();

	$names  = array(tn801_ttm_get_term_name($term));
	$parent = (int) $term->parent;

	while ($parent) {
		$parent_term = get_term($parent, $taxonomy);

		if (!$parent_term || is_wp_error($parent_term)) {
			break;
		}

		array_unshift($names, tn801_ttm_get_term_name($parent_term));
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

	return array_map('tn801_ttm_get_term_name', $terms);
}
