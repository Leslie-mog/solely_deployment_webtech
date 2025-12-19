<?php
require_once __DIR__ . '/../src/auth.php';
requireLogin();
//check if the user is a filmmaker or admin
if ($_SESSION['role'] !== 'filmmaker' && $_SESSION['role'] !== 'admin') {
    header('Location: viewer_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Filmmaker Dashboard - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <header class="main-header">
        <div class="logo">TetteyStudios+ Filmmaker Dashboard</div>
        <div class="user-menu">
            Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
            <a href="logout.php" class="btn btn-glass">Sign Out</a>
        </div>
    </header>


    <main class="main-content">
        <!-- Section with Playing Video -->
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
                            'https://qqwwtartsqtxyoirsiio.supabase.co/storage/v1/object/public/uploads/static_videos/1917_trailer.mp4',
                            'https://qqwwtartsqtxyoirsiio.supabase.co/storage/v1/object/public/uploads/static_videos/SKYFALL%20-%20Official%20Trailer%20-%20Sony%20Pictures%20Entertainment%20(720p,%20h264).mp4',
                            'https://qqwwtartsqtxyoirsiio.supabase.co/storage/v1/object/public/uploads/static_videos/thunderbolts_trailer.mp4'
                        ];
                        let currentIndex = 0;
                        const video = document.getElementById('hero-video');

                        if (video) {
                            video.src = playlist[currentIndex];
                            video.muted = true;
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
                    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                </h1>
                <div class="hero-meta">
                    <span
                        style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;">Filmmaker</span>
                    <span>Manage & Explore</span>
                </div>
                <div class="hero-actions">
                    <a href="upload_film.php" class="btn btn-primary">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                        </svg>
                        Upload New Film
                    </a>
                </div>
            </div>
        </section>

        <!-- SECTION 1: My Projects (Enhanced) -->
        <div style="padding: 40px; background: #0a0a0a; border-bottom: 1px solid #222;">
            <h2 style="font-weight: 300; margin-bottom: 30px; font-size: 28px;">My Projects</h2>

            <div class="grid-container"
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px;">
                <?php
                // Fetch my films with stats
                $myFilms = $supabase->request('GET', 'films', [
                    'filmmaker_id' => 'eq.' . $_SESSION['user_id'],
                    'order' => 'created_at.desc'
                ]);
                //Check if the $myFilms array is empty (meaning the user has no uploads)
                if (empty($myFilms)):
                    ?>
                    <div
                        style="grid-column: 1 / -1; padding: 40px; background: rgba(255,255,255,0.05); border-radius: 12px; text-align: center;">
                        <p style="color: #aaa; margin-bottom: 20px;">You haven't uploaded any films yet.</p>
                        <a href="upload_film.php" class="btn btn-primary">Create Your First Project</a>
                    </div>
                <?php else:
                    foreach ($myFilms as $film):
                        
                        //Calculate the percentage of funding raised
                        $percent = ($film['funding_goal'] > 0) ? min(100, round(($film['funding_raised'] / $film['funding_goal']) * 100)) : 0;
                        ?>
                        <div class="card" style="height: auto; cursor: default; background: #111; border: 1px solid #333;">
                            <div style="position: relative;">
                                <img src="<?= htmlspecialchars($film['poster_url'] ?: 'assets/images/thumb1.png') ?>"
                                    class="card-img" style="height: 180px; object-fit: cover; opacity: 0.8;">
                                <div
                                    style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.8); padding: 4px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: bold; color: <?= $film['status'] == 'approved' ? '#34c759' : '#ffcc00' ?>">
                                    <?= $film['status'] ?>
                                </div>
                            </div>

                            <div style="padding: 20px;">
                                <h3 style="margin: 0 0 10px 0; font-size: 18px; color: white;">
                                    <?= htmlspecialchars($film['title']) ?>
                                </h3>

                                <?php if ($film['funding_goal'] > 0): ?>
                                    <div style="margin-bottom: 15px;">
                                        <div
                                            style="display: flex; justify-content: space-between; font-size: 12px; color: #888; margin-bottom: 5px;">
                                            <span>Funding</span>
                                            <span>$<?= number_format($film['funding_raised']) ?> /
                                                $<?= number_format($film['funding_goal']) ?></span>
                                        </div>
                                        <div style="background: #333; height: 4px; border-radius: 2px;">
                                            <div
                                                style="background: var(--accent-blue); width: <?= $percent ?>%; height: 100%; border-radius: 2px;">
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; gap: 10px; margin-top: 20px;">
                                    <a href="watch.php?id=<?= $film['id'] ?>" class="btn btn-glass"
                                        style="flex: 1; font-size: 13px; text-align: center; padding: 8px;">View Page &
                                        Reviews</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
            </div>
        </div>

        
        <?php
        // Fetch approved films for carousel (Discovery)
        try {
            $approvedFilms = $supabase->request('GET', 'films', [
                'select' => '*,categories(name),users(username)',
                'status' => 'eq.approved',
                'visibility' => 'eq.public',
                'order' => 'created_at.desc',
                'limit' => 10
            ]);
            $films = $approvedFilms;
        } catch (Exception $e) {
            $films = [];
        }
        ?>

        <section class="carousel-section">
            <div class="section-header">
                Explore Community Work <span
                    style="font-size: 14px; color: #666; font-weight: 400; margin-left: 10px;">Support your fellow
                    creators</span>
            </div>
            <div class="scroll-container">
                <?php if (!empty($films)): ?>
                    <?php foreach ($films as $film): ?>
                        <div class="card" onclick="window.location.href='watch.php?id=<?= $film['id'] ?>'">
                            <img src="<?= htmlspecialchars($film['poster_url'] ?: $film['thumbnail_url'] ?: 'assets/images/circles_cover.png') ?>"
                                alt="<?= htmlspecialchars($film['title']) ?>" class="card-img">
                            <div class="card-overlay">
                                <div class="card-title"><?= htmlspecialchars($film['title']) ?></div>
                                <div class="card-subtitle">
                                    by <?= htmlspecialchars($film['users']['username'] ?? 'Unknown') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; padding: 20px;">No other films available yet.</p>
                <?php endif; ?>
            </div>
        </section>

    </main>
    <script src="js/script.js"></script>
</body>

</html>