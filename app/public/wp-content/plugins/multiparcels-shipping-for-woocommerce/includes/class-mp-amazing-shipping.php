<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


/**
 * Class MP_Amazing_shipping
 */
class MP_Amazing_shipping {

	const ACTION_DELETE_SHIPPING = 'multiparcels_delete_shipping';

	/**
	 * Admin constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_' . 'multiparcels_amazing_shipping_next', [ $this, 'do_next' ] );
		add_action( 'wp_ajax_' . 'multiparcels_amazing_shipping_next', [ $this, 'do_next' ] );

		add_action( 'admin_post_' . self::ACTION_DELETE_SHIPPING, [ $this, 'delete_shipping' ] );
	}

	public static function link() {
		return admin_url( 'admin.php?page=multiparcels-shippings' );
	}

	public function delete_shipping() {
		MultiParcels()->shippings->delete( $_GET['id'] );
		wp_redirect( self::link() );
	}

	static function free_shipments() {
	    $link = 'https://multiparcels.com/registration/';

		if ( get_locale() == 'lt_LT' || get_locale() == 'lt' ) {
			$link = 'https://multisiuntos.lt/registracija/';
		}

		?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
				<?php _e( 'Shipments', 'multiparcels-shipping-for-woocommerce' ); ?>
            </h1>

            <hr class="wp-header-end">

            <div style="width: 100%;height: 400px;display: flex;background: white;justify-content: center;align-items: center;flex-direction: column;">
                <div style="margin-bottom: 15px;">
                    <img src="<?php echo MultiParcels()->public_plugin_url( 'images/logo.svg' ); ?>" alt="MultiParcels"
                         style="width: 250px;vertical-align: middle"> <span
                            style="vertical-align: middle;display: inline-block;font-size: 18px;padding-top: 8px;"> - <?php _e( 'One solution for all courier parcels',
							'multiparcels-shipping-for-woocommerce' ) ?></span>
                </div>
                <h1 style="margin-bottom: 25px;">
					<?php _e( 'From here you could ship your orders in seconds without copying any order details, why not give it a try?',
						'multiparcels-shipping-for-woocommerce' ) ?>
                </h1>
                <div>
                    <a href="<?php _e( $link,
						'multiparcels-shipping-for-woocommerce' ); ?>" target="_blank" class="button button-primary">
						<?php _e( 'Get free full version', 'multiparcels-shipping-for-woocommerce' ) ?>
                    </a>
                </div>
            </div>
        </div>
		<?php
	}

	private static function labels( $id ) {
		$shipments = MultiParcels()->shippings->get_shipments( $id );

		$external_ids = [];

		foreach ( $shipments as $shipment ) {
			$data = get_post_meta( $shipment['order_id'], 'multiparcels_external_id' );

			if ( count( $data ) ) {
				$ids = explode( ',', $data[0] );

				foreach ( $ids as $id ) {
					$external_ids[] = $id;
				}
			}
		}

		if ( count( $external_ids ) ) {
			$response = MultiParcels()->api_client->request( 'batch_labels', 'POST', [
				'shipments' => $external_ids,
			] );

			if ( $response->was_successful() ) {
				$file_name = sprintf( "labels_%d.pdf", mt_rand( 10000, 99999 ) );
				$upload    = wp_upload_bits( $file_name, null, base64_decode( $response->get_data()['content'] ) );

				header( 'Location: ' . $upload['url'] );
				exit;
			}
		}
	}

	private static function reset_failed( $id ) {
		$shipments = MultiParcels()->shippings->get_shipments( $id, MultiParcels_Delivery_Shippings::STATUS_FAILED );

		foreach ( $shipments as $shipment ) {
			MultiParcels()->shippings->update_shipment( $shipment['id'],
				MultiParcels_Delivery_Shippings::STATUS_WAITING );
		}

		MultiParcels()->shippings->recalculate( $id );
	}

	static function shipments() {
		if ( array_key_exists( 'start', $_GET ) ) {
			self::start( $_GET['start'] );

			return;
		}

		if ( array_key_exists( 'labels', $_GET ) ) {
			self::labels( $_GET['labels'] );

			return;
		}

		if ( array_key_exists( 'reset_failed', $_GET ) ) {
			self::reset_failed( $_GET['reset_failed'] );
		}

		$table = new MultiParcels_List_Table();
		$table->prepare_items();
		?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
				<?php _e( 'Shipments', 'multiparcels-shipping-for-woocommerce' ) ?>
            </h1>
            <a href="<?php echo admin_url( 'edit.php?post_type=shop_order' ); ?>" class="page-title-action">
				<?php _e( 'Create', 'multiparcels-shipping-for-woocommerce' ) ?>
            </a>
            <hr class="wp-header-end">

            <div id="multiparcels-amazing-shipping-table">
				<?php $table->display(); ?>
            </div>
        </div>

        <style>
            #multiparcels-amazing-shipping-table .column-id, #multiparcels-amazing-shipping-table .column-done, #multiparcels-amazing-shipping-table .column-status {
                width: 70px;
            }
        </style>
		<?php
	}

	static function start( $id ) {
		$shipping = MultiParcels()->shippings->get( $id );
		$history  = MultiParcels()->shippings->get_history( $id );

		if ( ! $shipping ) {
			echo 'Not found';

			return;
		}

		echo "<div class='wrap'>";
		?>
        <h1 class="wp-heading-inline">
			<?php _e( 'Shipments', 'multiparcels-shipping-for-woocommerce' ) ?>
        </h1>
		<?php

		wc_get_template( 'admin/amazing-shipping.php', [
			'shipping' => $shipping,
			'history'  => $history,
		],'',MultiParcels()->plugin_path() . '/woocommerce/' );

		echo "</div>";
	}

	public function do_next() {
		$id            = $_POST['id'];
		$next_shipment = MultiParcels()->shippings->get_next_shipment( $id );

		if ( $next_shipment ) {
			$already_confirmed = (bool) get_post_meta( $next_shipment['order_id'],
				MP_Woocommerce_Order_Shipping::CONFIRMED_KEY, true );

			if ( ! $already_confirmed ) {
				/** @var MP_Woocommerce_Order_Shipping $shippingClass */
				$shippingClass = new MP_Woocommerce_Order_Shipping();
				$shippingClass->ship_order( $next_shipment['order_id'], [], false );

				$status = MultiParcels_Delivery_Shippings::STATUS_DONE;

				if ( ! (bool) get_post_meta( $next_shipment['order_id'], MP_Woocommerce_Order_Shipping::CONFIRMED_KEY,
					true ) ) {
					$status = MultiParcels_Delivery_Shippings::STATUS_FAILED;
				}
			} else {
				$status = MultiParcels_Delivery_Shippings::STATUS_ALREADY_SHIPPED;
			}

			MultiParcels()->shippings->update_shipment( $next_shipment['id'], $status );
			MultiParcels()->shippings->recalculate( $id );
		}

		$shipping = MultiParcels()->shippings->get( $id );
		$history  = MultiParcels()->shippings->get_history( $id );

		wp_send_json_success( [
			'shipping' => $shipping,
			'next'     => $next_shipment,
			'history'  => $history,
		] );
	}
}

class MultiParcels_List_Table extends WP_List_Table {
	public function prepare_items() {

		$columns      = $this->get_columns();
		$data         = $this->table_data();
		$per_page     = 10;
		$current_Page = $this->get_pagenum();
		$total_items  = count( $data );
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
		rsort( $data );
		$data                  = array_slice( $data, ( ( $current_Page - 1 ) * $per_page ), $per_page );
		$this->_column_headers = [ $columns, [], [] ];
		$this->items           = $data;
	}

	public function get_columns() {
		$columns = [

			'id'         => 'ID',
			'done'       => _x( 'Done', 'Amazing shipping', 'multiparcels-shipping-for-woocommerce' ),
			'failed'     => _x( 'Failed', 'Amazing shipping', 'multiparcels-shipping-for-woocommerce' ),
			'shipments'  => __( 'Shipments', 'multiparcels-shipping-for-woocommerce' ),
			'status'     => __( 'Status', 'multiparcels-shipping-for-woocommerce' ),
			'created_at' => __( 'Created at', 'multiparcels-shipping-for-woocommerce' ),
			'actions'    => __( 'Actions', 'multiparcels-shipping-for-woocommerce' ),
		];

		return $columns;
	}

	private function table_data() {
		return MultiParcels()->shippings->all();
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'done':
			case 'shipments':
			case 'created_at':
				return $item[ $column_name ];
			case 'status':
				return sprintf(
					"<span style='color: %s'>%s</span>",
					MultiParcels()->shippings->status_color( $item[ $column_name ] ),
					MultiParcels()->shippings->status( $item[ $column_name ] )
				);
			case 'count':
				return sprintf( '%d/%d', $item['done'], $item['shipments'] );
			case 'failed':
				if ( $item['failed'] > 0 ) {
					return sprintf( "<div style='%s'>%d</div>", 'background:red;color:white;padding:5px;',
						$item['failed'] );
				} else {
					return 0;
				}
			case 'actions':
				return $this->actions_html( $item );
			default:
				return print_r( $item, true );
		}
	}

	public function actions_html( $item ) {
		$html = '';

		if ( $item['status'] == MultiParcels_Delivery_Shippings::STATUS_WAITING ) {
			$html .= sprintf(
				"<a class='button button-primary' href='%s'>%s</a>",
				MP_Amazing_shipping::link() . '&start=' . $item['id'],
				__( 'Start', 'multiparcels-shipping-for-woocommerce' )
			);

			$html .= '<div style="height: 5px;"></div>';
		} elseif ( $item['failed'] > 0 ) {
			$html .= sprintf(
				"<a class='button' href='%s'>%s</a>",
				MP_Amazing_shipping::link() . '&start=' . $item['id'],
				__( 'Failed shipments', 'multiparcels-shipping-for-woocommerce' )
			);

			$html .= '<div style="height: 5px;"></div>';
		}

		if ( $item['status'] == MultiParcels_Delivery_Shippings::STATUS_DONE && $item['done'] > 0 ) {
			$html .= sprintf(
				"<a class='button' href='%s'>%s</a>",
				MP_Amazing_shipping::link() . '&labels=' . $item['id'],
				__( 'Download all labels', 'multiparcels-shipping-for-woocommerce' )
			);

			$html .= '<div style="height: 5px;"></div>';
		}

		if ( $item['status'] == MultiParcels_Delivery_Shippings::STATUS_DONE && $item['failed'] > 0 ) {
			$html .= sprintf(
				"<a class='button' href='%s'>%s</a>",
				MP_Amazing_shipping::link() . '&reset_failed=' . $item['id'],
				__( 'Reset failed', 'multiparcels-shipping-for-woocommerce' )
			);

			$html .= '<div style="height: 5px;"></div>';
		}

		$html .= sprintf( "<a class='button' href='%s'>%s</a>",
			admin_url( 'admin-post.php?action=' . MP_Amazing_shipping::ACTION_DELETE_SHIPPING . '&id=' . $item['id'] ),
			__( 'Delete', 'multiparcels-shipping-for-woocommerce' ) );

		return $html;
	}
}

return new MP_Amazing_shipping();
