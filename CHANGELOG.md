# Changelog

All notable changes to TN Tax Manager are recorded here.

## 1.0.28 - 2026-06-21

- Restored the original add-term flow so existing or newly created category terms are assigned to the current post before redirect.
- Used WordPress's native category assignment path when the managed taxonomy is `category`.

## 1.0.27 - 2026-06-21

- Treated native AI Client zero-candidate responses as no suggestions instead of surfacing an OpenAI connector error.
- Clarified the AI prompt to return an empty suggestions array when no suitable unassigned category exists.

## 1.0.26 - 2026-06-21

- Verified category assignment after adding a term before launching the new-term cleanup flow.
- Switched add actions to `wp_set_object_terms()` and refreshed post term cache after assignment.

## 1.0.25 - 2026-06-21

- Displayed all current assigned public taxonomy terms in the front-end current tags UI.
- Passed the term taxonomy through remove actions so current terms outside the managed taxonomy can be removed correctly.

## 1.0.24 - 2026-06-21

- Removed the AI Client temperature setting so the native OpenAI provider can use models that do not support `temperature`.

## 1.0.23 - 2026-06-21

- Updated the minimum WordPress requirement to 7.0.
- Switched AI suggestions from direct OpenAI HTTP requests to the native WordPress AI Client OpenAI provider.
- Removed the plugin-specific OpenAI API key setting; credentials are now managed by the WordPress OpenAI provider.
- Excluded all terms already assigned to the post from AI suggestions, including terms displayed by related-list taxonomies.

## 1.0.22 - 2026-06-21

- Fixed OpenAI Responses API suggestions by using a supported model and structured JSON output.
- Added clearer OpenAI HTTP/API error handling for failed suggestion requests.
- Escaped AI suggestion labels in the front-end renderer.
- Added GitHub release update metadata and a native WordPress GitHub updater.
- Added release packaging through `scripts/build-plugin-zip.sh`.

## 1.0.0 - 2026-05-23

- Initial release.
