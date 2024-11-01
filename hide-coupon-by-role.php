<?php
/**
  * Plugin Name: Coupons by Role
  * Plugin URI: http://wordpress.org/plugins/woocommerce-coupons-by-role
  * Description: This plugin adds a Woocommerce setting tab to the dashboard where user roles can be selected for which the coupon codes are hidden and optionally replaced by a specific message shown on the cart page.
  * Version: 1.2.1
  * Author: Petri Rahikkala
  * Author URI: kontakti@ensens.fi
  * Text domain: hide_coupon_codes
  * Copyright 2024 Petri Rahikkala  
  * 
  * This program is free software: you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation, either version 3 of the License, or
  * (at your option) any later version.
  * 
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU General Public License for more details.
  * 
  * You should have received a copy of the GNU General Public License
  * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) or exit;

load_plugin_textdomain( 'hide_coupon_codes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );


//Add options to the woocommerce coupon settings tab



class WC_Settings_Hide_Coupons {

    /**
     * Bootstraps the class and hooks required actions & filters.
     *
     */
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_settings_tab_hide_coupons', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_settings_tab_hide_coupons', __CLASS__ . '::update_settings' );
    }


    /**
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_tab_hide_coupons'] = __( 'Coupons by Role', 'hide_coupon_codes' );
     //   print_r($settings_tabs);
        return $settings_tabs;
    }


    /**
     * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     *
     * @uses woocommerce_admin_fields()
     * @uses self::get_settings()
     */
    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }


    /**
     * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    /**
     * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {
        global $wp_roles;
        $roles = $wp_roles->get_names();

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Hide coupon code fields from selected roles', 'hide_coupon_codes' ),
                'type'     => 'title',
                'desc'     => '<p>First select the roles you want to prevent from seeing the coupon fields at the cart page. Next you can define optional message to be shown to those customers instead of the coupon fields. For example: "Coupons are not available for [role] customers".</p> <p>If you like the plugin and benefit from it, please consider <a href="https://www.paypal.me/couponsbyrole" target="_blank">donating</a>.</p>',
                'id'       => 'wc_settings_tab_hide_coupons_section_title'
            ),

            'roles' => array(
                'name' => __( 'Select roles:', 'hide_coupon_codes' ),
                'type' => 'multiselect',
                'desc' => __( '', 'hide_coupon_codes' ),
                'options'=>$roles, //array('admin'=>'Administrator','other'=>'Other'),
				'css' => 'min-height:150px;',
                'id'   => 'wc_settings_tab_hide_coupons_roles',
                'default'=>array()
            )
        );
        foreach($roles as $k=>$v){
            $settings['message_'.$k]=array(
                'name' => __( 'Show message for role: '.$v.' ', 'hide_coupon_codes' ),
                'type' => 'text',
                'desc' => __( '', 'hide_coupon_codes' ),
                'css' => 'min-width:300px;',
                'id'   => 'wc_settings_tab_hide_coupons_message_'.$k,
                'default'=>''
            );
        }
        $settings['section_end']=array(
                'type' => 'sectionend',
                'id' => 'wc_settings_tab_hide_coupons_section_end'
        );

        return apply_filters( 'wc_settings_tab_hide_coupons_settings', $settings );
    }

}

WC_Settings_Hide_Coupons::init();


// hide coupon field on cart page for roles selected in setting page
function hdr_hide_coupon_field_on_cart( $enabled ) {
    global $current_user;
    $user_roles = $current_user->roles;
    $configured_roles=get_option('wc_settings_tab_hide_coupons_roles');
    if(!is_array($configured_roles)){
        $configured_roles=array();
    }
    if(is_cart() || is_checkout()) {
        foreach ($user_roles as $role) {
            if (in_array($role, $configured_roles)) {
                return false;
            }
        }
    }

  return $enabled;
}
add_filter( 'woocommerce_coupons_enabled', 'hdr_hide_coupon_field_on_cart' );

add_action('woocommerce_after_cart_table','hdr_show_hidden_coupon_message');
add_action('woocommerce_before_checkout_form','hdr_show_hidden_coupon_message');

function hdr_show_hidden_coupon_message(){
    if(!hdr_hide_coupon_field_on_cart( true )){
        global $current_user;
        $user_roles = $current_user->roles;
       // print_r($user_roles);
        $r=array_shift($user_roles);
//        print_r($r);
        $msg=addslashes(get_option('wc_settings_tab_hide_coupons_message_'.$r));
        ?>
        <script type="text/javascript">
            var hdr_msg='<div class="coupon" style="display:inline-block"><span class="hidden-coupon-message"><?php echo $msg; ?></span></div>';
            <?php if(is_cart()){ ?>
            jQuery('.actions input[type=submit]').before(hdr_msg);
            <?php }else{ ?>
            jQuery('.woocommerce').prepend(hdr_msg);
           // console.log(hdr_msg);
            <?php } ?>
        </script>
        <?php
    }
}