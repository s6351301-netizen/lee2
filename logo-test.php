<?php
session_start();
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['user_input']) && $_POST['user_input'] === $_SESSION['captcha']) {
        $message = "驗證成功！";
    } else {
        $message = "驗證錯誤，請重試。";
    }
}
?>

<!DOCTYPE html>
<html>
<body>
    <form method="POST" action="">
        <p>請輸入認證圖案中的字：</p>
        <!-- 這裡 src 指向上面那個獨立的檔案 -->
        <img src="captcha_gen.php" alt="驗證碼" style="border:1px solid #000;">
        <br>
        <input type="text" name="user_input" required>
        <button type="submit">OK</button>
    </form>
    <p><?php echo $message; ?></p>
</body>
</html>