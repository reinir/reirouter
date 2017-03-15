ReiRouter
==========

Version 1.0.0 - 14 Mar 2017

by Reinir Puradinata

Introduction
------------

ReiRouter is a fast non-regex request router with a constant performance.
In other words, the worst-case time complexity of its route matching algorithm is O(1),
unlike regex-based routers that have a worst-case time complexity of O(N), where N is the number of routes.

On top of that, its unknown routes resolution is exceptionally fast, which makes it useful as a [Denial of Service shield](#dos-shield).

Number of routes | Known routes | Unknown routes
---------------- | ------------ | --------------
10 routes | 55,691 req/s | 367,564 req/s
100 routes | 54,885 req/s | 368,860 req/s
1000 routes | 55,947 req/s | 367,872 req/s

As a comparison, the following is FastRoute performing the same benchmark on the same machine.
In fairness, FastRoute is actually faster in 1 case (see top left), but slower in all others.

Number of routes | Known routes | Unknown routes
---------------- | ------------ | --------------
10 routes | 66,158 req/s | 129,132 req/s
100 routes | 27,823 req/s | 21,248 req/s
1000 routes | 4,144 req/s | 2,231 req/s

Both are measured on a single core of an i3 with PHP 5.5.
Each route has 9 placeholders with random prefix and suffix.

Features
--------

* Scalable route matching algorithm (no performance degradation with more routes)
* Exceptionally fast unknown routes resolution
* No route-shadowing (see [Automatic route priority](#automatic-route-priority) below)
* Can be used as Denial of Service shield (see [DoS shield](#dos-shield) below)

Upcoming features
-----------------

* Optional patterns

Getting started
---------------

Basic usage:

```php
class Hello{
    function GET($params){
        echo 'Hello, '.$params['name'].'!';
    }
}

$router = new ReiRouter;
$router->add('/hello/:name', 'Hello');
$router->add('/hello/world', 'Hello', 'GET', ['name'=>'World']);
$router->execute();
```

The 1st parameter defines the route pattern.
Supported patterns are:
* Literal such as `/items`
* Placeholder such as `/:itemId`
* Catch-all such as `/*all` if named, or simply `/*` if unnamed

```php
$router->add('/items/:itemId/*', 'ItemHandler');
```

The 2nd parameter defines the request handler, which has to be a class name if the route resolution uses `execute()`, like this example does.
For any other kind of request handler, such as an anonymous function, use `find()`.

The optional 3rd parameter defines the allowed HTTP methods for the route, which can be a string, an array of strings, or null to activate automatic method routing mode.

```php
$router->add('/hello/:name', 'Hello', 'GET');
$router->add('/hello/:name', 'Hello', ['GET','POST']);
$router->add('/hello/:name', 'Hello', null);
```

The optional 4th parameter defines the default parameters.

```php
$router->add('/hello/world', 'Hello', 'GET', ['name'=>'World']);
```

Automatic route priority
------------------------

Automatic route priority ensures that routes can be defined in any order without affecting route resolution.

This is a rare feature that effectively eliminates route-shadowing.
This feature is impossible for most request routers simply because they evaluate routes one by one in order of definition,
which is actually the cause of route-shadowing.

As an example, this:

```php
$router->add('/hello/*', 'Handler1');
$router->add('/hello/:name', 'Handler2');
$router->add('/hello/world', 'Handler3');
```

Is the same as this:

```php
$router->add('/hello/world', 'Handler3');
$router->add('/hello/:name', 'Handler2');
$router->add('/hello/*', 'Handler1');
```

Because route resolution does not depend on the order of definition, you can order and group your routes based on any criteria you desire.

DoS shield
----------

Regex-based request routers are usually slow when it comes to resolving unknown routes because all defined routes have to be evaluated before deciding that the route for a particular request doesn't exist.
This fact can be exploited to cause denial of service by sending a large number of requests to random routes.
This is exacerbated if the router supports optional patterns and expands them to individual routes.

In short, with a regex-based router, the more routes an application has, the more vulnerable it is to denial of service attacks.

ReiRouter can be used as a DoS shield to protect such application.
All you have to do is define all routes with ReiRouter without request handlers to activate passthrough mode.

Example:

```php
//to use ReiRouter as a DoS shield,
//define your routes without request handlers to activate passthrough mode

(new ReiRouter)
    ->add('/users')
    ->add('/user/:id')
    ->add('/articles/:id')
    ->add('/articles/:id/:title')
    ->execute();

//unknown routes will never reach this point, they will get "404 Not Found" immediately and efficiently
//known routes will continue here

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/users', 'get_all_users_handler');
    // {id} must be a number (\d+)
    $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
    // The /{title} suffix is optional
    $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
});
```

DoS shield works by combining 2 features of ReiRouter: the passthrough mode and the exceptionally fast unknown routes resolution.
