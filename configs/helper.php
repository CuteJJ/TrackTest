<?php
session_start();

class Helper {
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: /project/login.php"); // Adjust path if needed
            exit();
        }
    }
    
    public static function setFlash($message, $type = 'info') {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    public static function displayFlash() {
        if (isset($_SESSION['flash'])) {
            $msg = htmlspecialchars($_SESSION['flash']['message']);
            $type = $_SESSION['flash']['type'];
            
            // Clean SVG Icons
            $icon = match($type) {
                'success' => '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6L9 17l-5-5"/></svg>',
                'error'   => '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12"/></svg>',
                default   => ''
            };

            echo "<div id='flash-toast' class='flash-toast $type'>$icon <span>$msg</span></div>";
            unset($_SESSION['flash']);
        }
    }
}