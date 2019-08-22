<?php

namespace Limber\Router;

use Limber\Exceptions\MethodNotAllowedHttpException;
use Limber\Exceptions\NotFoundHttpException;
use Psr\Http\Message\ServerRequestInterface;

class TreeRouter extends RouterAbstract
{
    /**
     * Set of indexed RouteBranches
     *
     * @var RouteBranch
     */
    protected $tree;

    /**
     * @inheritDoc
     */
    public function __construct(array $routes = null)
    {
        $this->tree = new RouteBranch;

        if( $routes ){
            foreach( $routes as $route ){
                $this->indexRoute($route);
            }
        }
	}

	/**
	 * @inheritDoc
	 */
	public function getRoutes(): array
	{
		return $this->getRoutesFromBranch($this->tree);
	}

	/**
	 * Recursive method to traverse tree and flatten out routes.
	 *
	 * @param RouteBranch $branch
	 * @return array
	 */
	private function getRoutesFromBranch(RouteBranch $branch): array
	{
		$routes = \array_values($branch->getRoutes());

		foreach( $branch->getBranches() as $branch ){
			$routes = \array_merge($routes, $this->getRoutesFromBranch($branch));
		}

		return $routes;
	}

    /**
     * Index the route.
     *
     * @param Route $route
     * @return void
     */
    protected function indexRoute(Route $route): void
    {
        $currentBranch = $this->tree;
        $patternParts = $route->getPatternParts();

        foreach( $patternParts as $pattern ){
            $currentBranch = $currentBranch->next($pattern);
        }

        $currentBranch->addRoute($route);
    }

    /**
     * @inheritDoc
     */
    public function add(array $methods, string $uri, $target): Route
    {
        // Create new Route instance
        $route = new Route($methods, $uri, $target, $this->config);

        // Index the route
        $this->indexRoute($route);

        return $route;
    }

    /**
     * @inheritDoc
     */
    public function resolve(ServerRequestInterface $request): ?Route
    {
        // Break the request path apart
        $pathParts = \explode("/", \trim($request->getUri()->getPath(), "/"));

        // Set the starting node.
        $branch = $this->tree;

        foreach( $pathParts as $part ){
            if( ($branch = $branch->findBranch($part)) === null ){
                throw new NotFoundHttpException("Route not found.");
            }
        }

        if( ($route = $branch->getRouteForMethod($request->getMethod())) === null ){
            throw new MethodNotAllowedHttpException;
        }

        // Now match against the remaining criteria.
        if( $route->matchScheme($request->getUri()->getScheme()) &&
            $route->matchHostname($request->getUri()->getHost()) ){

            return $route;
        }

        throw new NotFoundHttpException("Route not found.");
    }

    /**
     * @inheritDoc
     */
    public function getMethodsForUri(ServerRequestInterface $request): array
    {
        // Break the request path apart
        $pathParts = \explode("/", \trim($request->getUri()->getPath(), "/"));

        // Set the starting node.
        $branch = $this->tree;

        foreach( $pathParts as $part ){
            if( ($branch = $branch->findBranch($part)) === null ){
                return [];
            }
        }

        return $branch->getMethods();
    }
}