<?php
/**
 * Token-based authentication for web wizard
 */
class Auth
{
    private static string $tokenFile = '/etc/atom-heratio/.wizard-token';

    public static function check(): bool
    {
        $token = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_WIZARD_TOKEN'] ?? '';

        if (empty($token)) {
            return false;
        }

        // Check against stored token
        if (file_exists(self::$tokenFile)) {
            $stored = trim(file_get_contents(self::$tokenFile));
            return hash_equals($stored, $token);
        }

        // Check against PHP ini setting (set at launch)
        $iniToken = ini_get('atom_heratio.token');
        if (!empty($iniToken)) {
            return hash_equals($iniToken, $token);
        }

        return false;
    }

    public static function getToken(): string
    {
        return $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_WIZARD_TOKEN'] ?? '';
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or missing access token']);
            exit;
        }
    }
}
