<?php
require_once __DIR__ . '/Supabase.php';

$supabaseUrl = 'https://qqwwtartsqtxyoirsiio.supabase.co';
$supabaseKey = 'sb_publishable__54GYg9DdVMepcbgDo1W-A_5T2uKOcu';

try {
    $supabase = new Supabase($supabaseUrl, $supabaseKey);
    
} catch (Exception $e) {
    die("Supabase initialization failed: " . $e->getMessage());
}

?>