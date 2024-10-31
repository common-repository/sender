<?php
/**
 * Displays the content on the plugin settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Sndr_Settings_Tabs' ) ) {
	/**
	 * Sndr_Settings_Tabs Class
	 */
	class Sndr_Settings_Tabs extends Bws_Settings_Tabs {
		/**
		 * Constructor.
		 *
		 * @access public
		 *
		 * @see Bws_Settings_Tabs::__construct() for more information on default arguments.
		 *
		 * @param string $plugin_basename Plugin basename.
		 */
		public function __construct( $plugin_basename ) {
			global $sndr_options, $sndr_plugin_info;

			$tabs = array(
				'settings' => array( 'label' => __( 'Settings', 'facebook-button-plugin' ) ),
				'misc'     => array( 'label' => __( 'Misc', 'facebook-button-plugin' ) ),
				'license'  => array( 'label' => __( 'License Key', 'facebook-button-plugin' ) ),
			);

			parent::__construct(
				array(
					'plugin_basename'    => $plugin_basename,
					'plugins_info'       => $sndr_plugin_info,
					'prefix'             => 'sndr',
					'default_options'    => sndr_get_options_default(),
					'options'            => $sndr_options,
					'is_network_options' => is_network_admin(),
					'tabs'               => $tabs,
					'wp_slug'            => 'sender',
					'link_key'           => '9436d142212184502ae7f7af7183d0eb',
					'link_pn'            => '114',
					'doc_link'           => 'https://bestwebsoft.com/documentation/sender/sender-user-guide/',
				)
			);

			add_action( get_parent_class( $this ) . '_additional_misc_options_affected', array( $this, 'additional_misc_options_affected' ) );

			$args             = array(
				'public'   => true,
				'_builtin' => false,
			);
			$this->post_types = get_post_types( $args, 'names', 'and' );

			$this->post_types = array_merge(
				array(
					'post' => __( 'Post', 'sender' ),
					'page' => __( 'Page', 'sender' ),
				),
				$this->post_types
			);
		}

		/**
		 * Save plugin options to the database
		 *
		 * @access public
		 * @return array    The action results
		 */
		public function save_options() {
			$message = '';
			$notice  = '';
			$error   = '';

			if ( isset( $_POST['sndr_nonce_admin'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sndr_nonce_admin'] ) ), plugin_basename( __FILE__ ) ) ) {

				$this->options['from_custom_name'] = isset( $_POST['sndr_from_custom_name'] ) && ! empty( $_POST['sndr_from_custom_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sndr_from_custom_name'] ) ) : $this->options['from_custom_name'];
				$this->options['from_email']       = isset( $_POST['sndr_from_email'] ) && is_email( trim( sanitize_text_field( wp_unslash( $_POST['sndr_from_email'] ) ) ) ) ? trim( sanitize_text_field( wp_unslash( $_POST['sndr_from_email'] ) ) ) : $this->options['from_email'];
				$this->options['method']           = isset( $_POST['sndr_method'] ) && in_array( sanitize_text_field( wp_unslash( $_POST['sndr_method'] ) ), array( 'wp_mail', 'mail' ) ) ? sanitize_text_field( wp_unslash( $_POST['sndr_method'] ) ) : $this->options['method'];

				if ( isset( $_POST['sndr_mail_send_count'] ) ) {
					if ( 50 < absint( $_POST['sndr_mail_send_count'] ) ) {
						$notice .= __( 'You may have entered too large a value in the "Number of sent messages at the same time" option. Check please.', 'sender' ) . '<br/>';
					}
					$this->options['send_count'] = absint( $_POST['sndr_mail_send_count'] );
				}
				if ( isset( $_POST['sndr_mail_run_time'] ) ) {
					if ( 360 < absint( $_POST['sndr_mail_run_time'] ) ) {
						$notice .= __( 'You may have entered too large value in the "Interval for sending mail" option. Check please.', 'sender' ) . '<br/>';
					}
					$this->options['run_time'] = absint( $_POST['sndr_mail_run_time'] );
					add_filter( 'cron_schedules', 'sndr_more_reccurences' );
				}

				$this->options = array_map( 'stripslashes_deep', $this->options );
				if ( empty( $error ) ) {
					if ( is_multisite() ) {
						update_site_option( 'sndr_options', $this->options );
					} else {
						update_option( 'sndr_options', $this->options );
					}
					$message .= __( 'Settings saved.', 'sender' );
				}
			}

			return compact( 'message', 'notice', 'error' );
		}

		/**
		 * Display tab settings
		 */
		public function tab_settings() { ?>
			<h3 class="bws_tab_label"><?php esc_html_e( 'Sender Settings', 'sender' ); ?></h3>
			<?php $this->help_phrase(); ?>
			<hr>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Send from', 'sender' ); ?></th>
					<td class="sndr_input_text">
						<fieldset>
							<label>
								<?php esc_html_e( 'Name', 'sender' ); ?><br />
								<input type="text" name="sndr_from_custom_name" maxlength="250" value="<?php echo esc_attr( $this->options['from_custom_name'] ); ?>" />
							</label><br />
							<label>
								<?php esc_html_e( 'Email', 'sender' ); ?><br />
								<input type="text" name="sndr_from_email" maxlength="250" value="<?php echo esc_attr( $this->options['from_email'] ); ?>" />
							</label>
						</fieldset>
						<span class="bws_info"><?php esc_html_e( 'If this option is changed, email messages may be moved to the spam folder or email delivery failures may occur.', 'sender' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Sending Method', 'sender' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type='radio' name='sndr_method' value='wp_mail' <?php checked( 'wp_mail', $this->options['method'] ); ?> />
								<?php esc_html_e( 'WP-Mail', 'sender' ); ?>
							</label><br />
							<label>
								<input type='radio' name='sndr_method' value='mail' <?php checked( 'mail', $this->options['method'] ); ?> />
								<?php esc_html_e( 'Mail', 'sender' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php if ( ! $this->hide_pro_tabs ) { ?>
				<div class="bws_pro_version_bloc">
					<div class="bws_pro_version_table_bloc">
						<button type="submit" name="bws_hide_premium_options" class="notice-dismiss bws_hide_premium_options" title="<?php esc_html_e( 'Close', 'sender' ); ?>"></button>
						<div class="bws_table_bg"></div>
						<table class="form-table bws_pro_version">
							<tr>
								<th><?php esc_html_e( 'HTML Format', 'sender' ); ?></th>
								<td>
									<label>
										<input disabled="disabled" type="checkbox" name="sndr_html_email" value="1" />
										<?php esc_html_e( 'Enable to send emails in HTML format', 'sender' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>
					<?php $this->bws_pro_block_links(); ?>
				</div>
			<?php } ?>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Frequency', 'sender' ); ?></th>
					<td class="sndr_input_number">
						<input id="sndr_mail_send_count" name="sndr_mail_send_count" type="number" min="1" value="<?php echo esc_attr( $this->options['send_count'] ); ?>" />
						<?php esc_html_e( 'email(-s) every', 'sender' ); ?>
						<input id="sndr_mail_run_time" name="sndr_mail_run_time" type="number" min="1" value="<?php echo esc_attr( $this->options['run_time'] ); ?>" />
						<?php esc_html_e( 'minutes', 'sender' ); ?><br />
						<?php
						if ( intval( $this->options['run_time'] ) >= 60 ) {
							$number = intval( $this->options['send_count'] );
						} else {
							if ( 0 === ( 60 % intval( $this->options['run_time'] ) ) ) {
								$number = floor( 60 / intval( $this->options['run_time'] ) ) * intval( $this->options['send_count'] );
							} else {
								$number = ( floor( 60 / intval( $this->options['run_time'] ) ) + 1 ) * intval( $this->options['send_count'] );
							}
						}
						?>
						<p><?php esc_html_e( 'Total:', 'sender' ); ?>&nbsp;<span id="sndr-calculate"><?php echo esc_attr( $number ); ?></span>&nbsp;<?php esc_html_e( 'emails per hour', 'sender' ); ?></p>
						<span class="bws_info">
							<?php esc_html_e( 'Make sure that this number is smaller than max allowed number allowed by your hosting provider.', 'sender' ); ?><br />
							<?php esc_html_e( 'This counter shows only the number of messages that will be sended by Sender Pro plugin, and does not shows the total number of outgoing messages from your site.', 'sender' ); ?>
						</span>
					</td>
				</tr>
			</table>
			<?php if ( ! $this->hide_pro_tabs ) { ?>
				<div class="bws_pro_version_bloc">
					<div class="bws_pro_version_table_bloc">
						<button type="submit" name="bws_hide_premium_options" class="notice-dismiss bws_hide_premium_options" title="<?php esc_html_e( 'Close', 'sender' ); ?>"></button>
						<div class="bws_table_bg"></div>
						<table class="form-table bws_pro_version">
							<tr>
								<th><?php esc_html_e( 'Attempts', 'sender' ); ?></th>
								<td class="sndr_input_number">
									<input disabled="disabled" name="sndr_max_try_count" type="number" min="1" value="2" /><br />
									<span class="bws_info"><?php esc_html_e( 'Maximum number of attempts per user.', 'sender' ); ?></span>
								</td>
							</tr>
							<tr class="sndr_new_post">
								<th><?php esc_html_e( 'Automatic Mailout when Publishing a New', 'sender' ); ?></th>
								<td>
									<fieldset>
										<?php foreach ( $this->post_types as $post_type => $post_type_name ) { ?>
											<label>
												<input type="checkbox" disabled="disabled"/> 
												<?php
												$post_obj = get_post_type_object( $post_type_name );
												if ( ! isset( $post_obj ) ) {
													echo esc_html( ucfirst( $post_type_name ) );
												} else {
													echo esc_html( ucfirst( $post_obj->labels->singular_name ) );
												}
												?>
											</label><br />
											<div data-post-type="<?php echo esc_attr( $post_type ); ?>">
												<p>
													<select name="sndr_distribution_select[post]" id="sndr-distribution-select-post" class="sndr-form-select" disabled="disabled">
														<option><?php esc_html_e( 'Example of mailing list', 'sender' ); ?></option>
													</select><?php esc_html_e( 'Choose a mailing list', 'sender' ); ?>
												</p>
												<p>
													<select name="sndr_templates_select[post]" id="sndr-templates-select-post" class="sndr-form-select" disabled="disabled">
														<option><?php esc_html_e( 'Example of letter', 'sender' ); ?></option>
													</select><?php esc_html_e( 'Choose a letter', 'sender' ); ?>
												</p>
												<p>
													<select name="sndr_priority[post]" id="sndr-priority-select-post" class="sndr-form-select" disabled="disabled">
														<option><?php esc_html_e( 'Example of priority', 'sender' ); ?></option>
													</select><?php esc_html_e( 'Select mailout priority	', 'sender' ); ?>		                            
													<br />
													<span class="bws_info"><?php esc_html_e( 'Less number - higher priority', 'sender' ); ?></span>
												</p><br/>
											</div>
										<?php } ?>
									</fieldset>
								</td>
							</tr>
						</table>
					</div>
					<?php $this->bws_pro_block_links(); ?>
				</div>
				<?php
			}
			wp_nonce_field( plugin_basename( __FILE__ ), 'sndr_nonce_admin' );
		}

		/**
		 * Display custom options on the 'misc' tab
		 *
		 * @access public
		 */
		public function additional_misc_options_affected() {
			if ( ! $this->hide_pro_tabs ) {
				?>
				</table>
				<div class="bws_pro_version_bloc">
					<div class="bws_pro_version_table_bloc">
						<button type="submit" name="bws_hide_premium_options" class="notice-dismiss bws_hide_premium_options" title="<?php esc_html_e( 'Close', 'sender' ); ?>"></button>
						<div class="bws_table_bg"></div>
						<table class="form-table bws_pro_version">
							<tr>
								<th><?php esc_html_e( 'Sender Slug', 'sender' ); ?></th>
								<td>
									<input disabled="disabled" type="text" maxlength='250' name="sndr_view_slug" value="newsletter" />
									<div class="bws_info"><?php esc_html_e( 'Used for browser view.', 'sender' ); ?></div>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Send Email with Confirmation', 'sender' ); ?></th>
								<td>
									<input disabled="disabled" type='checkbox' name='sndr_confirm' value="1" />
									<br/>
									<span class="bws_info"><?php esc_html_e( 'This function may not work on all mail servers.', 'sender' ); ?></span>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Editors Access', 'sender' ); ?></th>
								<td>
									<label>
										<input disabled="disabled" type="checkbox" name="sndr_allow_editor" value="1" />
										<span class="bws_info"><?php esc_html_e( 'Enable to provide the access for Editors to create letter templates, letters and send test letters.', 'sender' ); ?></span>
									</label>
									<br/>
									<span class="bws_info"><?php echo esc_html__( 'If you want to create another role with special capabilities', 'sender' ) . ' - ' . esc_html__( 'download User Role Pro by BestWebSoft', 'sender' ); ?></span>
								</td>
							</tr>
						</table>
					</div>
					<?php $this->bws_pro_block_links(); ?>
				</div>
				<table class="form-table">
				<?php
			}
		}
	}
}
