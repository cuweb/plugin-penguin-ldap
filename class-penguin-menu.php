<?php class Penguin_Menu {

	private $penguin_settings;

	public function __construct( $penguin_settings) {
		$this->penguin_settings = $penguin_settings;
	}

	public function add_menu_hook() {
		add_menu_page(
			"Penguin LDAP", // Page title
			"Penguin LDAP", // Menu title
			'edit_users', // Capability
			"penguin_options", // Menu slug
			array ( $this, "penguin_menu" ) // Callback function
		);
	}
	// The menu
	public function penguin_menu () {
		?>
		<style>
			hr {
				display: block; height: 1px;
				border: 0; border-top: 1px solid #dfdfdf;
				margin: 1em 0; padding: 0;
			}

			#group-table tbody {
				padding : 2px 8px !important;
				font-size: 12px;
			}

			#group-table tbody th {
				text-align: left;
				padding-bottom: 0px !important;
			}

			#group-table tbody td {
				display: table-cell;
				line-height: 5px;
				padding-left: 0px !important;
				padding-bottom: 0px !important;
			}

			.priority-grab {
				cursor: move !important;
			}
		</style>

		<?php

		if( isset( $_GET[ 'tab' ] ) ) {
				$active_tab = $_GET[ 'tab' ];
		} else {
			$active_tab = 'general';
		}

		?>
		<div class="wrap">
			<div id="icon-themes" class="icon32"></div>
			<h2>Penguin LDAP Settings</h2>

			<h2 class="nav-tab-wrapper">
			<a href="<?php echo add_query_arg( array('tab' => 'general'), $_SERVER['REQUEST_URI']); ?>" class="nav-tab
			<?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>

			<a href="<?php echo add_query_arg( array('tab' => 'roles'), $_SERVER['REQUEST_URI']); ?>" class="nav-tab
			<?php echo $active_tab == 'roles' ? 'nav-tab-active' : ''; ?>">Roles</a>

			<a href="<?php echo add_query_arg( array('tab' => 'help'), $_SERVER['REQUEST_URI']); ?>" class="nav-tab
			<?php echo $active_tab == 'help' ? 'nav-tab-active' : ''; ?>">Help</a>
			
			<a href="<?php echo add_query_arg( array('tab' => 'error_codes'), $_SERVER['REQUEST_URI']); ?>" class="nav-tab
			<?php echo $active_tab == 'error_codes' ? 'nav-tab-active' : ''; ?>">Error Codes</a>
			
			</h2>
		</div>

		<?php
		if ( $active_tab == "general" ) {
			// Very important!
			$this->penguin_settings->load_all_options();
			?>
			<div id="wrap">
				<form method="POST" action="options.php">
					<?php
					$this->test_ldap_connect_button();
					settings_fields( "pgn_general");
					do_settings_sections( "pgn_general");
					//do_settings_fields( "penguin_options", "penguin_general_section" );
					submit_button( );
					?>
				</form>
			</div>

			<?php
		}
		elseif ( $active_tab == "roles" ) {
			// Very important!
			$this->penguin_settings->load_all_options();
			wp_enqueue_script( 'roles_tab_script' ,
				plugin_dir_url( __FILE__ ) . 'javascript/roles-tab.js');
			wp_enqueue_script('jquery-ui-sortable');

			?>
			<div id="wrap">
				<form method="POST" action="options.php">
					<?php
					$this->penguin_settings->load_roles( );
					settings_fields( "pgn_roles");
					do_settings_sections( "pgn_roles");
					submit_button( );
					?>
				</form>
			</div>

			<?php
		}
		elseif ( $active_tab == "help" ) {
			$this->test_ldap_connect_button();

			echo "<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum</p>";
		}
		else if ( $active_tab = "error_codes" ) {
			?>
			<table>
				<tr>
					<th>
						ID
					</th>
					<th>
						Error Type
					</th>
					<th>
						Description
					</th>
				</tr>
				<tr>
					<td>
						01
					</td>
					<td>
						Cannot connect
					</td>
					<td>
						When Lorem ipsum dolor sit amet
					</td>
				</tr>
				
			</table>
			
			<?php
		}
	}

	public function test_ldap_connect_button() {
		wp_enqueue_script( 'help_tab_script' ,
				plugin_dir_url( __FILE__ ) . 'javascript/help-tab.js');

			?>
			<div id='test-section'>
			<h3>Test server and port</h3>
			<a class="button" id='test-button'>Test</a>
			<p id="push-result" style="display:none;"></p>
			</div>
			<?php
	}

	public function add_ajax_script() {
		add_action ( 'wp_ajax_my_action', array ( $this, "my_action_callback" ) );
	}

	public function my_action_callback() {
		$this->penguin_settings->load_all_options();

		$result = @ldap_connect( $this->penguin_settings->get_option( 'protocol' ) . 
		$this->penguin_settings->get_option( 'server' ),
		$this->penguin_settings->get_option( 'port') );

		if ( $result != false) {
			echo "Sucessfully connected.";
		}
		else {
			echo "Could not connect.";
		}

		die(); // this is required to return a proper result
	}
}
?>