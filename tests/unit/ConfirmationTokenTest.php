<?php
/**
 * Double opt-in links: valid for 7 days from when the link was last mailed.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ConfirmationTokenTest extends TestCase {

	private const NOW   = '2026-11-16 12:00:00';
	private const TOKEN = 'tttttttttttttttttttttttttttttttt'; // Fake; a random-looking value trips the secret scan.

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'current_time' )->justReturn( self::NOW );
		Functions\when( 'is_email' )->alias(
			function ( $email ) {
				return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
			}
		);
		$GLOBALS['wpdb'] = new Task_Table_Fake_Wpdb();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private static function ago( $seconds ) {
		return gmdate( 'Y-m-d H:i:s', strtotime( self::NOW ) - $seconds );
	}

	public function cases() {
		$week = 7 * 86400;
		return array(
			'mailed just under 7 days ago'                => array( self::ago( 40 * 86400 ), self::ago( $week - 1 ), 1, 'confirmed' ),
			'mailed just over 7 days ago'                 => array( self::ago( 40 * 86400 ), self::ago( $week + 1 ), 1, 'invalid' ),
			'old signup, link re-sent an hour ago'        => array( self::ago( 30 * 86400 ), self::ago( 3600 ), 1, 'confirmed' ),
			'database write fails: user is not told "ok"' => array( self::ago( 3600 ), self::ago( 3600 ), false, 'error' ),
		);
	}

	/**
	 * @dataProvider cases
	 */
	public function test_link_validity( $created_at, $updated_at, $update_result, $expected ) {
		$GLOBALS['wpdb']->row           = (object) array(
			'id'                 => 5,
			'confirmed'          => 0,
			'confirmation_token' => self::TOKEN,
			'created_at'         => $created_at,
			'updated_at'         => $updated_at,
		);
		$GLOBALS['wpdb']->update_result = $update_result;

		$this->assertSame( $expected, task_table_confirm_subscription_token( 'a@example.test', self::TOKEN ) );
	}
}
