<?php

class Router {
    private $routes = [];
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function add($path, $callback) {
        // Convert URL parameters to regex pattern
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?<$1>[^/]+)', $path);
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        $this->routes[$pattern] = $callback;
        return $this;
    }

    public function match($requestUri) {
        // Parse the URL and remove query string
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'];
        
        // Clean the path
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }

        // Store query parameters
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            
            // Set query parameters as globals
            foreach ($queryParams as $key => $value) {
                $GLOBALS[$key] = $value;
            }
        }

        foreach ($this->routes as $pattern => $callback) {
            if (preg_match($pattern, $path, $matches)) {
                // Remove numeric keys from matches
                $params = array_filter($matches, function($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                
                return ['callback' => $callback, 'params' => $params, 'query' => $queryParams];
            }
        }
        
        return null;
    }

    public function dispatch($requestUri) {
        $match = $this->match($requestUri);
        
        if ($match) {
            $callback = $match['callback'];
            $params = array_values($match['params']); // Only use named parameters from URL
            
            if (is_array($callback)) {
                list($controller, $method) = $callback;
                $controller = new $controller($this->config);
                return call_user_func_array([$controller, $method], $params);
            }
            
            return call_user_func_array($callback, $params);
        }
        
        // Handle 404
        header("HTTP/1.0 404 Not Found");
        return ['error' => '404 Not Found'];
    }
} 