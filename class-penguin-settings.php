<?php

class Penguin_Settings {

	public $options;
	private $options_general;
	private $options_roles;
	private $roles;
	private $options_set;

	public function __construct() {
		$this->options_general = "pgn_general";
		$this->options_roles = "pgn_roles";
		$this->options_set = false;
	}

	public function load_all_options () {
		$options_general = (array) get_option ( $this->options_general );
		$options_roles = (array) get_option ( $this->options_roles );

		$this->options = array_merge ( $options_general, $options_roles);
		$this->give_values_if_not_set();
	}

	public function load_options( $key ) {
		$options = (array) get_option ( $this->prefix . $key );

		if ( ! empty( $this->options) ) {
			$this->options = array_merge ($this->options, $options);
		}
		else {
			$this->options = $options;
		}
	}

	private function give_values_if_not_set () {
		$this->give_value_if_not_set ( 'penguin.example.com',
			'server' );

		$this->give_value_if_not_set ( '389',
			'port' );

		$this->give_value_if_not_set ( '@example.com',
			'extension' );

		$this->give_value_if_not_set ( 'ldap://',
			'protocol' );

		$this->give_value_if_not_set ( 0,
			'referrals' );

		$this->give_value_if_not_set ( 3,
			'protocol_version' );

		$this->give_value_if_not_set ( 'example username',
			'user' );

		$this->give_value_if_not_set ( '',
			'password');

		$this->give_value_if_not_set ( 'DC=value,DC=value,DC=value,',
			'dn' );

		$this->give_value_if_not_set ( 'samaccountname',
			'login_field' );

		$this->give_value_if_not_set ( '',
			'filter' );

		$this->give_value_if_not_set ( 'samaccountname',
			'display_name');

		$this->give_value_if_not_set ( 'userprincipalname',
			'email' );

		$this->give_value_if_not_set ( 'givenname',
			'first_name' );

		$this->give_value_if_not_set ( 'sn',
			'last_name');

		$this->give_value_if_not_set ( 'user',
			 'objectclass');

		if ( ! isset ( $this->options['default_role']) ) {
			$lowest_priority_role = $this->get_lowest_priority_role();
			$lowest_priority_role_name = $lowest_priority_role[0];
			$this->options['default_role'] = $lowest_priority_role_name;
		}

		$this->give_value_if_not_set ( 0,
			 'enable_group_mapping' );

		$this->options_set = true;
	}

	private function give_value_if_not_set ( $default_value, $option_index ) {
		$this->options[$option_index] = $this->get_option_temp($default_value, $option_index);
	}

	public function get_option_temp ( $default = "", $option_index, $sub1 = null, $sub2 = null) {
		if ( isset ( $this->options ) ) {
			if ( isset ( $this->options[$option_index] ) ) {
				if ( is_null( $sub1 ) ) {
					return $this->options[$option_index];
				}
				elseif (isset ( $this->options[$option_index][$sub1] ) ) {
					if ( is_null ( $sub2 ) ) {
						return $this->options[$option_index][$sub1];
					}
					elseif (isset ( $this->options[$option_index][$sub1][$sub2]) ) {
						return $this->options[$option_index][$sub1][$sub2];
					}
					else {
						return $default;
					}
				}
				else {
					return $default;
				}
			}
			else {
				return $default;
			}
		}
		else {
			die ("Error: Options must be loaded in first with load_all_options.");
		}
	}

	public function get_option ( $option_index, $sub1 = null, $sub2 = null, $default = null) {
		return $this->get_option_temp ( $default, $option_index, $sub1, $sub2);

		/**
		 *
		 * Instead of this function being called, one can just access the array directly,
		 * this function is made for testing purposes before changing everything
		 * to access the array directly. However I might decide to keep this function
		 * because the ability to set an overriding default might be of use somewhere.
		 */
		// if ( $this->options_set ) {
			// if ($default != null) return $default;
			// if ( $sub1 == null ) {
				// return $this->options[$option_index];
			// }
			// elseif ( $sub2 == null ) {
				// return $this->options[$options_index][$sub1];
			// }
			// else {
				// return $this->options[$options_index][$sub1][$sub2];
			// }
		// }
	}

	public function add_settings () {
		// ---------------------------------------------------------------------
		// GENERAL SECTION
		// ---------------------------------------------------------------------
		add_settings_section(
			'penguin_general_section', // ID
			'Penguin General Section', // Title
			array ($this, 'general_section_desc'), // Callback function
			$this->options_general // Menu page (should match a menu slug)
		);

		add_settings_field(
			'pgn_server', // ID
			'Server', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'server' )
		);

		add_settings_field(
			'pgn_port', // ID
			'Port', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'port' )
		);

		add_settings_field(
			'pgn_extension', // ID
			'Extension', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'extension' )
		);

		add_settings_field(
			'pgn_protocol', // ID
			'Protocol', // Title
			array ($this, 'do_dropdown') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'protocol', array( 'ldap://', 'ldaps://' ) )
		);

		add_settings_field(
			'pgn_referrals', // ID
			'Referrals', // Title
			array ($this, 'do_dropdown') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'referrals', array (0, 1) )
		);

		add_settings_field(
			'pgn_protocol_version', // ID
			'Protocol Version', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'protocol_version' )
		);

		add_settings_field(
			'pgn_user', // ID
			'User', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'user' )
		);

		add_settings_field(
			'pgn_password', // ID
			'Password', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'password' )
		);

		add_settings_field(
			'pgn_dn', // ID
			'DN', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'dn' )
		);

		add_settings_field(
			'pgn_login_field', // ID
			'Login Field', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'login_field' )
		);

		add_settings_field(
			'pgn_filter', // ID
			'Filter', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'filter' )
		);

		add_settings_field(
			'pgn_display_name', // ID
			'Display Name', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'display_name' )
		);

		add_settings_field(
			'pgn_email', // ID
			'Email', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'email' )
		);

		add_settings_field(
			'pgn_first_name', // ID
			'First Name', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'first_name' )
		);

		add_settings_field(
			'pgn_last_name', // ID
			'Last Name', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'last_name' )
		);

		add_settings_field(
			'pgn_objectclass', // ID
			'Objectclass', // Title
			array ($this, 'do_general_field_row') , // Callback function
			$this->options_general, // Menu page (should match a menu slug)
			'penguin_general_section', // Setting section this field belongs to
			array ( 'objectclass' )
		);

		register_setting(
			$this->options_general, // Options group
			$this->options_general  // Name of the option
		);

		// ---------------------------------------------------------------------
		// ROLES SECTION
		// ---------------------------------------------------------------------
		add_settings_section(
			'penguin_roles_section', // ID
			'Penguin Roles Section', // Title
			array ($this, 'roles_section_desc'), // Callback function
			$this->options_roles // Menu page (should match a menu slug)
		);

		add_settings_field(
			'penguin_priority',
			'Penguin Priority',
			array ($this, 'do_priority_section'),
			$this->options_roles,
			'penguin_roles_section'
		);

		add_settings_field(
			'penguin_default_role', // ID
			'Default Role', // Title
			array ($this, 'field_default_role') , // Callback function
			$this->options_roles, // Menu page (should match a menu slug)
			'penguin_roles_section' // Setting section this field belongs to
		);

		add_settings_field(
			'penguin_enable_mapping', // ID
			'Enable group mapping', // Title
			array ($this, 'do_enable_mapping_checkbox') , // Callback function
			$this->options_roles, // Menu page (should match a menu slug)
			'penguin_roles_section' // Setting section this field belongs to
		);

		add_settings_field(
			'penguin_groups', // ID
			'Group Mapping', // Title
			array ($this, 'do_mapping_section') , // Callback function
			$this->options_roles, // Menu page (should match a menu slug)
			'penguin_roles_section', // Setting section this field belongs to
			array ( 'groups' )
		);

		register_setting(
			$this->options_roles, // Options group
			$this->options_roles  // Name of the option
		);
	}

	public function field_groups () {
	}

	public function general_section_desc () {
		echo "Configure Basic LDAP settings.";
	}

	public function roles_section_desc () {
		echo "Map LDAP groups to WordPress roles.";
	}

	public function do_dropdown ( $args ) {
		$func_name = "do_dropdown";
		if ( ! isset( $args[0] ) ) {
			$this->arg_error( $func_name, 0);
		}
		if ( ! isset( $args[1] ) ) {
			$this->arg_error( $func_name, 1);
		}
		$key = $args[0];
		$options = (array) $args[1];

		$options_length = count ($options);

		echo '<select name="' . $this->opt_str( $this->options_general, $key ) . '">';
		$value = $this->options[$key];
		for ($i = 0; $i < $options_length; $i ++) {
			if ($options[$i] == $value) {
				$selected = 'selected="selected"';
			}
			else {
				$selected = '';
			}
			echo '<option ' . $selected . ' value="' . $options[$i] . '">' . $options[$i] . '</option>';
		}
		echo '</select>';
	}

	public function arg_error ( $func_name, $index ) {
		die ("argument missing at $index in function $func_name");
	}

	public function opt_str ( $key1, $key2 = null, $key3 = null) {
		$s = $key1;
		if ( isset( $key2 ) ) $s .= '[' . $key2 . ']';
		if ( isset( $key3 ) ) $e .= '[' . $key3 . ']';
		return $s;
	}

	public function do_general_field_row ( $args ) {
		echo '<input type="text" id="ld-'.$args[0].'" name="'.$this->options_general.'['.$args[0].']" value="' .
			//$this->get_option($args[0], null, null, $default ) . '"/></td>';
			$this->get_option($args[0]) . '"/></td>';
	}

	public function field_default_role () {
		?>
			<select id="default-group" name="pgn_roles[default_role]">
			<?php wp_dropdown_roles( $this->options['default_role'] );?>
			</select>
		<?php
	}

	public function do_checkbox ( $args ) {
		$section = $args[0];
		$key = $args[1];
		$value = $this->get_option($key[1]);
	}

	public function do_enable_mapping_checkbox () {
		?>
		<input id="grpmp-enable" type="checkbox" name="pgn_roles[enable_group_mapping]"
		value="1" <?php checked( 1, $this->options['enable_group_mapping'] ); ?>/>
		<?php
	}

	public function do_mapping_section ( $key ) {
		if ( ! isset( $this->roles ) ) {
			$this->load_roles();
		}

		if ( $key[0] != 'groups') return;

		$group_mapping_options = array (
			'idPrefix' => 'grpmp-',
			'value' => 'pgn_roles[groups]',
			'attr' => 'name',
			'rowSize' => 40,
			'tableHeaders' => array ('Group Name', 'Role'),
			'anchorID' => 'mapping-section',
			'rowDataCount' => 2,
			'initialSettings' => $this->get_option( 'groups' ),
			'defaultSettings' => array ("", "editor")
		);

		echo '<div id="'.$group_mapping_options['anchorID'].'"></div>';
		?>

		<script type="text/javascript">
			var options = <?php echo json_encode( $group_mapping_options );?>;
			var roles = <?php echo json_encode( $this->roles ); ?>;
			<?php
			/**
			 * lowestPriorityRole is used to when an assigned role has been deleted.
			 * It is safest to assign the user to the role chosen to be the least
			 * powerful.
			 */
			?>
			var lowestPriorityRole = <?php echo json_encode( $this->get_lowest_priority_role() ); ?>;
			var enabled = "<?php echo $this->options['enable_group_mapping']; ?>";
		</script>

		<?php
	}

	// Functionality broken on first-time use of plugin, defaulting to subscriber as 
	// the lowest priority role. 
	private function get_lowest_priority_role () {
		// $priority_array = $this->options['priority']; #1
		// $priority_array = $this->get_option('priority'); // #1
		//$role_name = $this->get_highest_value($priority_array); // #1

		/**
		 * If the lowest priority in the priority array actually doesn't exist
		 * (the role must have been deleted recently), then unset that index and look for
		 * a new lowest priority that we can actually use.
		 */
		// if ( ! $this->role_exists ($role_name) ) { #1
			// unset($priority_array[$role_name]); #1
			// $role_name = $this->get_highest_value($priority_array); #1
		// }

		/**
		 * Return an array with the role lowest priority low and associated label.
		 */
		//return array ( $role_name, $this->roles[$role_name] ); #1
		return array ( 'subscriber', 'Subscriber' ); // #1
	}

	/**s
	 * Search for the highest value (lowest priority) using max() in the priority
	 * array.
	 */
	private function get_highest_value( $array ) {
		return array_search ( max ( $array ), $array );
	}

	/**
	 * Outputs an associative array of all editable wordpress roles with the key being
	 * the role name and the value being the role label
	 */
	public function load_roles( $selected = false ) {
		$editable_roles = array_reverse( get_editable_roles() );
		$out_array = array();

		foreach ( $editable_roles as $role => $details ) {
			$out_array [esc_attr($role)] = translate_user_role($details['name']);
		}
		$this->roles = $out_array;

		if ( ! isset( $this->options ) ) {
			die ("use load_all_options() before you load roles!");
		}

		if ( ! $this->role_exists( $this->options['default_role'] ) ) {

			$lowest_priority_role = $this->get_lowest_priority_role();
			$lowest_priority_role_name = $lowest_priority_role[0];

			echo '<strong style="background-color:red">The mapped role ' . $this->options['default_role'] . '
				has been deleted. Save new changes now to prevent lockout!</strong>';

			$this->options['default_role'] = $lowest_priority_role_name;
		}
	}

	public function role_exists ($role_name_that_might_exist) {
		if ( DEBUG ) {
			error_log ( "role_name_that_might_exist: " . $role_name_that_might_exist ,
				0, LOG_OUTPUT_FILE );
		}
		foreach ($this->roles as $role_name => $role_label) {
			if ( DEBUG ) {
				error_log ( "role_name: " . $role_name , 0, LOG_OUTPUT_FILE );
			}
			if ($role_name_that_might_exist === $role_name) {
				return true;
			}
		}
		return false;
	}

	public function do_priority_section () {
		if ( ! isset( $this->roles ) ) {
			$this->load_roles();
		}

		$priority_array = $this->get_option( 'priority' );

		$priority_array = $priority_array == false ? array() : $priority_array;
		echo '<ul id="sortable">';

		/**
		 * $priority_array is an array where the keys are roles and the values are
		 * priorities. What we need to do is go through the array and print out
		 * the keys and their corresponding values.
		 */
		$lowest_priority = -1; // Actually the highest value through.

		if ( $priority_array !== "") {
			foreach ( $priority_array as $priority_level) {
				$role = array_search( $priority_level, $priority_array );

				//die ("LEVEL: " . $priority_level . " ARRAY: " . print_r ($priority_array) . " RESOLVED ROW: " . $role);

				if ( isset( $this->roles[$role]) ) {
				echo '<li><input type="text" style="display:none" name="'.
					$this->options_roles.'[priority]['. $role .']" value="' .
					$this->get_option('priority', $role) .
					 '" readonly></input><label class="priority-grab">'. $this->roles[$role] .
					 '</label></li>';
				$lowest_priority = max( $lowest_priority, $this->get_option( 'priority', $role ) );
				}
			}
		}

		/**
		 * If these settings haven't been saved before, or a new role has been created,
		 * then the priority array is empty. We need to look at the differences between
		 * the list of roles and the list of roles than have been assigned priorities
		 * using array_diff_key(). The result will give us all the roles that haven't
		 * been assigned a priority yet. Once they have been, this only comes in handy
		 * when new roles have been added.
		 */
		$missing_roles = array_diff_key((array)$this->roles, (array) $priority_array );

		if ( ! empty( $missing_roles ) ) {
			$missing_roles = array_reverse( $missing_roles );
			foreach ( $missing_roles as $role_key => $role_val ) {
				if ( isset( $this->roles[$role_key] ) ) {
					echo '<li><input type="text" style="display:none" name="'.
						$this->options_roles.'[priority]['. $role_key .']" value="' .
						 ++ $lowest_priority . // Decrease priority further
						 '" readonly></input><label>'. $this->roles[$role_key] .
						 '</label><br></li>';
					$this->add_missing_priority_index($role_key, $lowest_priority);
				}
			}
		}
		echo '</ul>';
	}

	private function add_missing_priority_index ($role_key, $priority) {
		if ( ! isset ( $this->options['priority'] ) ) {
			$this->options['priority'] = array();
		}
		$this->options['priority'][$role_key] = $priority;
	}

	private function get_role_name( $role, $data ) {
		if ($role != false)
			return	$data[$role]["name"];
		return false;
	}
}