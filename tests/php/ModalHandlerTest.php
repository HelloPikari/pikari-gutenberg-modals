<?php
/**
 * Tests for ModalHandler::validate_url().
 *
 * @package Pikari\Tests
 */

namespace Pikari\Tests;

use Brain\Monkey\Functions;
use Pikari\GutenbergModals\ModalHandler;

/**
 * @covers \Pikari\GutenbergModals\ModalHandler::validate_url
 */
class ModalHandlerTest extends TestCase {

	/**
	 * Stub the WordPress functions validate_url() reaches for.
	 *
	 * @param string $environment What wp_get_environment_type() should report.
	 */
	private function stub_wordpress( string $environment = 'production' ): void {
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url ) {
				return parse_url( $url );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'wp_get_environment_type' )->justReturn( $environment );
	}

	/**
	 * A public host must validate on a production site.
	 *
	 * This is the regression: is_local_url() asked filter_var() whether the host
	 * was a non-private IP, and filter_var() returns false for every string that
	 * is not an IP literal at all. Every hostname was therefore treated as a
	 * private address, and validate_url() fell through to returning true only on
	 * a local environment — so external URL modals silently did nothing on every
	 * production site.
	 *
	 * @dataProvider public_url_provider
	 *
	 * @param string $url A URL that should be allowed.
	 */
	public function test_public_hosts_validate_in_production( string $url ): void {
		$this->stub_wordpress( 'production' );

		$this->assertTrue(
			ModalHandler::validate_url( $url ),
			"Expected {$url} to validate on a production site."
		);
	}

	/**
	 * Public URLs that must be allowed.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function public_url_provider(): array {
		return array(
			'youtube embed'  => array( 'https://www.youtube.com/embed/aqz-KE-bpKQ' ),
			'vimeo player'   => array( 'https://player.vimeo.com/video/76979871' ),
			'bare domain'    => array( 'https://example.com' ),
			'public ip'      => array( 'https://8.8.8.8/' ),
			'host with port' => array( 'https://example.com:8443/embed' ),
		);
	}

	/**
	 * Genuinely local and private hosts stay blocked in production.
	 *
	 * @dataProvider local_url_provider
	 *
	 * @param string $url A URL that should be rejected.
	 */
	public function test_local_hosts_are_rejected_in_production( string $url ): void {
		$this->stub_wordpress( 'production' );

		$this->assertFalse(
			ModalHandler::validate_url( $url ),
			"Expected {$url} to be rejected on a production site."
		);
	}

	/**
	 * URLs that must not be allowed to reach the server-side fetcher.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function local_url_provider(): array {
		return array(
			'localhost'      => array( 'http://localhost/wp-admin/' ),
			'loopback ipv4'  => array( 'http://127.0.0.1/' ),
			'loopback ipv6'  => array( 'http://[::1]/' ),
			'private 10/8'   => array( 'http://10.0.0.5/' ),
			'private 192168' => array( 'http://192.168.1.1/' ),
			'link local'     => array( 'http://169.254.169.254/latest/meta-data/' ),
		);
	}

	/**
	 * Local hosts remain reachable while developing.
	 */
	public function test_local_hosts_are_allowed_in_a_local_environment(): void {
		$this->stub_wordpress( 'local' );

		$this->assertTrue( ModalHandler::validate_url( 'http://localhost/embed' ) );
	}

	/**
	 * Non-HTTP schemes are refused whatever the host.
	 */
	public function test_non_http_schemes_are_rejected(): void {
		$this->stub_wordpress( 'production' );

		$this->assertFalse( ModalHandler::validate_url( 'javascript:alert(1)' ) );
		$this->assertFalse( ModalHandler::validate_url( 'ftp://example.com/file' ) );
	}
}
