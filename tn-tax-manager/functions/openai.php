<?php
/**
 * TN Tax Manager - OpenAI
 */

if (!defined('ABSPATH')) exit;

function tn801_ttm_get_ai_suggestions($post_id) {

	$current_names = tn801_ttm_get_assigned_term_names($post_id);
	$tree = tn801_ttm_get_taxonomy_tree_paths();
	$post = get_post($post_id);

	if (!$post) return array();

	$page_payload = array(
		'title'   => get_the_title($post_id),
		'excerpt' => wp_strip_all_tags(get_the_excerpt($post_id)),
		'content' => tn801_ttm_get_post_plain_text($post),
	);

	$prompt = array(
		'task' => 'Suggest existing taxonomy terms for this page.',
		'rules' => array(
			'Only suggest terms from the taxonomy_tree.',
			'Do not invent new terms.',
			'Do not suggest terms already assigned to this post in the managed taxonomy.',
			'Prefer fewer strong suggestions.',
			'If no unassigned terms are suitable, return {"suggestions":[]}.',
			'Return JSON only: {"suggestions":["Term A","Term B"]}'
		),
		'assigned_terms' => array_values($current_names),
		'taxonomy_tree' => $tree,
		'page' => $page_payload,
	);

	$response = tn801_ttm_call_openai_json($prompt);

	if (is_wp_error($response)) return $response;

	$suggestions = array();

	if (!empty($response['suggestions']) && is_array($response['suggestions'])) {

		$allowed = tn801_ttm_get_term_name_lookup();
		$current = array();

		foreach ($current_names as $name) {
			$current[tn801_ttm_get_term_name_key($name)] = true;
		}

		foreach ($response['suggestions'] as $name) {
			$name = tn801_ttm_decode_term_name(sanitize_text_field($name));
			$key = tn801_ttm_get_term_name_key($name);

			if (!$name) continue;
			if (isset($current[$key])) continue;
			if (!isset($allowed[$key])) continue;

			$suggestions[] = $allowed[$key];
		}
	}

	return array_slice(array_values(array_unique($suggestions)), 0, 6);
}

function tn801_ttm_call_openai_json($payload) {

	if (!function_exists('wp_ai_client_prompt')) {
		return new WP_Error(
			'tn801_ttm_missing_ai_client',
			'WordPress AI Client is unavailable. TN Tax Manager requires WordPress 7.0+ with the OpenAI provider configured.'
		);
	}

	$text = wp_ai_client_prompt(wp_json_encode($payload))
		->using_provider('openai')
		->using_system_instruction('Return valid JSON only. No commentary.')
		->using_max_tokens(1000)
		->generate_text();

	if (is_wp_error($text)) {
		return new WP_Error(
			'tn801_ttm_ai_client_error',
			'OpenAI connector error: ' . $text->get_error_message(),
			$text->get_error_data()
		);
	}

	$json = tn801_ttm_decode_ai_json((string) $text);

	if (!is_array($json)) {
		return new WP_Error('tn801_ttm_bad_json', 'Bad AI response: ' . (string) $text);
	}

	return $json;
}

function tn801_ttm_decode_ai_json($text) {
	$text = trim($text);
	$json = json_decode($text, true);

	if (is_array($json)) {
		return $json;
	}

	$start = strpos($text, '{');
	$end = strrpos($text, '}');

	if ($start === false || $end === false || $end <= $start) {
		return null;
	}

	$json = json_decode(substr($text, $start, $end - $start + 1), true);

	return is_array($json) ? $json : null;
}

function tn801_ttm_get_taxonomy_tree_paths() {

	$taxonomy = tn801_ttm_get_taxonomy();

	$terms = get_terms(array(
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
	));

	if (is_wp_error($terms) || empty($terms)) return array();

	$out = array();

	foreach ($terms as $term) {
		$out[] = array(
			'name' => tn801_ttm_get_term_name($term),
			'path' => tn801_ttm_get_term_path($term),
		);
	}

	return $out;
}

function tn801_ttm_get_term_name_lookup() {

	$taxonomy = tn801_ttm_get_taxonomy();

	$terms = get_terms(array(
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
	));

	$out = array();

	if (is_wp_error($terms) || empty($terms)) return $out;

	foreach ($terms as $term) {
		$name = tn801_ttm_get_term_name($term);
		$out[tn801_ttm_get_term_name_key($name)] = $name;
	}

	return $out;
}
