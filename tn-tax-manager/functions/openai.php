<?php
/**
 * TN Tax Manager - OpenAI
 */

if (!defined('ABSPATH')) exit;

function tn801_ttm_get_ai_suggestions($post_id) {

	$terms = tn801_ttm_get_terms($post_id);
	$current_names = wp_list_pluck($terms, 'name');
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
			'Do not suggest terms already assigned.',
			'Prefer fewer strong suggestions.',
			'Return JSON only: {"suggestions":["Term A","Term B"]}'
		),
		'current_terms' => array_values($current_names),
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
			$current[strtolower($name)] = true;
		}

		foreach ($response['suggestions'] as $name) {
			$name = sanitize_text_field($name);
			$key = strtolower($name);

			if (!$name) continue;
			if (isset($current[$key])) continue;
			if (!isset($allowed[$key])) continue;

			$suggestions[] = $allowed[$key];
		}
	}

	return array_slice(array_values(array_unique($suggestions)), 0, 6);
}

function tn801_ttm_call_openai_json($payload) {

	$key = trim(get_option(TN801_TTM_OPENAI_KEY_OPTION, ''));
	$model = apply_filters('tn801_ttm_openai_model', TN801_TTM_OPENAI_MODEL);

	if ($key === '') {
		return new WP_Error('tn801_ttm_missing_key', 'Missing OpenAI API key.');
	}

	$response = wp_remote_post(
		'https://api.openai.com/v1/responses',
		array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode(array(
				'model' => $model,
				'input' => array(
					array(
						'role' => 'developer',
						'content' => array(
							array(
								'type' => 'input_text',
								'text' => 'Return valid JSON only. No commentary.'
							)
						)
					),
					array(
						'role' => 'user',
						'content' => array(
							array(
								'type' => 'input_text',
								'text' => wp_json_encode($payload)
							)
						)
					)
				),
				'text' => array(
					'format' => array(
						'type' => 'json_schema',
						'name' => 'tn801_taxonomy_suggestions',
						'strict' => true,
						'schema' => array(
							'type' => 'object',
							'additionalProperties' => false,
							'properties' => array(
								'suggestions' => array(
									'type' => 'array',
									'items' => array(
										'type' => 'string',
									),
								),
							),
							'required' => array('suggestions'),
						),
					),
				),
				'max_output_tokens' => 300,
			)),
		)
	);

	if (is_wp_error($response)) return $response;

	$status = wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if ($status < 200 || $status >= 300) {
		$message = '';

		if (!empty($data['error']['message'])) {
			$message = $data['error']['message'];
		} elseif (!empty($body)) {
			$message = wp_strip_all_tags($body);
		}

		if ($message === '') {
			$message = 'OpenAI request failed with HTTP ' . intval($status) . '.';
		}

		return new WP_Error('tn801_ttm_openai_http', 'OpenAI error: ' . $message);
	}

	if (!is_array($data)) {
		return new WP_Error('tn801_ttm_bad_response', 'OpenAI returned an unreadable response.');
	}

	if (!empty($data['error']['message'])) {
		return new WP_Error('tn801_ttm_openai_error', 'OpenAI error: ' . $data['error']['message']);
	}

	$text = '';

	if (!empty($data['output_text'])) {
		$text = $data['output_text'];
	} elseif (!empty($data['output']) && is_array($data['output'])) {
		foreach ($data['output'] as $output) {
			if (empty($output['content']) || !is_array($output['content'])) continue;

			foreach ($output['content'] as $content) {
				if (!empty($content['text'])) {
					$text = $content['text'];
					break 2;
				}
			}
		}
	}

	$json = json_decode(trim($text), true);

	if (!is_array($json)) {
		return new WP_Error('tn801_ttm_bad_json', 'Bad AI response: ' . $text);
	}

	return $json;
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
			'name' => $term->name,
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
		$out[strtolower($term->name)] = $term->name;
	}

	return $out;
}
