<?php
session_start();
require 'db.php'; // PDO connection

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: loginreg.php');
    exit;
}

// ---------- Handle AJAX actions: create_post, like_post, create_comment ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];

    // CREATE POST
    if ($action === 'create_post') {
        $content = trim($_POST['content'] ?? '');
        $imagePath = null;

        if ($content === '' && empty($_FILES['image']['name'])) {
            echo json_encode(['success' => false, 'message' => 'Post cannot be empty.']);
            exit;
        }

        if (!empty($_FILES['image']['name'])) {
            $uploadDir = __DIR__ . '/uploads/posts/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                    echo json_encode(['success' => false, 'message' => 'Unable to create upload directory.']);
                    exit;
                }
            }

            $origName = basename($_FILES['image']['name']);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array(strtolower($ext), $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
                exit;
            }

            $safeName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $targetPath = $uploadDir . $safeName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
                exit;
            }

            // store relative path for serving
            $imagePath = 'uploads/posts/' . $safeName;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
            $ok = $stmt->execute([$user_id, $content, $imagePath]);
            if ($ok) {
                echo json_encode(['success' => true]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }

    // LIKE TOGGLE
    if ($action === 'like_post') {
        $post_id = intval($_POST['post_id'] ?? 0);
        if ($post_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid post id']);
            exit;
        }

        try {
            // check if like exists
            $check = $pdo->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ? LIMIT 1");
            $check->execute([$post_id, $user_id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                // remove like
                $del = $pdo->prepare("DELETE FROM likes WHERE id = ?");
                $del->execute([$existing['id']]);
                // count likes
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
                $cnt->execute([$post_id]);
                $likes = (int)$cnt->fetchColumn();
                echo json_encode(['success' => true, 'liked' => false, 'likes' => $likes]);
                exit;
            } else {
                // insert like
                $ins = $pdo->prepare("INSERT INTO likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
                $ins->execute([$post_id, $user_id]);
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
                $cnt->execute([$post_id]);
                $likes = (int)$cnt->fetchColumn();
                echo json_encode(['success' => true, 'liked' => true, 'likes' => $likes]);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }

    // CREATE COMMENT
    if ($action === 'create_comment') {
        $post_id = intval($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($post_id <= 0 || $content === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }

        try {
            $ins = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
            $ins->execute([$post_id, $user_id, $content]);

            // fetch comment with user data to return
            $lastId = $pdo->lastInsertId();
            $q = $pdo->prepare("SELECT c.*, u.first_name, u.last_name, u.avatar AS user_avatar FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?");
            $q->execute([$lastId]);
            $comment = $q->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'comment' => $comment]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }

    // unknown action
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
// ---------- end AJAX handlers ----------
// ---------- Fetch user info ----------
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT first_name, last_name, username, email, avatar, bio, cover_photo 
                       FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$avatar = $user['avatar'] ?? 'https://via.placeholder.com/150';
$cover = $user['cover_photo'] ?? null;
$username = $user['username'] ?? 'User';

// ---------- Theme ----------
$theme = $_SESSION['theme'] ?? 'dark';
// ---------- Fetch posts (for this user only) ----------
$postStmt = $pdo->prepare("
    SELECT p.*, 
           u.first_name, 
           u.last_name, 
           u.avatar AS user_avatar,
           (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count,
           (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) AS comment_count,
           (SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id = p.id) AS share_count
    FROM posts p
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$postStmt->execute([$user_id]); 
$posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Fetch recent comments for each post ----------
$commentsByPost = [];
if (!empty($posts)) {
    $postIds = array_column($posts, 'id');
    $q = $pdo->prepare("
        SELECT pc.*, 
               u.first_name, 
               u.last_name, 
               u.avatar AS user_avatar 
        FROM post_comments pc
        LEFT JOIN users u ON u.id = pc.user_id 
        WHERE pc.post_id = ? 
        ORDER BY pc.created_at ASC
    ");

    foreach ($postIds as $pid) {
        $q->execute([$pid]);
        $commentsByPost[$pid] = $q->fetchAll(PDO::FETCH_ASSOC);
    }
}


// ---------- Utility: format time ----------
function timeAgo($datetime) {
    date_default_timezone_set('Asia/Manila'); // ensure consistent timezone

    $timestamp = strtotime($datetime);
    $current = time();
    $diff = $current - $timestamp;

    // 🛠 Fix: if negative (future time), treat as "just now"
    if ($diff < 0) $diff = 0;

    $seconds = $diff;
    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);

    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return $days == 1 ? "Yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}


?>
<!DOCTYPE html>
<html lang="en" class="<?= $theme === 'dark' ? 'dark' : '' ?>">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Profile</title>
<script>tailwind = {config: {darkMode: 'class'}}</script>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">

<style>
  body { font-family: 'Poppins', sans-serif; }
  .cover-photo { object-fit: cover; width: 100%; height: 220px; }
  /* Facebook-like subtle card */
  .fb-card { background: var(--card-bg, white); border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .compose-placeholder { background: #f0f2f5; border-radius: 999px; padding: 10px 14px; cursor: text; }
  .action-btn { display:flex; align-items:center; gap:8px; padding:8px 12px; font-weight:500; border-radius:8px; cursor:pointer; }
  .sep { height:1px; background: #e9ecef; margin: 8px 0; }
  /* dark adjustments */
  .dark .fb-card { --card-bg: #1f2937; }
  .dark .compose-placeholder { background: #111827; color: #e5e7eb; }
  .comment-input { resize:none; }
</style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">

<!-- Layout wrapper -->
<div class="flex">
<!-- Sidebar -->
<nav id="sidebar" class="sidebar bg-[#11121a] w-56 min-h-screen p-4 flex-shrink-0 transition-all duration-300 flex flex-col relative">
  
  <!-- Logo -->
  <div class="flex items-center mb-4">
    <span class="text-white font-semibold text-[15px] ml-[10px] mt-[10px] sidebar-text truncate">Conversative</span>
  </div>

  <!-- Toggle Button -->
  <button id="toggleBtn" class="absolute top-4 right-4 p-2 rounded hover:bg-[#222533] z-50">
    <svg id="toggleArrow" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed">
      <path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"></path>
    </svg>
  </button>

  <!-- Menu -->
  <ul class="flex-1 flex flex-col justify-start space-y-2 mt-6 overflow-y-auto">
    <?php
      $menu = [
        ['href' => 'dashboard.php', 'icon' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z', 'text' => 'Dashboard'],
        ['href' => 'chat.php', 'icon' => 'M20 2H4C2.897 2 2 2.897 2 4v14c0 1.103.897 2 2 2h14l4 4V4c0-1.103-.897-2-2-2zm-2 12H6v-2h12v2zm0-4H6V8h12v2z', 'text' => 'Chat'],
        ['href' => 'profile.php', 'icon' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z', 'text' => 'Profile'],
        ['href' => 'settings.php', 'icon' => 'M19.14 12.936c.036-.3.056-.607.056-.936s-.02-.636-.056-.936l2.036-1.581a.495.495 0 00.118-.641l-1.82-3.146a.494.494 0 00-.609-.22l-2.39.958a6.992 6.992 0 00-1.63-.943l-.362-2.525A.495.495 0 0014.001 2h-4a.495.495 0 00-.491.426l-.362 2.525c-.57.247-1.116.578-1.63.943l-2.39-.958a.494.494 0 00-.609.22l-1.82 3.146a.495.495 0 00.118.641l2.036 1.581c-.036.3-.056.607-.056.936s.02.636.056.936l-2.036 1.581a.495.495 0 00-.118.641l1.82 3.146c.122.221.39.3.609.22l2.39-.958c.514.365 1.06.696 1.63.943l.362 2.525c.03.237.242.426.491.426h4c.25 0 .46-.189.491-.426l.362-2.525c.514-.247 1.06-.578 1.63-.943l2.39.958c.22.08.487 0 .609-.22l1.82-3.146a.495.495 0 00-.118-.641l-2.036-1.581zM12 15.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z', 'text' => 'Settings'],
        ['href' => 'logout.php', 'icon' => 'M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z', 'text' => 'Logout']
      ];
      foreach ($menu as $item):
    ?>
      <li>
        <a href="<?= $item['href'] ?>" class="flex items-center gap-3 text-[#e6e6ef] p-2 rounded hover:bg-[#222533] transition-colors whitespace-nowrap">
          <svg class="w-5 h-5 flex-shrink-0 text-[#e6e6ef]" fill="currentColor" viewBox="0 0 24 24">
            <path d="<?= $item['icon'] ?>" />
          </svg>
          <span class="sidebar-text"><?= $item['text'] ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

</nav>
  <!-- Main content -->
  <main class="flex-1 p-6 flex justify-center">
    <div class="w-full max-w-3xl">

      <!-- Profile Header -->
      <div class="fb-card overflow-hidden mb-6">
        <div class="relative">
          <?php if ($cover): ?>
            <img src="<?= htmlspecialchars($cover) ?>" alt="Cover Photo" class="cover-photo block">
          <?php else: ?>
            <div class="cover-photo bg-gray-200 dark:bg-gray-700"></div>
          <?php endif; ?>
          <button id="addCoverBtn" class="absolute top-4 right-4 bg-white dark:bg-gray-800 text-indigo-600 dark:text-white px-3 py-1 rounded shadow">Add Cover Photo</button>
          <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="absolute left-6 -bottom-12 w-28 h-28 rounded-full border-4 border-white dark:border-gray-800 object-cover">
        </div>
        <div class="pt-16 pb-6 px-6">
          <h1 class="text-2xl font-semibold"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h1>
          <p class="text-sm text-gray-500">@<?= htmlspecialchars($username) ?></p>
        </div>
      </div>

      <!-- Bio Card -->
      <div class="fb-card p-5 mb-6">
        <h2 class="text-lg font-semibold mb-2">Bio</h2>
        <p id="bioDisplay" class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($user['bio']) ?: 'Add a short bio to tell people about yourself.' ?></p>
        <textarea id="bioField" class="w-full mt-3 p-3 border rounded hidden dark:bg-gray-800" rows="4"><?= htmlspecialchars($user['bio']) ?></textarea>
        <div class="flex justify-end mt-3">
          <button id="saveBioBtn" class="hidden px-3 py-1 bg-indigo-600 text-white rounded">Save</button>
          <button id="editBioBtn" class="px-3 py-1 bg-green-600 text-white rounded ml-2">Edit</button>
          <span id="bioStatus" class="ml-3 self-center text-sm"></span>
        </div>
      </div>

      <!-- Facebook-style Composer -->
      <div class="fb-card p-4 mb-6">
        <div class="flex items-start gap-3">
          <img src="<?= htmlspecialchars($avatar) ?>" alt="me" class="w-12 h-12 rounded-full object-cover">
          <div class="flex-1">
            <div id="composer" class="bg-white dark:bg-gray-800 p-3 rounded-md border">
              <textarea id="postContent" rows="3" placeholder="What's on your mind?" class="w-full resize-none border-none focus:outline-none bg-transparent text-gray-900 dark:text-gray-100"></textarea>
              <div class="mt-3 flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M21 6.5a2 2 0 0 0-2-2h-3l-1-2H9L8 4.5H5a2 2 0 0 0-2 2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.5z"/></svg>
                    <input type="file" id="postImage" accept="image/*" class="hidden">
                    Photo
                  </label>
                  <button id="feelingBtn" class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.75c-2.97 0-5.49-1.9-6.4-4.5h12.8c-.91 2.6-3.43 4.5-6.4 4.5zM6.5 10.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm11 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
                    Feeling
                  </button>
                </div>
                <div class="flex items-center gap-3">
                  <span id="fileName" class="text-sm text-gray-500"></span>
                  <button id="postBtn" class="bg-blue-600 text-white px-4 py-1.5 rounded font-medium">Post</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Feed -->
      <div class="space-y-4">
        <?php if ($posts): ?>
          <?php foreach ($posts as $post): ?>
            <div class="fb-card p-4" data-post-id="<?= htmlspecialchars($post['id']) ?>">
              <div class="flex items-start gap-3">
                <img src="<?= htmlspecialchars($post['user_avatar'] ?: $avatar) ?>" alt="user" class="w-12 h-12 rounded-full object-cover">
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="font-semibold"><?= htmlspecialchars($post['first_name'].' '.$post['last_name']) ?></div>
                      <div class="text-xs text-gray-500"><?= htmlspecialchars(timeAgo($post['created_at'])) ?></div>
                    </div>
                    <div class="text-gray-500 text-sm">• • •</div>
                  </div>
                  <div class="mt-3 text-gray-800 dark:text-gray-200"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                  <?php if (!empty($post['image'])): ?>
                    <div class="mt-3">
                      <img src="<?= htmlspecialchars($post['image']) ?>" class="w-full rounded-md object-cover max-h-[500px]" alt="post image">
                    </div>
                  <?php endif; ?>
                  <!-- Facebook style action bar -->
                  <div class="mt-3 border-t pt-3 flex items-center justify-between text-sm text-gray-600">
                    <div class="flex items-center gap-6">
                      <button class="action-btn like-btn" data-post-id="<?= htmlspecialchars($post['id']) ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 like-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l-1 7H5l6 6-2 8 8-6 3-6h-6l1-9z"/></svg> <span class="like-text">Like</span>
                      </button>
                      <button class="action-btn comment-toggle-btn" data-post-id="<?= htmlspecialchars($post['id']) ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2 3v14l4-4h14V3z"/></svg> Comment
                      </button>
                      <button class="action-btn"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8L14 8v-4l7 7.5zM3 12.5a8.38 8.38 0 00.9 3.8L10 8v-4L3 12.5z"/></svg> Share</button>
                    </div>
                    <div class="text-gray-500 like-count" data-count="<?= htmlspecialchars($post['like_count']) ?>"><?= htmlspecialchars($post['like_count']) ?> Likes</div>
                  </div>

                  <!-- Comments list -->
                  <div class="mt-3 comments-list">
                    <?php
                      $pid = $post['id'];
                      $comments = $commentsByPost[$pid] ?? [];
                      if ($comments) {
                        foreach ($comments as $c) {
                          ?>
                          <div class="flex items-start gap-3 mb-3">
                            <img src="<?= htmlspecialchars($c['user_avatar'] ?: $avatar) ?>" alt="cuser" class="w-9 h-9 rounded-full object-cover">
                            <div class="bg-gray-100 dark:bg-gray-800 p-3 rounded-md w-full">
                              <div class="text-sm font-semibold"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></div>
                              <div class="text-xs text-gray-500"><?= htmlspecialchars(timeAgo($c['created_at'])) ?></div>
                              <div class="mt-1 text-gray-800 dark:text-gray-200"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                            </div>
                          </div>
                          <?php
                        }
                      }
                    ?>
                  </div>

                  <!-- Comment form (hidden by default) -->
                  <div class="mt-2 comment-form hidden" data-post-id="<?= htmlspecialchars($post['id']) ?>">
                    <div class="flex items-start gap-3">
                      <img src="<?= htmlspecialchars($avatar) ?>" alt="me" class="w-9 h-9 rounded-full object-cover">
                      <div class="flex-1">
                        <textarea class="w-full p-2 border rounded comment-input" rows="2" placeholder="Write a comment..."></textarea>
                        <div class="flex justify-end mt-2">
                          <button class="px-3 py-1 bg-indigo-600 text-white rounded submit-comment-btn" data-post-id="<?= htmlspecialchars($post['id']) ?>">Comment</button>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="fb-card p-6 text-center text-gray-500 dark:text-gray-300">No posts yet.</div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<!-- Cover Modal (kept as in original) -->
<div id="addCoverModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-lg w-full max-w-md">
    <h2 class="text-xl font-bold mb-4">Add Cover Photo</h2>
    <form id="coverForm" class="space-y-3" enctype="multipart/form-data">
      <div>
        <label class="block mb-1">Select Cover Photo</label>
        <input type="file" name="cover_photo" accept="image/*" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
      </div>
      <div class="flex justify-end gap-2 mt-2">
        <span id="coverStatus" class="self-center text-sm"></span>
        <button type="button" id="closeCoverModal" class="px-3 py-1 bg-gray-300 dark:bg-gray-600 rounded hover:bg-gray-400 dark:hover:bg-gray-500">Cancel</button>
        <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Upload</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Sidebar toggle (preserved logic)
  const sidebar = document.getElementById('sidebar');
  const toggleArrow = document.getElementById('toggleArrow');
  const toggleBtn = document.getElementById('toggleBtn');
  if (toggleBtn && sidebar) {
  const sidebarTexts = document.querySelectorAll('.sidebar-text');

  toggleBtn.addEventListener('click', () => {
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');

    // Toggle width
    sidebar.classList.toggle('w-16', collapsed);
    sidebar.classList.toggle('w-56', !collapsed);

    // Rotate the arrow
    if (toggleArrow)
      toggleArrow.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';

    // Hide / show sidebar text
    sidebarTexts.forEach(text => {
      if (collapsed) {
        text.style.opacity = '0';
        text.style.width = '0';
        text.style.overflow = 'hidden';
        text.style.pointerEvents = 'none';
      } else {
        text.style.opacity = '1';
        text.style.width = 'auto';
        text.style.overflow = 'visible';
        text.style.pointerEvents = 'auto';
      }
    });
  });
}


  // Cover modal controls (kept)
  const addCoverBtn = document.getElementById('addCoverBtn');
  const coverModal = document.getElementById('addCoverModal');
  const closeCoverModal = document.getElementById('closeCoverModal');
  const coverForm = document.getElementById('coverForm');
  const coverStatus = document.getElementById('coverStatus');
  if (addCoverBtn) addCoverBtn.addEventListener('click', () => coverModal.classList.remove('hidden'));
  if (closeCoverModal) closeCoverModal.addEventListener('click', () => coverModal.classList.add('hidden'));
  if (coverForm) {
    coverForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      coverStatus.textContent = '';
      const fd = new FormData(coverForm);
      try {
        const res = await fetch('upload_cover.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          coverStatus.textContent = 'Cover photo uploaded!';
          setTimeout(() => location.reload(), 900);
        } else {
          coverStatus.textContent = data.error || 'Upload failed';
        }
      } catch (err) {
        coverStatus.textContent = 'Network error';
      }
    });
  }

  // Bio editing (preserved)
  const bioDisplay = document.getElementById('bioDisplay');
  const bioField = document.getElementById('bioField');
  const saveBioBtn = document.getElementById('saveBioBtn');
  const editBioBtn = document.getElementById('editBioBtn');
  const bioStatus = document.getElementById('bioStatus');

  if (editBioBtn) editBioBtn.addEventListener('click', () => {
    bioField.classList.remove('hidden');
    saveBioBtn.classList.remove('hidden');
    editBioBtn.classList.add('hidden');
    bioDisplay.classList.add('hidden');
  });

  if (saveBioBtn) saveBioBtn.addEventListener('click', async () => {
    bioStatus.textContent = '';
    try {
      const res = await fetch('update_bio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'bio=' + encodeURIComponent(bioField.value)
      });
      const data = await res.json();
      if (data.success) {
        bioStatus.textContent = 'Saved!';
        setTimeout(() => bioStatus.textContent = '', 1000);
        bioDisplay.textContent = bioField.value || 'Add a short bio to tell people about yourself.';
        bioDisplay.classList.remove('hidden');
        bioField.classList.add('hidden');
        saveBioBtn.classList.add('hidden');
        editBioBtn.classList.remove('hidden');
      } else {
        bioStatus.textContent = data.error || 'Save failed';
      }
    } catch (err) {
      bioStatus.textContent = 'Network error';
    }
  });

  // Composer: file input handling & posting to same file (action=create_post)
  const postImage = document.getElementById('postImage');
  const fileName = document.getElementById('fileName');
  const postBtn = document.getElementById('postBtn');
  const postContent = document.getElementById('postContent');

  if (postImage) {
    postImage.addEventListener('change', () => {
      if (postImage.files && postImage.files[0]) {
        fileName.textContent = postImage.files[0].name;
      } else {
        fileName.textContent = '';
      }
    });
  }

  if (postBtn) {
    postBtn.addEventListener('click', async () => {
      const content = postContent.value.trim();
      const fd = new FormData();
      fd.append('action', 'create_post');
      fd.append('content', content);
      if (postImage && postImage.files[0]) fd.append('image', postImage.files[0]);

      // simple UI feedback
      postBtn.disabled = true;
      postBtn.textContent = 'Posting...';

      try {
        const res = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          postBtn.textContent = 'Posted';
          setTimeout(() => location.reload(), 800);
        } else {
          alert(data.message || 'Failed to post');
          postBtn.disabled = false;
          postBtn.textContent = 'Post';
        }
      } catch (err) {
        alert('Network error while posting.');
        postBtn.disabled = false;
        postBtn.textContent = 'Post';
      }
    });
  }

  // LIKE handling (AJAX toggle)
  document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const postId = btn.getAttribute('data-post-id');
      if (!postId) return;
      const fd = new FormData();
      fd.append('action', 'like_post');
      fd.append('post_id', postId);

      // disable button briefly
      btn.disabled = true;
      try {
        const res = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          // update like count display
          const card = btn.closest('[data-post-id]');
          if (card) {
            const likeCountEl = card.querySelector('.like-count');
            if (likeCountEl) {
              likeCountEl.textContent = data.likes + ' Likes';
              likeCountEl.setAttribute('data-count', data.likes);
            }
            // toggle style
            const likeIcon = btn.querySelector('.like-icon');
            const likeText = btn.querySelector('.like-text');
            if (data.liked) {
              if (likeIcon) likeIcon.style.opacity = '1';
              if (likeText) likeText.textContent = 'Liked';
            } else {
              if (likeIcon) likeIcon.style.opacity = '';
              if (likeText) likeText.textContent = 'Like';
            }
          }
        } else {
          alert(data.message || 'Failed to like');
        }
      } catch (err) {
        alert('Network error while liking');
      } finally {
        btn.disabled = false;
      }
    });
  });

  // Comment toggle buttons
  document.querySelectorAll('.comment-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const pid = btn.getAttribute('data-post-id');
      const parentCard = btn.closest('[data-post-id]');
      if (!parentCard) return;
      const form = parentCard.querySelector('.comment-form');
      if (form) form.classList.toggle('hidden');
    });
  });

  // Submit comment
  document.querySelectorAll('.submit-comment-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const postId = btn.getAttribute('data-post-id');
      const parentCard = btn.closest('[data-post-id]');
      if (!parentCard) return;
      const textarea = parentCard.querySelector('.comment-input');
      if (!textarea) return;
      const content = textarea.value.trim();
      if (!content) return;

      const fd = new FormData();
      fd.append('action', 'create_comment');
      fd.append('post_id', postId);
      fd.append('content', content);

      btn.disabled = true;
      btn.textContent = 'Posting...';
      try {
        const res = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.comment) {
          // append comment to comments-list
          const list = parentCard.querySelector('.comments-list');
          const c = data.comment;
          const avatar = c.user_avatar || '<?= htmlspecialchars($avatar) ?>';
          // use escapeHtml helper defined below
          const name = escapeHtml((c.first_name || '') + ' ' + (c.last_name || ''));
          const contentEsc = escapeHtml(c.content || '').replace(/\n/g, '<br>');
          const timestamp = 'Just now';
          const html = `
            <div class="flex items-start gap-3 mb-3">
              <img src="${avatar}" alt="cuser" class="w-9 h-9 rounded-full object-cover">
              <div class="bg-gray-100 dark:bg-gray-800 p-3 rounded-md w-full">
                <div class="text-sm font-semibold">${name}</div>
                <div class="text-xs text-gray-500">${timestamp}</div>
                <div class="mt-1 text-gray-800 dark:text-gray-200">${contentEsc}</div>
              </div>
            </div>
          `;
          if (list) list.insertAdjacentHTML('beforeend', html);
          textarea.value = '';
        } else {
          alert(data.message || 'Failed to post comment');
        }
      } catch (err) {
        alert('Network error while commenting');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Comment';
      }
    });
  });

  // simple helper to escape HTML in strings
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
});
</script>
</body>
</html>
