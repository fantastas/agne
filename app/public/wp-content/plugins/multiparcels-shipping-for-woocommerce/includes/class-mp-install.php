<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Install
 */
class MP_Install
{
    public static function install()
    {
        self::table();
    }

    public static function table()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta(self::main_table_sql());
        dbDelta(self::shippings_table_sql());
        dbDelta(self::shippings_shipments_table_sql());
        dbDelta(self::carrier_selections_sql());
    }

    public static function remove()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'multiparcels_terminals';
        $table_name2 = $wpdb->prefix . 'multiparcels_shippings';
        $table_name3 = $wpdb->prefix . 'multiparcels_shipping_shipments';
        $table_name4 = $wpdb->prefix . 'multiparcels_carrier_selections';

        $wpdb->query("DROP TABLE IF EXISTS `" . $table_name . "`");
        $wpdb->query("DROP TABLE IF EXISTS `" . $table_name2 . "`");
        $wpdb->query("DROP TABLE IF EXISTS `" . $table_name3 . "`");
        $wpdb->query("DROP TABLE IF EXISTS `" . $table_name4 . "`");
    }

    public static function update()
    {
        self::install();
        MultiParcels()->options->set('version', MultiParcels()->version, true);
        MultiParcels()->permissions->update();

        // Convert single sender details to sender locations
	    if ( $sender_details = MultiParcels()->options->get_other( 'sender_details' ) ) {
		    if ( array_key_exists( 'name', $sender_details ) ) {
			    if ( count( MultiParcels()->options->get_sender_locations() ) == 0 ) {
				    $sender_details['code'] = 'LOCATION1';
				    MultiParcels()->options->set_sender_location( $sender_details['code'], $sender_details );
				    MultiParcels()->options->set_default_sender_location( $sender_details['code'] );
				    MultiParcels()->options->set_other( 'sender_details', null );
			    }
		    }
	    }
    }

	private static function main_table_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'multiparcels_terminals';

		return "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		carrier_code VARCHAR(255) NOT NULL,
		identifier VARCHAR(255) NOT NULL,
		type VARCHAR(255) NOT NULL,
		name VARCHAR(255) NOT NULL,
		address VARCHAR(255) NOT NULL,
		postal_code VARCHAR(255) NOT NULL,
		city VARCHAR(255) NOT NULL,
		country_code VARCHAR(255) NOT NULL,
		latitude VARCHAR(255) NULL,
		longitude VARCHAR(255) NULL,
		comment VARCHAR(255) NULL,
		working_hours VARCHAR(255) NULL,
		UNIQUE KEY `id` (`id`),
		KEY `carrier_code` (`carrier_code`),
		KEY `identifier` (`identifier`),
		KEY `type` (`type`)
	) $charset_collate;";
	}

	private static function shippings_table_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'multiparcels_shippings';

		return "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			done mediumint(9) NOT NULL,
			failed mediumint(9) NOT NULL,
			shipments mediumint(9) NOT NULL,
			status VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";
	}

	private static function shippings_shipments_table_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'multiparcels_shipping_shipments';

		return "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			shipping_id mediumint(9) NOT NULL,
			order_id mediumint(9) NOT NULL,
			status VARCHAR(255) NOT NULL,
			UNIQUE KEY id (id),
			KEY shipping_id (shipping_id)
		) $charset_collate;";
	}

    private static function carrier_selections_sql()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'multiparcels_carrier_selections';

        return $x= "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			country_code VARCHAR(2) NOT NULL,
			shipping_name VARCHAR(255) NOT NULL,
			carrier VARCHAR(255) NOT NULL,
			method VARCHAR(255) NULL,
			UNIQUE KEY id (id),
			KEY country_code (country_code),
			KEY shipping_name (shipping_name)
		) $charset_collate;";
    }
}
