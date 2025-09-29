<?php
// Include the configuration file, usually contains DB connection and settings
include 'config.php';

// Get 'board' parameter from URL — the board identifier we're working with
$board = $_GET['board'];

// Initialize the board title variable
$board_title = '';

// Determine board title based on the code. If unknown, terminate script with error
if ($board == 'pol') {
    $board_title = '/pol/';
} elseif ($board == 'ph') {
    $board_title = '/ph/';
} else {
    // If board doesn't exist — stop execution and display message
    die('Board does not exist');
}

// Prepare SQL query to get all threads from the current board, ordered by creation date (newest first)
$stmt = $conn->prepare("SELECT * FROM threads WHERE board = :board ORDER BY created_at DESC");
// Bind :board parameter to $board variable to prevent SQL injection
$stmt->bindParam(':board', $board);
// Execute query
$stmt->execute();
// Fetch all results as an array
$threads = $stmt->fetchAll();

// Check if the request is POST (creating new thread) and if thread content is provided
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['content'])) {
    // Get the content and title from the form
    $content = $_POST['content'];
    $title = $_POST['title'];

    // Variables for media file and thumbnail paths, initially null
    $media_path = null;
    $thumb_path = null;

    // Check if a file was uploaded without errors
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        // Directory to upload files
        $upload_dir = 'uploads/';
        // Get just the filename
        $file_name = basename($_FILES['file']['name']);
        // Create a unique filename to avoid conflicts
        $media_path = $upload_dir . uniqid(rand(), true) . '.' . pathinfo($file_name, PATHINFO_EXTENSION);

        // Move the uploaded file to the permanent directory
        move_uploaded_file($_FILES['file']['tmp_name'], $media_path);

        // Get file extension in lowercase
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // If image — create a thumbnail
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $thumb_name = uniqid(rand(), true) . '.' . $file_ext;
            $thumb_path = 'uploads/thumbs/' . $thumb_name;

            // Get original image size
            list($width, $height) = getimagesize($media_path);
            // Thumbnail size fixed at 350x350
            $thumb_width = 350;
            $thumb_height = 350;

            $image = false;

            // Create image resource depending on file type
            if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                $image = imagecreatefromjpeg($media_path);
            } elseif ($file_ext === 'png') {
                $image = imagecreatefrompng($media_path);
            } elseif ($file_ext === 'gif') {
                $image = imagecreatefromgif($media_path);
            }

            // If image resource loaded successfully — create thumbnail
            if ($image !== false) {
                $thumb = imagecreatetruecolor($thumb_width, $thumb_height);

                // For PNG and GIF preserve transparency
                if ($file_ext === 'png' || $file_ext === 'gif') {
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                    imagefilledrectangle($thumb, 0, 0, $thumb_width, $thumb_height, $transparent);
                }

                // Copy and resize original image into thumbnail
                imagecopyresized($thumb, $image, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);

                // Save thumbnail depending on file type
                if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                    imagejpeg($thumb, $thumb_path);
                } elseif ($file_ext === 'png') {
                    imagepng($thumb, $thumb_path);
                } elseif ($file_ext === 'gif') {
                    imagegif($thumb, $thumb_path);
                }

                // Free memory
                imagedestroy($thumb);
                imagedestroy($image);
            }
        }
        // If video — create thumbnail using ffmpeg
        elseif (in_array($file_ext, ['mp4', 'avi', 'mov'])) {
            $thumb_name = uniqid(rand(), true) . '.jpg';
            $thumb_path = 'uploads/thumbs/' . $thumb_name;

            // ffmpeg command: take the first frame at 1 second and save as jpg
            $command = "ffmpeg -i $media_path -ss 00:00:01.000 -vframes 1 $thumb_path";
            exec($command);
        }
    }

    // Insert new thread into database, including media and thumbnail paths if available
    $stmt = $conn->prepare("INSERT INTO threads (board, title, content, media_path, media_thumb_path) VALUES (:board, :title, :content, :media_path, :media_thumb_path)");
    $stmt->bindParam(':board', $board);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':content', $content);
    // media_path and media_thumb_path can be NULL if no file uploaded
    $stmt->bindParam(':media_path', $media_path, PDO::PARAM_STR);
    $stmt->bindParam(':media_thumb_path', $thumb_path, PDO::PARAM_STR);
    $stmt->execute();

    // After creating the thread, redirect back to the board page
    header("Location: board.php?board=" . $board);
    exit;
}
?>

<!-- Start of HTML page -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Output board title in page title -->
    <title><?php echo htmlspecialchars($board_title); ?> - Board</title>
</head>
<body>
    <!-- Link back to board list -->
    <a href="index.php">Back to boards list</a>
    <!-- Page header with board title -->
    <h1><?php echo htmlspecialchars($board_title); ?> <small>board ID: <?php echo $board; ?></small></h1>
    
    <!-- Form to create new thread -->
    <h2>Create a New Thread</h2>
    <form method="POST" enctype="multipart/form-data">
        <!-- Thread title input -->
        <input type="text" name="title" placeholder="Thread title" required><br><br>
        <!-- Thread content textarea -->
        <textarea name="content" placeholder="Thread content" rows="4" required></textarea><br><br>
        <!-- File upload input -->
        <input type="file" name="file" accept="image/*,video/*,audio/*,application/*" required><br><br>
        <!-- Hidden field with current board for convenience -->
        <input type="hidden" name="board" value="<?php echo $board; ?>">

        <button type="submit">Create Thread</button>
    </form>

    <!-- List of existing threads -->
    <h2>Threads</h2>
    <ul>
        <?php foreach ($threads as $thread): ?>
            <li>
                <!-- Link to thread page with its title -->
                <strong><a href="thread.php?id=<?php echo $thread['id']; ?>&board=<?php echo $board; ?>"><?php echo htmlspecialchars($thread['title']); ?></a></strong><br>
                <?php if (!empty($thread['media_path'])): ?>
                    <?php 
                    // Get media file extension
                    $ext = strtolower(pathinfo($thread['media_path'], PATHINFO_EXTENSION));
                    // If image, show thumbnail linking to full media
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <br><a href='<?php echo $thread['media_path']; ?>' target='_blank'>
                            <img src='<?php echo $thread['media_thumb_path']; ?>' width='350' height='350' alt='<?php echo htmlspecialchars($thread['media_path']); ?>'>
                        </a>
                    <?php 
                    // If video, show video player with link
                    elseif (in_array($ext, ['mp4', 'avi', 'mov'])): ?>
                        <br><a href='<?php echo $thread['media_path']; ?>' target='_blank'>
                            <video width='350' height='350' controls>
                                <source src='<?php echo $thread['media_path']; ?>' type='video/<?php echo $ext; ?>'>
                            </video>
                        </a>
                    <?php 
                    // If audio, show audio player and download link
                    elseif (in_array($ext, ['mp3', 'wav', 'ogg'])): ?>
                        <br><audio controls>
                            <source src='<?php echo $thread['media_path']; ?>' type='audio/<?php echo $ext; ?>'>
                            Your browser does not support the audio element.
                        </audio>
                        <br><a href='<?php echo $thread['media_path']; ?>' download>Download audio</a>
                    <?php 
                    // For other files, show download link
                    else: ?>
                        <br><a href='<?php echo $thread['media_path']; ?>' download>Download file</a>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
