<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the ActiveCampaign v3 REST API.
 *
 * All public methods return either the decoded response array on success
 * or a WP_Error on failure. Callers should check with is_wp_error().
 */
class Swish_AC_Client {

	private $api_url;
	private $api_key;

	public function __construct( $api_url, $api_key ) {
		$this->api_url = untrailingslashit( trim( (string) $api_url ) );
		$this->api_key = trim( (string) $api_key );
	}

	public function is_configured() {
		return $this->api_url !== '' && $this->api_key !== '';
	}

	/**
	 * Test credentials by calling /users/me.
	 */
	public function test_connection() {
		return $this->request( 'GET', '/api/3/users/me' );
	}

	/**
	 * Upsert a contact by email. AC returns the contact (existing or new).
	 *
	 * @param string $email
	 * @param string|null $first_name
	 * @return array|WP_Error  The contact object on success.
	 */
	public function upsert_contact( $email, $first_name = null ) {
		$contact = array( 'email' => $email );
		if ( $first_name !== null && $first_name !== '' ) {
			$contact['firstName'] = $first_name;
		}
		$response = $this->request( 'POST', '/api/3/contact/sync', array( 'contact' => $contact ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response['contact']['id'] ) ) {
			return new WP_Error( 'swish_ac_no_contact', 'ActiveCampaign did not return a contact id.', $response );
		}
		return $response['contact'];
	}

	/**
	 * Subscribe contact to a list (status=1).
	 */
	public function add_contact_to_list( $contact_id, $list_id ) {
		return $this->request( 'POST', '/api/3/contactLists', array(
			'contactList' => array(
				'list'    => (int) $list_id,
				'contact' => (int) $contact_id,
				'status'  => 1,
			),
		) );
	}

	/**
	 * Find a tag by exact name, or create it.
	 *
	 * @return int|WP_Error  Tag id.
	 */
	public function find_or_create_tag( $tag_name ) {
		$tag_name = trim( (string) $tag_name );
		if ( $tag_name === '' ) {
			return new WP_Error( 'swish_ac_empty_tag', 'Empty tag name.' );
		}
		$search = $this->request( 'GET', '/api/3/tags', null, array( 'search' => $tag_name ) );
		if ( is_wp_error( $search ) ) {
			return $search;
		}
		if ( ! empty( $search['tags'] ) ) {
			foreach ( $search['tags'] as $tag ) {
				if ( isset( $tag['tag'] ) && strcasecmp( $tag['tag'], $tag_name ) === 0 ) {
					return (int) $tag['id'];
				}
			}
		}
		$created = $this->request( 'POST', '/api/3/tags', array(
			'tag' => array(
				'tag'         => $tag_name,
				'tagType'     => 'contact',
				'description' => '',
			),
		) );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		if ( empty( $created['tag']['id'] ) ) {
			return new WP_Error( 'swish_ac_no_tag', 'ActiveCampaign did not return a tag id.', $created );
		}
		return (int) $created['tag']['id'];
	}

	/**
	 * Apply a tag to a contact.
	 */
	public function add_tag_to_contact( $contact_id, $tag_id ) {
		return $this->request( 'POST', '/api/3/contactTags', array(
			'contactTag' => array(
				'contact' => (int) $contact_id,
				'tag'     => (int) $tag_id,
			),
		) );
	}

	/**
	 * List tags (for the sidebar picker). Paginated by AC; we pull up to 100.
	 */
	public function list_tags( $limit = 100 ) {
		return $this->request( 'GET', '/api/3/tags', null, array( 'limit' => (int) $limit ) );
	}

	/**
	 * List AC lists (the things contacts subscribe to). Up to 100.
	 */
	public function list_lists( $limit = 100 ) {
		return $this->request( 'GET', '/api/3/lists', null, array( 'limit' => (int) $limit ) );
	}

	/**
	 * List AC custom contact fields.
	 */
	public function list_fields( $limit = 100 ) {
		return $this->request( 'GET', '/api/3/fields', null, array( 'limit' => (int) $limit ) );
	}

	/**
	 * Fetch the current field value (if any) for a contact + field pair.
	 * Returns the fieldValue object (with 'id' and 'value'), null if none,
	 * or WP_Error on transport failure.
	 */
	public function get_field_value( $contact_id, $field_id ) {
		$result = $this->request( 'GET', '/api/3/fieldValues', null, array(
			'filters[fieldid]'   => (int) $field_id,
			'filters[contactid]' => (int) $contact_id,
		) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['fieldValues'] ) && is_array( $result['fieldValues'] ) ) {
			return $result['fieldValues'][0];
		}
		return null;
	}

	/**
	 * Create or update a custom field value for a contact.
	 */
	public function set_field_value( $contact_id, $field_id, $value ) {
		$existing = $this->get_field_value( $contact_id, $field_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$payload = array(
			'fieldValue' => array(
				'contact' => (int) $contact_id,
				'field'   => (int) $field_id,
				'value'   => (string) $value,
			),
		);

		if ( $existing && isset( $existing['id'] ) ) {
			return $this->request( 'PUT', '/api/3/fieldValues/' . (int) $existing['id'], $payload );
		}
		return $this->request( 'POST', '/api/3/fieldValues', $payload );
	}

	/**
	 * Low-level request.
	 *
	 * @param string $method
	 * @param string $path        Starts with /api/3/...
	 * @param array|null $body    JSON-encoded if provided.
	 * @param array $query        Query string params.
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $body = null, $query = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'swish_ac_not_configured', 'ActiveCampaign API URL and key are not set.' );
		}

		$url = $this->api_url . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Api-Token' => $this->api_key,
				'Accept'    => 'application/json',
			),
		);

		if ( $body !== null ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[swish-ac] %s %s -> HTTP %d: %s',
					$method, $path, $code, substr( (string) $raw, 0, 500 )
				) );
			}
			return new WP_Error(
				'swish_ac_http_' . $code,
				sprintf( 'ActiveCampaign returned HTTP %d on %s', $code, $path ),
				array( 'status' => $code, 'body' => $data ?: $raw, 'path' => $path )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
