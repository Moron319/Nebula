<?php
include 'config.php'; 
// Include the configuration file with the database connection ($conn)

// Get thread ID and board name from URL parameters
$thread_id = $_GET['id']; // Thread ID
$board = $_GET['board'];  // Board name

// Query the database to get thread data by its ID
$stmt = $conn->prepare("SELECT * FROM threads WHERE id = :thread_id");
$stmt->bindParam(':thread_id', $thread_id);
$stmt->execute();
$thread = $stmt->fetch(); 

// If the thread is not found, show an error or redirect
if (!$thread) {
    die("Thread not found.");
}

// Save the IP address of the thread creator (OP - original poster)
$op_ip_address = $thread['ip_address']; 

// Handling new post submission (reply)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['content'])) {
    $content = $_POST['content']; // Reply text
    $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $media_path = null;

    // Handling file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = 'uploads/';

        // Check if the directory exists, if not - create it
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate a unique filename to avoid conflicts
        $file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_ext;
        $media_path = $upload_dir . $file_name;

        // Move the uploaded file
        move_uploaded_file($_FILES['file']['tmp_name'], $media_path);
    }

    // Determine if the author is the OP (original poster)
    $is_op = ($_SERVER['REMOTE_ADDR'] == $op_ip_address);

    // Insert the new post into the database
    $stmt = $conn->prepare("INSERT INTO posts (thread_id, content, is_op, parent_id, media_path, ip_address) VALUES (:thread_id, :content, :is_op, :parent_id, :media_path, :ip_address)");
    $stmt->bindParam(':thread_id', $thread_id);
    $stmt->bindParam(':content', $content);
    $stmt->bindParam(':is_op', $is_op, PDO::PARAM_BOOL);
    if ($parent_id === null) {
        $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
    }
    $stmt->bindParam(':media_path', $media_path, PDO::PARAM_STR);
    $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $stmt->execute();

    // Redirect after submission to prevent resubmission
    header("Location: thread.php?id=$thread_id&board=$board");
    exit;
}

// Get all posts for the thread
$stmt = $conn->prepare("SELECT * FROM posts WHERE thread_id = :thread_id ORDER BY created_at ASC");
$stmt->bindParam(':thread_id', $thread_id);
$stmt->execute();
$posts = $stmt->fetchAll();

// Prepare array of replies for recursive display
$replies = [];
foreach ($posts as $post) {
    if ($post['parent_id']) {
        $replies[$post['parent_id']][] = $post;
    }
}

// Recursive function to display posts with nested replies
function displayReplies($post, $replies, $op_ip_address) {
    echo "<li>";
    
    // Show id and date
    echo "id: <strong>" . htmlspecialchars($post['id']) . "</strong> | Date: " . htmlspecialchars($post['created_at']) . "<br>";
    
    // Content with safe output and line breaks
    echo nl2br(htmlspecialchars($post['content'])) . "<br>";
    
    // OP mark if IP matches
    if ($post['ip_address'] == $op_ip_address) {
        echo "<strong> - OP (original poster)</strong>";
    }

    // Display media if available
    if (!empty($post['media_path'])) {
        $file_ext = strtolower(pathinfo($post['media_path'], PATHINFO_EXTENSION));

        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' target='_blank'>
                    <img src='" . htmlspecialchars($post['media_path']) . "' width='350' height='350' alt='Image'>
                  </a>";
        } elseif (in_array($file_ext, ['mp4', 'avi', 'mov'])) {
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' target='_blank'>
                    <video width='350' height='350' controls>
                        <source src='" . htmlspecialchars($post['media_path']) . "' type='video/{$file_ext}'>
                    </video>
                  </a>";
        } elseif (in_array($file_ext, ['mp3', 'wav', 'ogg'])) {
            echo "<br><audio controls>
                    <source src='" . htmlspecialchars($post['media_path']) . "' type='audio/{$file_ext}'>
                    Your browser does not support the audio element.
                  </audio>";
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' download>Download audio</a>";
        } else {
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' download>Download file</a>";
        }
    }

    // Reply form for this post
    echo "<form method='POST' enctype='multipart/form-data' style='margin-top:10px; margin-bottom:20px;'>
            <textarea name='content' placeholder='Reply to this post' required rows='3' cols='50'></textarea><br>
            <label>Attach file (optional):</label>
            <input type='file' name='file' accept='image/*,video/*,audio/*,application/*'><br>
            <input type='hidden' name='parent_id' value='" . htmlspecialchars($post['id']) . "' />
            <button type='submit'>Reply</button>
        </form>";

    // If there are replies, display them recursively
    if (isset($replies[$post['id']])) {
        echo "<ul>";
        foreach ($replies[$post['id']] as $reply) {
            displayReplies($reply, $replies, $op_ip_address);
        }
        echo "</ul>";
    }

    echo "</li>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="data/icon.png" type="image/x-icon"> <!-- Set icon -->
    <link rel="stylesheet" href="css/thread.css"> <!-- Connect CSS -->
    <title><?php echo htmlspecialchars($thread['title']); ?> - Thread</title>
</head>
<body>
    <a href="board.php?board=<?php echo htmlspecialchars($board); ?>">Back to board</a>
    <h1><?php echo htmlspecialchars($thread['title']); ?> <small>(id: <?php echo $thread['id']; ?>)</small></h1>

    <p><?php echo nl2br(htmlspecialchars($thread['content'])); ?></p>

    <?php if (!empty($thread['media_path'])): ?>
        <?php
            $file_ext = strtolower(pathinfo($thread['media_path'], PATHINFO_EXTENSION));
            $media_path = htmlspecialchars($thread['media_path']);
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                <a href="<?php echo $media_path; ?>" target="_blank">
                    <img src="<?php echo $media_path; ?>" alt="Thread image">
                </a>
        <?php elseif (in_array($file_ext, ['mp4', 'avi', 'mov'])): ?>
                <a href="<?php echo $media_path; ?>" target="_blank">
                    <video controls width="350" height="350">
                        <source src="<?php echo $media_path; ?>" type="video/<?php echo $file_ext; ?>">
                        Your browser does not support the video tag.
                    </video>
                </a>
        <?php elseif (in_array($file_ext, ['mp3', 'wav', 'ogg'])): ?>
                <audio controls>
                    <source src="<?php echo $media_path; ?>" type="audio/<?php echo $file_ext; ?>">
                    Your browser does not support the audio element.
                </audio>
                <br>
                <a href="<?php echo $media_path; ?>" download>Download audio</a>
        <?php else: ?>
                <a href="<?php echo $media_path; ?>" download>Download file</a>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Replies</h2>
    <ul>
        <?php foreach ($posts as $post): ?>
            <?php if ($post['parent_id'] === null): ?>
                <?php displayReplies($post, $replies, $op_ip_address); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <h2>Reply to thread</h2>
    <form method="POST" enctype="multipart/form-data">
        <textarea name="content" rows="4" placeholder="Enter your reply" required></textarea><br>
        <label>Attach file (optional):</label>
        <input type="file" name="file" accept="image/*,video/*,audio/*,application/*"><br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
