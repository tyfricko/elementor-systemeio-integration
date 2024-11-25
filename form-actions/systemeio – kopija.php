<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SystemeIO_Action_After_Submit extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    public function get_name() {
        return 'systemeio';
    }

    public function get_label() {
        return 'Systeme.io';
    }

    public function register_settings_section( $widget ) {
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
            ]
        );

        $widget->add_control(
            'systemeio_email_field',
            [
                'label' => 'Email Field ID',
                'type' => \Elementor\Controls_Manager::TEXT,
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
                'description' => 'Enter the tag name to add to the contact',
            ]
        );

        $widget->end_controls_section();
    }

    public function run( $record, $ajax_handler ) {
        $settings = $record->get( 'form_settings' );
        $fields = $record->get( 'fields' );
    
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
    
        // Add last name if present - using the correct 'surname' slug
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
            $ajax_handler->add_error_message('Error: ' . $response->get_error_message());
            return;
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('Systeme.io API Response: ' . $response_body);
    
        if ($response_code !== 200 && $response_code !== 201) {
            $ajax_handler->add_error_message('Error creating contact: ' . $response_body);
            return;
        }
    
        $contact_data = json_decode($response_body, true);
    
        // Handle tag
        if (!empty($settings['systemeio_tag_name']) && !empty($contact_data['id'])) {
            // First try to find if tag exists
            $tags_response = wp_remote_get('https://api.systeme.io/api/tags', [
                'headers' => [
                    'X-API-Key' => $settings['systemeio_api_key']
                ]
            ]);
    
            error_log('Tags Response: ' . wp_remote_retrieve_body($tags_response));
    
            if (!is_wp_error($tags_response)) {
                $existing_tags = json_decode(wp_remote_retrieve_body($tags_response), true);
                $tag_id = null;
    
                // Look for existing tag
                if (is_array($existing_tags)) {
                    foreach ($existing_tags as $tag) {
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
    
                    error_log('Create Tag Response: ' . wp_remote_retrieve_body($create_tag_response));
    
                    if (!is_wp_error($create_tag_response)) {
                        $tag_data = json_decode(wp_remote_retrieve_body($create_tag_response), true);
                        if (isset($tag_data['id'])) {
                            $tag_id = $tag_data['id'];
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
    
                    error_log('Add Tag to Contact Response: ' . wp_remote_retrieve_body($add_tag_response));
                }
            }
        }
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
