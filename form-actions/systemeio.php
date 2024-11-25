<?php
if (!defined('ABSPATH')) {
    exit;
}

class SystemeIO_Action_After_Submit extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    public function get_name() {
        return 'systemeio';
    }

    public function get_label() {
        return 'Systeme.io';
    }

    public function register_settings_section($widget) {
        $widget->start_controls_section(
            'section_systemeio',
            [
                'label' => 'Systeme.io',
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'systemeio_api_key',
            [
                'label' => 'API Key',
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Enter your Systeme.io API key',
                'required' => true,
            ]
        );

        $widget->add_control(
            'systemeio_email_field',
            [
                'label' => 'Email Field ID',
                'type' => \Elementor\Controls_Manager::TEXT,
                'required' => true,
            ]
        );

        $widget->add_control(
            'systemeio_firstname_field',
            [
                'label' => 'First Name Field ID',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $widget->add_control(
            'systemeio_lastname_field',
            [
                'label' => 'Last Name Field ID',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $widget->add_control(
            'systemeio_tag_name',
            [
                'label' => 'Tag Name',
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Enter the tag name. If the tag doesn\'t exist, it will be created automatically.',
                'required' => true,
            ]
        );

        $widget->end_controls_section();
    }

    public function run($record, $ajax_handler) {
        $settings = $record->get('form_settings');
        $fields = $record->get('fields');

        // Validate required fields
        if (empty($settings['systemeio_api_key'])) {
            $ajax_handler->add_error_message('Systeme.io API Key is required.');
            return;
        }

        if (empty($settings['systemeio_email_field']) || empty($fields[$settings['systemeio_email_field']]['value'])) {
            $ajax_handler->add_error_message('Email field is required.');
            return;
        }

        if (empty($settings['systemeio_tag_name'])) {
            $ajax_handler->add_error_message('Tag name is required.');
            return;
        }

        // Prepare contact data
        $contact_data = [
            'email' => $fields[$settings['systemeio_email_field']]['value'],
            'fields' => []
        ];

        // Add first name if present
        if (!empty($fields[$settings['systemeio_firstname_field']]['value'])) {
            $contact_data['fields'][] = [
                'slug' => 'first_name',
                'value' => $fields[$settings['systemeio_firstname_field']]['value']
            ];
        }

        // Add last name if present
        if (!empty($fields[$settings['systemeio_lastname_field']]['value'])) {
            $contact_data['fields'][] = [
                'slug' => 'surname',
                'value' => $fields[$settings['systemeio_lastname_field']]['value']
            ];
        }

        // Create contact
        $response = wp_remote_post('https://api.systeme.io/api/contacts', [
            'headers' => [
                'X-API-Key' => $settings['systemeio_api_key'],
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($contact_data)
        ]);

        if (is_wp_error($response)) {
            $ajax_handler->add_error_message('Error connecting to Systeme.io: ' . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200 && $response_code !== 201) {
            $error_data = json_decode($response_body, true);
            
            // Check if the error is because the email is already used
            if ($response_code === 422 && isset($error_data['detail']) && strpos($error_data['detail'], 'email: This value is already used') !== false) {
                $ajax_handler->add_error_message('This email is already subscribed.');
            } else {
                // For admins, show detailed error
                if (current_user_can('manage_options')) {
                    $error_message = 'Error creating contact: ' . (isset($error_data['detail']) ? $error_data['detail'] : 'Unknown error');
                    $ajax_handler->add_error_message($error_message . ' This message is not visible to site visitors.');
                } else {
                    // For regular users, show simple error
                    $ajax_handler->add_error_message('Error creating contact. Please try again or contact support.');
                }
            }
            return;
        }
        
        $contact_data = json_decode($response_body, true);

        // Handle tag
        if (!empty($settings['systemeio_tag_name']) && !empty($contact_data['id'])) {
            // First try to find if tag exists
            $tags_response = wp_remote_get('https://api.systeme.io/api/tags', [
                'headers' => [
                    'X-API-Key' => $settings['systemeio_api_key'],
                    'accept' => 'application/json'
                ]
            ]);

            if (!is_wp_error($tags_response)) {
                $tags_data = json_decode(wp_remote_retrieve_body($tags_response), true);
                $tag_id = null;

                // Look for existing tag
                if (isset($tags_data['items']) && is_array($tags_data['items'])) {
                    foreach ($tags_data['items'] as $tag) {
                        if (strtolower($tag['name']) === strtolower($settings['systemeio_tag_name'])) {
                            $tag_id = $tag['id'];
                            break;
                        }
                    }
                }

                // If tag doesn't exist, create it
                if (!$tag_id) {
                    $create_tag_response = wp_remote_post('https://api.systeme.io/api/tags', [
                        'headers' => [
                            'X-API-Key' => $settings['systemeio_api_key'],
                            'Content-Type' => 'application/json'
                        ],
                        'body' => json_encode([
                            'name' => $settings['systemeio_tag_name']
                        ])
                    ]);

                    if (!is_wp_error($create_tag_response)) {
                        $response_code = wp_remote_retrieve_response_code($create_tag_response);
                        $response_body = json_decode(wp_remote_retrieve_body($create_tag_response), true);

                        if ($response_code === 200 || $response_code === 201) {
                            if (isset($response_body['id'])) {
                                $tag_id = $response_body['id'];
                            }
                        } else {
                            // Handle plan limitation error
                            if (isset($response_body['detail']) && strpos($response_body['detail'], 'upgrade your plan') !== false) {
                                $ajax_handler->add_error_message('Unable to create new tag: Maximum number of tags reached in your Systeme.io plan.');
                                return;
                            } else {
                                $ajax_handler->add_error_message('Failed to create tag in Systeme.io');
                                return;
                            }
                        }
                    }
                }

                // Add tag to contact if we have a tag ID
                if ($tag_id) {
                    $add_tag_response = wp_remote_post("https://api.systeme.io/api/contacts/{$contact_data['id']}/tags", [
                        'headers' => [
                            'X-API-Key' => $settings['systemeio_api_key'],
                            'Content-Type' => 'application/json'
                        ],
                        'body' => json_encode([
                            'tagId' => $tag_id
                        ])
                    ]);

                    $response_code = wp_remote_retrieve_response_code($add_tag_response);
                    
                    if ($response_code !== 204) {
                        $ajax_handler->add_error_message('Failed to add tag to contact');
                        return;
                    }
                }
            }
        }

        $ajax_handler->add_success_message('Contact successfully added to Systeme.io with tag.');
    }

    public function on_export($element) {
        unset(
            $element['systemeio_api_key'],
            $element['systemeio_email_field'],
            $element['systemeio_firstname_field'],
            $element['systemeio_lastname_field'],
            $element['systemeio_tag_name']
        );
        return $element;
    }
}
