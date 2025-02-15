<?php

namespace Limber\Tests;

use Capsule\ServerRequest;
use Nimbly\Limber\Router\Engines\DefaultRouter as Router;
use Nimbly\Limber\Router\Route;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Limber\Router\Engines\DefaultRouter
 * @covers Nimbly\Limber\Router\Route
 */
class IndexedRouterTest extends TestCase
{
    public function test_constructor(): void
    {
        $router = new Router([
            new Route("get", "books", "BooksController@all"),
            new Route("get", "books/{id}", "BooksController@get"),
            new Route("post", "books", "BooksController@create")
        ]);

        $this->assertNotEmpty(
            $router->resolve(new ServerRequest('get', 'books'))
        );

        $this->assertNotEmpty(
            $router->resolve(new ServerRequest('get', 'books/123'))
        );

        $this->assertNotEmpty(
            $router->resolve(new ServerRequest('post', 'books'))
        );
    }

    public function test_add_route(): void
    {
        $router = new Router;
        $route = $router->add(["get", "post"], "books/edit", "BooksController@edit");

        $this->assertEquals(["GET", "POST"], $route->getMethods());
        $this->assertEquals("books/edit", $route->getPath());
        $this->assertEquals("BooksController@edit", $route->getHandler());
    }

    public function test_resolve(): void
    {
        $router = new Router([
            new Route("get", "books/{id}", "BooksController@get"),
            new Route("post", "books", "BooksController@create"),

            new Route("get", "authors/{id}", "AuthorsController@get"),
            new Route("post", "authors", "AuthorsController@create")
		]);

		$request = new ServerRequest("get", "https://example.com/authors/1234");

        $route = $router->resolve(
            new ServerRequest("get", "https://example.com/authors/1234")
		);

		$this->assertNotNull($route);
        $this->assertEquals(["GET"], $route->getMethods());
        $this->assertEquals("AuthorsController@get", $route->getHandler());
    }

    public function test_get_methods(): void
    {
        $router = new Router([
            new Route("get", "books/{id}", "BooksController@get"),
            new Route("patch", "books/{id}", "BooksController@update"),
            new Route("delete", "books/{id}", "BooksController@delete"),
            new Route("post", "books", "BooksController@create"),

            new Route("get", "authors/{id}", "AuthorsController@get"),
            new Route("post", "authors", "AuthorsController@create")
        ]);

        $methods = $router->getMethods(
            new ServerRequest("get", "https://example.com/books/1234")
        );

        $this->assertEquals(["GET", "PATCH", "DELETE"], $methods);
    }

    public function test_resolving_method_that_is_not_indexed(): void
    {
        $router = new Router([
            new Route("post", "books", "BooksController@create"),
        ]);

        $route = $router->resolve(
            new ServerRequest("get", "https://example.com/authors/1234")
        );

        $this->assertNull($route);
    }
}