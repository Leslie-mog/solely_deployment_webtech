<?php
require_once __DIR__ . '/../src/auth.php';//makes sure user is logged in
requireLogin();

// stops regular users from accessing admin dashboard
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied. Admins only.");
}

$message = ''; // Variable to store success or error messages for the UI

// Runs when an admin clicks Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filmId = $_POST['film_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($filmId && $action) {
        //decides whether to approve or reject based on the action
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

        try {
            $supabase->request('PATCH', "films?id=eq.$filmId", [
                'status' => $newStatus
            ]);
            $message = "Film #$filmId marked as $newStatus.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}


// Get films waiting for approval (i.e. Status = Pending)
$pendingFilmsRaw = $supabase->request('GET', 'films', [
    'status' => 'eq.pending',
    'select' => '*,users(username)',
    'order' => 'created_at.asc'//oldest submissions first
]);

$pendingFilms = array_map(function ($f) {
    $f['filmmaker'] = $f['users']['username'] ?? 'Unknown';
    return $f;
}, $pendingFilmsRaw);


// Get a list of the 20 most recent films
$allFilms = $supabase->request('GET', 'films', [
    'order' => 'created_at.desc',
    'limit' => 20
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <header class="main-header">
        <div class="logo">Admin Panel</div>
        <div class="user-menu">
            <?= htmlspecialchars($_SESSION['username']) ?>
            <a href="logout.php" class="btn btn-glass">Sign Out</a>
        </div>
    </header>

    <main class="main-content">
        <!-- Hero Section with Playing Video -->
        <section class="hero" style="margin-top: 0;">
            <div class="hero-video-container">
                <img id="hero-cover" src="assets/images/circles_cover.png" alt="Cover"
                    style="opacity: 0; transition: opacity 1s;">
                <video id="hero-video" muted playsinline autoplay preload="auto"
                    style="opacity: 1; transition: opacity 1s;" webkit-playsinline>
                    Your browser does not support the video tag.
                </video>
                <script>
                    (function () {
                        const playlist = [
                            'assets/videos/1917_trailer.mp4',
                            'assets/videos/thunderbolts_trailer.mp4',
                            'assets/videos/circles_trailer.mp4'
                        ];
                        let currentIndex = 0;
                        const video = document.getElementById('hero-video');

                        if (video) {
                            //set the first video
                            video.src = playlist[currentIndex];
                            video.muted = true;
                            //when the current video ends, play the next one
                            video.addEventListener('ended', () => {
                                currentIndex = (currentIndex + 1) % playlist.length;
                                video.src = playlist[currentIndex];
                                video.play().catch(e => console.error(e));
                            });
                            video.play().catch(e => console.error(e));
                        }
                    })();
                </script>
            </div>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <h1
                    style="font-size: 60px; font-weight: 200; line-height: 0.9; margin-bottom: 15px; text-transform: uppercase;">
                    Admin Panel
                </h1>
                <div class="hero-meta">
                    <span
                        style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;">TetteyStudios+</span>
                    <span>Content Management</span>
                </div>
                <p class="hero-description">
                    Review and approve films submitted by filmmakers.
                </p>
            </div>
        </section>

        <div class="auth-box"
            style="width: 90%; max-width: 1000px; margin: 0 auto; text-align: left; position: relative; z-index: 10; background: rgba(0,0,0,0.8); margin-top: -50px; padding: 40px; border-radius: 12px;">
            <h1>Content Management</h1>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <h2>Pending Approvals</h2>
            <?php if (empty($pendingFilms)): ?>
                <p style="color: #aaa;">No pending films.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid #444;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">Title</th>
                            <th style="padding: 10px;">Filmmaker</th>
                            <th style="padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFilms as $film): ?>
                            <tr style="border-bottom: 1px solid #333;">
                                <td style="padding: 10px;"><?= $film['id'] ?></td>
                                <td style="padding: 10px;">
                                    <strong><?= htmlspecialchars($film['title']) ?></strong><br>
                                    <small><?= htmlspecialchars(substr($film['synopsis'], 0, 50)) ?>...</small>
                                </td>
                                <td style="padding: 10px;"><?= htmlspecialchars($film['filmmaker']) ?></td>
                                <td style="padding: 10px;">
                                    <form method="POST" style="display: flex; gap: 10px;">
                                        <input type="hidden" name="film_id" value="<?= $film['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-primary"
                                            style="padding: 5px 10px; font-size: 12px; background: #34c759; border: none;">Approve</button>
                                        <button type="submit" name="action" value="reject" class="btn btn-primary"
                                            style="padding: 5px 10px; font-size: 12px; background: #ff3b30; border: none;">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top: 40px;">Recent Activity</h2>
            <!-- List recent films regardless of status -->
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid #444;">
                        <th style="padding: 10px;">Title</th>
                        <th style="padding: 10px;">Status</th>
                        <th style="padding: 10px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allFilms as $f): ?>
                        <tr style="border-bottom: 1px solid #333;">
                            <td style="padding: 10px;"><?= htmlspecialchars($f['title']) ?></td>
                            <td style="padding: 10px;"><?= ucfirst($f['status']) ?></td>
                            <td style="padding: 10px;"><?= $f['created_at'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script src="js/script.js"></script>
</body>

</html>