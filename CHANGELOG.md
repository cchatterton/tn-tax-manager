# Changelog

All notable changes to TN Tax Manager are recorded here.

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
