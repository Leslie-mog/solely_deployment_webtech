<?php
require_once __DIR__ . '/../src/auth.php';
requireLogin();

// Ensure only filmmakers/admins can upload
if ($_SESSION['role'] !== 'filmmaker' && $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$error = '';
$success = '';

// Handle Form Submission
// Handle Form Submission
$title = '';
$synopsis = '';
$category_id = null;
$duration = 0;
$funding_goal = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? 'Untitled';
    $synopsis = $_POST['synopsis'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $duration = $_POST['duration'] ?? 0;
    $funding_goal = $_POST['funding_goal'] ?? 0;
    $video_url_link = trim($_POST['video_url_link'] ?? '');
    $trailer_url_link = trim($_POST['trailer_url_link'] ?? '');

    // Validate URLs are provided
    if (empty($video_url_link)) {
        $error = "Please provide a video URL link.";
    } elseif (empty($trailer_url_link)) {
        $error = "Please provide a trailer URL link.";
    } else {
        try {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);

            function handleUpload($fileKey, $maxSizeMB = 100)
            {
                global $supabase; // Access global Supabase instance

                // Check if the file actually exists and has no errors
                if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                    return null;
                }

                // If the file is bigger than 100MB, reject it
                $fileSize = $_FILES[$fileKey]['size'];
                $maxSizeBytes = $maxSizeMB * 1024 * 1024; // Convert MB to bytes

                if ($fileSize > $maxSizeBytes) {
                    throw new Exception("File size exceeds maximum allowed size of {$maxSizeMB}MB.");
                }

                // Simple MIME check (optional but recommended)
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES[$fileKey]['tmp_name']);
                if (strpos($mimeType, 'image/') !== 0) {
                    throw new Exception("Only image files are allowed.");
                }

                // Give the file a random unique name
                $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
                $filename = uniqid($fileKey . '_') . '.' . $ext;

                // Upload to Supabase Storage 'uploads' bucket
                try {
                    // Note: You must create a PUBLIC bucket named 'uploads' in your Supabase project first
                    return $supabase->uploadFile('uploads', $filename, $_FILES[$fileKey]['tmp_name'], $mimeType);
                } catch (Exception $e) {
                    throw new Exception("Storage Error: " . $e->getMessage());
                }
            }

            try {
                $posterUrl = handleUpload('poster');
                // Trailer is now a link, handled above
                $thumbnailUrl = handleUpload('thumbnail');
            } catch (Exception $uploadError) {
                throw new Exception("Upload error: " . $uploadError->getMessage());
            }

            // Supabase Insert - video_url now stores the external link
            $response = $supabase->request('POST', 'films', [
                'filmmaker_id' => $_SESSION['user_id'],
                'title' => $title,
                'synopsis' => $synopsis,
                'category_id' => $category_id,
                'duration_minutes' => $duration,
                'funding_goal' => $funding_goal,
                'poster_url' => $posterUrl,
                'video_url' => $video_url_link, // External video URL
                'trailer_url' => $trailer_url_link, // External trailer URL
                'thumbnail_url' => $thumbnailUrl,
                'status' => 'pending'
                // created_at is default
            ]);

            if (empty($response) || !isset($response[0]['id'])) {
                throw new Exception("Failed to create film record.");
            }
            $filmId = $response[0]['id'];

            // Handle Credits
            $credits = [
                ['role' => 'Director', 'name' => $_POST['director_name'] ?? ''],
                ['role' => 'Writer', 'name' => $_POST['writer_name'] ?? ''],
                ['role' => 'Producer', 'name' => $_POST['producer_name'] ?? '']
            ];

            foreach ($credits as $credit) {
                if (!empty($credit['name'])) {
                    $supabase->request('POST', 'film_credits', [
                        'film_id' => $filmId,
                        'role' => $credit['role'],
                        'name' => $credit['name']
                    ]);
                }
            }

            $success = "Film uploaded successfully! It is now pending approval.";
            header("refresh:2;url=filmmaker_dashboard.php");

        } catch (Exception $e) {
            $error = "Upload failed: " . $e->getMessage();
        }
    }
}


// Fetch Categories for Dropdown
try {
    $cats = $supabase->request('GET', 'categories');
} catch (Exception $e) {
    $cats = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Film - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .step-container {
            display: none;
        }

        .step-container.active {
            display: block;
        }

        .step-indicators {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .step-indicator {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            color: #aaa;
        }

        .step-indicator.active {
            background: var(--accent-blue);
            color: white;
        }
    </style>
</head>

<body>
    <header class="main-header">
        <div class="logo">Upload Project</div>
        <div class="auth-buttons">
            <a href="filmmaker_dashboard.php" class="btn btn-glass">Back to Dashboard</a>
        </div>
    </header>

    <main class="main-content" style="padding-top: 100px; padding-bottom: 50px;">
        <div class="auth-box" style="width: 800px; margin: 0 auto; text-align: left;">
            <h1>Submit Your Film</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="step-indicators">
                <div class="step-indicator active" id="ind-1">1. Project Info</div>
                <div class="step-indicator" id="ind-2">2. Credits</div>
                <div class="step-indicator" id="ind-3">3. Media</div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">

                <!-- Info -->
                <div class="step-container active" id="step-1">
                    <div class="form-group">
                        <label class="form-label">Project Title</label>
                        <input type="text" name="title" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Synopsis</label>
                        <textarea name="synopsis" class="form-input" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-input">
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration (minutes)</label>
                        <input type="number" name="duration" class="form-input" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Funding Goal ($)</label>
                        <input type="number" name="funding_goal" class="form-input" value="0">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="showStep(2)">Next: Credits</button>
                </div>

                <!-- Credits -->
                <div class="step-container" id="step-2">
                    <div class="form-group">
                        <label class="form-label">Director Name</label>
                        <input type="text" name="director_name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Writer Name</label>
                        <input type="text" name="writer_name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Producer Name</label>
                        <input type="text" name="producer_name" class="form-input">
                    </div>
                    <button type="button" class="btn btn-glass" onclick="showStep(1)">Back</button>
                    <button type="button" class="btn btn-primary" onclick="showStep(3)">Next: Media</button>
                </div>

                <!-- Media -->
                <div class="step-container" id="step-3">
                    <div class="form-group">
                        <label class="form-label">Poster Image (JPG/PNG)</label>
                        <input type="file" name="poster" class="form-input" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Thumbnail (Optional)</label>
                        <input type="file" name="thumbnail" class="form-input" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trailer Video URL *</label>
                        <input type="url" name="trailer_url_link" class="form-input"
                            placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..." required>
                        <small style="color: #aaa; font-size: 12px;">Provide a link to your trailer (YouTube, Vimeo,
                            etc.)</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Film Video URL *</label>
                        <input type="url" name="video_url_link" class="form-input"
                            placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..." required>
                        <small style="color: #aaa; font-size: 12px;">Provide a link to your full film (YouTube, Vimeo,
                            or other video hosting service)</small>
                    </div>

                    <button type="button" class="btn btn-glass" onclick="showStep(2)">Back</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--accent-blue);">Submit
                        Film</button>
                </div>

            </form>
        </div>
    </main>

    <script>
        function showStep(step) {
            document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-indicator').forEach(el => el.classList.remove('active'));

            document.getElementById('step-' + step).classList.add('active');
            document.getElementById('ind-' + step).classList.add('active');
        }
    </script>
</body>

</html>