<?php

namespace Limber\Tests;

use Capsule\Response;
use Capsule\ResponseStatus;
use Capsule\ServerRequest;
use Limber\Middleware\CallableMiddleware;
use Limber\Middleware\RequestHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * @covers Limber\Middleware\CallableMiddleware
 * @covers Limber\Middleware\RequestHandler
 */
class CallableMiddlewareTest extends TestCase
{
	public function test_process()
	{
		$callableMiddleware = new CallableMiddleware(
			function(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {

				$response = $handler->handle($request);
				$response = $response->withHeader('X-Callable-Middleware', 'OK');
				return $response;

			}
		);

		$response = $callableMiddleware->process(
			new ServerRequest("get", "http://example.org"),
			new RequestHandler(
				function(ServerRequestInterface $request): ResponseInterface {
					return new Response(
						ResponseStatus::OK,
						"Ok"
					);
				}
			)
		);

		$this->assertEquals(
			'OK',
			$response->getHeader('X-Callable-Middleware')[0]
		);
	}
}