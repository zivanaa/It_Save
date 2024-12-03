<?php
session_start();
include "header.php";

if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login terlebih dahulu.";
    exit();
}

$koneksi = mysqli_connect("localhost", "root", "", "db_itsave");

if (mysqli_connect_errno()) {
    echo "<script>alert('Koneksi database gagal: " . mysqli_connect_error() . "');</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "
    SELECT pa.id, pa.post_id, pa.user_id, pa.action_type, pa.created_at, 
           p.content AS post_content, 
           u.name AS action_user_name, u.username AS action_user_username, u.profile_image AS action_user_image, 
           CASE
               WHEN pa.action_type = 'like' THEN 'liked'
               WHEN pa.action_type = 'dislike' THEN 'disliked'
               WHEN pa.action_type = 'repost' THEN 'reposted'
           END AS action_text,
           NULL AS comment_content
    FROM post_actions pa
    JOIN posts p ON pa.post_id = p.id
    JOIN users u ON pa.user_id = u.id
    WHERE p.user_id = $user_id AND pa.user_id != $user_id
    UNION ALL
    SELECT c.id, c.post_id, c.user_id, 'comment' AS action_type, c.created_at, 
           p.content AS post_content, 
           u.name AS action_user_name, u.username AS action_user_username, u.profile_image AS action_user_image, 
           'commented' AS action_text,
           c.comment AS comment_content
    FROM comments c
    JOIN posts p ON c.post_id = p.id
    JOIN users u ON c.user_id = u.id
    WHERE p.user_id = $user_id AND c.user_id != $user_id
    UNION ALL
    SELECT n.id, n.post_id, n.user_id, 'tag' AS action_type, n.created_at, 
           p.content AS post_content, 
           u.name AS action_user_name, u.username AS action_user_username, u.profile_image AS action_user_image, 
           'tagged you in' AS action_text,
           NULL AS comment_content
    FROM notifications n
    JOIN posts p ON n.post_id = p.id
    JOIN users u ON n.user_id = u.id
    WHERE n.user_id = $user_id
    ORDER BY created_at DESC";

$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo "Error: " . mysqli_error($koneksi);
    exit();
}
?>

<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<div class="container" style="margin-top: 15px;">
    <h2>Notifications</h2>
    <div class="list-group">
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <a href="?mod=detail_post&post_id=<?= $row['post_id'] ?>" class="list-group-item list-group-item-action" style="display: flex; align-items: center;">
                <img src="<?= !empty($row['action_user_image']) ? htmlspecialchars($row['action_user_image']) : 'assets/profile/none.png' ?>" class="rounded-circle" alt="User Image" style="width: 50px; height: 50px; margin-right: 15px;">
                <div>
                    <strong><?= htmlspecialchars($row['action_user_name']) ?> (<?= htmlspecialchars($row['action_user_username']) ?>)</strong> 
                    <?= $row['action_text'] ?>
                    <?php if ($row['action_type'] == 'comment'): ?>
                        on your post: "<?= htmlspecialchars($row['post_content']) ?>"
                        <br>Comment: "<?= htmlspecialchars($row['comment_content']) ?>"
                    <?php elseif ($row['action_type'] == 'tag'): ?>
                        in your post: "<?= htmlspecialchars($row['post_content']) ?>"
                    <?php else: ?>
                        your post: "<?= htmlspecialchars($row['post_content']) ?>"
                    <?php endif; ?>
                    <br><small class="text-muted"><?= date('F j, Y, g:i a', strtotime($row['created_at'])) ?></small>
                </div>
            </a>
        <?php endwhile; ?>
    </div>
</div>

<?php include "footer.php"; ?>
