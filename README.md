# Elementor Systeme.io Integration

A WordPress plugin that adds Systeme.io integration to Elementor forms.

## Description

This plugin adds a new action to Elementor Pro forms that allows you to integrate form submissions with Systeme.io. It supports:
- Adding contacts to Systeme.io
- Setting first name and last name
- Adding tags to contacts
- Automatic tag creation if it doesn't exist (beware of your systeme.io plan limit)
- Handling existing contacts gracefully
- Adding tags to existing contacts without duplications

## Installation

1. Upload the plugin files to the `wp-content/plugins/elementor-systemeio-integration` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings-Plugin Name screen to configure the plugin
4. Add the Systeme.io action to your Elementor forms

## Requirements

- WordPress 5.6 or later
- Elementor Pro
- PHP 7.0 or later
- Systeme.io API key

## Configuration

1. Get your Systeme.io API key from your Systeme.io account
2. In your Elementor form settings, add the Systeme.io action
3. Configure the field mappings:
   - Email Field ID (required)
   - First Name Field ID (optional)
   - Last Name Field ID (optional)
   - Tag Name (required)

## Changelog

### 1.1.0
- Added support for handling existing contacts
- Added automatic tag checking to prevent duplicates
- Improved error handling and messages
- Added different error messages for admins and regular users

### 1.0.0
- Initial release

## Support

For support, please create an issue in the GitHub repository.

## License

GPL v2 or later
