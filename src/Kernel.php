<?php

namespace Limber;

use Limber\Exceptions\ApplicationException;
use Limber\Exceptions\DependencyResolutionException;
use Limber\Router\Route;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class Kernel implements RequestHandlerInterface
{
	/**
	 * Container instance.
	 *
	 * @var ContainerInterface|null
	 */
	protected $container;

	/**
	 * Kernel constructor.
	 *
	 * @param ContainerInterface|null $container
	 */
	public function __construct(?ContainerInterface $container)
	{
		$this->container = $container;
	}

	/**
	 * Pass request off to Route handler.
	 *
	 * @param ServerRequestInterface $request
	 * @return ResponseInterface
	 */
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		/**
		 * @var Route|null $route
		 */
		$route = $request->getAttribute(Route::class);

		if( empty($route) ){
			throw new ApplicationException("Route instance not found in ServerRequest instance attributes.");
		}

		$routeHandler = $route->getCallableAction();

		return \call_user_func_array(
			$routeHandler,
			$this->resolveDependencies(
				$this->getParametersForCallable($routeHandler),
				\array_merge([ServerRequestInterface::class => $request], $route->getPathParams($request->getUri()->getPath()))
			)
		);
	}

	/**
	 * Get the reflection parameters for a callable.
	 *
	 * @param callable $handler
	 * @throws ApplicationException
	 * @return array<ReflectionParameter>
	 */
	private function getParametersForCallable(callable $handler): array
	{
		if( \is_array($handler) ) {
			$reflector = new ReflectionMethod($handler[0], $handler[1]);
		}
		else {
			/**
			 * @psalm-suppress ArgumentTypeCoercion
			 */
			$reflector = new ReflectionFunction($handler);
		}

		return $reflector->getParameters();
	}

	/**
	 * Resolve an array of reflection parameters into an array of concrete instances/values.
	 *
	 * @param array<ReflectionParameter> $reflectionParameters
	 * @param array<string,mixed> $userArgs Array of user supplied arguments to be fed into dependecy resolution.
	 * @return array<mixed>
	 */
	private function resolveDependencies(array $reflectionParameters, array $userArgs = []): array
	{
		return \array_map(
			/**
			 * @return mixed
			 */
			function(ReflectionParameter $reflectionParameter) use ($userArgs) {

				$parameterName = $reflectionParameter->getName();
				$parameterType = $reflectionParameter->getType();

				// No type or the type is a primitive (built in)
				if( empty($parameterType) || $parameterType->isBuiltin() ){

					// Check in user supplied argument list first.
					if( \array_key_exists($parameterName, $userArgs) ){
						return $userArgs[$parameterName];
					}

					// Does parameter offer a default value?
					elseif( $reflectionParameter->isDefaultValueAvailable() ){
						return $reflectionParameter->getDefaultValue();
					}

					elseif( $reflectionParameter->isOptional() || $reflectionParameter->allowsNull() ){
						return null;
					}
				}

				// Parameter type is a class
				else {

					if( $this->container && $this->container->has($parameterType->getName()) ){
						return $this->container->get($parameterType->getName());
					}
					elseif( isset($userArgs[ServerRequestInterface::class]) &&
						\is_a($userArgs[ServerRequestInterface::class], $parameterType->getName()) ){
						return $userArgs[ServerRequestInterface::class];
					}
					else {

						/**
						 * @psalm-suppress ArgumentTypeCoercion
						 */
						return $this->make($parameterType->getName(), $userArgs);
					}
				}

				throw new DependencyResolutionException("Cannot resolve dependency for " . "<" . ($parameterType ? $parameterType->getName() : "mixed") . "> " . "\${$parameterName}.");
			},
			$reflectionParameters
		);
	}

	/**
	 * Make an instance of a class using autowiring with values from the container.
	 *
	 * @param class-string $className
	 * @param array<string,mixed> $userArgs
	 * @return object
	 */
	public function make(string $className, array $userArgs = []): object
	{
		if( $this->container &&
			$this->container->has($className) ){
			return $this->container->get($className);
		}

		$reflectionClass = new ReflectionClass($className);

		if( $reflectionClass->isInterface() || $reflectionClass->isAbstract() ){
			throw new DependencyResolutionException("Cannot make an instance of an Interface or Abstract.");
		}

		$constructor = $reflectionClass->getConstructor();

		if( empty($constructor) ){
			return $reflectionClass->newInstance();
		}

		$args = $this->resolveDependencies(
			$constructor->getParameters(),
			$userArgs
		);

		return $reflectionClass->newInstanceArgs($args);
	}
}