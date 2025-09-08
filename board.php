<?php
// Подключаем файл конфигурации, где обычно лежит подключение к базе данных и настройки
include 'config.php';

// Получаем из URL параметр 'board' — идентификатор доски (борда), с которой работаем
$board = $_GET['board']; 

// Инициализируем переменную с названием борда
$board_title = '';

// Определяем название борда по его коду. Если не совпадает, прерываем работу скрипта с ошибкой
if ($board == 'b') {
    $board_title = '/b/';
} elseif ($board == 'ph') {
    $board_title = '/ph/';
} else {
    // Если борда не существует — прекращаем выполнение и выводим сообщение
    die('Борда не существует');
}

// Готовим SQL-запрос для получения всех тредов с текущей борды, отсортированных по дате создания (новые сверху)
$stmt = $conn->prepare("SELECT * FROM threads WHERE board = :board ORDER BY created_at DESC");
// Привязываем параметр :board к переменной $board, чтобы избежать SQL-инъекций
$stmt->bindParam(':board', $board);
// Выполняем запрос
$stmt->execute();
// Получаем все записи в массив
$threads = $stmt->fetchAll();

// Проверяем, был ли отправлен POST-запрос (создание нового треда) и передан ли текст треда
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['content'])) {
    // Получаем из формы текст и заголовок треда
    $content = $_POST['content'];
    $title = $_POST['title'];

    // Переменные для пути к загруженному файлу и миниатюре, изначально пустые
    $media_path = null;
    $thumb_path = null;

    // Проверяем, был ли загружен файл и без ошибок
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        // Каталог для загрузки файлов
        $upload_dir = 'uploads/';
        // Получаем имя файла без пути
        $file_name = basename($_FILES['file']['name']);
        // Формируем уникальное имя файла для хранения, чтобы избежать конфликтов
        $media_path = $upload_dir . uniqid(rand(), true) . '.' . pathinfo($file_name, PATHINFO_EXTENSION);

        // Перемещаем временный загруженный файл в постоянную папку
        move_uploaded_file($_FILES['file']['tmp_name'], $media_path);

        // Определяем расширение файла в нижнем регистре
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Если это изображение — создаём миниатюру
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            // Уникальное имя для миниатюры
            $thumb_name = uniqid(rand(), true) . '.' . $file_ext;
            $thumb_path = 'uploads/thumbs/' . $thumb_name;

            // Получаем исходные размеры изображения
            list($width, $height) = getimagesize($media_path);
            // Размер миниатюры (фишка фиксированного размера 350x350)
            $thumb_width = 350;
            $thumb_height = 350;

            $image = false;

            // Создаём изображение из файла в зависимости от формата
            if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                $image = imagecreatefromjpeg($media_path);
            } elseif ($file_ext === 'png') {
                $image = imagecreatefrompng($media_path);
            } elseif ($file_ext === 'gif') {
                $image = imagecreatefromgif($media_path);
            }

            // Если удалось загрузить изображение — создаём миниатюру
            if ($image !== false) {
                // Создаём пустое изображение нужного размера
                $thumb = imagecreatetruecolor($thumb_width, $thumb_height);

                // Для PNG и GIF устанавливаем прозрачность для миниатюры
                if ($file_ext === 'png' || $file_ext === 'gif') {
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                    imagefilledrectangle($thumb, 0, 0, $thumb_width, $thumb_height, $transparent);
                }

                // Копируем и изменяем размер исходного изображения в миниатюру
                imagecopyresized($thumb, $image, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);

                // Сохраняем миниатюру в зависимости от формата
                if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                    imagejpeg($thumb, $thumb_path);
                } elseif ($file_ext === 'png') {
                    imagepng($thumb, $thumb_path);
                } elseif ($file_ext === 'gif') {
                    imagegif($thumb, $thumb_path);
                }

                // Освобождаем память
                imagedestroy($thumb);
                imagedestroy($image);
            }
        }
        // Если это видео — создаём миниатюру с помощью ffmpeg
        elseif (in_array($file_ext, ['mp4', 'avi', 'mov'])) {
            $thumb_name = uniqid(rand(), true) . '.jpg';
            $thumb_path = 'uploads/thumbs/' . $thumb_name;

            // Команда для ffmpeg: берем 1-й кадр через секунду и сохраняем как jpg
            $command = "ffmpeg -i $media_path -ss 00:00:01.000 -vframes 1 $thumb_path";
            exec($command);
        }
    }

    // Вставляем новый тред в базу, включая пути к медиафайлу и миниатюре, если есть
    $stmt = $conn->prepare("INSERT INTO threads (board, title, content, media_path, media_thumb_path) VALUES (:board, :title, :content, :media_path, :media_thumb_path)");
    $stmt->bindParam(':board', $board);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':content', $content);
    // media_path и media_thumb_path могут быть NULL, если файл не был загружен
    $stmt->bindParam(':media_path', $media_path, PDO::PARAM_STR);
    $stmt->bindParam(':media_thumb_path', $thumb_path, PDO::PARAM_STR);
    $stmt->execute();

    // После создания треда перенаправляем обратно на страницу борда
    header("Location: board.php?board=" . $board);
    exit;
}
?>

<!-- Начало HTML страницы -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <!-- вывод названия борда в заголовок -->
    <title><?php echo htmlspecialchars($board_title); ?> - Имиджборд</title>
</head>
<body>
    <!-- Ссылка назад к списку всех бордов -->
	<a href="index.php">Назад к списку бордов</a>
    <!-- Заголовок страницы с названием борда -->
    <h1><?php echo htmlspecialchars($board_title); ?> <small>id борда: <?php echo $board; ?></small></h1>
    
    <!-- Форма создания нового треда -->
    <h2>Создать новый тред</h2>
    <form method="POST" enctype="multipart/form-data">
        <!-- Поле для заголовка треда -->
        <input type="text" name="title" placeholder="Заголовок треда" required><br><br>
        <!-- Текстовое поле для содержимого треда -->
        <textarea name="content" placeholder="Текст треда" rows="4" required></textarea><br><br>
        <!-- Загрузка файла (картинка, видео, аудио и др.) -->
        <input type="file" name="file" accept="image/*,video/*,audio/*,application/*" required><br><br>
        <!-- Скрытое поле с текущим бордом (для удобства) -->
        <input type="hidden" name="board" value="<?php echo $board; ?>">

        <button type="submit">Создать тред</button>
    </form>

    <!-- Список существующих тредов -->
    <h2>Треды</h2>
    <ul>
        <?php foreach ($threads as $thread): ?>
            <li>
                <!-- Ссылка на страницу треда с его заголовком -->
                <strong><a href="thread.php?id=<?php echo $thread['id']; ?>&board=<?php echo $board; ?>"><?php echo $thread['title']; ?></a></strong><br>
                <?php if (isset($thread['media_path'])): ?>
                    <?php 
                    // Определяем расширение медиафайла
                    $ext = strtolower(pathinfo($thread['media_path'], PATHINFO_EXTENSION));
                    // Если это изображение, показываем миниатюру с ссылкой на полный файл
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <br><a href='<?php echo $thread['media_path']; ?>' target='_blank'>
                            <img src='<?php echo $thread['media_thumb_path']; ?>' width='350' height='350' alt='<?php echo $thread['media_path']; ?>'>
                        </a>
                    <?php 
                    // Если это видео, показываем видеоплеер
                    elseif (in_array($ext, ['mp4', 'avi', 'mov'])): ?>
                        <br><a href='<?php echo $thread['media_path']; ?>' target='_blank'>
                            <video width='350' height='350' controls>
                                <source src='<?php echo $thread['media_path']; ?>' type='video/<?php echo $ext; ?>'>
                            </video>
                        </a>
                    <?php 
                    // Если это аудио — аудиоплеер и ссылка на скачивание
                    elseif (in_array($ext, ['mp3', 'wav', 'ogg'])): ?>
                        <br><audio controls>
                            <source src='<?php echo $thread['media_path']; ?>' type='audio/<?php echo $ext; ?>'>
                            Ваш браузер не поддерживает аудиоэлемент.
                        </audio>
                        <br><a href='<?php echo $thread['media_path']; ?>' download>Скачать аудио</a>
                    <?php 
                    // Для других файлов показываем просто ссылку на скачивание
                    else: ?>
                        <br><a href='<?php echo $thread['media_path']; ?>' download>Скачать файл</a>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
