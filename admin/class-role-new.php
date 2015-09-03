<?php

final class Members_Admin_Role_New {

	/**
	 * Holds the instances of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    object
	 */
	private static $instance;

	/**
	 * Name of the page we've created.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $page = '';

	/**
	 * Role that's being created.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $role = '';

	/**
	 * Name of the role that's being created.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $role_name = '';

	/**
	 * Array of the role's capabilities.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    array
	 */
	public $capabilities = array();

	/**
	 * Conditional to see if we're cloning a role.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    bool
	 */
	public $is_clone = false;

	/**
	 * Role that is being cloned.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $clone_role = '';

	/**
	 * Sets up our initial actions.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		// If the role manager is active.
		if ( members_role_manager_enabled() )
			add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
	}

	/**
	 * Adds the roles page to the admin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function add_admin_page() {

		$this->page = add_submenu_page( 'users.php', esc_html__( 'Add New Role', 'members' ), esc_html__( 'Add New Role', 'members' ), 'create_roles', 'role-new', array( $this, 'page' ) );

		// Let's roll if we have a page.
		if ( $this->page ) {

			// Load actions.
			add_action( "load-{$this->page}", array( $this, 'load' ) );

			// Load scripts/styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( "admin_head-{$this->page}", array( $this, 'print_scripts' ) );
		}
	}

	/**
	 * Checks posted data on load and performs actions if needed.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		// Are we cloning a role?
		$this->is_clone = isset( $_GET['clone'] ) && members_role_exists( $_GET['clone'] );

		if ( $this->is_clone ) {

			//add_filter( 'members_new_role_default_capabilities', array( $this, 'clone_default_caps' ) );
			add_filter( 'members_new_role_default_caps', array( $this, 'clone_default_caps' ) );

			$this->clone_role = members_sanitize_role( $_GET['clone'] );
		}

		// Check if the current user can create roles and the form has been submitted.
		if ( current_user_can( 'create_roles' ) && ( isset( $_POST['role_name'] ) || isset( $_POST['role'] ) || isset( $_POST['grant-caps'] ) || isset( $_POST['deny-caps'] ) || isset( $_POST['grant-new-caps'] ) || isset( $_POST['deny-new-caps'] ) ) ) {

			// Verify the nonce.
			check_admin_referer( 'new_role', 'members_new_role_nonce' );

			// Assume no caps.
			$new_caps = array();

			// Check if any capabilities were selected.
			if ( isset( $_POST['grant-caps'] ) || isset( $_POST['deny-caps'] ) ) {

				$grant_caps = ! empty( $_POST['grant-caps'] ) ? array_unique( $_POST['grant-caps'] ) : array();
				$deny_caps  = ! empty( $_POST['deny-caps'] )  ? array_unique( $_POST['deny-caps']  ) : array();

				foreach ( members_get_capabilities() as $cap ) {

					if ( in_array( $cap, $grant_caps ) )
						$new_caps[ $cap ] = true;

					else if ( in_array( $cap, $deny_caps ) )
						$new_caps[ $cap ] = false;
				}

				if ( ! empty( $new_caps ) )
					$this->capabilities = array_keys( $new_caps );
			}

			$grant_new_caps = ! empty( $_POST['grant-new-caps'] ) ? array_unique( $_POST['grant-new-caps'] ) : array();
			$deny_new_caps  = ! empty( $_POST['deny-new-caps'] )  ? array_unique( $_POST['deny-new-caps']  ) : array();

			$_m_caps = members_get_capabilities();

			foreach ( $grant_new_caps as $grant_new_cap ) {

				$_cap = members_sanitize_cap( $grant_new_cap );

				if ( ! in_array( $_cap, $_m_caps ) )
					$new_caps[ $_cap ] = true;
			}

			foreach ( $deny_new_caps as $deny_new_cap ) {

				$_cap = members_sanitize_cap( $deny_new_cap );

				if ( ! in_array( $_cap, $_m_caps ) )
					$new_caps[ $_cap ] = false;
			}

			// Sanitize the new role name/label. We just want to strip any tags here.
			if ( ! empty( $_POST['role_name'] ) )
				$this->role_name = strip_tags( $_POST['role_name'] );

			// Sanitize the new role, removing any unwanted characters.
			if ( ! empty( $_POST['role'] ) )
				$this->role = members_sanitize_role( $_POST['role'] );

			else if ( $this->role_name )
				$this->role = members_sanitize_role( $this->role_name );

			// Add a new role with the data input.
			if ( $this->role && $this->role_name ) {

				add_role( $this->role, $this->role_name, $new_caps );

				// If the current user can edit roles, redirect to edit role screen.
				if ( current_user_can( 'edit_roles' ) ) {
					wp_redirect( add_query_arg( 'message', 'role_added', members_get_edit_role_url( $this->role, true ) ) );
 					exit;
				}

				// Add role added message.
				add_settings_error( 'members_role_new', 'role_added', sprintf( esc_html__( 'The %s role has been created.', 'members' ), $this->role_name ), 'updated' );
			}

			// Add error if there's no role.
			if ( ! $this->role )
				add_settings_error( 'members_role_new', 'no_role', esc_html__( 'You must enter a valid role.', 'members' ) );

			// Add error if there's no role name.
			if ( ! $this->role_name )
				add_settings_error( 'members_role_new', 'no_role_name', esc_html__( 'You must enter a valid role name.', 'members' ) );
		}

		// If we don't have caps yet, get the new role default caps.
		if ( empty( $this->capabilities ) )
			$this->capabilities = members_new_role_default_caps();
	}

	/**
	 * Loads necessary scripts/styles.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue( $hook ) {

		if ( $this->page !== $hook )
			return;

		wp_enqueue_style(  'members-admin' );

		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'members-edit-role' );
	}

	public function print_scripts() { ?>
		<script type="text/javascript">
			jQuery(document).ready( function($) {
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				postboxes.add_postbox_toggles( 'members_edit_role' );
			});
		</script>
	<?php }

	/**
	 * Outputs the page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function page() { ?>

		<div class="wrap">

			<h1><?php ! $this->is_clone ? esc_html_e( 'Add New Role', 'members' ) : esc_html_e( 'Clone Role', 'members' ); ?></h1>

			<?php settings_errors( 'members_role_new' ); ?>

			<div id="poststuff">

				<form name="form0" method="post" action="<?php echo members_get_new_role_url(); ?>">

					<?php wp_nonce_field( 'new_role', 'members_new_role_nonce' ); ?>

					<div id="post-body" class="columns-2">

						<div id="post-body-content">

							<div id="titlediv" class="members-title-div">

								<div id="titlewrap">
									<span class="screen-reader-text"><?php esc_html_e( 'Role Name', 'members' ); ?></span>
									<input type="text" name="role_name" value="<?php echo ! $this->role && $this->clone_role ? esc_attr( sprintf( __( '%s Clone', 'members' ), members_get_role_name( $this->clone_role ) ) ) : esc_attr( $this->role_name ); ?>" placeholder="<?php esc_attr_e( 'Enter role name', 'members' ); ?>" />
								</div><!-- #titlewrap -->

								<div class="inside">
									<div id="edit-slug-box">
										<strong><?php esc_html_e( 'Role:', 'members' ); ?></strong> <?php echo esc_attr( $this->role ); ?> <!-- edit box -->
										<input type="text" name="role" value="<?php echo ! $this->role && $this->clone_role ? esc_attr( "{$this->clone_role}_clone" ) : members_sanitize_role( $this->role ); ?>" />
									</div>
								</div><!-- .inside -->

							</div><!-- .members-title-div -->

							<?php $cap_tabs = new Members_Cap_Tabs( '', $this->capabilities ); ?>
							<?php $cap_tabs->display(); ?>

						</div><!-- #post-body-content -->

						<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
						<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>

						<div id="postbox-container-1" class="post-box-container column-1 side">

							<?php do_action( 'members_add_meta_boxes_role', '' ); ?>
							<?php do_meta_boxes( 'members_edit_role', 'side', '' ); ?>

						</div><!-- .post-box-container -->

					</div><!-- #post-body -->
				</form>

			</div><!-- #poststuff -->

		</div><!-- .wrap -->

	<?php }

	/**
	 * Filters the new role default caps in the case that we're cloning a role.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $capabilities
	 * @param  array
	 */
	public function clone_default_caps( $capabilities ) {

		if ( $this->is_clone ) {

			$role = get_role( $this->clone_role );

			if ( $role && isset( $role->capabilities ) && is_array( $role->capabilities ) )
				$capabilities = $role->capabilities;
		}

		return $capabilities;
	}

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
}

Members_Admin_Role_New::get_instance();
