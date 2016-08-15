<?php namespace Febalist\LaravelRouter;

use Route;

class Router
{
    protected static $routes = [];

    public static function get($route, $uri = null)
    {
        static::route('get', $route, $uri);
    }

    public static function post($route, $uri = null)
    {
        static::route('post', $route, $uri);
    }

    public static function any($route, $uri = null)
    {
        static::get($route, $uri);
        static::post($route, $uri);
    }

    public static function put($route, $uri = null)
    {
        static::route('put', $route, $uri);
        static::route('patch', $route, $uri);
    }

    public static function patch($route, $uri = null)
    {
        static::put($route, $uri);
    }

    public static function delete($route, $uri = null)
    {
        static::route('delete', $route, $uri);
    }

    public static function all($route, $uri = null)
    {
        static::get($route, $uri);
        static::post($route, $uri);
        static::put($route, $uri);
        static::delete($route, $uri);
    }

    public static function group($route, $callback, $middleware = null)
    {
        static::middleware($middleware, function () use ($route, $callback) {
            static::$routes[] = $route;
            call_user_func($callback);
            array_pop(static::$routes);
        });
    }

    public static function middleware($middleware, $callback)
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        if ($middleware) {
            Route::group([
                'middleware' => $middleware,
            ], $callback);
        } else {
            call_user_func($callback);
        }
    }

    public static function rest($origin, $resource = null, $group = null)
    {
        $origin = explode('.', $origin);
        $origin = implode('$.', $origin);
        static::group($origin, function () use ($group) {
            static::get('index');
            static::any('create');
            if ($group) {
                call_user_func($group);
            }
        });
        static::group($origin.'$', function () use ($resource) {
            static::get('show');
            static::any('edit');
            static::get('delete');
            if ($resource) {
                call_user_func($resource);
            }
        });
    }

    public static function resource($name, $options = [])
    {
        $controller = str_replace('.', '_', $name);
        $controller = camel_case($controller);
        $controller = ucfirst($controller);
        $model      = explode('.', $name);
        $model      = last($model);
        Route::model($model, 'App\\'.ucfirst($model));
        Route::resource($name, $controller.'Controller', $options);
    }

    protected static function route($method, $route, $uri = null)
    {
        $route  = static::getRoute($route);
        $uri    = $uri ?: static::getUri($route);
        $action = static::getAction($route, $method);
        $name   = static::getName($route);
        $method = strtoupper($method);
        Route::$method($uri, [
            'as'   => $name,
            'uses' => $action,
        ]);
    }

    protected static function getName($route)
    {
        $name = strtolower($route);
        $name = str_replace('?', '', $name);
        $name = str_replace('$', '', $name);
        return $name;
    }

    protected static function getUri($route)
    {
        $route = strtolower($route);
        $parts = explode('.', $route);
        if (last($parts) == 'index' || last($parts) == 'show') {
            array_splice($parts, -1);
        }
        foreach ($parts as &$part) {
            if (str_contains($part, ['$', '?'])) {
                $optional = str_contains($part, '?') ? '?' : '';
                if (starts_with($part, ['$', '?'])) {
                    $part = substr($part, 1);
                    $part = '{'.$part.$optional.'}';
                } else {
                    $part = substr($part, 0, -1);
                    $part = $part.'/{'.$part.$optional.'}';
                }
            }
        }
        $uri = implode('/', $parts);
        return $uri;
    }

    protected static function getRoute($route = null)
    {
        $route = array_merge(static::$routes, [$route]);
        $route = array_filter($route);
        return implode('.', $route);
    }

    protected static function getAction($route, $method)
    {
        $route        = preg_replace('/([A-Z][a-z]+)\./', '$1\\', $route);
        $namespaces   = explode('\\', $route);
        $route        = array_pop($namespaces);
        $name         = static::getName($route);
        $name         = explode('.', $name);
        $action       = array_splice($name, -1);
        $action       = head($action);
        $action       = $method.ucfirst($action);
        $controller   = implode('_', $name);
        $controller   = $controller ? camel_case($controller) : 'index';
        $controller   = ucfirst($controller);
        $namespaces[] = $controller;
        $controller   = implode('\\', $namespaces);
        return $controller.'Controller@'.$action;
    }
}
