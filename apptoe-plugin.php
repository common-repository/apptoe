<?php
/*
	* Plugin Name: AppToE
	* Plugin URI: http://goapptoe.com
	* Description:  AppToe Plugin helps you to create a page which takes appointments for your Repair Website. It adds menu to the Main Menu of your Website which has an iframe code of your AppToe Store. Create your AppToe Store and get the Secret Key to add this Plugin to your Website..
	* Version: 1.0.0
	* Author: AppToE Inc.
	* Author URI: http://goapptoe.com
*/


function apptoe_enqueue_style()
{
	wp_enqueue_style( 'apptoe-css' ,plugins_url( 'apptoe-plugin/css/apptoe-plugin.css', _FILE_ ) );
}
add_action('admin_enqueue_scripts','apptoe_enqueue_style');


function apptoe_creating_menu()
{
	if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') )
		return;
	add_menu_page('AppToE','AppToE','manage_options','Main','apptoe_menu_centent',plugins_url('apptoe-plugin/Icon.png',_FILE_));
}
add_action('admin_menu','apptoe_creating_menu');

function apptoe_menu_centent()
{						
	require_once ('apptoe-form-content.php');
}
function apptoe_deactivate_plugin()
{
	global $wpdb;
	$page= $wpdb->get_row( "SELECT * FROM $wpdb->options WHERE option_name='apptoe_page_id'" );
	$page_id= $page->option_value;
	if($page_id != null)
	{
		$menu_page= $wpdb->get_row( "SELECT * FROM $wpdb->options WHERE option_name='apptoe_menu_page_id'" );
		$menu_page_id= $menu_page->option_value;
	
		if($menu_page_id != null)
		{
			$obj_term_relationship=$wpdb->get_row("SELECT * FROM $wpdb->term_relationships WHERE object_id=$menu_page_id" );
			$primary_menu_id=$obj_term_relationship->term_taxonomy_id;
			
			$obj_term_taxonomy=$wpdb->get_row("SELECT * FROM $wpdb->term_taxonomy WHERE term_taxonomy_id=$primary_menu_id" );
			$total_count=$obj_term_taxonomy->count;
			$update_count=$total_count-1;
			
			wp_delete_post($menu_page_id, true); /* Deleting menu Page */
			$tableTermTaxonomy=$prefix.'term_taxonomy';
			$wpdb->update($tableTermTaxonomy, array ( 'count'=> $update_count),array('term_taxonomy_id'=>$primary_menu_id) );
		}
		$trashPage=array(
					 'ID' => $page_id,
					 'post_status' => 'trash',
				 );
		$result=wp_update_post( $trashPage ); /*RETURN POST ID*/
		//Start Deleting 
		delete_option('apptoe_page_id'); /* Delete from wp_option table*/
		delete_option('apptoe_menu_page_id'); /*Delete from wp_option table*/
		delete_option('apptoe_secret_key');
		delete_option('apptoe_page_title');
		delete_option('apptoe_width');
		delete_option('apptoe_height');
		
	}
}
register_deactivation_hook(__FILE__,'apptoe_deactivate_plugin');

