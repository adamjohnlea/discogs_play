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

        // Initialize ImageService
        require_once __DIR__ . '/Services/ImageService.php';
        $this->imageService = new ImageService($config);

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

        // Add filters
        $this->twig->addFilter(new \Twig\TwigFilter('nl2br', function($text) {
            return nl2br($text);
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