<?php

/*
 *
 * Plugin Name: Utility Styler
 *
 */

namespace UtilityStyler;

define( 'UTILITY_STYLER_URL', plugin_dir_url( __FILE__ ) );
define( 'UTILITY_STYLER_PATH', plugin_dir_path( __FILE__ ) );
define( 'UTILITY_STYLER_VERSION', '0.0.0' );

class Plugin {

	public function __construct() {

		$this->scripts();
		$this->api();
		add_action('save_post', function( $post_id ) {
			$this->set_utility_styles_changed_flag( $post_id, true );
		});

	}

	public function api() {

		add_action('rest_api_init', function () {

	    register_rest_route('utility-styler/v1', 'save-css-json', array(
        'methods' => 'POST',
        'callback' => function ($request) {

					$payload_data = $request->get_json_params();

					if ( isset( $payload_data['css_json'] ) ) {
					  $css_json = $payload_data['css_json'];
					}

					$current_post = (int) $payload_data['current_post'];

					$css = json_decode( $css_json, 1 );
					foreach( $css as $selector => $rules ) {
						$style_exists =  $this->style_exists_by_selector( $selector );
						if( $style_exists ) {
							$existing_style_row           = $this->fetch_style_by_selector( $selector );
							$existing_style_row_locations = json_decode( $existing_style_row->locations );

							if ( ! in_array( $current_post, $existing_style_row_locations ) ) {
								$updated_style_locations   = $existing_style_row_locations;
		            $updated_style_locations[] = $current_post;
		            $this->update_style($selector, $rules, $updated_style_locations );
			        }
						}
						if( ! $style_exists ) {
							$locations = [ $current_post ];
							$this->insert_style( $selector, $rules, $locations );
						}
						echo "\n" . $selector;
					}
					$this->generate();
					$this->set_utility_styles_changed_flag( $current_post, false );
		    },
				'permission_callback' => function() { return true; },
	    ));
		});


	}

	/**
	 * Check if a style exists in the utility_styles table based on the selector.
	 *
	 * @param string $selector The CSS selector to check.
	 * @return bool True if the style exists, false otherwise.
	 */
	function style_exists_by_selector($selector) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'utility_styles';
    $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE selector = %s", $selector);
    $result = $wpdb->get_var($query);
    return (intval($result) > 0);
	}

	/**
	 * Insert a style into the utility_styles table.
	 *
	 * @param string $selector The CSS selector.
	 * @param string $rules    The CSS rules associated with the selector.
	 * @return bool True if the style is inserted, false if it already exists or an error occurs.
	 */
	public function insert_style( $selector, $rules, $locations ) {

    global $wpdb;
    $table_name = $wpdb->prefix . 'utility_styles';
    $insert_result = $wpdb->insert(
      $table_name,
      array(
        'selector'  => $selector,
        'rules'     => $rules,
				'locations' => json_encode( $locations ),
      ),
      array('%s', '%s')
    );
    if ($insert_result === false) {
        return false;
    }
    return true;

	}

	/**
	 * Update a style's locations in the utility_styles table.
	 *
	 * @param string $selector  The CSS selector.
	 * @param string $rules     The CSS rules associated with the selector.
	 * @param array  $locations An array of locations to update.
	 * @return bool True if the style is updated successfully, false if an error occurs.
	 */
	public function update_style($selector, $rules, $locations) {

		  global $wpdb;
	    if (!$this->style_exists_by_selector($selector)) {
	       return false;
	    }
	    $update_result = $wpdb->update(
        $wpdb->prefix . 'utility_styles',
        array('locations' => json_encode($locations)),
        array('selector' => $selector),
        array('%s'),
        array('%s')
	    );
	    return $update_result !== false;

	}


	/**
	 * Fetch a style by selector from the utility_styles table.
	 *
	 * @param string $selector The CSS selector to fetch.
	 * @return array|false An associative array containing the style data if found, or false if not found.
	 */
	function fetch_style_by_selector($selector) {

	    global $wpdb;
	    $table_name = $wpdb->prefix . 'utility_styles';
	    $query = $wpdb->prepare("SELECT * FROM $table_name WHERE selector = %s", $selector);
	    $result = $wpdb->get_row( $query );
	    return $result;

	}

	public function scripts() {

		add_action( 'enqueue_block_assets', function() {

			global $post;
			global $current_screen;

			if ( $current_screen !== null && $current_screen->is_block_editor() || $this->post_needs_utility_styles_parsed( $post->ID ) ) {

				wp_enqueue_script(
	        'unocss-runtime-preset-wind',
	        'https://cdn.jsdelivr.net/npm/@unocss/runtime@0.58.3/preset-wind.global.js',
	        array(),
	        time(),
	        true
		    );

				wp_enqueue_script(
	        'unocss-runtime-config',
	        UTILITY_STYLER_URL . '/script/unocss-config.js',
	        array( 'unocss-runtime-preset-wind' ),
	        time(),
	        true
		    );

		    wp_enqueue_script(
	        'unocss-runtime',
	        'https://cdn.jsdelivr.net/npm/@unocss/runtime/core.global.js',
	        array( 'unocss-runtime-config' ),
	        time(),
	        true
		    );

				wp_enqueue_script(
	        'utility-styler-parser',
	        UTILITY_STYLER_URL . '/script/parser.js',
	        array( 'unocss-runtime' ),
	        time(),
	        true
		    );

				wp_localize_script(
	        'utility-styler-parser',
	        'utilityStylerData',
	        array(
		        'currentPostId' => $post->ID,
	        )
		    );

			} else {

				$css_file_url = content_url('utility-styles.css');
				wp_enqueue_style('utility-styles-compiled', $css_file_url, array(), time());

			}

		});

		add_action( 'wp_enqueue_scripts', function () {



		});

	}

	/**
	 * Generate a CSS stylesheet from the utility_styles table and save it to a file.
	 */
	function generate() {

	    global $wpdb;
	    $table_name = $wpdb->prefix . 'utility_styles';
	    $query = "SELECT selector, rules FROM $table_name";
	    $styles = $wpdb->get_results($query, ARRAY_A);
	    $stylesheet = '.wp-block';
			foreach ($styles as $style) {
        $selector = $style['selector'];
        $rules = $style['rules'];
        $rule_lines = explode(";", $rules);
				if ( end( $rule_lines ) === '' ) {
          array_pop( $rule_lines );
        }
        $formatted_rules = implode(";\n\t", $rule_lines);
        $stylesheet .= "$selector {\n\t$formatted_rules\n}\n\n";
    }
	    $file_path = ABSPATH . 'wp-content/utility-styles.css';
	    file_put_contents($file_path, $stylesheet);

	}

	function db_table() {

    global $wpdb;
    $table_name = $wpdb->prefix . 'utility_styles';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        selector varchar(255) NOT NULL,
        rules varchar(1000) NOT NULL,
        locations JSON NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

	}

	public static function activate() {

	  $plugin = new Plugin();
	  $plugin->db_table();

	}

	/**
	 * Hook into the save_post action to set a post meta flag when a post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	function set_utility_styles_changed_flag( $post_id, $value ) {

    $post_type = get_post_type( $post_id );
    if ( $this->is_public_post_type( $post_type ) ) {
      update_post_meta( $post_id, 'utility_styles_changed', $value );
    }

	}

	function post_needs_utility_styles_parsed( $post_id ) {
		return get_post_meta( $post_id, 'utility_styles_changed', 1 );
	}

	/**
	 * Check if a post type is public.
	 *
	 * @param string $post_type The post type to check.
	 * @return bool Whether the post type is public or not.
	 */
	function is_public_post_type($post_type) {
    $post_type_object = get_post_type_object($post_type);
    return $post_type_object && $post_type_object->public === true;
	}

}

new Plugin();

register_activation_hook(__FILE__, array('UtilityStyler\Plugin', 'activate'));
