<?php

namespace Titan;

use Titan\Exception\DuplicateBindingException;
use Titan\Exception\DuplicateAliasException;
use Titan\Exception\UndefinedKeyException;

class Container
{
    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @var array
     */
    protected $instances = [];

    /**
     * @param string $class
     * @param bool $singleton
     * @return Container
     */
    public function bind(string $class, bool $singleton = false): Container
    {
        if ($this->isBinded($class)) {
            throw new DuplicateBindingException('Duplicate binding for ' . $class);
        }

        $this->bindings[$class] = [
            'value'     => $class,
            'singleton' => $singleton
        ];

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function store(string $key, $value)
    {
        if ($this->isBinded($key)) {
            throw new DuplicateBindingException('Duplicate bindings for ' . $key);
        }

        $this->bindings[$key] = [
            'value'     => $value,
            'singleton' => false
        ];
    }

    /**
     * @param string $class
     * @param string $value
     * @return Container
     */
    public function singleton(string $class): Container
    {
        return $this->bind($class, true);
    }

    /**
     * @param string $key
     * @param string|null $binding
     * @return void
     */
    public function alias(string $key, string $binding = null)
    {
        if (array_key_exists($key, $this->aliases)) {
            throw new DuplicateAliasException('Duplicate alias for ' . $key);
        }

        if ($binding === null) {
            $binding = end($this->bindings)['value'];
        }

        $this->aliases[$key] = $this->bindings[$binding];
    }

    /**
     * @param string $key
     * @return bool
     */
    public function isBinded(string $key): bool
    {
        return array_key_exists($key, $this->bindings);
    }

    /**
     * @param string $key
     * @return string
     */
    public function get(string $key): string
    {
        if (!$this->isBinded($key)) {
            throw new UndefinedKeyException('Undefined key for ' . $key);
        }

        return $this->getBinding($key)['value'];
    }

    /**
     * @param $key
     * @param array $args
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function resolve($key, array $args = [])
    {
        $class = $this->getBinding($key);

        if ($class === null) {
            $class = [
                'value' => $key,
                'singleton' => false
            ];
        }

        if ($this->isSingleton($key) && $this->isSingletonResolved($key)) {
            return $this->getSingletonInstance($key);
        }

        $object = $this->buildObject($class, $args);

        return $this->prepareObject($key, $object);
    }

    /**
     * @param array $class
     * @param array $args
     * @return object
     * @throws \ReflectionException
     */
    protected function buildObject(array $class, array $args = [])
    {
        $className = $class['value'];

        $reflector = new \ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class [$className] is not a resolvable dependency!");
        }

        if ($reflector->getConstructor() !== null) {
            $constructor    = $reflector->getConstructor();
            $params         = $constructor->getParameters();
            $args           = $this->buildDependencies($args, $params);

            return $reflector->newInstanceArgs($args);
        }

        return $reflector->newInstance();
    }

    /**
     * @param array $args
     * @param $params
     * @return mixed
     * @throws \ReflectionException
     */
    protected function buildDependencies(array $args, $params)
    {
        foreach ($params as $param) {
            if ($param->isOptional()) continue;
            if ($param->isArray()) continue;

            $class = $param->getClass();

            if ($class === null) continue;

            if (get_class($this) === $class->name) {
                array_push($args, $this);
                continue;
            }

            array_push($args, $this->resolve($class->name));
        }

        return $args;
    }

    /**
     * @param string $key
     * @param $object
     * @return mixed
     */
    protected function prepareObject(string $key, $object)
    {
        if ($this->isSingleton($key)) {
            $this->instances[$key] = $object;
        }

        return $object;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isSingleton(string $key): bool
    {
        $binding = $this->getBinding($key);

        if ($binding === null) {
            return false;
        }

        return $binding['singleton'];
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isSingletonResolved(string $key): bool
    {
        return array_key_exists($key, $this->instances);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    protected function getSingletonInstance(string $key)
    {
        return $this->isSingletonResolved($key) ? $this->instances[$key] : null;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    protected function getBinding($key)
    {
        if (!array_key_exists($key, $this->bindings)) {
            return array_key_exists($key, $this->aliases) ? $this->aliases[$key] : null;
        }
        
        return $this->bindings[$key];
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function offsetGet(string $key)
    {
        return $this->resolve($key);
    }

    /**
     * @param string $key
     * @param $value
     */
    public function offsetSet(string $key, $value)
    {
        return $this->bind($key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function offsetExists(string $key): bool
    {
        return array_key_exists($key, $this->bindings);
    }

    /**
     * @param string $key
     */
    public function offsetUnset(string $key)
    {
        unset($this->bindings[$key]);
    }
}