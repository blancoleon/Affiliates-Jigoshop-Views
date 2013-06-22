<?php
/**
 * affiliates-jigoshop-views.php
 *
 * Copyright (c) 2013 "eggemplo" Antonio Blanco www.eggemplo.com
 *
 * This code is released under the GNU General Public License.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author Eggemplo
 * @package affiliates-jigoshop-views
 * @since affiliates-jigoshop-viewa 1.0.0
 *
 * Plugin Name: Affiliates Jigoshop Views
 * Plugin URI: http://www.itthinx.com
 * Description: Views toolbox for the Affiliates Jigoshop integrations. Requires <a href="http://www.itthinx.com/plugins/affiliates/">Affiliates</a>, <a href="http://www.itthinx.com/plugins/affiliates-pro/">Affiliates Pro</a> or <a href="http://www.itthinx.com/plugins/affiliates-enterprise/">Affiliates Enterprise</a>.
 * Version: 1.0.0
 * Author: eggemplo
 * Author URI: http://www.eggemplo.com
 * Donate-Link: http://www.eggemplo.com
 * License: GPLv3
 */

/**
 * Extended views for Affiliates with Jigoshop.
 */
class Affiliates_Jigoshop_Views {

	/**
	 * Setup.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'wp_init' ) );
	}

	/**
	 * Adds shortcodes.
	 */
	public static function wp_init() {
		if ( defined( 'AFFILIATES_CORE_VERSION' ) ) {
			add_shortcode( 'affiliates_jigoshop_orders', array( __CLASS__, 'affiliates_jigoshop_orders' ) );
		}
	}

	/**
	 * Renders the [affiliates_jigoshop_orders] shortcode.
	 * 
	 * @param array $atts
	 * @param string $content not used
	 * @return string
	 */
	public static function affiliates_jigoshop_orders( $atts, $content = null ) {
		global $wpdb;
		
		$output = "";
		if ( class_exists( 'jigoshop_order' ) ) {
			$options = shortcode_atts(
				array(
					'status'     => AFFILIATES_REFERRAL_STATUS_ACCEPTED,
					'from'       => !empty( $_POST['from_date'] ) && empty( $_POST['clear_filters'] ) ? $_POST['from_date'] : null,
					'until'      => !empty( $_POST['thru_date'] ) && empty( $_POST['clear_filters'] )? $_POST['thru_date'] : null,
					'for'        => null,
					'order_by'   => 'date',
					'order'      => 'DESC',
					'limit'      => null,
					'auto_limit' => '20',
					'show_limit' => 'Showing up to %d orders.'
				),
				$atts
			);
			extract( $options );
	
			self::for_from_until( $for, $from, $until );
	
			if ( ( intval( $auto_limit ) > 0 ) && ( $from === null || $until === null ) ) {
				$limit = intval( $auto_limit );
			}
	
			$user_id = get_current_user_id();
			if ( $user_id && affiliates_user_is_affiliate( $user_id ) ) {
				$affiliates_table = _affiliates_get_tablename( 'affiliates' );
				$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
				if ( $affiliate_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT au.affiliate_id FROM $affiliates_users_table au LEFT JOIN $affiliates_table a ON au.affiliate_id = a.affiliate_id WHERE au.user_id = %d AND a.status = 'active'",
					intval( $user_id )
				))) {
					$referrals = self::get_affiliate_referrals( $affiliate_id, $from, $until, $status, $order_by, $order, $limit );
					if ( !empty( $show_limit ) && $limit > 0 ) {
						$output .= '<p class="show_limit">';
						$output .= sprintf( $show_limit, $limit );
						$output .= '</p>';
					}
						
					foreach( $referrals as $referral ) {
						$order_id = $referral->post_id;
						
						$order = new jigoshop_order( $order_id );
						//$order = new WC_Order( $order_id );
						$orderdata = $order->_data;
						
						$output .= '<table class="shop_table order_details">';
						$output .= '<thead>';
						$output .= '<tr>';
						$output .= '<th class="product-name">';
						$output .= sprintf( __( '%s, Order #%d', 'jigoshop' ),  $orderdata['order_date']  , $order_id );
						$output .= '</th>';
						$output .= '<th class="product-total">';
						$output .= __( 'Total', 'jigoshop' );
						$output .= '</th>';
						$output .= '</tr>';
						$output .= '</thead>';
						$output .= '<tfoot>';
						$output .= '<tr>';
						$output .= '<th scope="row">' . __( 'Referral amount' ) . '</th>';
						$output .= '<td>';
						$output .= '<span class="amount">' . jigoshop_price( $referral->amount ) . '</span>';
						$output .= '</td>';
						$output .= '</tr>';
						$output .= '</tfoot>';
						$output .= '<tbody>';
						if ( sizeof( $order->items ) > 0 ) {
							foreach ( $order->items as $item ) {
								if (is_array($item)) {
								$_product = $order->get_product_from_item( $item );
									//$_product = get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
									$output .= '<tr>';
									$output .= '<td class="product-name">';
									$output .= '<a href="' . get_permalink( $_product->ID ) . '">' . $_product->get_title() . '</a>';
									$output .= '<strong class="product-quantity"> x ' . $item['qty'] . '</strong>';
									$output .= '</td><td class="product-total">' . jigoshop_price($_product->get_price_excluding_tax()) . '</td></tr>';
								}
							}
						}
						$output .= '</tbody>';
						$output .= '</table>';
						
					}
				}
			}
		}
		return $output;
	}

	/**
	 * Returns referrals for a given affiliate.
	 *
	 * @param int $affiliate_id the affiliate's id
	 * @param string $from_date optional from date
	 * @param string $thru_date optional thru date
	 * @return int number of hits
	 */
	public static function get_affiliate_referrals( $affiliate_id, $from_date = null , $thru_date = null, $status = null, $order_by = 'datetime', $order = 'DESC', $limit = null ) {
		global $wpdb;
		$referrals_table = _affiliates_get_tablename( 'referrals' );
		$where = " WHERE affiliate_id = %d";
		$values = array( $affiliate_id );
		
		
		switch( $order_by ) {
			case 'date' :
				$order_by = 'datetime';
				break;
			case 'amount' :
				break;
			default :
				$order_by = 'datetime';
		}
		$order = strtoupper( $order );
		switch( $order ) {
			case 'ASC' :
			case 'DESC' :
				break;
			default :
				$order = 'DESC';
		}
		$order_query = ' ORDER BY ' . $order_by . ' ' . $order;
		
		$limit_query = '';
		if ( $limit !== null ) {
			$limit = intval( $limit );
			if ( $limit > 0 ) {
				$limit_query = ' LIMIT ' . $limit;
			}
		}

		if ( $from_date ) {
			$from_date = date( 'Y-m-d', strtotime( $from_date ) );
		}
		if ( $thru_date ) {
			$thru_date = date( 'Y-m-d', strtotime( $thru_date ) + 24*3600 );
		}
		if ( $from_date && $thru_date ) {
			$where .= " AND datetime >= %s AND datetime < %s ";
			$values[] = $from_date;
			$values[] = $thru_date;
		} else if ( $from_date ) {
			$where .= " AND datetime >= %s ";
			$values[] = $from_date;
		} else if ( $thru_date ) {
			$where .= " AND datetime < %s ";
			$values[] = $thru_date;
		}
		if ( !empty( $status ) ) {
			$where .= " AND status = %s ";
			$values[] = $status;
		} else {
			$where .= " AND status IN ( %s, %s ) ";
			$values[] = AFFILIATES_REFERRAL_STATUS_ACCEPTED;
			$values[] = AFFILIATES_REFERRAL_STATUS_CLOSED;
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $referrals_table $where $order_query $limit_query",
			$values
		) );
	}

	/**
	 * Adjust from und until dates from UTZ to STZ and take into account the
	 * for option which will adjust the from date to that of the current
	 * day, the start of the week or the month, leaving the until date
	 * set to null.
	 *
	 * @param string $for "day", "week" or "month"
	 * @param string $from date/datetime
	 * @param string $until date/datetime
	 */
	private static function for_from_until( $for, &$from, &$until ) {
		include_once( AFFILIATES_CORE_LIB . '/class-affiliates-date-helper.php');
		if ( $for === null ) {
			if ( $from !== null ) {
				$from = date( 'Y-m-d H:i:s', strtotime( DateHelper::u2s( $from ) ) );
			}
			if ( $until !== null ) {
				$until = date( 'Y-m-d H:i:s', strtotime( DateHelper::u2s( $until ) ) );
			}
		} else {
			$user_now                      = strtotime( DateHelper::s2u( date( 'Y-m-d H:i:s', time() ) ) );
			$user_now_datetime             = date( 'Y-m-d H:i:s', $user_now );
			$user_daystart_datetime        = date( 'Y-m-d', $user_now ) . ' 00:00:00';
			$server_now_datetime           = DateHelper::u2s( $user_now_datetime );
			$server_user_daystart_datetime = DateHelper::u2s( $user_daystart_datetime );
			$until = null;
			switch ( strtolower( $for ) ) {
				case 'day' :
					$from = date( 'Y-m-d H:i:s', strtotime( $server_user_daystart_datetime ) );
					break;
				case 'week' :
					$fdow = intval( get_option( 'start_of_week' ) );
					$dow  = intval( date( 'w', strtotime( $server_user_daystart_datetime ) ) );
					$d    = $dow - $fdow;
					$from = date( 'Y-m-d H:i:s', mktime( 0, 0, 0, date( 'm', strtotime( $server_user_daystart_datetime ) )  , date( 'd', strtotime( $server_user_daystart_datetime ) )- $d, date( 'Y', strtotime( $server_user_daystart_datetime ) ) ) );
					break;
				case 'month' :
					$from = date( 'Y-m', strtotime( $server_user_daystart_datetime ) ) . '-01 00:00:00';
					break;
				default :
					$from = null;
			}
		}
	}
	
}
Affiliates_Jigoshop_Views::init();
