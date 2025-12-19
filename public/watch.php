<?php
require_once __DIR__ . '/../src/auth.php';

// Get Film ID
$filmId = $_GET['id'] ?? null;
if (!$filmId) {
    die("Film not specified.");
}


// Fetch Film Data
$response = $supabase->request('GET', 'films', [
    'id' => "eq.$filmId",
    'select' => '*,users(username,email),categories(name)'
]);

if (empty($response)) {
    die("Film not found.");
}
$film = $response[0];
$film['filmmaker_name'] = $film['users']['username'] ?? 'Unknown';
$film['filmmaker_contact'] = $film['users']['email'] ?? '';
$film['category_name'] = $film['categories']['name'] ?? 'Uncategorized';

// Handle Donation
$donationMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donate_amount'])) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    // Donation Handling
    $amount = (float) $_POST['donate_amount'];
    $message = $_POST['donate_message'] ?? '';

    try {
        // Record Donation
        $supabase->request('POST', 'donations', [
            'user_id' => $_SESSION['user_id'],
            'film_id' => $filmId,
            'amount' => $amount,
            'message' => $message
        ]);

        // Update Film Funding (Read-Modify-Write)
        $newRaised = $film['funding_raised'] + $amount;
        $supabase->request('PATCH', "films?id=eq.$filmId", [
            'funding_raised' => $newRaised
        ]);

        $donationMsg = "Thank you for your donation of $$amount!";

        // Update local object
        $film['funding_raised'] = $newRaised;

    } catch (Exception $e) {
        $donationMsg = "Donation failed: " . $e->getMessage();
    }
}

// Handle Review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    $rating = (int) $_POST['rating'];
    $comment = $_POST['review_comment'] ?? '';

    try {
        $supabase->request('POST', 'reviews', [
            'user_id' => $_SESSION['user_id'],
            'film_id' => $filmId,
            'rating' => $rating,
            'comment' => $comment
        ]);
    } catch (Exception $e) {
        // Ignore duplicate
    }
}


// Fetch Reviews
$reviewsRaw = $supabase->request('GET', 'reviews', [
    'film_id' => "eq.$filmId",
    'select' => '*,users(username)',
    'order' => 'created_at.desc'
]);

$filmReviews = array_map(function ($r) {
    $r['username'] = $r['users']['username'] ?? 'Anonymous';
    return $r;
}, $reviewsRaw);

// Calculate Average Rating
$avgRating = 0;
if (count($filmReviews) > 0) {
    $sum = array_reduce($filmReviews, fn($carry, $item) => $carry + $item['rating'], 0);
    $avgRating = round($sum / count($filmReviews), 1);
}

// Fetch Cast/Crew (Credits)
$filmCredits = $supabase->request('GET', 'film_credits', ['film_id' => "eq.$filmId"]);

// Function to convert video URLs to embeddable format
function getEmbedUrl($url)
{
    // If there is no URL, stop early.
    if (empty($url))
        return null;

    // this regular expression looks for the 11-character ID that identifies a YouTube video (found after ?v= or in youtu.be/ links).
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
        // $matches[1] is the 11-character ID. We wrap it in the official embed link.
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    // Vimeo uses a string of numbers for its IDs.
    if (preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $url, $matches)) {
        // $matches[1] is the ID. We wrap it in the official embed link.
        return 'https://player.vimeo.com/video/' . $matches[1];
    }

    // If it's already an embed URL or other format, return as is
    return $url;
}

$embedUrl = getEmbedUrl($film['video_url']);
$isExternalVideo = !empty($embedUrl) && (strpos($embedUrl, 'youtube.com/embed') !== false || strpos($embedUrl, 'vimeo.com/video') !== false || strpos($film['video_url'], 'http') === 0);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($film['title']) ?> - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --glass-bg: rgba(22, 22, 22, 0.8);
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent-color: #3b82f6;
        }

        body {
            background-color: #050505;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            color: #ffffff;
        }

        /* Navbar */
        .main-header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 20px 40px;
            z-index: 100;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.8), transparent);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }

        .logo a {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: white;
            text-decoration: none;
        }

        /* Cinema Player */
        .player-container {
            width: 100%;
            height: 90vh;
            background: #000;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .video-wrapper {
            width: 100%;
            height: 100%;
            max-width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        video,
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            object-fit: contain;
        }

        iframe {
           
        }

        /* Content Layout */
        .film-content {
            max-width: 1400px;
            margin: -60px auto 40px;
            padding: 0 40px;
            position: relative;
            z-index: 10;
            display: grid;
            grid-template-columns: 65% 30%;
            gap: 5%;
        }

        /* Main Info Column */
        .film-main {
            padding-top: 20px;
        }

        .film-header {
            margin-bottom: 30px;
        }

        .film-title {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 15px;
            color: #fff;
        }

        .film-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #a0a0a0;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .meta-tag {
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 10px;
            border-radius: 4px;
            color: #fff;
            font-weight: 500;
        }

        .rating-star {
            color: #fbbf24;
            margin-right: 4px;
        }

        .film-synopsis {
            font-size: 18px;
            line-height: 1.7;
            color: #d4d4d4;
            margin-bottom: 40px;
            font-weight: 300;
        }

        .credits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #111;
            border-radius: 12px;
            margin-bottom: 40px;
        }

        .credit-item .role {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            margin-bottom: 4px;
        }

        .credit-item .name {
            font-size: 16px;
            font-weight: 500;
            color: #fff;
        }

        /* Reviews Section */
        .reviews-section {
            margin-top: 50px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .review-card {
            background: #111;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .review-author {
            font-weight: 600;
            color: #fff;
        }

        .review-text {
            color: #ccc;
            line-height: 1.5;
        }

        .film-sidebar {
            position: relative;
        }

        .funding-card {
            background: linear-gradient(145deg, #1a1a1a, #111);
            border: 1px solid #333;
            border-radius: 16px;
            padding: 30px;
            position: sticky;
            top: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .funding-header {
            margin-bottom: 20px;
        }

        .funding-amount {
            font-size: 36px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }

        .funding-goal {
            font-size: 14px;
            color: #888;
        }

        .progress-container {
            height: 6px;
            background: #333;
            border-radius: 3px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-val {
            height: 100%;
            background: var(--accent-color);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }

        .donate-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            color: #888;
        }

        .donate-input {
            width: 100%;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-family: inherit;
        }

        .donate-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .btn-donate {
            width: 100%;
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-donate:hover {
            filter: brightness(110%);
            transform: translateY(-2px);
        }

        .recent-donors {
            margin-top: 30px;
            border-top: 1px solid #333;
            padding-top: 20px;
        }

        .donor-row {
            padding: 10px 0;
            border-bottom: 1px solid #222;
            font-size: 13px;
            color: #bbb;
        }

        .donor-row:last-child {
            border-bottom: none;
        }

        /* Mobile Responsive */
        @media (max-width: 900px) {
            .film-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .player-container {
                height: 50vh;
            }

            .film-title {
                font-size: 32px;
            }

            .funding-card {
                position: static;
            }
        }
    </style>
</head>

<body>
    <header class="main-header">
        <div class="logo"><a href="index.php">TetteyStudios+</a></div>
        <div class="auth-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="<?= $_SESSION['role'] === 'filmmaker' ? 'filmmaker_dashboard.php' : 'viewer_dashboard.php' ?>"
                    class="btn btn-glass"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Sign In</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Cinema Player Section -->
    <div class="player-container">
        <div class="video-wrapper">
            <?php if ($isExternalVideo && $embedUrl): ?>
    
                <iframe class="video-embed" src="<?= htmlspecialchars($embedUrl) ?>?autoplay=1&modestbranding=1&rel=0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            <?php elseif ($film['video_url'] && strpos($film['video_url'], 'http') === 0): ?>
                <!-- Direct Iframe Fallback -->
                <iframe class="video-embed" src="<?= htmlspecialchars($film['video_url']) ?>"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            <?php else: ?>
                <!-- Native Video -->
                <video controls autoplay poster="<?= htmlspecialchars($film['poster_url'] ?? '') ?>">
                    <?php if ($film['video_url']): ?>
                        <source src="<?= htmlspecialchars($film['video_url']) ?>" type="video/mp4">
                    <?php endif; ?>
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </div>
    </div>

    <main class="film-content">
        <!-- Info & Content -->
        <div class="film-main">
            <div class="film-header">
                <div class="film-meta">
                    <span class="meta-tag"><?= htmlspecialchars($film['category_name']) ?></span>
                    <span><?= $film['duration_minutes'] ?> min</span>
                    <span>•</span>
                    <span style="display: flex; align-items: center;"><span class="rating-star">★</span>
                        <?= $avgRating ?></span>
                </div>
                <h1 class="film-title"><?= htmlspecialchars($film['title']) ?></h1>
            </div>

            <div class="film-synopsis">
                <?= nl2br(htmlspecialchars($film['synopsis'])) ?>
            </div>

            <div class="credits-grid">
                <?php foreach ($filmCredits as $c): ?>
                    <div class="credit-item">
                        <div class="role"><?= htmlspecialchars($c['role']) ?></div>
                        <div class="name"><?= htmlspecialchars($c['name']) ?></div>
                    </div>
                <?php endforeach; ?>
                <!-- Fallback if no credits -->
                <div class="credit-item">
                    <div class="role">Filmmaker</div>
                    <div class="name"><?= htmlspecialchars($film['filmmaker_name']) ?></div>
                </div>
            </div>

            <div class="reviews-section">
                <h3 class="section-title">Reviews & Discussions</h3>

                <?php if (isLoggedIn()): ?>
                    <form method="POST"
                        style="margin-bottom: 30px; background: #1a1a1a; padding: 20px; border-radius: 8px;">
                        <label style="display: block; margin-bottom: 10px; color: #aaa; font-size: 13px;">Leave a
                            review</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                            <select name="rating" class="donate-input" style="width: 120px; margin-bottom: 0;">
                                <option value="5">★★★★★</option>
                                <option value="4">★★★★☆</option>
                                <option value="3">★★★☆☆</option>
                                <option value="2">★★☆☆☆</option>
                                <option value="1">★☆☆☆☆</option>
                            </select>
                            <input type="text" name="review_comment" class="donate-input"
                                placeholder="Share your thoughts..." style="margin-bottom: 0; flex-grow: 1;">
                        </div>
                        <button type="submit" class="btn-donate"
                            style="width: auto; padding: 10px 24px; font-size: 14px; background: #333;">Post Review</button>
                    </form>
                <?php else: ?>
                    <div
                        style="padding: 20px; background: #111; border-radius: 8px; margin-bottom: 30px; text-align: center;">
                        <p style="color: #888;">Please <a href="login.php" style="color: var(--accent-color);">sign in</a>
                            to leave a review.</p>
                    </div>
                <?php endif; ?>

                <?php if (empty($filmReviews)): ?>
                    <p style="color: #666;">No reviews yet. Be the first to share your thoughts!</p>
                <?php else: ?>
                    <?php foreach ($filmReviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <span class="review-author"><?= htmlspecialchars($review['username']) ?></span>
                                <span style="color: #fbbf24;">★ <?= $review['rating'] ?></span>
                            </div>
                            <div class="review-text"><?= htmlspecialchars($review['comment']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Sidebar (Funding) -->
        <div class="film-sidebar">
            <div class="funding-card">
                <?php if ($film['funding_goal'] > 0): ?>
                    <div class="funding-header">
                        <h3 class="section-title" style="margin-bottom: 5px; font-size: 20px;">Support Project</h3>
                        <p style="color: #888; font-size: 13px;">Help bring this story to life</p>
                    </div>

                    <?php
                    $percent = min(100, round(($film['funding_raised'] / $film['funding_goal']) * 100));
                    ?>

                    <div class="funding-amount">
                        $<?= number_format($film['funding_raised']) ?>
                        <span class="funding-goal">/ $<?= number_format($film['funding_goal']) ?></span>
                    </div>

                    <div class="progress-container">
                        <div class="progress-val" style="width: <?= $percent ?>%;"></div>
                    </div>

                    <?php if ($donationMsg): ?>
                        <div
                            style="background: rgba(52, 199, 89, 0.2); color: #34c759; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px;">
                            <?= htmlspecialchars($donationMsg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <form method="POST" class="donate-form">
                            <label>Amount (USD)</label>
                            <input type="number" name="donate_amount" class="donate-input" min="5" value="10">

                            <label>Message (Optional)</label>
                            <input type="text" name="donate_message" class="donate-input" placeholder="Encouragement...">

                            <button type="submit" class="btn-donate">Back this Project</button>
                        </form>
                    <?php else: ?>
                        <a href="login.php" class="btn-donate"
                            style="display: block; text-align: center; text-decoration: none;">Sign In to Donate</a>
                    <?php endif; ?>

                    <div class="recent-donors">
                        <h4 style="font-size: 14px; margin-bottom: 15px; color: #fff;">Recent Backers</h4>
                        <?php if (empty($recentDonors)): ?>
                            <p style="font-size: 13px; color: #666;">Be the first backer!</p>
                        <?php else: ?>
                            <?php foreach ($recentDonors as $d): ?>
                                <div class="donor-row">
                                    <strong style="color: #ddd;"><?= htmlspecialchars($d['username']) ?></strong>
                                    <span style="color: #666;">contributed $<?= $d['amount'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- No Funding Goal / Show Profile -->
                    <div class="funding-header">
                        <h3 class="section-title" style="margin-bottom: 5px;">Filmmaker</h3>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <div style="width: 50px; height: 50px; background: #333; border-radius: 50%;"></div>
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($film['filmmaker_name']) ?></div>
                            <div style="font-size: 12px; color: #888;">Creator</div>
                        </div>
                    </div>
                    <button class="btn-donate" style="background: #333;">View Profile</button>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>

</html>