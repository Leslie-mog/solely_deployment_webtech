<?php
require_once __DIR__ . '/src/Supabase.php';

// Config
$supabaseUrl = 'https://qqwwtartsqtxyoirsiio.supabase.co';
$supabaseKey = 'sb_publishable__54GYg9DdVMepcbgDo1W-A_5T2uKOcu';
$supabase = new Supabase($supabaseUrl, $supabaseKey);

$username = 'admin';
$password = 'admin123';
$email = 'admin@tetteystudios.com';
$role = 'admin';

echo "Creating Admin User...\n";

try {
    // 1. Create User
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $users = $supabase->request('POST', 'users', [
        'username' => $username,
        'email' => $email,
        'password_hash' => $hash
    ]);

    if (empty($users) || !isset($users[0]['id'])) {
        throw new Exception("Failed to insert user (User might already exist).");
    }

    $userId = $users[0]['id'];
    echo "User created: " . $userId . "\n";

    // 2. Create Profile
    $supabase->request('POST', 'user_profiles', [
        'user_id' => $userId,
        'role' => $role
    ]);

    echo "Profile created with role: $role\n";
    echo "SUCCESS! You can now login with:\nUsername: $username\nPassword: $password\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>