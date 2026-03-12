<?php
include "config.php";

$lost_id = 0;
$found_id = 0;
if (isset($_GET['lost_id']) && isset($_GET['found_id'])) {
    $lost_id = (int)$_GET['lost_id'];
    $found_id = (int)$_GET['found_id'];
} elseif (isset($_GET['user_confirm'])) {
    // Support ?user_confirm=lost_id=...&found_id=...
    parse_str($_GET['user_confirm'], $parsed);
    $lost_id = (int)($parsed['lost_id'] ?? 0);
    $found_id = (int)($parsed['found_id'] ?? 0);
}
$notification = '';
$notificationType = '';
$foundName = '';
$match_status = '';

function getItemName($conn, $item_id) {
    $stmt = $conn->prepare("SELECT name FROM items WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['name'] : '';
}

if ($lost_id <= 0 || $found_id <= 0) {
    $notification = 'Invalid request parameters.';
    $notificationType = 'error';
} else {
    // Find the match
    $stmt = $conn->prepare("SELECT id, status FROM matches WHERE lost_item_id = ? AND found_item_id = ?");
    $stmt->bind_param("ii", $lost_id, $found_id);
    $stmt->execute();
    $matchResult = $stmt->get_result();
    $match = $matchResult->fetch_assoc();
    $stmt->close();

    if (!$match) {
        $notification = 'This match may have already been processed or does not exist.';
        $notificationType = 'error';
        $match_status = 'not_found';
    } else {
        $match_id = $match['id'];
        $current_status = $match['status'];

        // Check if already confirmed
        if ($current_status === 'user_confirmed') {
            $foundName = getItemName($conn, $found_id);
            $notification = 'Thank you for confirming that "' . htmlspecialchars($foundName) . '" is your lost item. Your confirmation has been recorded and forwarded to our administration team for final approval.';
            $notificationType = 'success';
            $match_status = 'confirmed';
        } elseif ($current_status === 'confirmed') {
            $notification = 'This match has already been approved by our administration team. Please check your email for pickup instructions.';
            $notificationType = 'info';
            $match_status = 'admin_confirmed';
        } elseif ($current_status === 'rejected') {
            $notification = 'This match has been rejected. We will continue searching for your lost item.';
            $notificationType = 'warning';
            $match_status = 'rejected';
        } else {
            // Update match status to user_confirmed
            $stmt = $conn->prepare("UPDATE matches SET status = 'user_confirmed' WHERE id = ?");
            $stmt->bind_param("i", $match_id);
            $stmt->execute();
            $stmt->close();

            $foundName = getItemName($conn, $found_id);
            $notification = 'Thank you for confirming that "' . htmlspecialchars($foundName) . '" is your lost item. Your confirmation has been recorded and forwarded to our administration team for final approval.';
            $notificationType = 'success';
            $match_status = 'confirmed';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Item Match Confirmation — JB's Print&amp;Go</title>
  <link rel="icon" type="image/png" href="logo.png">
  <link rel="stylesheet" href="style.css">
  <style>
    .confirm-wrapper {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      background: #f7f8fa;
    }
    .confirm-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.10);
      padding: 2.5rem 2rem;
      max-width: 520px;
      width: 100%;
      text-align: center;
    }
    .confirm-card .brand-logo {
      width: 80px;
      margin-bottom: 1.2rem;
    }
    .confirm-card h1 {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
      color: #1a1a2e;
    }
    .alert {
      border-radius: 8px;
      padding: 1rem 1.25rem;
      margin: 1.5rem 0 1rem;
      font-size: 1rem;
      line-height: 1.6;
      text-align: left;
    }
    .alert-success  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error    { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .alert-info     { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    .alert-warning  { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .alert-icon {
      font-size: 1.4rem;
      margin-right: 0.5rem;
      vertical-align: middle;
    }
    .confirm-card .back-link {
      display: inline-block;
      margin-top: 1.5rem;
      padding: 0.65rem 1.5rem;
      background: #1a1a2e;
      color: #fff;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: background 0.2s;
    }
    .confirm-card .back-link:hover {
      background: #2d2d5e;
    }
  </style>
</head>
<body>
  <div class="confirm-wrapper">
    <div class="confirm-card">
      <img src="logo.png" alt="JB's Print&amp;Go Logo" class="brand-logo">
      <h1>Item Match Confirmation</h1>

      <?php if ($notification): ?>
        <?php
          $icons = [
            'success' => '✅',
            'error'   => '❌',
            'info'    => 'ℹ️',
            'warning' => '⚠️',
          ];
          $icon = $icons[$notificationType] ?? 'ℹ️';
        ?>
        <div class="alert alert-<?php echo htmlspecialchars($notificationType); ?>">
          <span class="alert-icon"><?php echo $icon; ?></span>
          <?php echo $notification; ?>
        </div>

        <?php if ($match_status === 'confirmed'): ?>
          <p style="color:#555;font-size:0.95rem;">
            Our admin team will review your confirmation and contact you with pickup instructions.
          </p>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-info">
          <span class="alert-icon">ℹ️</span>
          No confirmation action was taken.
        </div>
      <?php endif; ?>

      <a href="index.html" class="back-link">← Back to Home</a>
    </div>
  </div>
</body>
</html>
