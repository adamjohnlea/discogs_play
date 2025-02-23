<?php

class HomeController {
    private $twig;
    private $authService;
    private $db;
    private $config;
    private $logger;
    
    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/LogService.php';
        $this->authService = new AuthService($config);
        $this->db = DatabaseService::getInstance($config)->getConnection();
        $this->logger = LogService::getInstance($config);
    }
    
    public function index() {
        if ($this->authService->isLoggedIn()) {
            try {
                // Check if user has set up their Discogs OAuth credentials
                $stmt = $this->db->prepare("
                    SELECT discogs_username, oauth_access_token 
                    FROM user_settings 
                    WHERE user_id = :user_id
                ");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$settings || empty($settings['discogs_username']) || empty($settings['oauth_access_token'])) {
                    // Redirect to settings if OAuth credentials are not set
                    header('Location: /settings');
                    exit;
                }
                
                // Redirect to collection view with default parameters
                header('Location: /collection?folder=all');
                exit;
            } catch (Exception $e) {
                $this->logger->error('Error in home page: ' . $e->getMessage());
                echo $this->twig->render('error.html.twig', [
                    'error' => 'An error occurred. Please try again.'
                ]);
                return;
            }
        }
        
        echo $this->twig->render('landing.html.twig', [
            'app_name' => 'Discogs Player'
        ]);
    }
} 