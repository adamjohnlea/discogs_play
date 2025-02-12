<?php

class Router {
    private $routes = [];
    private $config;
    private $folderService;

    public function __construct($config) {
        $this->config = $config;
        require_once __DIR__ . '/Services/FolderService.php';
        $this->folderService = new FolderService($config);
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
        }

        // First try to match clean URLs
        foreach ($this->routes as $pattern => $callback) {
            if (preg_match($pattern, $path, $matches)) {
                // Remove numeric keys from matches
                $params = array_filter($matches, function($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                
                // For clean URLs, we'll still need some parameters in a standard format
                $standardParams = $this->standardizeParams($params, $queryParams);
                
                // Set standardized parameters as globals for backward compatibility
                foreach ($standardParams as $key => $value) {
                    $GLOBALS[$key] = $value;
                }
                
                return [
                    'callback' => $callback,
                    'params' => $params,
                    'query' => $standardParams
                ];
            }
        }

        // If no clean URL match, handle legacy query parameters
        if ($path === '/' && !empty($queryParams)) {
            // Set query parameters as globals for backward compatibility
            foreach ($queryParams as $key => $value) {
                $GLOBALS[$key] = $value;
            }
            
            // Determine which controller/action to use based on query params
            $callback = $this->determineLegacyCallback($queryParams);
            return [
                'callback' => $callback,
                'params' => [],
                'query' => $queryParams
            ];
        }
        
        return null;
    }

    private function standardizeParams($params, $queryParams) {
        $standard = [];
        
        // Convert clean URL parameters to standard format
        if (isset($params['id'])) {
            $standard['releaseid'] = $params['id'];
        }
        if (isset($params['folder'])) {
            $standard['folder_id'] = $this->folderService->getFolderId($params['folder']);
        }
        if (isset($params['page'])) {
            $standard['page'] = $params['page'];
        }
        if (isset($params['field'])) {
            $standard['sort_by'] = $params['field'];
        }
        if (isset($params['direction'])) {
            $standard['order'] = $params['direction'];
        }
        
        // Set defaults if not provided
        if (!isset($standard['page'])) {
            $standard['page'] = '1';
        }
        if (!isset($standard['sort_by'])) {
            $standard['sort_by'] = 'added';
        }
        if (!isset($standard['order'])) {
            $standard['order'] = 'desc';
        }
        if (!isset($standard['folder_id'])) {
            $standard['folder_id'] = '0';
        }
        
        // Merge with any existing query parameters
        return array_merge($standard, $queryParams);
    }

    private function determineLegacyCallback($params) {
        // Default to collection view
        $callback = ['ReleaseController', 'showCollection'];
        
        // If we have a release ID, show the release
        if (isset($params['releaseid'])) {
            $callback = ['ReleaseController', 'showRelease'];
        }
        
        return $callback;
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