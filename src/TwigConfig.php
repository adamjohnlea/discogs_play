<?php

class TwigConfig {
    private static $instance = null;
    private $twig;
    private $imageService;

    private function __construct($config) {
        $loader = new \Twig\Loader\FilesystemLoader($config['paths']['templates']);
        
        $this->twig = new \Twig\Environment($loader, [
            'cache' => __DIR__ . '/../cache/twig',
            'auto_reload' => true,
            'debug' => $config['app']['environment'] === 'development',
        ]);

        // Initialize Services
        require_once __DIR__ . '/Services/ImageService.php';
        require_once __DIR__ . '/Services/UrlService.php';
        $this->imageService = new ImageService($config);
        $urlService = new UrlService($config);

        // Add any global variables here
        $this->twig->addGlobal('app_name', $config['app']['name']);
        
        // Add custom functions
        $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($path) {
            return '/img/' . $path;
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('cover_image', function ($url, $releaseId) {
            return $this->imageService->getCoverImage($url, $releaseId);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('release_image', function ($url, $releaseId, $index = 0) {
            return $this->imageService->getReleaseImage($url, $releaseId, $index);
        }));

        // Add URL generation functions
        $this->twig->addFunction(new \Twig\TwigFunction('release_url', function ($id) use ($urlService) {
            return $urlService->release($id);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('folder_url', function ($id, $name = null) use ($urlService) {
            return $urlService->folder($id, $name);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('sort_url', function ($field, $direction, $currentParams = []) use ($urlService) {
            return $urlService->sort($field, $direction, $currentParams);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('page_url', function ($number, $currentParams = []) use ($urlService) {
            return $urlService->page($number, $currentParams);
        }));

        // Add filters
        $this->twig->addFilter(new \Twig\TwigFilter('nl2br', function($text) {
            return nl2br($text);
        }));

        $this->twig->addFilter(new \Twig\TwigFilter('clean_artist_name', function($name) {
            // Remove the number in parentheses from artist names
            return preg_replace('/\s*\(\d+\)\s*$/', '', $name);
        }));

        $this->twig->addFilter(new \Twig\TwigFilter('clean_notes', function($text) {
            // Convert Discogs URLs to actual links
            $text = preg_replace('/\[url=([^\]]+)\]([^\[]+)\[\/url\]/', '<a href="$1">$2</a>', $text);
            
            // Remove Discogs reference IDs
            $text = preg_replace('/\[a\d+\]/', '', $text);
            $text = preg_replace('/\[l\d+\]/', '', $text);
            
            // Clean up multiple "appears courtesy of"
            $text = preg_replace('/(\s*appears courtesy of\s*)+/', ' appears courtesy of ', $text);
            
            // Clean up spaces around forward slashes
            $text = preg_replace('/\s*\/\s*/', '/', $text);
            
            // Fix spaces in HTML tags
            $text = preg_replace('/\s*<\s*\/\s*a\s*>/', '</a>', $text);
            
            // Clean up any resulting multiple spaces
            $text = preg_replace('/\s+/', ' ', $text);
            
            // Remove any empty brackets that might be left
            $text = str_replace('[]', '', $text);
            
            return trim($text);
        }));
    }

    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('Config must be provided for first instantiation');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function render($template, $data = []) {
        // Add Font Awesome to all templates
        $data['fontawesome_css'] = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        
        return $this->twig->render($template, $data);
    }
} 