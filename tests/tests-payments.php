<?php

use \EDD_Payments_Query;
/**
 * @group edd_payments
 */
class Tests_Payments extends WP_UnitTestCase {

	protected $_payment_id = null;
	protected $_key = null;
	protected $_post = null;
	protected $_payment_key = null;

	public function setUp() {

		global $edd_options;

		parent::setUp();

		// Enable a few options
		$edd_options['enable_sequential'] = '1';
		$edd_options['sequential_prefix'] = 'EDD-';
		update_option( 'edd_settings', $edd_options );

		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Download', 'post_type' => 'download', 'post_status' => 'publish' ) );

		$_variable_pricing = array(
			array(
				'name' => 'Simple',
				'amount' => 20
			),
			array(
				'name' => 'Advanced',
				'amount' => 100
			)
		);

		$_download_files = array(
			array(
				'name' => 'File 1',
				'file' => 'http://localhost/file1.jpg',
				'condition' => 0
			),
			array(
				'name' => 'File 2',
				'file' => 'http://localhost/file2.jpg',
				'condition' => 'all'
			)
		);

		$meta = array(
			'edd_price' => '0.00',
			'_variable_pricing' => 1,
			'_edd_price_options_mode' => 'on',
			'edd_variable_prices' => array_values( $_variable_pricing ),
			'edd_download_files' => array_values( $_download_files ),
			'_edd_download_limit' => 20,
			'_edd_hide_purchase_link' => 1,
			'edd_product_notes' => 'Purchase Notes',
			'_edd_product_type' => 'default',
			'_edd_download_earnings' => 129.43,
			'_edd_download_sales' => 59,
			'_edd_download_limit_override_1' => 1
		);
		foreach( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		$this->_post = get_post( $post_id );

		/** Generate some sales */
		$user = get_userdata(1);

		$user_info = array(
			'id' => $user->ID,
			'email' => $user->user_email,
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'discount' => 'none'
		);

		$download_details = array(
			array(
				'id' => $this->_post->ID,
				'options' => array(
					'price_id' => 1
				)
			)
		);

		$price = '100.00';

		$total = 0;

		$prices = get_post_meta($download_details[0]['id'], 'edd_variable_prices', true);
		$item_price = $prices[1]['amount'];

		$total += $item_price;

		$cart_details = array(
			array(
				'name' => 'Test Download',
				'id' => $this->_post->ID,
				'item_number' => array(
					'id' => $this->_post->ID,
					'options' => array(
						'price_id' => 1
					)
				),
				'price' =>  100,
				'item_price' => 100,
				'tax' => 0,
				'quantity' => 1
			)
		);

		$this->_payment_key = strtolower( md5( uniqid() ) );

		$purchase_data = array(
			'price' => number_format( (float) $total, 2 ),
			'date' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'purchase_key' => $this->_payment_key,
			'user_email' => $user_info['email'],
			'user_info' => $user_info,
			'currency' => 'USD',
			'downloads' => $download_details,
			'cart_details' => $cart_details,
			'status' => 'pending'
		);

		$_SERVER['REMOTE_ADDR'] = '10.0.0.0';
		$_SERVER['SERVER_NAME'] = 'edd_virtual';

		$payment_id = edd_insert_payment( $purchase_data );

		$this->_payment_id = $payment_id;
		$this->_key = $purchase_data['purchase_key'];

		$this->_transaction_id = 'FIR3SID3';
		edd_set_payment_transaction_id( $payment_id, $this->_transaction_id );
		edd_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'edd' ) , $this->_transaction_id ) );
	}

	public function test_get_payments() {
		$out = edd_get_payments();
		$this->assertTrue( is_array( (array) $out[0] ) );
		$this->assertArrayHasKey( 'ID', (array) $out[0] );
		$this->assertArrayHasKey( 'post_type', (array) $out[0] );
		$this->assertEquals( 'edd_payment', $out[0]->post_type );
	}

	public function test_payments_query() {
		$payments = new EDD_Payments_Query;
		$out = $payments->get_payments();
		$this->assertTrue( is_array( (array) $out[0] ) );
		$this->assertArrayHasKey( 'ID', (array) $out[0] );
		$this->assertArrayHasKey( 'cart_details', (array) $out[0] );
		$this->assertArrayHasKey( 'user_info', (array) $out[0] );
	}

	public function test_edd_get_payment_by() {
		$this->assertObjectHasAttribute( 'ID', edd_get_payment_by( 'id', $this->_payment_id ) );
		$this->assertObjectHasAttribute( 'ID', edd_get_payment_by( 'key', $this->_key ) );
	}

	public function test_fake_insert_payment() {
		$this->assertFalse( edd_insert_payment() );
	}

	public function test_payment_completd_flag_not_exists() {

		$completed_date = edd_get_payment_completed_date( $this->_payment_id );
		$this->assertEmpty( $completed_date );

	}

	public function test_update_payment_status() {
		edd_update_payment_status( $this->_payment_id, 'publish' );

		$out = edd_get_payments();
		$this->assertEquals( 'publish', $out[0]->post_status );
	}

	public function test_check_for_existing_payment() {
		edd_update_payment_status( $this->_payment_id, 'publish' );
		$this->assertTrue( edd_check_for_existing_payment( $this->_payment_id ) );
	}

	public function test_get_payment_statuses() {
		$out = edd_get_payment_statuses();

		$expected = array(
			'pending' => 'Pending',
			'publish' => 'Complete',
			'refunded' => 'Refunded',
			'failed' => 'Failed',
			'revoked' => 'Revoked',
			'abandoned' => 'Abandoned'
		);

		$this->assertEquals( $expected, $out );
	}

	public function test_undo_purchase() {
		edd_undo_purchase( $this->_post->ID, $this->_payment_id );
		$this->assertEquals( 0, edd_get_total_earnings() );
	}

	public function test_delete_purchase() {
		edd_delete_purchase( $this->_payment_id );
		// This returns an empty array(), so empty makes it false
		$cart = edd_get_payments();
		$this->assertTrue( empty( $cart ) );
	}

	public function test_get_payment_completed_date() {

		edd_update_payment_status( $this->_payment_id, 'publish' );
		$completed_date = edd_get_payment_completed_date( $this->_payment_id );
		$this->assertInternalType( 'string', $completed_date );
		$this->assertEquals( date( 'Y-m-d' ), date( 'Y-m-d', strtotime( $completed_date ) ) );

	}

	public function test_get_payment_number() {
		global $edd_options;

		$this->assertEquals( 'EDD-1', edd_get_payment_number( $this->_payment_id ) );
		$this->assertEquals( 'EDD-2', edd_get_next_payment_number() );

		// Now disable sequential and ensure values come back as expected
		unset( $edd_options['enable_sequential'] );
		update_option( 'edd_settings', $edd_options );

		$this->assertEquals( $this->_payment_id, edd_get_payment_number( $this->_payment_id ) );
	}

	public function test_get_payment_transaction_id() {
		$this->assertEquals( $this->_transaction_id, edd_get_payment_transaction_id( $this->_payment_id ) );
	}

	public function test_get_payment_transaction_id_legacy() {
		$this->assertEquals( $this->_transaction_id, edd_paypal_get_payment_transaction_id( $this->_payment_id ) );
	}

	public function test_get_payment_meta() {

		// Test by getting the payment key with three different methods
		$this->assertEquals( $this->_payment_key, edd_get_payment_meta( $this->_payment_id, '_edd_payment_purchase_key' ) );
		$this->assertEquals( $this->_payment_key, get_post_meta( $this->_payment_id, '_edd_payment_purchase_key', true ) );
		$this->assertEquals( $this->_payment_key, edd_get_payment_key( $this->_payment_id ) );

		// Try and retrieve the transaction ID
		$this->assertEquals( $this->_transaction_id, edd_get_payment_meta( $this->_payment_id, '_edd_payment_transaction_id' ) );

		$user_info = edd_get_payment_meta_user_info( $this->_payment_id );
		$this->assertEquals( $user_info['email'], edd_get_payment_meta( $this->_payment_id, '_edd_payment_user_email' ) );

	}

	public function test_update_payment_meta() {

		$old_value = $this->_payment_key;
		$this->assertEquals( $old_value, edd_get_payment_meta( $this->_payment_id, '_edd_payment_purchase_key' ) );

		$new_value = 'test12345';
		$this->assertNotEquals( $old_value, $new_value );

		$ret = edd_update_payment_meta( $this->_payment_id, '_edd_payment_purchase_key', $new_value );

		$this->assertTrue( $ret );

		$this->assertEquals( $new_value, edd_get_payment_meta( $this->_payment_id, '_edd_payment_purchase_key' ) );

		$ret = edd_update_payment_meta( $this->_payment_id, '_edd_payment_user_email', 'test@test.com' );

		$this->assertTrue( $ret );

		$user_info = edd_get_payment_meta_user_info( $this->_payment_id );
		$this->assertEquals( 'test@test.com', edd_get_payment_meta( $this->_payment_id, '_edd_payment_user_email' ) );

	}

	public function test_get_payment_currency_code() {

		$this->assertEquals( 'USD', edd_get_payment_currency_code( $this->_payment_id ) );
		$this->assertEquals( 'US Dollars (&#36;)', edd_get_payment_currency( $this->_payment_id ) );

		$total1 = edd_currency_filter( edd_format_amount( edd_get_payment_amount( $this->_payment_id ) ), edd_get_payment_currency_code( $this->_payment_id ) );
		$total2 = edd_currency_filter( edd_format_amount( edd_get_payment_amount( $this->_payment_id ) ) );

		$this->assertEquals( '&#36;100.00', $total1 );
		$this->assertEquals( '&#36;100.00', $total2 );

	}

}
