<?php

class Penguin_Login {
	private $enabled, $link_identifier, $bind, $penguin_settings;

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
		$options = &$this->settings->options;

		// Connect to LDAP
		$this->link_identifier = @ldap_connect( $options['protocol'] . $options['server'],
			$options['port'] );

		if ( ! $this->link_identifier ) {
			return $this->error_message( "ldap_connect_fail", "LDAP error." );
		}

		//Set LDAP options
		$protocol_result = @ldap_set_option( $this->link_identifier, LDAP_OPT_PROTOCOL_VERSION,
			$options['protocol_version'] );

		if ( ! $protocol_result ) {
			return $this->error_message( "ldap_set_option_protocol_fail",
				"Could not set protocol");
		}

		$opt_ref_result = @ldap_set_option( $this->link_identifier, LDAP_OPT_REFERRALS,
			$options['referrals'] );

		if ( ! $opt_ref_result ) {
			return $this->error_message( "ldap_set_option_opt_ref_fail",
				"Could not set opt referrals" );
		}

		$this->bind = @ldap_bind( $this->link_identifier,
			$username . $options['extension'], $password );

		if ( ! $this->bind ) {
			//do_action( 'wp_login_failed', $username );
			return $this->error_message( "ldap_bind_fail", 'Invalid credentials.' );
		}

		// True if the user exists, false otherwise
		$user_exists = ! is_null( username_exists( $username ) );

		$object_class_string = "";
		if ( $this->settings->get_option( 'objectclass') !== "" ) {
			$object_class_string = "(objectClass=" .
			$options['objectclass'] .
			")";
		}

		$result_identifier = @ldap_search( $this->link_identifier,
			$options['dn'],
			"(&$object_class_string(" . $options['login_field'] . "=" . $username . "))" );

		if ( ! $result_identifier ) {
			return $this->error_message( "ldap_search_fail",
				'There was an error searching for your username.' );
		}

		$entries = ldap_get_entries( $this->link_identifier, $result_identifier );

		if ( ! $entries ) {
			return $this->error_message( "ldap_get_entries_fail",
				'There was an error searching for your username.' );
		}
		
		if ( is_array ( $entries ) ) {
			if ( $entries['count'] != 1 ) {
				return $this->error_message( "ldap_wrong_number_of_entries",
					'Cannot find username.' );
			}
			elseif ( isset ( $entries[0] ) ) {
				$entry = $entries[0];
			}
			else {
				return $this->error_message( "ldap_wrong_number_of_entries_2",
					'Cannot find username.' );
			}
		}
		
		// Get the email attribute from this AD entry
		$email = $this->get_ldap_user_attribute( $options['email'], $entry );
		
		if ( is_wp_error ( $email ) ) {
			return $email;
		}
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return $this->error_message('ldap_invalid_email', 'LDAP Error.');
		}

		/**
		 * If the user exists we want to get their user object, and give them a new 
		 * random password. We give them a random password because WordPress requires
		 * each user to have one. The random password given to a user is not meant to be 
		 * used. If the user is in active directory, we use the password they entered in 
		 * the password field to bind against ldap. If the bind was successful, then that
		 * means they matched their ldap password.
		 *  
		 * Theoretically one could sign in using the password
		 * that was generated, however this is extremely unlikely. You really should only
		 * need to generate a new password once, but doing it on every login is more 
		 * secure since it isn't static. If performance is an issue, remove the random
		 * password generation at each login.
		 */
		if ( $user_exists ) {
			$user_LDAP = get_user_by ( 'login', $username );
			wp_set_password( wp_generate_password(), $user_LDAP->ID );
		}
		/**
		 * If the user doesn't exist we want to create a new WordPress user
		 */
		else {
			// Generate a new user ID
			$user_id = wp_create_user( $username, wp_generate_password(),
				$email );
			
			/**
			 * Sometimes wp_create_user() will return an WP_Error object. One example of
			 * something that can go wrong is you attempt to add a user with an email
			 * address that already belong to another user.
			 */
			if ( is_wp_error ( $user_id ) ) {
				$err_code = $user_id->get_error_code();
				return $this->error_message($err_code, $user_id->get_error_message( $err_code ) );
			}
			
			$user_LDAP = new WP_User( $user_id );
		}
		ldap_unbind( $this->link_identifier );

		/**
		* If group mapping is not enabled, then assign the default role.
		* Otherwise, look through what LDAP groups have been assigned to roles.
		*/
		if ( ! $options['enable_group_mapping'] ) {
			$user_LDAP->set_role( $options['default_role'] );
		}
		else {
			$result = $this->set_user_role_from_group( $user_LDAP, $entry );
			if ( is_wp_error ( $result ) ) {
				return $result;
			}
		}
		
		/**
		 * Update the user's first and last name in WordPress.
		 */
		$first_name = $this->get_ldap_user_attribute( $options['first_name'],
			$entry);
			
		if ( is_wp_error( $first_name ) ) {
			return $first_name;
		}
	
		update_user_meta( $user_LDAP->ID, 'first_name', $first_name );
		
		$last_name = $this->get_ldap_user_attribute( $options['last_name'], 
			$entry);
			
		if ( is_wp_error( $last_name ) ) {
			return $last_name;
		}	
		update_user_meta( $user_LDAP->ID, 'last_name', $last_name );
		
		// $email has already been checked for errors previously
		update_user_meta( $user_LDAP->ID, 'user_email', $email );
		
		return $user_LDAP;
	}

	private function get_ldap_user_attribute( $attribute, $ad_entry, $index = 0 ) {
		if ( isset ( $ad_entry[$attribute] ) ) {
			if ( isset ( $ad_entry[$attribute][$index] ) ) {
				return $ad_entry[$attribute][$index];
			}
		}
		else {
			return $this->error_message('ldap_' . $attribute . '_undefined.', 'LDAP Error code: 3');
			// Handle error here.
		}
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
						 * so far. We do a min() between the values of
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
					return $this->error_message( 'ldap_unresolved_role_conflict',
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

	// Returns a formatted error message and unbinds if necessary 
	private function error_message( $code, $message ) {
		if ( ( ! is_null( $this->link_identifier ) ) && ( ! is_null( $this->bind ) ) ) {
			ldap_unbind( $this->link_identifier );
		}
		return new WP_Error ( $code, __( "<strong>ERROR</strong>: $message" ) );
	}
}
?>