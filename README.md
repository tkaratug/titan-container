# Titan Container (Dependency Injection)

Titan-Container is a small Dependency Injection Container for PHP.


# Installation

Via composer
```$ composer require tkaratug/titan-container```

## Usage

```php
use Titan\Container;

// initialize the container
$container = Container::init();

// Without alias
$container->bind(Example\Foo\Bar::class);
$bar = $container->resolve(Example\Foo\Bar::class);

// With alias #Example-1
$container->bind(Example\Foo\Bar::class);
$container->alias('bar', Example\Foo\Bar::class);
$bar = $container->resolve('bar');

// With alias #Example-2
$container->bind(Example\Foo\Bar::class)->alis('bar');
$container->resolve('bar');

// Singleton without alias
$container->singleton(Example\Foo\Bar::class);

// Singleton with alias
$container->singleton(Example\Foo\Bar::class)->alias('bar');

// Store data
$container->store('key', 'data');
$data = $container->get('key');
```

## License

The MIT License (MIT). Please see [License File](https://github.com/tkaratug/titan-container/blob/master/LICENSE) for more information.