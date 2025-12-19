<?php
require_once __DIR__ . '/../src/auth.php';

// Prepare Filters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';


$queryParams = [
    'select' => '*,categories(name),users(username)', // Get film data + category name + author name
    'status' => 'eq.approved', // Film must be approved by admin
    'visibility' => 'eq.public', // Film must be public
    'order' => 'created_at.desc' // Show newest films first
];

if ($category) {
    // !inner ensures that if a film doesn't have this category, it is hidden entirely
    $queryParams['select'] = '*,categories!inner(name),users(username)';
    $queryParams['categories.name'] = 'eq.' . $category;
}

if ($search) {
    // The '*' acts as a wildcard (matches text before or after)
    // the "or" logic finds it in the TITLE or the SYNOPSIS
    $term = $search; 
    $pattern = "*$term*";
    $queryParams['or'] = "(title.ilike.$pattern,synopsis.ilike.$pattern)";
}

try {
    // send request to Supabase
    $filmsRaw = $supabase->request('GET', 'films', $queryParams);

    
    if (!is_array($filmsRaw)) {
        $filmsRaw = [];
    }

    // Transform Films to match simpler structure expected by valid HTML below (flattener)
    $films = array_map(function ($f) {
        $f['category_name'] = $f['categories']['name'] ?? 'Uncategorized';
        $f['filmmaker_name'] = $f['users']['username'] ?? 'Unknown';
        return $f;
    }, $filmsRaw);


    // Pick a featured film (random or first)
    $featuredFilm = !empty($films) ? $films[0] : null;

} catch (Exception $e) {
    $films = [];
    $cats = [];
    $featuredFilm = null;

}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="logo">
            tetteystudios+
        </div>

        <!-- Search Bar -->
        <div class="search-bar-container" style="flex: 1; margin: 0 40px; display: flex; gap: 10px;">
            <form action="index.php" method="GET" style="display: flex; width: 100%; gap: 10px;">
                <select name="category" class="form-input" style="width: 150px; padding: 8px;">
                    <option value="">All Categories</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>" <?= $category === $c['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search films..." value="<?= htmlspecialchars($search) ?>"
                    class="form-input" style="padding: 8px;">
                <button type="submit" class="btn btn-glass" style="padding: 8px 15px;">Search</button>
            </form>
        </div>

        <div class="auth-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="<?= $_SESSION['role'] === 'filmmaker' ? 'filmmaker_dashboard.php' : 'viewer_dashboard.php' ?>"
                    class="btn btn-glass">Dashboard</a>
                <a href="logout.php" class="btn btn-glass" style="text-decoration: none; font-size: 14px;">Sign Out</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary" style="text-decoration: none; font-size: 14px;">Sign In /
                    Register</a>
            <?php endif; ?>
        </div>
    </header>


    <main class="main-content">

        <?php if ($featuredFilm): ?>
            <section class="hero">
                <div class="hero-video-container">
                    <img id="hero-cover"
                        src="<?= htmlspecialchars($featuredFilm['poster_url'] ?: 'assets/images/circles_cover.png') ?>"
                        alt="Cover" style="opacity: 0; transition: opacity 1s;">

                    <!-- Video - Always show hero video as background -->
                    <video id="hero-video" muted playsinline loop autoplay preload="auto" style="opacity: 1; transition: opacity 1s;" webkit-playsinline>
                        <source src="https://qqwwtartsqtxyoirsiio.supabase.co/storage/v1/object/public/uploads/static_videos/thunderbolts_trailer.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <script>
                        // Immediate attempt to play video
                        (function() {
                            const video = document.getElementById('hero-video');
                            if (video) {
                                video.muted = true;
                                video.volume = 0;
                                video.play().catch(e => console.log("Immediate play failed:", e));
                            }
                        })();
                    </script>
                    <?php if ($featuredFilm['trailer_url']): ?>
                        <!-- Optional: Featured film trailer can overlay or be shown in carousel -->
                    <?php endif; ?>
                </div>

                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1
                        style="font-size: 60px; font-weight: 200; line-height: 0.9; margin-bottom: 15px; text-transform: uppercase;">
                        <?= htmlspecialchars($featuredFilm['title']) ?>
                    </h1>

                    <div class="hero-meta">
                        <span
                            style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;">TetteyStudios+</span>
                        <span><?= htmlspecialchars($featuredFilm['category_name']) ?></span>
                        <span>•</span>
                        <span><?= $featuredFilm['duration_minutes'] ?>m</span>
                    </div>

                    <p class="hero-description">
                        <?= htmlspecialchars(substr($featuredFilm['synopsis'], 0, 150)) ?>...
                    </p>

                    <div class="hero-actions">
                        <a href="watch.php?id=<?= $featuredFilm['id'] ?>" class="btn btn-primary">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                            Play Now
                        </a>
                        <button class="btn btn-glass">+ Watchlist</button>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <!-- Default Carousel when no films -->
            <section class="hero">
                <div class="hero-video-container">
                    <!-- Default Cover Image (Hidden, video plays immediately) -->
                    <img id="hero-cover"
                        src="assets/images/circles_cover.png"
                        alt="Cover" style="opacity: 0; transition: opacity 1s;">
                    
                    <!-- Default Video - Always playing -->
                    <video id="hero-video" muted playsinline loop autoplay preload="auto" style="opacity: 1; transition: opacity 1s;" webkit-playsinline>
                        <source src="https://qqwwtartsqtxyoirsiio.supabase.co/storage/v1/object/public/uploads/static_videos/thunderbolts_trailer.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <script>
                        // Immediate attempt to play video
                        (function() {
                            const video = document.getElementById('hero-video');
                            if (video) {
                                video.muted = true;
                                video.volume = 0;
                                video.play().catch(e => console.log("Immediate play failed:", e));
                            }
                        })();
                    </script>
                </div>
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1 style="font-size: 60px; font-weight: 200; line-height: 0.9; margin-bottom: 15px; text-transform: uppercase;">
                        Welcome to TetteyStudios+
                    </h1>
                    <div class="hero-meta">
                        <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;">TetteyStudios+</span>
                        <span>Independent Films</span>
                    </div>
                    <p class="hero-description">
                        Discover amazing independent films from talented filmmakers around the world.
                    </p>
                    <div class="hero-actions">
                        <a href="#films" class="btn btn-primary">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                            Explore Films
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Featured Trailers Carousel -->
        <section class="carousel-section" id="featured-trailers">
            <div class="section-header">
                Featured Trailers <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"
                    style="opacity: 0.5;">
                    <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z" />
                </svg>
            </div>

            <div class="scroll-container">
                <!-- Default trailer cards using assets -->
                <div class="card" style="cursor: pointer;" onclick="document.getElementById('hero-video')?.play()">
                    <img src="assets/images/circles_cover.png" alt="Featured Trailer" class="card-img">
                    <div class="card-overlay">
                        <div class="card-title">Featured Content</div>
                        <div class="card-subtitle">TetteyStudios+ Original</div>
                    </div>
                </div>
                
                <!-- Show approved films if available -->
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
            </div>
        </section>

        <!-- Available Films Section -->
        <section class="carousel-section" id="films">
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
                    <div style="padding: 40px; text-align: center; color: #666;">
                        <p>No films available at the moment. Check back soon for new releases!</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Mock Content Below -->
        <section class="carousel-section">
            <div class="section-header">
                Top Charts
            </div>
            <div class="scroll-container">
                <div class="card" style="width: 200px; height: 300px; min-width: 200px;">
                    <div
                        style="height: 100%; width: 100%; background: #333; display: flex; align-items: center; justify-content: center; font-size: 50px; color: #555; font-weight: 800;">
                        1</div>
                </div>
                <div class="card" style="width: 200px; height: 300px; min-width: 200px;">
                    <div
                        style="height: 100%; width: 100%; background: #333; display: flex; align-items: center; justify-content: center; font-size: 50px; color: #555; font-weight: 800;">
                        2</div>
                </div>
                <div class="card" style="width: 200px; height: 300px; min-width: 200px;">
                    <div
                        style="height: 100%; width: 100%; background: #333; display: flex; align-items: center; justify-content: center; font-size: 50px; color: #555; font-weight: 800;">
                        3</div>
                </div>
            </div>
        </section>

    </main>

    <script src="js/script.js"></script>
</body>

</html>