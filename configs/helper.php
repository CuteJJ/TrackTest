<?php
session_start();

class Helper {
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }

    public static function requireRole($allowedRoles) {
        // 1. Ensure they are logged in first
        self::requireLogin(); 
        
        $userRole = strtolower($_SESSION['role'] ?? '');
        
        // 2. Check if they have the required role (supports single string or array of roles)
        if (is_array($allowedRoles)) {
            $allowed = array_map('strtolower', $allowedRoles);
            if (!in_array($userRole, $allowed)) {
                self::denyAccess();
            }
        } else {
            if ($userRole !== strtolower($allowedRoles)) {
                self::denyAccess();
            }
        }
    }

    private static function denyAccess() {
        self::setFlash("Access Denied: You do not have permission to view that page.", "error");
        header("Location: index.php");
        exit();
    }
    // ----------------------------------------------
    
    public static function displayLoader() {
        echo '
        <div id="global-loader-overlay">
            <div class="typing-indicator">
                <div class="typing-circle"></div>
                <div class="typing-circle"></div>
                <div class="typing-circle"></div>
                <div class="typing-shadow"></div>
                <div class="typing-shadow"></div>
                <div class="typing-shadow"></div>
            </div>
        </div>';
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
                'success' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.3"><path d="m14.25 8.75c-.5 2.5-2.3849 4.85363-5.03069 5.37991-2.64578.5263-5.33066-.7044-6.65903-3.0523-1.32837-2.34784-1.00043-5.28307.81336-7.27989 1.81379-1.99683 4.87636-2.54771 7.37636-1.54771"/><polyline points="5.75 7.75 8.25 10.25 14.25 3.75"/></g></svg>',
                'error'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16"><path fill="currentColor" d="M8 1C4.14 1 1 4.14 1 8s3.14 7 7 7s7-3.14 7-7s-3.14-7-7-7m0 13c-3.309 0-6-2.691-6-6s2.691-6 6-6s6 2.691 6 6s-2.691 6-6 6m2.854-8.146L8.708 8l2.146 2.146a.5.5 0 0 1-.708.707L8 8.707l-2.146 2.146a.5.5 0 0 1-.708 0a.5.5 0 0 1 0-.707L7.292 8L5.146 5.854a.5.5 0 0 1 .707-.707l2.146 2.146l2.146-2.146a.5.5 0 0 1 .707.707z" stroke-width="0.2" stroke="currentColor"></path></svg>',
                default   => ''
            };

            // Modern SaaS Toast Structure
            echo "
            <div class='flash-toast $type'>
                <div class='toast-icon'>$icon</div>
                <div class='toast-content'>$msg</div>
                <button class='toast-close' aria-label='Close'>
                    <span class='material-symbols-outlined' style='font-size: 16px;'>close</span>
                </button>
                <div class='toast-progress'></div>
            </div>";
            
            unset($_SESSION['flash']);
        }
    }

    public static function renderPrinterImage($path, $modelName, $iconSize = 24) {
        if (!empty($path) && (str_contains($path, '/') || str_contains($path, '.'))) {
            $safePath = htmlspecialchars($path);
            
            // Auto-adjust path if we are inside the /admin/ subdirectory
            $prefix = (str_contains($_SERVER['SCRIPT_NAME'], '/admin/')) ? '../' : '';
            if (str_starts_with($safePath, 'http')) $prefix = ''; // External URL
            
            return "<img src='{$prefix}{$safePath}?v=" . time() . "' style='width:100%; height:100%; object-fit:cover; display:block;'>";
        }
        
        $n = strtolower($modelName);
        $icon = 'print';
        if (str_contains($n, 'flare')) $icon = 'local_fire_department';
        if (str_contains($n, 'ray'))   $icon = 'bolt';
        if (str_contains($n, 'mfp'))   $icon = 'content_copy';
        
        if (!empty($path) && !str_contains($path, '/') && !str_contains($path, '.')) {
            $icon = htmlspecialchars($path);
        }
        
        return "<span class='material-symbols-outlined' style='font-size: {$iconSize}px;'>{$icon}</span>";
    }
}