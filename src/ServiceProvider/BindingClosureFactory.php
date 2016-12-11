<?php
declare(strict_types = 1);
namespace Bigcommerce\Injector\ServiceProvider;

use Bigcommerce\Injector\InjectorInterface;
use Pimple\Container;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;

/**
 * Factory to create closures for ServiceProvider bindings allowing JIT injection and Lazy Bindings through Proxies.
 */
class BindingClosureFactory
{
    /**
     * @var LazyLoadingValueHolderFactory
     */
    private $proxyFactory;

    /**
     * @var InjectorInterface
     */
    private $injector;

    /**
     * BindingClosureFactory constructor.
     * @param LazyLoadingValueHolderFactory $proxyFactory
     * @param InjectorInterface $injector
     */
    public function __construct(LazyLoadingValueHolderFactory $proxyFactory, InjectorInterface $injector)
    {
        $this->proxyFactory = $proxyFactory;
        $this->injector = $injector;
    }

    /**
     * Generate a closure that will use the Injector to auto-wire a service definition.
     *
     * @param string $className FQCN of a class to auto-wire bind.
     * @param callable|null $parameterFactory Callable to generate parameters to inject to the service. Will receive
     * the IoC container as its first parameter.
     * @return \Closure
     */
    public function createAutoWireClosure($className, callable $parameterFactory = null)
    {
        return function (Container $app) use ($className, $parameterFactory) {
            $parameters = $parameterFactory ? $parameterFactory($app) : [];
            return $this->injector->create($className, $parameters);
        };
    }

    /**
     * Automatically bind and wire a lazy service using the Injector. Accepts an optional callable to build parameter
     * overrides. Lazy services will return Proxies when retrieved, which will only fetch the underlying real service
     * when first used. See http://ocramius.github.io/presentations/proxy-pattern-in-php/
     *
     * HINT: You can use this binding type for expensive services that you *might* need but don't want to instantiate
     * eagerly.
     *
     * @param string $className FQCN of a class to auto-wire bind.
     * @param callable|null $parameterFactory Callable to generate parameters to inject to the service. Will receive
     * the IoC container as its first parameter.
     * @return callable
     */
    public function createAutoWireProxyClosure($className, callable $parameterFactory = null)
    {
        return function (Container $app) use ($className, $parameterFactory) {
            $serviceFactory = $this->createAutoWireClosure($className, $parameterFactory);
            return $this->createProxy($className, $serviceFactory, $app);
        };
    }

    /**
     * Create a project object for the specified ClassName bound to the given ServiceFactory method.
     * @param string $className
     * @param callable $serviceFactory
     * @param Container $app
     * @return \ProxyManager\Proxy\VirtualProxyInterface
     */
    private function createProxy($className, callable $serviceFactory, Container $app)
    {
        return $this->proxyFactory->createProxy(
            $className,
            function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use (
                $className,
                $serviceFactory,
                $app
            ) {
                $wrappedObject = $serviceFactory($app);
                $initializer = null;
                return true;
            }
        );
    }
}
