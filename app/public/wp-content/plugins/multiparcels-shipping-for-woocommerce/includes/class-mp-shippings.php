<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class MultiParcels_Delivery_Shippings
 */
class MultiParcels_Delivery_Shippings {
	const STATUS_WAITING = 'waiting';
	const STATUS_DONE = 'done';
	const STATUS_FAILED = 'failed';
	const STATUS_ALREADY_SHIPPED = 'already_shipped';

	/** @var string */
	private $table = 'multiparcels_shippings';

	private $table_shipments = 'multiparcels_shipping_shipments';

	/**
	 * @param string|null $carrier_code
	 * @param string|null $country
	 *
	 * @return mixed
	 */
	public function all() {
		global $wpdb;

		$query = "SELECT * FROM " . $this->table();

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * @param integer $id
	 *
	 * @return array
	 */
	public function get( $id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM " . $this->table() . " WHERE `id` = %d", $id );

		return $wpdb->get_row( $query, ARRAY_A );
	}

	/**
	 * @param integer     $id
	 *
	 * @param string|null $status
	 *
	 * @return array
	 */
	public function get_shipments( $id, $status = null ) {
		global $wpdb;

		$status_query = '';

		if ( $status ) {
			$status_query = sprintf( "AND `status` = '%s'", $status );
		}

		$query = $wpdb->prepare( "SELECT * FROM " . $this->shipments_table() . " WHERE `shipping_id` = %d " . $status_query . " ORDER BY `order_id` ASC",
			$id );

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * @param integer     $id
	 *
	 * @param string|null $status
	 *
	 * @return array
	 */
	public function get_next_shipment( $id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM " . $this->shipments_table() . " WHERE `shipping_id` = %d AND `status` = '" . self::STATUS_WAITING . "' ORDER BY `order_id` ASC LIMIT 1",
			$id );

		return $wpdb->get_row( $query, ARRAY_A );
	}

	/**
	 * @param integer     $id
	 *
	 * @param string|null $status
	 *
	 * @return bool|int
	 */
	public function update_shipment( $id, $status ) {
		global $wpdb;

		$query = $wpdb->prepare( "UPDATE " . $this->shipments_table() . " SET `status` = %s WHERE `id` = %d", $status,
			$id );

		return $wpdb->query( $query );
	}

	/**
	 * @param integer $id
	 *
	 * @return bool|int
	 */
	public function recalculate( $id ) {
		global $wpdb;

		$status = self::STATUS_WAITING;

		$shipping        = $this->get( $id );
		$done            = count( $this->get_shipments( $id, self::STATUS_DONE ) );
		$already_shipped = count( $this->get_shipments( $id, self::STATUS_ALREADY_SHIPPED ) );
		$failed          = count( $this->get_shipments( $id, self::STATUS_FAILED ) );
		$total           = $done + $failed + $already_shipped;

		$done += $already_shipped;

		if ( $total == $shipping['shipments'] ) {
			$status = self::STATUS_DONE;
		}

		$query = $wpdb->prepare( "UPDATE " . $this->table() . " SET `status` = '%s', `done` = '%s', `failed` = '%s' WHERE `id` = %d",
			$status, $done, $failed, $id );

		return $wpdb->query( $query );
	}

	/**
	 * Returns prefixed table name
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;

		return $wpdb->prefix . $this->table;
	}

	/**
	 * Returns prefixed table name
	 *
	 * @return string
	 */
	private function shipments_table() {
		global $wpdb;

		return $wpdb->prefix . $this->table_shipments;
	}

	/**
	 * Create a location
	 *
	 * @param array     $data
	 * @param integer[] $ids
	 */
	public function create( $data = [], $ids = []) {
        global $wpdb;

        $skip_methods = MultiParcels()->options->get_array('skip_methods_for_dispatching');

        foreach ($ids as $key => $value) {
            $order            = wc_get_order($value);
            $shipping_methods = $order->get_shipping_methods();
            $shipping_method  = reset($shipping_methods);

            if (in_array($shipping_method['method_id'], $skip_methods)) {
                unset($ids[$key]);
            }
        }

        if (count($ids) == 0) {
            return;
        }

        $data['status']     = self::STATUS_WAITING;
        $data['shipments']  = count($ids);
        $data['created_at'] = current_time('Y-m-d H:i:s');

        $wpdb->insert($this->table(), $data);
        $shipping_id = $wpdb->insert_id;

        foreach ($ids as $id) {
            $wpdb->insert($this->shipments_table(), [
                'shipping_id' => $shipping_id,
                'order_id'    => $id,
                'status'      => self::STATUS_WAITING,
            ]);
        }
	}

	public function status( $status_code ) {
		if ( $status_code == self::STATUS_DONE ) {
			return __( 'Done', 'multiparcels-shipping-for-woocommerce' );
		}

		if ( $status_code == self::STATUS_WAITING ) {
			return __( 'Waiting', 'multiparcels-shipping-for-woocommerce' );
		}

		if ( $status_code == self::STATUS_FAILED ) {
			return __( 'Failed', 'multiparcels-shipping-for-woocommerce' );
		}

		if ( $status_code == self::STATUS_ALREADY_SHIPPED ) {
			return __( 'Already shipped', 'multiparcels-shipping-for-woocommerce' );
		}

		return $status_code;
	}

	public function status_color( $status_code ) {
		if ( $status_code == self::STATUS_DONE ) {
			return 'green';
		}

		if ( $status_code == self::STATUS_FAILED ) {
			return 'red';
		}

		return '';
	}

	public function delete( $id ) {
		global $wpdb;

		$wpdb->query( "DELETE FROM " . $this->table() . ' WHERE `id` = ' . $id );
		$wpdb->query( "DELETE FROM " . $this->shipments_table() . ' WHERE `shipping_id` = ' . $id );
	}

	public function get_history( $id ) {
		$shipments = $this->get_shipments( $id );
		$shipping  = $this->get( $id );

		usort( $shipments, function ( $shipment ) {
			return $shipment['order_id'];
		} );

		$html = '';

		if ( $shipping['status'] == self::STATUS_DONE && $shipping['done'] > 0 ) {
			$html .= "<div style='text-align: center'>";
			$html .= sprintf(
				"<a class='button button-primary' href='%s' target='_blank' style='margin-bottom: 15px;'>%s</a>",
				MP_Amazing_shipping::link() . '&labels=' . $shipping['id'],
				__( 'Download all labels', 'multiparcels-shipping-for-woocommerce' )
			);
			$html .= "</div>";
		}

		$html .= ' <h2 style="text-align: center;">' . __( 'History',
				'multiparcels-shipping-for-woocommerce' ) . '</h2>';
		$html .= "<table style='width: 100%;'>";
		$html .= '<thead>';
		$html .= '<tr>';
		$html .= sprintf( "<th>%s</th>", __('Order', 'multiparcels-shipping-for-woocommerce') );
		$html .= sprintf( "<th>%s</th>", __('Status', 'multiparcels-shipping-for-woocommerce') );
		$html .= sprintf( "<th>%s</th>", __('Errors', 'multiparcels-shipping-for-woocommerce') );
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';

		foreach ( $shipments as $shipment ) {
			if ( $shipment['status'] == self::STATUS_WAITING ) {
				continue;
			}

			$all_errors = json_decode( get_post_meta( $shipment['order_id'], MP_Woocommerce_Order_Shipping::ERRORS_KEY,
				true ), true );

			if ( $all_errors == '[]' ) {
				$all_errors = [];
			}

			$errors = [];

			foreach ( $all_errors as $validation_errors ) {
				foreach ( $validation_errors as $validation_error ) {
					$errors[] = $validation_error['text'];
				}
			}

			$html .= '<tr>';
			$html .= sprintf(
				"<td><a href='%s' target='_blank'>%s</a></td>",
				admin_url( 'post.php?post=' . $shipment['order_id'] . '&action=edit' ),
				$shipment['order_id']
			);

			if ( $shipment['status'] == self::STATUS_FAILED ) {
				$html .= sprintf(
					"<td style='background: red;color: white;'>%s</td>",
					$this->status( $shipment['status'] )
				);
			} elseif ( in_array( $shipment['status'], [ self::STATUS_DONE, self::STATUS_ALREADY_SHIPPED ] ) ) {
				$html .= sprintf(
					"<td style='background: green;color: white;'>%s</td>",
					$this->status( $shipment['status'] )
				);
			} else {
				$html .= sprintf( "<td>%s</td>", $this->status( $shipment['status'] ) );
			}

			if ( count( $errors ) ) {
				$html .= "<td style='list-style: disc;padding-left: 10px;'><ul>";
				foreach ( $errors as $error ) {
					$html .= sprintf( "<li>%s</li>", $error );
				}
				$html .= '</ul></td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
	}
}

return new MultiParcels_Delivery_Shippings();
