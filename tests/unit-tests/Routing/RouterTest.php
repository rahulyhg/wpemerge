<?php

namespace WPEmergeTests\Routing;

use ArrayAccess;
use Exception;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use WPEmerge\Exceptions\ErrorHandlerInterface;
use WPEmerge\Facades\Framework;
use WPEmerge\Middleware\MiddlewareInterface;
use WPEmerge\Requests\Request;
use WPEmerge\Routing\Router;
use WPEmerge\Routing\RouteInterface;
use WPEmerge\Support\Facade;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \WPEmerge\Routing\Router
 */
class RouterTest extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		$this->error_handler = Mockery::mock( ErrorHandlerInterface::class )->shouldIgnoreMissing();
		$this->subject = new Router( Mockery::mock( Request::class ), [], [], 0, $this->error_handler );
	}

	public function tearDown() {
		parent::tearDown();
		Mockery::close();

		Facade::clearResolvedInstance( WPEMERGE_RESPONSE_KEY );
		Facade::clearResolvedInstance( WPEMERGE_FRAMEWORK_KEY );

		unset( $this->error_handler );
		unset( $this->subject );
	}

	/**
	 * @covers ::getMiddlewarePriority
	 */
	public function testGetMiddlewarePriority() {
		$default_middleware_priority = 100;
		$middleware1 = 'foo';
		$middleware1_priority = 99;
		$middleware2 = 'bar';
		$middleware3 = function() {};

		$subject = new Router( Mockery::mock( Request::class ), [], [
			$middleware1 => $middleware1_priority,
		], $default_middleware_priority, $this->error_handler );

		$this->assertEquals( $middleware1_priority, $subject->getMiddlewarePriority( $middleware1 ) );
		$this->assertEquals( $default_middleware_priority, $subject->getMiddlewarePriority( $middleware2 ) );
		$this->assertEquals( $default_middleware_priority, $subject->getMiddlewarePriority( $middleware3 ) );
	}

	/**
	 * @covers ::sortMiddleware
	 */
	public function testSortMiddleware() {
		$default_middleware_priority = 100;
		$middleware1 = 'foo';
		$middleware1_priority = 99;
		$middleware2 = 'bar';
		$middleware3 = function() {};

		$subject = new Router( Mockery::mock( Request::class ), [], [
			$middleware1 => $middleware1_priority,
		], $default_middleware_priority, $this->error_handler );

		$expected = $middleware1;
		$result = $subject->sortMiddleware( [$middleware3, $middleware2, $middleware1] );
		// We only check that the first middleware is the correct one because (PHP docs):
		// > If two members compare as equal, their relative order in the sorted array is undefined.
		$this->assertEquals( $expected, $result[0] );
	}

	/**
	 * @covers ::addRoute
	 */
	public function testAddRoute() {
		$route = Mockery::mock( RouteInterface::class );
		$middleware = [Mockery::mock( MiddlewareInterface::class )];
		$subject = new Router( Mockery::mock( Request::class ), $middleware, [], 0, $this->error_handler );

		$route->shouldReceive( 'addMiddleware' )
			->with( $middleware )
			->once();

		$this->assertSame( $route, $subject->addRoute( $route ) );
	}

	/**
	 * @covers ::getCurrentRoute
	 * @covers ::setCurrentRoute
	 */
	public function testSetCurrentRoute() {
		$expected = Mockery::mock( RouteInterface::class );

		$this->subject->setCurrentRoute( $expected );
		$this->assertSame( $expected, $this->subject->getCurrentRoute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_GlobalMiddleware_AddToRoutes() {
		$route = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$middleware = Mockery::mock( MiddlewareInterface::class );
		$middleware_array = [$middleware];

		$subject = new Router( Mockery::mock( Request::class ), $middleware_array, [], 0, $this->error_handler );

		$route->shouldReceive( 'addMiddleware' )
			->with( $middleware_array )
			->once();

		$subject->addRoute( $route );

		$subject->execute( '' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_Routes_CheckIfRoutesAreSatisfied() {
		$route1 = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$route2 = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();

		$route1->shouldReceive( 'isSatisfied' )
			->andReturn( false );

		$route2->shouldReceive( 'isSatisfied' )
			->andReturn( false );

		$this->subject->addRoute( $route1 );
		$this->subject->addRoute( $route2 );

		$this->subject->execute( '' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_SatisfiedRoute_StopCheckingCallHandleSetCurrent() {
		$route1 = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$route2 = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$response = Mockery::mock( ResponseInterface::class )->shouldIgnoreMissing();

		$route1->shouldReceive( 'isSatisfied' )
			->andReturn( true );

		$route1->shouldReceive( 'handle' )
			->andReturn( $response );

		$route2->shouldReceive( 'isSatisfied' )
			->never();

		$this->subject->addRoute( $route1 );
		$this->subject->addRoute( $route2 );

		$this->subject->execute( '' );

		$this->assertSame( $route1, $this->subject->getCurrentRoute() );
	}

	/**
	 * @covers ::execute
	 * @covers ::handle
	 */
	public function testExecute_Response() {
		$route = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$response = Mockery::mock( ResponseInterface::class )->shouldIgnoreMissing();
		$container = Mockery::mock( ArrayAccess::class );

		$route->shouldReceive( 'isSatisfied' )
			->andReturn( true );

		$route->shouldReceive( 'handle' )
			->andReturn( $response );

		$this->subject->addRoute( $route );

		Framework::shouldReceive( 'debugging' )
			->andReturn( false );

		Framework::shouldReceive( 'getContainer' )
			->andReturn( $container );

		$container->shouldReceive( 'offsetSet' )
			->with( WPEMERGE_RESPONSE_KEY, $response );

		$this->subject->execute( '' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::execute
	 * @covers ::handle
	 */
	public function testExecute_Response_ReturnsBuiltInView() {
		$expected = WPEMERGE_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'view.php';
		$route = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$response = Mockery::mock( ResponseInterface::class )->shouldIgnoreMissing();

		$route->shouldReceive( 'isSatisfied' )
			->andReturn( true );

		$route->shouldReceive( 'handle' )
			->andReturn( $response );

		$this->subject->addRoute( $route );

		$this->assertSame( $expected, $this->subject->execute( '' ) );
	}

	/**
	 * @covers ::execute
	 * @covers ::handle
	 * @expectedException \Exception
	 * @expectedExceptionMessage Exception handled
	 */
	public function testExecute_Exception_UseErrorHandler() {
		$route = Mockery::mock( RouteInterface::class )->shouldIgnoreMissing();
		$exception = new Exception();

		$route->shouldReceive( 'isSatisfied' )
			->andReturn( true );

		$route->shouldReceive( 'handle' )
			->andReturnUsing( function() use ( $exception ) {
				throw $exception;
			} );

		$this->error_handler->shouldReceive( 'getResponse' )
			->with( $exception )
			->andReturnUsing( function() {
				throw new Exception( 'Exception handled' );
			} );

		$this->subject->addRoute( $route );

		$this->subject->execute( '' );
	}

	/**
	 * @covers ::handleAll
	 */
	public function testHandleAll() {
		$expected = $this->subject->any( '*' );

		$result = $this->subject->handleAll();

		$this->assertEquals( $expected, $result );
	}
}
