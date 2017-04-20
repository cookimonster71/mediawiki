<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group BagOStuff
 */
class HashBagOStuffTest extends PHPUnit_Framework_TestCase {

	/**
	 * @covers HashBagOStuff::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			HashBagOStuff::class,
			new HashBagOStuff()
		);
	}

	/**
	 * @covers HashBagOStuff::__construct
	 * @expectedException InvalidArgumentException
	 */
	public function testConstructBadZero() {
		$cache = new HashBagOStuff( [ 'maxKeys' => 0 ] );
	}

	/**
	 * @covers HashBagOStuff::__construct
	 * @expectedException InvalidArgumentException
	 */
	public function testConstructBadNeg() {
		$cache = new HashBagOStuff( [ 'maxKeys' => -1 ] );
	}

	/**
	 * @covers HashBagOStuff::__construct
	 * @expectedException InvalidArgumentException
	 */
	public function testConstructBadType() {
		$cache = new HashBagOStuff( [ 'maxKeys' => 'x' ] );
	}

	/**
	 * @covers HashBagOStuff::delete
	 */
	public function testDelete() {
		$cache = new HashBagOStuff();
		for ( $i = 0; $i < 10; $i++ ) {
			$cache->set( "key$i", 1 );
			$this->assertEquals( 1, $cache->get( "key$i" ) );
			$cache->delete( "key$i" );
			$this->assertEquals( false, $cache->get( "key$i" ) );
		}
	}

	/**
	 * @covers HashBagOStuff::clear
	 */
	public function testClear() {
		$cache = new HashBagOStuff();
		for ( $i = 0; $i < 10; $i++ ) {
			$cache->set( "key$i", 1 );
			$this->assertEquals( 1, $cache->get( "key$i" ) );
		}
		$cache->clear();
		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertEquals( false, $cache->get( "key$i" ) );
		}
	}

	/**
	 * @covers HashBagOStuff::doGet
	 * @covers HashBagOStuff::expire
	 */
	public function testExpire() {
		$cache = new HashBagOStuff();
		$cacheInternal = TestingAccessWrapper::newFromObject( $cache );
		$cache->set( 'foo', 1 );
		$cache->set( 'bar', 1, 10 );
		$cache->set( 'baz', 1, -10 );

		$this->assertEquals( 0, $cacheInternal->bag['foo'][$cache::KEY_EXP], 'Indefinite' );
		// 2 seconds tolerance
		$this->assertEquals( time() + 10, $cacheInternal->bag['bar'][$cache::KEY_EXP], 'Future', 2 );
		$this->assertEquals( time() - 10, $cacheInternal->bag['baz'][$cache::KEY_EXP], 'Past', 2 );

		$this->assertEquals( 1, $cache->get( 'bar' ), 'Key not expired' );
		$this->assertEquals( false, $cache->get( 'baz' ), 'Key expired' );
	}

	/**
	 * Ensure maxKeys eviction prefers keeping new keys.
	 *
	 * @covers HashBagOStuff::set
	 */
	public function testEvictionAdd() {
		$cache = new HashBagOStuff( [ 'maxKeys' => 10 ] );
		for ( $i = 0; $i < 10; $i++ ) {
			$cache->set( "key$i", 1 );
			$this->assertEquals( 1, $cache->get( "key$i" ) );
		}
		for ( $i = 10; $i < 20; $i++ ) {
			$cache->set( "key$i", 1 );
			$this->assertEquals( 1, $cache->get( "key$i" ) );
			$this->assertEquals( false, $cache->get( "key" . $i - 10 ) );
		}
	}

	/**
	 * Ensure maxKeys eviction prefers recently set keys
	 * even if the keys pre-exist.
	 *
	 * @covers HashBagOStuff::set
	 */
	public function testEvictionSet() {
		$cache = new HashBagOStuff( [ 'maxKeys' => 3 ] );

		foreach ( [ 'foo', 'bar', 'baz' ] as $key ) {
			$cache->set( $key, 1 );
		}

		// Set existing key
		$cache->set( 'foo', 1 );

		// Add a 4th key (beyond the allowed maximum)
		$cache->set( 'quux', 1 );

		// Foo's life should have been extended over Bar
		foreach ( [ 'foo', 'baz', 'quux' ] as $key ) {
			$this->assertEquals( 1, $cache->get( $key ), "Kept $key" );
		}
		$this->assertEquals( false, $cache->get( 'bar' ), 'Evicted bar' );
	}

	/**
	 * Ensure maxKeys eviction prefers recently retrieved keys (LRU).
	 *
	 * @covers HashBagOStuff::doGet
	 * @covers HashBagOStuff::hasKey
	 */
	public function testEvictionGet() {
		$cache = new HashBagOStuff( [ 'maxKeys' => 3 ] );

		foreach ( [ 'foo', 'bar', 'baz' ] as $key ) {
			$cache->set( $key, 1 );
		}

		// Get existing key
		$cache->get( 'foo', 1 );

		// Add a 4th key (beyond the allowed maximum)
		$cache->set( 'quux', 1 );

		// Foo's life should have been extended over Bar
		foreach ( [ 'foo', 'baz', 'quux' ] as $key ) {
			$this->assertEquals( 1, $cache->get( $key ), "Kept $key" );
		}
		$this->assertEquals( false, $cache->get( 'bar' ), 'Evicted bar' );
	}
}
