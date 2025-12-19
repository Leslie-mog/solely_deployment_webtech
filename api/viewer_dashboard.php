<?php
require_once __DIR__ . '/../src/auth.php';
requireLogin();


if ($_SESSION['role'] !== 'viewer' && $_SESSION['role'] !== 'admin') {
    // If filmmaker tries to access viewer dashboard let them
    if ($_SESSION['role'] === 'filmmaker') {
        header('Location: filmmaker_dashboard.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Viewer Dashboard - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <header class="main-header">
        <div class="logo">TetteyStudios+</div>
        <div class="user-menu">
            Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
            <a href="logout.php" class="btn btn-glass">Sign Out</a>
        </div>
    </header>

    <main class="main-content">
        <!-- Section with Default Carousel -->
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
                            // Start with first video
                            video.src = playlist[currentIndex];
                            video.muted = true;

                            // Play next when current video ends
                            video.addEventListener('ended', () => {
                                currentIndex = (currentIndex + 1) % playlist.length;
                                video.src = playlist[currentIndex];
                                video.play().catch(e => console.error("Auto-play failed:", e));
                            });

                            // Initial play
                            video.play().catch(e => console.error("Initial play failed:", e));
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
                        style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;">TetteyStudios+</span>
                    <span>Your Dashboard</span>
                </div>
                <p class="hero-description">
                    Discover and watch amazing independent films from talented filmmakers.
                </p>
            </div>
        </section>

        <?php
        // Fetch approved films for carousel
        try {
            $approvedFilms = $supabase->request('GET', 'films', [
                'select' => '*,categories(name),users(username)',
                'status' => 'eq.approved',
                'visibility' => 'eq.public',
                'order' => 'created_at.desc',
                'limit' => 10
            ]);

            $films = array_map(function ($f) {
                $f['category_name'] = $f['categories']['name'] ?? 'Uncategorized';
                $f['filmmaker_name'] = $f['users']['username'] ?? 'Unknown';
                return $f;
            }, $approvedFilms);
        } catch (Exception $e) {
            $films = [];
        }
        ?>

        <!-- Featured Trailers Carousel -->
        <section class="carousel-section">
            <div class="section-header">
                Featured Trailers <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"
                    style="opacity: 0.5;">
                    <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z" />
                </svg>
            </div>
            <div class="scroll-container">
                <div class="card" style="cursor: pointer;" onclick="document.getElementById('hero-video')?.play()">
                    <img src="assets/images/circles_cover.png" alt="Featured Trailer" class="card-img">
                    <div class="card-overlay">
                        <div class="card-title">Featured Content</div>
                        <div class="card-subtitle">TetteyStudios+ Original</div>
                    </div>
                </div>
                <?php foreach (array_slice($films, 0, 5) as $film): ?>
                        <div class="card" onclick="window.location.href='watch.php?id=<?= $film['id'] ?>'">
                        <img src="<?= htmlspecialchars($film['poster_url'] ?: $film['thumbnail_url'] ?: 'assets/images/circles_cover.png') ?>"
                            class="card-img">
                        <div class="card-overlay">
                            <div class="card-title"><?= htmlspecialchars($film['title']) ?></div>
                            <div class="card-subtitle"><?= htmlspecialchars($film['category_name']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Available Films -->
        <section class="carousel-section">
            <div class="section-header">
                Available Films <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"
                    style="opacity: 0.5;">
                    <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z" />
                </svg>
            </div>
            <div class="scroll-container">
                <?php if (!empty($films)): ?>
                    <?php foreach ($films as $film): ?>
                        <div class="card" onclick="window.location.href='watch.php?id=<?= $film['id'] ?>'">
                            <img src="<?= htmlspecialchars($film['thumbnail_url'] ?: $film['poster_url'] ?: 'assets/images/circles_cover.png') ?>"
                                alt="<?= htmlspecialchars($film['title']) ?>" class="card-img">
                            <div class="card-overlay">
                                <div class="card-title"><?= htmlspecialchars($film['title']) ?></div>
                                <div class="card-subtitle"><?= htmlspecialchars($film['category_name']) ?> •
                                    <?= $film['duration_minutes'] ?>m
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; padding: 20px;">No films available at the moment.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- My Contributions -->
        <section class="carousel-section">
            <div class="section-header">
                My Contributions
            </div>
            <?php
            $donationsRaw = $supabase->request('GET', 'donations', [
                'user_id' => 'eq.' . $_SESSION['user_id'],
                'select' => '*,films(title,thumbnail_url)',
                'order' => 'created_at.desc'
            ]);

            $myDonations = array_map(function ($d) {
                $d['title'] = $d['films']['title'] ?? 'Unknown Film';
                $d['thumbnail'] = $d['films']['thumbnail_url'] ?? 'assets/images/circles_cover.png';
                return $d;
            }, $donationsRaw);

            if (empty($myDonations)): ?>
                <p style="color: #aaa; padding: 20px;">You haven't made any donations yet.</p>
            <?php else: ?>
                <div class="scroll-container">
                    <?php foreach ($myDonations as $d): ?>
                        <div class="card" onclick="window.location.href='watch.php?id=<?= $d['film_id'] ?>'">
                            <img src="<?= htmlspecialchars($d['thumbnail']) ?>" class="card-img">
                            <div class="card-overlay">
                                <div class="card-title"><?= htmlspecialchars($d['title']) ?></div>
                                <div class="card-subtitle">Donated $<?= number_format($d['amount'], 2) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Continue Watching -->
        <section class="carousel-section">
            <div class="section-header">
                Continue Watching
            </div>
            <?php
            // Fetch some approved films as watch history
            $history = $supabase->request('GET', 'films', [
                'status' => 'eq.approved',
                'limit' => 5
            ]);

            if (empty($history)): ?>
                <p style="color: #aaa; padding: 20px;">Start watching to see your history here.</p>
            <?php else: ?>
                <div class="scroll-container">
                    <?php foreach ($history as $film): ?>
                        <div class="card" onclick="window.location.href='watch.php?id=<?= $film['id'] ?>'">
                            <img src="<?= htmlspecialchars($film['thumbnail_url'] ?: $film['poster_url'] ?: 'assets/images/circles_cover.png') ?>"
                                class="card-img">
                            <div class="card-overlay">
                                <div class="card-title"><?= htmlspecialchars($film['title']) ?></div>
                                <div style="margin-top: 5px; height: 3px; background: rgba(255,255,255,0.3);">
                                    <div style="width: <?= rand(20, 90) ?>%; height: 100%; background: var(--accent-blue);">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Watchlist -->
        <section class="carousel-section">
            <div class="section-header">
                My Watchlist
            </div>
            <p style="color: #aaa; padding: 20px;">Your watchlist is empty. Add films to your watchlist while browsing.
            </p>
        </section>
    </main>

    <script src="js/script.js"></script>
    </main>
</body>

</html>