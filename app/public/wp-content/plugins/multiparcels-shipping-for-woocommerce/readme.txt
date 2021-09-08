=== MultiParcels Shipping For WooCommerce ===
Contributors: multiparcels
Tags: Omniva, DPD, Venipak, LP Express, Itella
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 4.4
Tested up to: 5.8
Requires PHP: 5.6
Stable tag: 1.14.4

Easiest, fastest and the cheapest way to integrate couriers with all deliveries methods to send parcels with just a few button clicks.

== Description ==

### Main features
* Add pickup point selector to the checkout
* Hide city, postal code and address fields when delivering to pickup locations
* Hide city, postal code and address fields when customer chooses "Local pickup"
* Display carrier logos near shipping methods
* Create shipping labels for all carriers in one place
* Send tracking code to the customer (also we support Omnisend)
* Automatic shipping label creation
* Automatic pickup locations update

Easy way to integrate carriers with all deliveries methods to create shipments with just a few button clicks. Try for free!

### More features
* COD (paying in cash or with bank card on order delivery) service
* Free shipping option
* Shows only possible locations for customer's city
* Selected pickup location preview on email
* Display selected pickup location on Google maps
* Display shipping methods only on specific days/time
* Multiple sending locations/multiple warehouses
* Easy to change pickup point selector location
* Easy to get selected pickup point information for invoices etc. with code
* Ongoing Warehouse support

### Carriers

1. DPD (Lithuania/Latvia/Estonia)
2. Omniva (Lithuania/Latvia/Estonia)
3. Venipak (Lithuania/Latvia/Estonia)
4. LP Express / LPExpress / lpexpress.lt
5. Lithuania POST / Lietuvos paštas / post.lt
6. Latvian POST / expresspasts.lv / pasts.lv
7. ZITICITY
8. UPS / UPS SurePost
9. DHL Express
10. TNT
11. FedEx Parcel / LTL Freight
12. Deutsche Post International / DHL Global Mail / deutschepost.com
13. GLS
14. Hermes World / Hermes Border-guru
15. Hermes UK / MyHermes
16. InPost
17. Posti
18. Itella
19. SmartPOST by Itella
20. ParcelStars
21. Siuntos autobusais

### Pickup points

1. DPD (Lithuania, Latvia, Estonia)
2. LP Express (Lithuania)
3. Omniva (Lithuania, Latvia, Estonia)
4. Venipak (Lithuania, Latvia, Estonia)
5. Itella (Finland, Lithuania, Latvia, Estonia)
6. Posti (Finland, Lithuania, Latvia, Estonia)
7. SmartPOST by Itella (Finland, Estonia)
8. InPost (Poland)
9. Latvian post (Latvia, Lithuania)
10. ParcelStars (Lithuania, Latvia, Estonia, Poland)

### Supported checkouts

* Default WooCommerce checkout
* CheckoutWC
* AeroCheckout
* Rey theme checkout

### Always free shipping methods

MultiParcels **guarantees** different delivery options - terminal/pickup point - selection for free!

**Always** free:
- Terminals
- Pickup points

### Supported
- YITH WooCommerce Product Bundles
- WPC Product Bundles for WooCommerce

### Created for you, by
MultiParcels - MultiSiuntos

== Frequently Asked Questions ==

= How to get the selected pickup location information programmatically? =
> $order = 123; // or WC_Order class
> $selected_location = MultiParcels()->locations->get_location_for_order($order); // result is either an array or false

= How to change to change pickup point selector display hook? =
> add_filter('multiparcels_location_selector_hook', function () {
>    return 'woocommerce_before_order_notes';
> });

= How to change to change carrier logo? =
> add_filter('multiparcels_checkout_carrier_logo_url', function ($default_logo_url, $carrier, $delivery_type_code) {
>     if ($carrier == WC_MP_Shipping_Helper::CARRIER_LP_EXPRESS && $delivery_type_code == WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT) {
>         return "https://multiparcels.com/lp_expess_terminal.png";
>     }
>     return $default_logo_url;
> }, 10, 3);

== Installation ==

1. Install the plugin
2. Go through the installation of 1 click
3. Enable the couriers you need
4. Enter the pricing for your desired shipping methods
5. Start shipping!

== Screenshots ==

1. Selected pickup location information with Google maps
2. Hidden fields when delivering to pickup points
3. Shipping zone preview
4. Shipping zone shipping method settings
5. Settings page & carrier list
6. Email preview

== Changelog ==

= 1.14.4 - 2021-08-09
- [New carrier] Siuntos autobusais

= 1.14.3 - 2021-07-28
- Removed places where we ask for review

= 1.14.2 - 2021-07-27
- [Feature] Disable free shipping coupon for specific shipping methods
- [Tweak] Checkout fields for free plan
- [Tweak] Better PHP8 support
- [Tweak] Automatic shipment confirmation every 24 hours
- [Fix] Local pickup hidden fields

= 1.14.1 - 2021-05-21
- [Tweak] API errors in Lithuanian language
- [Tweak] Better select2 support

= 1.14 - 2021-05-21
- [New carrier] FedEx
- [New carrier] Itella
- [New carrier] Deutsche Post International / DHL Global Mail
- [New carrier] GLS
- [New carrier] Hermes World
- [New carrier] Hermes (United Kingdom)
- [Feature] Ability to select shipping method(economy, express, express saver etc.) in shipping zones
- [Feature] Ability to edit products worth when dispatching orders(customs value)
- [Feature] Filter "multiparcels_checkout_carrier_icon_url" to change carrier logo
- [Feature] Ability to remove selected pickup point
- [Tweak] Better support for default <select> and group by city

= 1.13.2 - 2021-05-21
- [Feature] Hide address fields when customers selects "Local pickup"
- [Feature] Hide delivery phone number field
- [Tweak] Fully clear locations table each update

= 1.13.1 - 2021-05-06
- [Fix] Filter woocommerce_order_get_formatted_shipping_address in WC < 3.9
- [Fix] Rey theme checkout support bug

= 1.13 - 2021-05-04
- [Feature] Latvian POST terminal/post office selection
- [Tweak] ZITICITY improvements

= 1.12.2 - 2021-04-09
- [Feature] Rey theme checkout support
- [Improvement] Automatic confirmation

= 1.12.1 - 2021-03-25
- [Feature] Automatic confirmation sends an email if confirmation fails
- [Feature] Ability to select which statuses automatic confirmation will confirm
- [Feature] Ability to show or hide address 2 field
- [Feature] Ability to force required phone number
- [Feature] Support WPC Product Bundles for WooCommerce - skip main bundle product
- [Improvement] ZITICITY delivery to any city
- [Tweak] Hide VAT status in shipping zones if taxes are disabled
- [Tweak] Hide address 2 field when delivering to pickup points
- [Tweak] Display selected pickup location information in order list when there are no shipping details

= 1.12 - 2021-03-08
- [Feature] Ability to hide not required fields when shipping to pickup locations
- [Feature] Automatic order confirmation
- [Feature] Added shipping phone field
- [Feature] Change pickup type for each shipping method
- [Tweak] Forbid dispatching if selected pickup location does not exist in the database
- [Tweak] Ability to change product names when dispatching an order
- [Tweak] Omnisend improvements
- [Tweak] ZITICITY improvements

= 1.11.3 - 2021-02-09
- [Tweak] ZITICITY cities

= 1.11.2 - 2021-01-26
- [Feature] New button to reset order shipment and change status to "Processing"
- [Fix] A bug with "does not fit in pickup locations" variations

= 1.11.1 - 2020-12-08
- [Fix] A bug with "does not fit in pickup locations"
- [Tweak] Ability to modify the quantity of products when dispatching a shipment

= 1.11 - 2020-12-04
- [New carrier] ZITICITY carrier added
- [Feature] Change COD value on order page
- [Feature] Omnisend support for tracking number, tracking link and carrier
- [Feature] Ability to specify which products/categories do not fit in pickup locations
- [Feature] Ability to apply minimum order amount rule before coupon discount. Now minimum order amount for free shipping calculates after coupon applied discount
- [Tweak] Added missing services translations
- [Tweak] Order created at time to include timezone difference
- [Tweak] Alert if the buyer will not receive the tracking code because the order status is "Completed"
- [Tweak] Alert if Paysera Paid Order Status is "Completed". The buyer would not get the tracking code
