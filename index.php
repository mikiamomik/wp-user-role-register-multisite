<?php
/*
Plugin Name: WP User Role Register Multisite
Plugin URI: https://github.com/mikiamomik/wp-user-role-register-multisite/
Description: Wordpress Plugin that allows you to select the user roles for every site in a multisite Wordpress project.
Author: Bernardo Picaro
Version: 1.1
*/
global $wurmm_saved;
$wurmm_saved=false;
function wurrm_form_multisite($type=null,$user=null,$is_network_profile=true) {
	if($type=="add-existing-user"){ return false; } 
	$is_editing=!empty($user);
	$is_network=empty($type);
	$sites=get_sites(); 
	global $wp_roles;

    $all_roles = $wp_roles->roles;
    $editable_roles = apply_filters('editable_roles', $all_roles);
    $user_roles=array();
    foreach($editable_roles as $k=>$role){ $user_roles[$k]=$role['name'];}
    ?>
	<table class="form-table">
		<tbody>
			<?php if(!$is_network) { ?>
			<tr class="form-field form-required">
				<th colspan='1000'><?= __('Other Sites') ?></th>
			</tr>
			<?php }
			foreach($sites as $site){
				if(!$is_editing || ($is_editing && $is_network_profile) || ($is_editing && !$is_network_profile && get_current_blog_id()!=$site->blog_id)){
					if($is_network || (!$is_network && get_current_blog_id()!=$site->blog_id)) {
						?><tr class="form-field form-required"><?php
						$domain=str_replace(".robadadonne.it",null,$site->domain);
						$domain=str_replace(".rdd.it",null,$domain);
						$domain=strtoupper($domain);
						if($is_editing){ 
							$_user = get_users(array('blog_id' => $site->blog_id,'search'  => $user->ID)); 
							if(isset($_user[0])){
								$user=$_user[0];
							} else {
								$user->roles=array();
							}
						}
						$is_null_editing=(!$is_network || ($is_editing && empty($user->roles)));
						echo "<th scope=\"row\">".__("Role")." ".$domain."</th>";
						echo "<td>
							  <select name=\"wurrm_site_enabled[".$site->blog_id."][]\" class='multiple' style='height: calc(18px * ".(count($user_roles)+1)."  + 6px)' multiple>";
							  	echo "<option value='none' ".($is_null_editing?"selected":null).">".__("&mdash; No role for this site &mdash;")."</option>";
							    foreach($user_roles as $k=>$role){
									$is_contributor_default=(!$is_editing && $is_network && $k=='contributor');
									$is_roles_editing=($is_editing && in_array($k,$user->roles) );
							    	echo "<option value=\"".$k."\" ".(($is_contributor_default || $is_roles_editing) ? "selected":null).">".__(ucfirst($role))."</option>";
							    }
						echo "</select>
							  </td>";
						?></tr><?php
					}
				}
			} ?>
		</tbody>
	</table>
	<style>
		body table.form-table td select{width:100% !important;max-width: 25em !important;}
		body table.form-table td select.multiple{height:100%;}
	</style>
	<?php
}

function wurrm_f_registration_save( $user_id ) {
	 wurrm_registration_save( $user_id );
}
function wurrm_registration_save( $user_id ) {
	global $wurmm_saved;
	if(!$wurmm_saved && is_numeric($user_id) && is_multisite() && isset($_POST['wurrm_site_enabled'])){
		update_user_meta( $user_id, '__wurrm_profile_updated', time() );
		// var_dump($_POST['wurrm_site_enabled']);
		foreach($_POST['wurrm_site_enabled'] as $_blog_id=>$roles){
			switch_to_blog($_blog_id);
			$u = new WP_User($user_id);
			global $wp_roles; 
			$all_roles = $wp_roles->roles;
			$editable_roles = apply_filters('editable_roles', $all_roles);
			
			foreach($editable_roles as $k=>$role){ 
				$u->remove_role($k);
			}
			if(isset($roles[0]) && $roles[0]!="none" && false!=$roles && !empty($roles)){
				foreach($roles as $role){
					$u->add_role($role);
				}
			}
		}
		$wurmm_saved=true;
	}
	restore_current_blog();
}

function wurrm_add_user_id_column($columns) {
	if(isset($columns['blogs'])){
		unset($columns['blogs']);
	}
    $columns['sites'] = __('Roles');
    return $columns;
}
 
function wurrm_show_user_id_column_content($value, $column_name, $user_id) {
    //$user = get_userdata( $user_id );
    $sites_content=null;
	if ( 'sites' == $column_name ){
		$sites=get_sites();
		foreach($sites as $site){
			$get_users_obj = get_users(array('blog_id' => $site->blog_id,'search'  => $user_id));
			if(isset($get_users_obj[0]->roles)){
				$sites_content.="<span class=\"".$site->blog_id."\">";
				$sites_content.="<a href=\"".network_admin_url("site-info.php?id=".$site->blog_id)."\">".$site->domain."</a>";
				$sites_content.=" <small><a href=\"".network_admin_url("site-info.php?id=".$site->blog_id)."\">".strtoupper(__("Edit"))."</a></small>";
				$sites_content.="<br>";
				foreach($get_users_obj[0]->roles as $role){
					$sites_content.="<span class='wurrm_role_user'>$role</span><br>";
				}
				$sites_content.="</span><br>";
			}
			
		}
		return $sites_content;
	} 
    return $value;
}

function wurrm_add_style_user_id() {
    echo '<style>.wurrm_role_user:before{content: " - ";}</style>';
}

function wurrm_load_admin_user_class() {
	global $pagenow;
	if ( in_array($pagenow, array( 'user-edit.php', 'profile.php' ) ) && current_user_can( 'edit_users' ) ) {
		new wurrm_Admin_User_Profile;
	}
}

/**
 * Customizes user profile.
 */
class wurrm_Admin_User_Profile {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'user_profile' ) );
		add_action( 'edit_user_profile', array( $this, 'user_profile' ) );
		add_action( 'personal_options_update', array( $this, 'process_user_option_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'process_user_option_update' ) );
	}

	public function process_user_option_update( $user_id ) {

		if(is_numeric($user_id) && is_multisite() && isset($_POST['wurrm_site_enabled'])){
			update_user_meta( $user_id, '__wurrm_profile_updated', time() );
			remove_action('user_register', 'wurrm_f_registration_save', 200000, 1 );
			wurrm_registration_save( $user_id );
		}
	}

	public function user_profile( $user ) {
		wp_nonce_field( 'wurrm_user_profile_update', 'wurrm_nonce' );
		echo "<h2 id=\"wordpress-wurrm\">". __( 'Roles' )."</h2>";
		wurrm_form_multisite(null,$user,is_network_admin());
	}
}

if ( is_multisite() ) { 
	add_action('admin_init', 'wurrm_load_admin_user_class' );
	add_action('admin_footer', 'wurrm_add_style_user_id' );
	add_filter('wpmu_users_columns', 'wurrm_add_user_id_column');
	add_action('manage_users_custom_column',  'wurrm_show_user_id_column_content', 10, 3);

	add_action('network_user_new_form', 'wurrm_form_multisite',10000,1);
	add_action('network_user_new_created_user', 'wurrm_f_registration_save', 200000, 1 );

	add_action('user_new_form', 'wurrm_form_multisite',10000,1);
	add_action('user_register', 'wurrm_f_registration_save', 200000, 1 );
}
