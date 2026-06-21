<?php
/**
 * TN Tax Manager - Actions
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_tn801_ttm_ai_suggest', 'tn801_ttm_ai_suggest');
add_action('wp_ajax_tn801_ttm_lookup', 'tn801_ttm_lookup');
add_action('wp_ajax_tn801_ttm_add_async', 'tn801_ttm_add_async');
add_action('admin_post_tn801_ttm_add', 'tn801_ttm_add');
add_action('admin_post_tn801_ttm_remove', 'tn801_ttm_remove');

function tn801_ttm_ai_suggest() {

	check_ajax_referer('tn801_ttm_ai_suggest', 'nonce');

	$post_id = absint($_POST['post_id'] ?? 0);

	if (!$post_id) {
		wp_send_json_error('Missing post ID.');
	}

	$suggestions = tn801_ttm_get_ai_suggestions($post_id);

	if (is_wp_error($suggestions)) {
		wp_send_json_error($suggestions->get_error_message());
	}

	wp_send_json_success(array_values($suggestions));
}

function tn801_ttm_lookup() {

	$search = sanitize_text_field($_GET['q'] ?? '');
	$taxonomy = tn801_ttm_get_taxonomy();

	$terms = get_terms(array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'search'     => $search,
		'number'     => 20,
	));

	$out = array();

	if (!is_wp_error($terms)) {
		foreach ($terms as $term) {
			$out[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'path' => tn801_ttm_get_term_path($term),
			);
		}
	}

	wp_send_json($out);
}

function tn801_ttm_add() {

	check_admin_referer('tn801_ttm_add', 'tn801_ttm_nonce');

	$post_id   = absint($_POST['post_id'] ?? 0);
	$redirect  = esc_url_raw($_POST['redirect_to'] ?? home_url());
	$term_name = sanitize_text_field($_POST['term_name'] ?? '');

	$result = tn801_ttm_create_and_assign_term($post_id, $term_name);

	if (!is_wp_error($result) && !empty($result['created_new'])) {
		$redirect = add_query_arg('tn801_ttm_new_term', $result['term_id'], $redirect);
	}

	wp_redirect($redirect);
	exit;
}

function tn801_ttm_add_async() {

	check_ajax_referer('tn801_ttm_add', 'tn801_ttm_nonce');

	$post_id   = absint($_POST['post_id'] ?? 0);
	$term_name = sanitize_text_field($_POST['term_name'] ?? '');

	$result = tn801_ttm_create_and_assign_term($post_id, $term_name);

	if (is_wp_error($result)) {
		wp_send_json_error($result->get_error_message());
	}

	wp_send_json_success($result);
}

function tn801_ttm_create_and_assign_term($post_id, $term_name) {

	if (!$post_id || !$term_name) {
		return new WP_Error('tn801_ttm_missing_add_data', 'Missing post ID or category name.');
	}

	$taxonomy = tn801_ttm_get_taxonomy();
	$term = get_term_by('name', $term_name, $taxonomy);
	$created_new = false;

	if (!$term) {
		$inserted = wp_insert_term($term_name, $taxonomy);

		if (is_wp_error($inserted)) {
			return $inserted;
		}

		$term = get_term($inserted['term_id'], $taxonomy);
		$created_new = true;
	}

	if (!$term || is_wp_error($term)) {
		return new WP_Error('tn801_ttm_missing_term', 'Could not find or create that category.');
	}

	$term_id = (int) $term->term_id;
	$assigned = wp_set_post_terms($post_id, array($term_id), $taxonomy, true);

	if (is_wp_error($assigned)) {
		return $assigned;
	}

	clean_object_term_cache($post_id, get_post_type($post_id));

	if ($term && !is_wp_error($term)) {
		if ($created_new) {
			update_term_meta($term_id, '_tn801_ttm_created_at', time());
		}
	}

	return array(
		'term_id'     => $term_id,
		'name'        => $term->name,
		'taxonomy'    => $taxonomy,
		'created_new' => $created_new,
	);
}

function tn801_ttm_remove() {

	check_admin_referer('tn801_ttm_remove', 'tn801_ttm_nonce');

	$post_id  = absint($_POST['post_id'] ?? 0);
	$term_id  = absint($_POST['term_id'] ?? 0);
	$taxonomy = sanitize_key($_POST['taxonomy'] ?? tn801_ttm_get_taxonomy());
	$redirect = esc_url_raw($_POST['redirect_to'] ?? home_url());

	if (!$post_id || !$term_id) {
		wp_redirect($redirect);
		exit;
	}

	if (!taxonomy_exists($taxonomy)) {
		$taxonomy = tn801_ttm_get_taxonomy();
	}

	wp_remove_object_terms($post_id, $term_id, $taxonomy);

	wp_redirect($redirect);
	exit;
}
