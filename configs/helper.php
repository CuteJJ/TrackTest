<?php
$lifetime = 86400; // 24 hours

session_set_cookie_params([
    'lifetime' => 0, // 0 = Session cookie dies when browser closes (unless Remember Me is active)
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.gc_maxlifetime', $lifetime);
session_start();

class Helper
{
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
    
    public static function requireLogin()
    {
        // 1. If session is dead, check for a valid Remember Me cookie
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['rmb_token'])) {
            global $pdo; 
            if (!$pdo) require_once __DIR__ . '/db.php';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
            $stmt->execute([$_COOKIE['rmb_token']]);
            $user = $stmt->fetch();

            if ($user && (!isset($user['status']) || $user['status'] !== 'blocked')) {
                // Restore the session silently in the background
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['pfp_path'] = !empty($user['pfp_path']) ? $user['pfp_path'] : 'imgs/default_pfp.svg';
                session_regenerate_id(true);
                return; // Let them through!
            }
        }

        // 2. If no session AND no valid cookie, kick to login
        if (!isset($_SESSION['user_id'])) {
            self::setFlash("Please sign in to access this workspace.", "error");
            $prefix = (str_contains($_SERVER['SCRIPT_NAME'], '/admin/')) ? '../' : '';
            header("Location: {$prefix}login.php");
            exit();
        }
    }

    public static function requireRole($allowedRoles)
    {
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

    private static function denyAccess()
    {
        self::setFlash("Access Denied: You do not have permission to view that page.", "error");
        header("Location: index.php");
        exit();
    }
    // ----------------------------------------------

    public static function displayLoader()
    {
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

    public static function setFlash($message, $type = 'info')
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    public static function displayFlash()
    {
        if (isset($_SESSION['flash'])) {
            $msg = htmlspecialchars($_SESSION['flash']['message']);
            $type = $_SESSION['flash']['type'];

            // Clean SVG Icons
            $icon = match ($type) {
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

    /**
     * Renders a sortable table header with an arrow icon.
     */
    public static function renderSortHeader($column, $label, $currentSort, $currentOrder)
    {
        $isCurrent = ($currentSort === $column);
        $newOrder = ($isCurrent && $currentOrder === 'asc') ? 'desc' : 'asc';
        $icon = 'unfold_more';

        if ($isCurrent) {
            $icon = $currentOrder === 'asc' ? 'arrow_upward' : 'arrow_downward';
        }

        $color = $isCurrent ? 'var(--primary)' : 'var(--text-muted)';

        return "<th style='cursor:pointer; user-select:none; white-space:nowrap;' onclick=\"updateSort('$column', '$newOrder')\">
                    <div style='display:flex; align-items:center; gap:6px;'>
                        $label <span class='material-symbols-outlined' style='font-size:16px; color:$color;'>$icon</span>
                    </div>
                </th>";
    }

    /**
     * Renders a modular pagination block with a rows-per-page selector.
     */
    public static function renderPagination($totalRows, $perPage, $currentPage, $options = [5, 15, 30, 50])
    {
        $totalPages = ceil($totalRows / $perPage);
        if ($totalRows == 0) return '';

        $html = '<div class="pagination-wrapper" style="display:flex; justify-content:space-between; align-items:center; padding: 16px 20px; border-top: 1px solid var(--border); background: var(--bg-body); flex-wrap: wrap; gap: 16px;">';

        // 1. Rows Per Page Selector
        $html .= '<div style="display:flex; align-items:center; gap:8px;">';
        $html .= '<span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Rows:</span>';
        $html .= '<select class="per-page-select form-control" style="padding: 4px 28px 4px 10px; width:auto; font-size:0.8rem; min-height: 30px;">';
        foreach ($options as $opt) {
            $sel = ($opt == $perPage) ? 'selected' : '';
            $html .= "<option value=\"$opt\" $sel>$opt</option>";
        }
        $html .= '</select>';
        $html .= '</div>';

        // 2. Page Links
        if ($totalPages > 1) {
            $html .= '<div class="pagination" style="margin:0;">';
            $getParams = $_GET;

            if ($currentPage > 1) {
                $getParams['page'] = $currentPage - 1;
                $html .= '<a href="?' . http_build_query($getParams) . '" class="page-link prev">←</a>';
            }

            // Windowed pagination (keeps it clean if there are 100 pages)
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);

            for ($i = $startPage; $i <= $endPage; $i++) {
                if ($i == $currentPage) {
                    $html .= '<span class="page-link active">' . $i . '</span>';
                } else {
                    $getParams['page'] = $i;
                    $html .= '<a href="?' . http_build_query($getParams) . '" class="page-link">' . $i . '</a>';
                }
            }

            if ($currentPage < $totalPages) {
                $getParams['page'] = $currentPage + 1;
                $html .= '<a href="?' . http_build_query($getParams) . '" class="page-link next">→</a>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div></div>'; // Spacer
        }

        // 3. Info Text
        $start = ($currentPage - 1) * $perPage + 1;
        $end = min($currentPage * $perPage, $totalRows);
        $html .= "<div style=\"font-size:0.75rem; color:var(--text-muted); font-weight:600;\">Showing $start – $end of $totalRows</div>";

        $html .= '</div>';
        return $html;
    }

    /**
     * Enhanced Dropdown Component
     * * @param array $config {
     * @type string $id          Unique DOM ID
     * @type string $name        Form input name (e.g. 'fw_curr' or 'types[]')
     * @type string $placeholder Text to show when empty
     * @type bool   $multiple    Allow multiple selections (Checkboxes & Chips)
     * @type bool   $creatable   Allow user to type and press Enter to create new option
     * @type array  $options     Flat ['A', 'B'] or Grouped ['Group1' => ['A'], 'Group2' => ['B']]
     * @type mixed  $selected    String or Array of currently selected values
     * }
     */
    public static function enhancedDropdown(array $config)
    {
        $id = $config['id'] ?? uniqid('dd_');
        $name = $config['name'] ?? $id;
        $placeholder = $config['placeholder'] ?? 'Select...';
        $multiple = $config['multiple'] ?? false;
        $creatable = $config['creatable'] ?? false;
        $options = $config['options'] ?? [];
        $selected = $config['selected'] ?? ($multiple ? [] : '');

        if (!is_array($selected)) {
            $selected = strlen((string)$selected) > 0 ? [$selected] : [];
        }

        // Pass config to JS via data attribute
        $jsConfig = htmlspecialchars(json_encode([
            'id' => $id,
            'name' => $name,
            'multiple' => $multiple,
            'creatable' => $creatable,
            'placeholder' => $placeholder
        ]), ENT_QUOTES, 'UTF-8');

        $html = "<div class='enh-dropdown' id='{$id}' data-config='{$jsConfig}'>";

        // Hidden inputs container (JS manages this)
        $html .= "<div class='enh-hidden-inputs'>";
        foreach ($selected as $val) {
            $html .= "<input type='hidden' name='{$name}' value='" . htmlspecialchars($val) . "'>";
        }
        $html .= "</div>";

        // Trigger UI
        $html .= "<div class='enh-trigger' tabindex='0'>";
        $html .= "<div class='enh-trigger-content'></div>"; // JS fills with text or chips
        $html .= "<span class='material-symbols-outlined enh-chevron'>expand_more</span>";
        $html .= "</div>";

        // Popover Menu
        $html .= "<div class='enh-menu hidden'>";
        $html .= "<div class='enh-search-wrap'>";
        $html .= "<span class='material-symbols-outlined'>search</span>";
        $html .= "<input type='text' class='enh-search' placeholder='Search...'>";
        $html .= "</div>";

        $html .= "<div class='enh-options'>";

        // Render Options
        foreach ($options as $groupLabel => $groupOptions) {
            if (is_array($groupOptions)) {
                // It's a Group
                $html .= "<div class='enh-optgroup-label'>" . htmlspecialchars($groupLabel) . "</div>";
                foreach ($groupOptions as $val => $label) {
                    // Handle sequential arrays
                    if (is_int($val)) $val = $label;
                    $isSelected = in_array((string)$val, $selected) ? 'selected' : '';
                    $html .= "<div class='enh-option {$isSelected}' data-value='" . htmlspecialchars($val) . "'>";
                    if ($multiple) {
                        $html .= "<div class='enh-checkbox'><span class='material-symbols-outlined'>check</span></div>";
                    }
                    $html .= "<span class='enh-opt-label'>" . htmlspecialchars($label) . "</span>";
                    $html .= "</div>";
                }
            } else {
                // It's a Flat Option
                $isSequential = array_keys($options) === range(0, count($options) - 1);
                $val = $isSequential ? $groupOptions : $groupLabel;
                $label = $groupOptions;

                $isSelected = in_array((string)$val, $selected) ? 'selected' : '';
                $html .= "<div class='enh-option {$isSelected}' data-value='" . htmlspecialchars($val) . "'>";
                if ($multiple) {
                    $html .= "<div class='enh-checkbox'><span class='material-symbols-outlined'>check</span></div>";
                }
                $html .= "<span class='enh-opt-label'>" . htmlspecialchars($label) . "</span>";
                $html .= "</div>";
            }
        }

        // Create Template (Hidden by default)
        if ($creatable) {
            $html .= "<div class='enh-create-opt hidden'>";
            $html .= "<span class='material-symbols-outlined' style='color:var(--primary); font-size:18px;'>add_box</span> ";
            $html .= "Create \"<span class='enh-create-text'></span>\"";
            $html .= "<span class='enh-kb'>Enter ↵</span>";
            $html .= "</div>";
        }

        $html .= "</div></div></div>"; // Close options, menu, wrapper

        return $html;
    }

    public static function renderPrinterImage($path, $modelName, $iconSize = 24)
    {
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
    
    // ----------------------------------------------
    // ENV PARSER
    // Simple .env file parser to retrieve configuration values without external dependencies
    // Usage: Helper::getEnv('GMAIL_USERNAME') or Helper::getEnv('GMAIL_USERNAME', 'default_value')
    // ----------------------------------------------
    private static $envData = null;

    /**
     * Parses the .env file in the root directory and retrieves the value.
     */
    public static function getEnv($key, $default = null)
    {
        // Only read the file once per page load to save performance
        if (self::$envData === null) {
            self::$envData = [];
            // Assuming this file is inside /configs/, so root is one level up
            $envPath = __DIR__ . '/../.env'; 

            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Skip comments
                    if (str_starts_with($line, '#')) continue; 
                    
                    if (str_contains($line, '=')) {
                        list($name, $value) = explode('=', $line, 2);
                        $name = trim($name);
                        // Remove spaces and surrounding quotes
                        $value = trim(trim($value), "\"'"); 
                        self::$envData[$name] = $value;
                    }
                }
            }
        }

        return self::$envData[$key] ?? $default;
    }
}
