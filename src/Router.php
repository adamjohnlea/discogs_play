<?php

class Router {
    private $routes = [];
    private $config;
    private $folderService;
    private $twig;

    public function __construct($config) {
        $this->config = $config;
        require_once __DIR__ . '/Services/FolderService.php';
        $this->folderService = new FolderService($config);
        $this->twig = TwigConfig::getInstance($config);
    }

    public function add($path, $callback, $method = 'GET') {
        // Convert URL parameters to regex pattern
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?<$1>[^/]+)', $path);
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }
        
        $this->routes[$method][$pattern] = $callback;
        return $this;
    }

    public function match($requestUri) {
        // Get the request method
        $method = $_SERVER['REQUEST_METHOD'];
        
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

        // Check if we have routes for this method
        if (isset($this->routes[$method])) {
            // First try to match clean URLs
            foreach ($this->routes[$method] as $pattern => $callback) {
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

    public function dispatch($requestUri) {
        $match = $this->match($requestUri);
        
        if ($match) {
            $callback = $match['callback'];
            $params = array_values($match['params']); // Only use named parameters from URL
            
            if (is_array($callback)) {
                list($controller, $method) = $callback;
                
                // Load controller classes based on the controller name
                switch ($controller) {
                    case 'CollectionController':
                        require_once __DIR__ . '/Controllers/CollectionController.php';
                        break;
                    case 'ReleaseController':
                        require_once __DIR__ . '/Controllers/ReleaseController.php';
                        break;
                    case 'HomeController':
                        require_once __DIR__ . '/Controllers/HomeController.php';
                        break;
                    case 'AuthController':
                        require_once __DIR__ . '/Controllers/AuthController.php';
                        break;
                    case 'OAuthController':
                        require_once __DIR__ . '/Controllers/OAuthController.php';
                        break;
                    case 'ProfileController':
                        require_once __DIR__ . '/Controllers/ProfileController.php';
                        break;
                    case 'SettingsController':
                        require_once __DIR__ . '/Controllers/SettingsController.php';
                        break;
                }
                
                $controller = new $controller($this->twig, $this->config);
                return call_user_func_array([$controller, $method], $params);
            }
            
            return call_user_func_array($callback, $params);
        }
        
        // Handle 404
        header("HTTP/1.0 404 Not Found");
        return ['error' => '404 Not Found'];
    }

    private function standardizeParams($params, $queryParams) {
        $standardParams = [];
        
        // Convert folder slug to ID if present and preserve the slug
        if (isset($params['folder'])) {
            $standardParams['folder'] = $params['folder'];
            $standardParams['folder_id'] = $this->folderService->getFolderId($params['folder']);
        }
        
        // Map sort parameters
        if (isset($params['field'])) {
            $standardParams['sort_by'] = $params['field'];
        }
        if (isset($params['direction'])) {
            $standardParams['order'] = $params['direction'];
        }
        
        // Map page parameter
        if (isset($params['page'])) {
            $standardParams['page'] = $params['page'];
        }
        
        // Merge in query parameters, but URL parameters take precedence
        foreach ($queryParams as $key => $value) {
            if (!isset($standardParams[$key])) {
                $standardParams[$key] = $value;
            }
        }
        
        // Set defaults if not present
        if (!isset($standardParams['folder_id'])) {
            $standardParams['folder_id'] = '0';
            $standardParams['folder'] = 'all';
        }
        if (!isset($standardParams['sort_by'])) {
            $standardParams['sort_by'] = 'added';
        }
        if (!isset($standardParams['order'])) {
            $standardParams['order'] = 'desc';
        }
        if (!isset($standardParams['page'])) {
            $standardParams['page'] = '1';
        }
        if (!isset($standardParams['per_page'])) {
            $standardParams['per_page'] = '25';
        }
        
        return $standardParams;
    }

    private function determineLegacyCallback($queryParams) {
        // Default to collection view
        return ['ReleaseController', 'showCollection'];
    }
} 