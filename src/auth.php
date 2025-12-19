<?php
ob_start();

// Using absolute path for db.php relative to this file to be safe
require_once __DIR__ . '/db.php';

/**
 * Custom Session Handler for Supabase
 * Ensures sessions persist across Vercel's ephemeral lambdas
 */
class SupabaseSessionHandler implements SessionHandlerInterface
{
    private $supabase;
    private $table = 'sessions';

    public function __construct()
    {
        global $supabase;
        $this->supabase = $supabase;
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }
    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        try {
            $resp = $this->supabase->request('GET', $this->table, [
                'id' => "eq.$id",
                'select' => 'data,expires_at'
            ]);
            if (!empty($resp) && strtotime($resp[0]['expires_at']) > time()) {
                error_log("Session read SUCCESS for ID: $id");
                return $resp[0]['data'];
            }
            error_log("Session read EMPTY/EXPIRED for ID: $id");
        } catch (Exception $e) {
            error_log("Session read ERROR for ID $id: " . $e->getMessage());
        }
        return '';
    }

    public function write($id, $data): bool
    {
        try {
            $expires_at = date('c', time() + (int) ini_get('session.gc_maxlifetime'));

            // Delete existing session first to avoid primary key conflicts
            try {
                $this->supabase->request('DELETE', $this->table . "?id=eq.$id");
            } catch (Exception $e) {
                // Ignore if session doesn't exist
            }

            // Insert new session
            $this->supabase->request('POST', $this->table, [
                'id' => $id,
                'data' => $data,
                'expires_at' => $expires_at
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Session write failed: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool
    {
        try {
            error_log("Session destroy for ID: $id");
            $this->supabase->request('DELETE', $this->table . "?id=eq.$id");
            return true;
        } catch (Exception $e) {
            error_log("Session destroy ERROR: " . $e->getMessage());
            return false;
        }
    }

    public function gc($maxlifetime): int|false
    {
        try {
            // Simple GC: delete expired sessions
            $now = date('c');
            $this->supabase->request('DELETE', $this->table . "?expires_at=lt.$now");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || true) { // Defaulting to Supabase session for all for consistency
        session_set_save_handler(new SupabaseSessionHandler(), true);
    }
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: /login');
        exit;
    }
}

function register($username, $email, $password, $role)
{
    global $supabase;

    if (strlen($password) < 4) {
        return ['success' => false, 'message' => 'Password must be at least 4 characters'];
    }

    if (!in_array($role, ['viewer', 'filmmaker'])) {
        return ['success' => false, 'message' => 'Invalid role selected'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // 1. Insert into users
        // Supabase returns the inserted row as an array of objects
        $users = $supabase->request('POST', 'users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash
        ]);

        if (empty($users) || !isset($users[0]['id'])) {
            throw new Exception("Failed to create user record.");
        }

        $userId = $users[0]['id'];


        $supabase->request('POST', 'user_profiles', [
            'user_id' => $userId,
            'role' => $role
        ]);

        return ['success' => true, 'message' => 'Registration successful!'];
    } catch (Exception $e) {

        $msg = $e->getMessage();
        if (strpos($msg, 'users_username_key') !== false || strpos($msg, 'username') !== false) {
            return ['success' => false, 'message' => 'Username already taken'];
        }
        if (strpos($msg, 'users_email_key') !== false || strpos($msg, 'email') !== false) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        return ['success' => false, 'message' => 'Registration failed: ' . $msg];
    }
}

function login($username, $password)
{
    global $supabase;

    try {
        // Fetch user with profile
        $response = $supabase->request('GET', 'users', [
            'select' => '*,user_profiles(role,avatar_url)',
            'username' => "eq.$username"
        ]);

        if (!empty($response)) {
            $user = $response[0];

            // Password Verify
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Handle joined data
                $profile = $user['user_profiles'] ?? [];

                if (isset($profile[0])) {
                    $profile = $profile[0];
                }

                $_SESSION['role'] = $profile['role'] ?? 'viewer';

                return ['success' => true, 'message' => 'Login successful', 'role' => $_SESSION['role']];
            }
        }
    } catch (Exception $e) {

    }

    return ['success' => false, 'message' => 'Invalid credentials'];
}

function logout()
{
    session_destroy();
    header('Location: login.php');
    exit;
}