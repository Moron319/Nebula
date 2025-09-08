<?php
include 'config.php'; 
// Подключаем файл конфигурации с базой данных ($conn)

// Получаем из URL параметры: id треда и название борда
$thread_id = $_GET['id']; // ID треда
$board = $_GET['board'];  // название борда

// Запрос к базе, чтобы получить данные треда по его ID
$stmt = $conn->prepare("SELECT * FROM threads WHERE id = :thread_id");
$stmt->bindParam(':thread_id', $thread_id);
$stmt->execute();
$thread = $stmt->fetch(); 

// Если тред не найден — можно вывести ошибку или редирект
if (!$thread) {
    die("Тред не найден.");
}

// Сохраняем IP-адрес создателя треда (для отметки OP — original poster)
$op_ip_address = $thread['ip_address']; 

// Обработка отправки нового сообщения (ответа)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['content'])) {
    $content = $_POST['content']; // Текст ответа
    $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $media_path = null;

    // Обработка загрузки файла
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = 'uploads/';

        // Проверяем, что папка существует, если нет — создаём
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Генерируем уникальное имя файла, чтобы избежать конфликтов
        $file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_ext;
        $media_path = $upload_dir . $file_name;

        // Перемещаем загруженный файл
        move_uploaded_file($_FILES['file']['tmp_name'], $media_path);
    }

    // Определяем, является ли автор поста OP (создателем треда)
    $is_op = ($_SERVER['REMOTE_ADDR'] == $op_ip_address);

    // Вставляем новый пост в базу
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

    // После отправки можно сделать редирект, чтобы избежать повторной отправки формы
    header("Location: thread.php?id=$thread_id&board=$board");
    exit;
}

// Получаем все посты треда
$stmt = $conn->prepare("SELECT * FROM posts WHERE thread_id = :thread_id ORDER BY created_at ASC");
$stmt->bindParam(':thread_id', $thread_id);
$stmt->execute();
$posts = $stmt->fetchAll();

// Формируем массив с ответами для рекурсивного отображения
$replies = [];
foreach ($posts as $post) {
    if ($post['parent_id']) {
        $replies[$post['parent_id']][] = $post;
    }
}

// Рекурсивная функция для отображения постов с вложенными ответами
function displayReplies($post, $replies, $op_ip_address) {
    echo "<li>";
    
    // Показываем id и дату
    echo "id: <strong>" . htmlspecialchars($post['id']) . "</strong> | Дата: " . htmlspecialchars($post['created_at']) . "<br>";
    
    // Контент с безопасным выводом и переносами строк
    echo nl2br(htmlspecialchars($post['content'])) . "<br>";
    
    // Отметка OP, если IP совпадает
    if ($post['ip_address'] == $op_ip_address) {
        echo "<strong> - OP (создатель треда)</strong>";
    }

    // Отображение медиа (если есть)
    if (!empty($post['media_path'])) {
        $file_ext = strtolower(pathinfo($post['media_path'], PATHINFO_EXTENSION));

        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' target='_blank'>
                    <img src='" . htmlspecialchars($post['media_path']) . "' width='350' height='350' alt='Изображение'>
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
                    Ваш браузер не поддерживает аудио элемент.
                  </audio>";
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' download>Скачать аудио</a>";
        } else {
            echo "<br><a href='" . htmlspecialchars($post['media_path']) . "' download>Скачать файл</a>";
        }
    }

    // Форма для ответа на этот пост
    echo "<form method='POST' enctype='multipart/form-data' style='margin-top:10px; margin-bottom:20px;'>
            <textarea name='content' placeholder='Ответить на сообщение' required rows='3' cols='50'></textarea><br>
            <label>Прикрепить файл (необязательно):</label>
            <input type='file' name='file' accept='image/*,video/*,audio/*,application/*'><br>
            <input type='hidden' name='parent_id' value='" . htmlspecialchars($post['id']) . "' />
            <button type='submit'>Ответить</button>
        </form>";

    // Если есть ответы, выводим их рекурсивно
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
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($thread['title']); ?> - Имиджборд</title>
</head>
<body>
    <a href="board.php?board=<?php echo htmlspecialchars($board); ?>">Назад к борде</a>
    <h1><?php echo htmlspecialchars($thread['title']); ?> <small>(id: <?php echo $thread['id']; ?>)</small></h1>

    <p><?php echo nl2br(htmlspecialchars($thread['content'])); ?></p>

    <?php if (!empty($thread['media_path'])): ?>
        <?php
            $file_ext = strtolower(pathinfo($thread['media_path'], PATHINFO_EXTENSION));
            $media_path = htmlspecialchars($thread['media_path']);
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                <a href="<?php echo $media_path; ?>" target="_blank">
                    <img src="<?php echo $media_path; ?>" alt="Изображение треда">
                </a>
        <?php elseif (in_array($file_ext, ['mp4', 'avi', 'mov'])): ?>
                <a href="<?php echo $media_path; ?>" target="_blank">
                    <video controls width="350" height="350">
                        <source src="<?php echo $media_path; ?>" type="video/<?php echo $file_ext; ?>">
                        Ваш браузер не поддерживает видео.
                    </video>
                </a>
        <?php elseif (in_array($file_ext, ['mp3', 'wav', 'ogg'])): ?>
                <audio controls>
                    <source src="<?php echo $media_path; ?>" type="audio/<?php echo $file_ext; ?>">
                    Ваш браузер не поддерживает аудио.
                </audio>
                <br>
                <a href="<?php echo $media_path; ?>" download>Скачать аудио</a>
        <?php else: ?>
                <a href="<?php echo $media_path; ?>" download>Скачать файл</a>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Ответы</h2>
    <ul>
        <?php foreach ($posts as $post): ?>
            <?php if ($post['parent_id'] === null): ?>
                <?php displayReplies($post, $replies, $op_ip_address); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <h2>Ответить на тред</h2>
    <form method="POST" enctype="multipart/form-data">
        <textarea name="content" rows="4" placeholder="Введите ответ" required></textarea><br>
        <label>Прикрепить файл (необязательно):</label>
        <input type="file" name="file" accept="image/*,video/*,audio/*,application/*"><br>
        <button type="submit">Отправить</button>
    </form>
</body>
</html>