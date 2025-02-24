<?php

class TwigConfig {
    private static $instance = null;
    private $twig;
    private $imageService;
    private $wantlistImageService;
    private $urlService;

    private function __construct($config) {
        $loader = new \Twig\Loader\FilesystemLoader($config['paths']['templates']);
        
        $this->twig = new \Twig\Environment($loader, [
            'cache' => __DIR__ . '/../cache/twig',
            'auto_reload' => true,
            'debug' => $config['app']['environment'] === 'development',
        ]);

        // Initialize Services
        require_once __DIR__ . '/Services/ImageService.php';
        require_once __DIR__ . '/Services/WantlistImageService.php';
        require_once __DIR__ . '/Services/UrlService.php';
        $this->imageService = new ImageService($config);
        $this->wantlistImageService = new WantlistImageService($config);
        $this->urlService = new UrlService($config);

        // Add any global variables here
        $this->twig->addGlobal('app_name', $config['app']['name']);
        
        // Add custom functions
        $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($path) {
            return '/img/' . $path;
        }));

        // Release image functions
        $this->twig->addFunction(new \Twig\TwigFunction('cover_image', function ($url, $releaseId) {
            return $this->imageService->getCoverImage($url, $releaseId);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('release_image', function ($url, $releaseId, $index = 0) {
            return $this->imageService->getReleaseImage($url, $releaseId, $index);
        }));

        // Wantlist image functions
        $this->twig->addFunction(new \Twig\TwigFunction('wantlist_cover_image', function ($url, $wantlistItemId) {
            return $this->wantlistImageService->getWantlistCoverImage($url, $wantlistItemId);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('wantlist_image', function ($url, $wantlistItemId, $index = 0) {
            return $this->wantlistImageService->getWantlistImage($url, $wantlistItemId, $index);
        }));

        // URL helper functions
        $this->twig->addFunction(new \Twig\TwigFunction('release_url', (function ($id, $releaseInfo = null) {
            return $this->urlService->release($id, $releaseInfo);
        })->bindTo($this)));

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

        // Add slugify filter
        $this->twig->addFilter(new \Twig\TwigFilter('slugify', function($text) {
            // Convert to lowercase
            $text = strtolower($text);
            
            // Replace spaces and special characters with hyphens
            $text = preg_replace('/[^a-z0-9-]/', '-', $text);
            
            // Remove multiple consecutive hyphens
            $text = preg_replace('/-+/', '-', $text);
            
            // Remove leading and trailing hyphens
            $text = trim($text, '-');
            
            return $text;
        }));

        // Add extensions and functions
        if ($config['app']['environment'] === 'development') {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }
        
        // Add custom extensions
        require_once __DIR__ . '/TwigExtensions/AuthExtension.php';
        $this->twig->addExtension(new AuthExtension($config));
    }

    public static function getInstance($config) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getTwig() {
        return $this->twig;
    }

    public function render($template, $data = []) {
        // Add Font Awesome to all templates
        $data['fontawesome_css'] = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        
        return $this->twig->render($template, $data);
    }
} 