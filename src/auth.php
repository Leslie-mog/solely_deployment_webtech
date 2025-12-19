<?php
$sessionDir = __DIR__ . '/../sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();
// Using absolute path for db.php relative to this file to be safe
require_once __DIR__ . '/db.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
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
?>