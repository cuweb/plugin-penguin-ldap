<?php

class Penguin_Login {
	private $enabled, $link_identifier, $bind, $penguin_settings, $options;

	public function __construct( $penguin_settings ) {
		$this->settings = $penguin_settings;
	}

	// Use LDAP to sign the user into wordpress
	public function authenticate( $user, $username, $password ) {
		/**
		 * Assumed this is done before this function is called:
		 * $username = sanitize_user( $username);
		 * $password = trim( $password);
		 */

		// If previous authentication succeeded, respect that
		if ( is_a( $user, 'WP_User') ) {
			return $user;
		}
		
		if ( empty( $username ) ){
			return $this->error_message( "empty_username",
				'The username field is empty.' );
		}
		
		if ( empty( $password ) ){
			return $this->error_message( "empty_password",
				"The password field is empty." );
		}
		
		$this->settings->load_all_options();

		// Connect to LDAP
		$this->link_identifier = ldap_connect( $this->settings->options['server'],
			$this->settings->options['port'] );

		if ( ! $this->link_identifier ) {
			return $this->error_message( "ldap_connect_fail", "LDAP error." );
		}

		//Set LDAP options
		$protocol_result = ldap_set_option( $this->link_identifier, LDAP_OPT_PROTOCOL_VERSION,
			$this->settings->options['protocol_version'] );

		if ( ! $protocol_result ) {
			return $this->error_message( "set_option_protocol_fail",
				"Could not set protocol");
		}

		$opt_ref_result = ldap_set_option( $this->link_identifier, LDAP_OPT_REFERRALS,
			$this->settings->options['referrals'] );

		if ( ! $opt_ref_result ) {
			return $this->error_message( "set_option_opt_ref_fail",
				"Could not set opt referrals" );
		}

		$this->bind = @ldap_bind( $this->link_identifier,
			$username . $this->settings->options['extension'], $password );

		if ( ! $this->bind ) {
			//do_action( 'wp_login_failed', $username );
			return $this->error_message( "bind_fail", 'Invalid credentials.' );
		}

		// True if the user exists, false otherwise
		$user_exists = ! is_null( username_exists( $username ) );

		$object_class_string = "";
		if ( $this->settings->get_option( 'objectclass') !== "" ) {
			$object_class_string = "( objectClass=" .
			$this->settings->options['objectclass'] .
			")";
		}

		$result_identifier = @ldap_search( $this->link_identifier,
			$this->settings->options['dn'],
			"( &$object_class_string(" . $this->settings->options['login_field'] . "=" . $username . "))" );

		if ( ! $result_identifier ) {
			do_action( 'wp_login_failed', $username );
			return $this->error_message( "ldap_search_fail",
				'There was an error searching for your username.' );
		}

		$entries = ldap_get_entries( $this->link_identifier, $result_identifier );

		if ( ! $entries ) {
			return $this->error_message( "ldap_get_entries_fail",
				'There was an error searching for your username.' );
		}

		if ( $user_exists ) {
			$user_LDAP = get_user_by ( 'login', $username );
			wp_set_password( wp_generate_password( ), $user_LDAP->ID );
		}
		else {

			if ( $entries['count'] != 1 ) {
				return $this->error_message( "ldap_wrong_number_of_entries",
					'Cannot find username.' );
			}

			$email = $this->settings->options['email'];
			if ( isset ( $entries[0][$email] ) ) {
				if ( isset ( $entries[0][$email][0] ) ) {
					$userID = wp_create_user( $username, wp_generate_password(),
						$entries[0][$email][0] );

					$user_LDAP = new WP_User( $userID );
				}
			}
			// Need this?: return $this->error_message( "ldap_entries_error", 'Cannot find username.' );
		}
		ldap_unbind( $this->link_identifier );

		/**
		* If group mapping is not enabled, then assign the default role.
		* Otherwise, look through what LDAP groups have been assigned to roles.
		*/
		if ( ! $this->settings->options['enable_group_mapping'] ) {
			$user_LDAP->set_role( $this->settings->options['default_role'] );
		}
		else {
			$this->set_user_role_from_group( $user_LDAP, $entries[0] );
		}
	
		update_user_meta( $user_LDAP->ID,
			'first_name',
			$entries[0][$this->settings->options['first_name']][0] );
		update_user_meta( $user_LDAP->ID,
			'last_name',
			$entries[0][$this->settings->options['last_name']][0] );

		return $user_LDAP;
	}

	private function set_user_role_from_group ( $wp_user, $ad_entry ) {
		$assigned_group_count = count( $this->settings->options['groups'] );
		$highest_role_priority_level = PHP_INT_MAX;

		// The user doesn't belong to any groups, so they are not allowed access
		if ( $ad_entry['memberof']['count'] === 0 ) {
			return $this->error_message( "access_denied",
				"You do not have permission to access this page." );
		}
		else {
			// For each group this user is a member of
			for ( $i = 0; $i < $ad_entry['memberof']['count']; $i ++) {

				// Grab the CN value for this group in the 'memberof' Array
				$common_name = substr(
					$ad_entry['memberof'][$i], // String to take a portion of
					3, // Starting from this index in the string ( From AD: "CN=")

					/**
					 * Up until the first comma ( Subtract 3 because this function
					 * grabs the substring based on starting position ( 3) and *length*
					 * instead of starting position and end position
					 */
					strpos( $ad_entry['memberof'][$i], ',' ) - 3
				);
				//regex that should work ^CN=([^,]+) (get the second index of the array though)

				/**
				 * For all assigned LDAP groups, we need to check if the user is a
				 * member of any of them. To clarify, we want to find all LDAP groups
				 * that have an assigned role *and* the current user is a member of.
				 */

				// For all of the LDAP groups that have been assigned a WordPress role
				for ( $k = 0; $k < $assigned_group_count; $k ++) {

					/**
					 * Check if one of the assigned groups matches a common name.
					 * If it does, that means that the LDAP group name in the
					 * Wordpress settings page matches a common name for a group from
					 * LDAP.
					 */
					if ( strcasecmp ( $this->settings->get_option( 'groups', $k, 0 ), $common_name ) === 0) {
						if ( DEBUG ) {
							error_log ( "Match found: $common_name".
								".\nThis group is matched to: ".
								$this->settings->get_option( 'groups', $k , 1 ).
								"The priority of this group is: ".
								$this->settings->get_option( 'priority',
										$this->settings->get_option( 'groups', $k, 1 ) ) .
								"\nThe highest priority level found thus far is: " .
								$highest_role_priority_level, 0,
									LOG_OUTPUT_FILE );
						}
						/**
						 * We have found an LDAP group that:
						 *
						 * 1) This user is a member of
						 * 2) Has been assigned a role
						 *
						 * Now we continue iterating through the loop to find the highest
						 * priority role we can find, which we will assign to the user
						 * afterwards.
						 *
						 * $highest_role_priority_level is the highest priority level found
						 * so far. We do a min( ) between the values of
						 * $highest_role_priority_level and the current priority of what role
						 * we are on in the iteration.
						 *
						 */
						$highest_role_priority_level = min (
							$this->settings->get_option( 'priority',
								$this->settings->get_option( 'groups', $k, 1 ) ),
							$highest_role_priority_level
						);
					}
				} // End loop through all assigned roles
			} // End loop through all LDAP groups

			foreach ( $this->settings->get_option( 'priority' ) as $priorityValue ) {
				if ( $priorityValue === '' ) {
					$this->error_message( 'unresolved_role_conflict',
						'There are unresolved role conflicts.' );
				}
			}

			if ( DEBUG ) {
				error_log ( "Setting user to role: " .
					array_search( $highest_role_priority_level,
					$this->settings->get_option( 'priority') ), 0,
					LOG_OUTPUT_FILE );
			}
			$wp_user->set_role( array_search( $highest_role_priority_level,
				$this->settings->get_option( 'priority' ) ), false );
		}
	}

	// Returns a formatted error message and unbinds
	private function error_message( $code, $message ) {
		if ( ( ! is_null( $this->link_identifier ) ) && ( ! is_null( $this->bind ) ) ) {
			ldap_unbind( $this->link_identifier );
		}
		return new WP_Error ( $code, __( "<strong>ERROR</strong>: $message" ) );
	}
}
?>