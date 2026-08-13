<?php
// ==========================================
// 後端 PHP 邏輯區塊 (處理圖片生成與驗證請求)
// ==========================================
session_start(); // 啟用 Session 以儲存驗證碼

// 定義常量，包含不容易混淆的英數字
define('CAPTCHA_CHARS', '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz');

// 1. 如果收到前端發來的 AJAX 驗證請求
if (isset($_POST['action']) && $_POST['action'] === 'verify_ajax') {
    $input_code = trim($_POST['captcha_input']);
    header('Content-Type: application/json');

    // 檢查 Session 是否存在且與輸入相符 (不區分大小寫)
    if (isset($_SESSION['captcha_code']) && strcasecmp($_SESSION['captcha_code'], $input_code) === 0) {
        echo json_encode(['status' => 'success', 'message' => '驗證成功！']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '驗證碼錯誤或已過期。']);
    }
    exit; // 驗證完畢後結束程式，不繼續往下執行 HTML
}

// 2. 如果收到產生圖片的請求
if (isset($_GET['action']) && $_GET['action'] === 'generate_image') {
    generateCaptchaImage();
    exit;
}


// 定義生成驗證碼圖片的函數 (包含圖形與干擾)
function generateCaptchaImage() {
    $width = 180;
    $height = 70;
    $image = imagecreatetruecolor($width, $height);

    // 定義顏色
    $bgColor = imagecolorallocate($image, 240, 240, 240);
    $textColor = imagecolorallocate($image, 20, 20, 20);
    $lineColor = imagecolorallocate($image, 150, 150, 150);
    $shapeColor = imagecolorallocate($image, 100, 149, 237); // 粉藍色

    // 填滿背景
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

    // ============================================================
    // A. 繪製圖形元素 (模擬 2 個圓柱體與 1 個圓錐體)
    // ============================================================
    // 繪製圓柱體 1
    imageellipse($image, 40, 25, 25, 15, $shapeColor);
    imagerectangle($image, 27, 25, 53, 55, $shapeColor);
    imageellipse($image, 40, 55, 25, 15, $shapeColor);

    // 繪製圓柱體 2
    imageellipse($image, 100, 30, 25, 15, $shapeColor);
    imagerectangle($image, 87, 30, 113, 60, $shapeColor);
    imageellipse($image, 100, 60, 25, 15, $shapeColor);

    // 繪製圓錐體
    $conePoints = [150, 10, 135, 60, 165, 60];
    imagepolygon($image, $conePoints, 3, $shapeColor);
    imageellipse($image, 150, 60, 30, 15, $shapeColor);

    // ============================================================
    // B. 產生文字並繪製到畫布
    // ============================================================
    $captchaCode = '';
    for ($i = 0; $i < 5; $i++) {
        $captchaCode .= CAPTCHA_CHARS[rand(0, strlen(CAPTCHA_CHARS) - 1)];
    }
    $_SESSION['captcha_code'] = $captchaCode;

    // 加入文字 (這裡使用 imagestring，若伺服器有 ttf 字型可改用 imagettftext)
    for ($i = 0; $i < 5; $i++) {
        $char = $captchaCode[$i];
        $x = 25 + ($i * 30);
        $y = rand(35, 45);
        $angle = rand(-15, 15);
        
        // 簡單的文字偏移效果
        imagestring($image, 5, $x, $y, $char, $textColor);
    }

    // 加入干擾線與噪點
    for ($i = 0; $i < 5; $i++) {
        imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lineColor);
    }
    for ($i = 0; $i < 100; $i++) {
        imagesetpixel($image, rand(0, $width), rand(0, $height), $lineColor);
    }

    // 輸出圖片並銷毀資源
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>完整的圖形驗證機制 (HTML + PHP)</title>
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f9f9f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .captcha-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; }
        .captcha-image { margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; display: block; cursor: pointer; }
        .refresh-link { font-size: 0.9em; color: #007bff; cursor: pointer; text-decoration: underline; margin-bottom: 15px; display: inline-block;}
        .input-group { margin-bottom: 20px; }
        input[type="text"] { padding: 10px; font-size: 1.1em; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 12px 25px; background-color: #28a745; color: white; border: none; border-radius: 5px; font-size: 1.1em; cursor: pointer; width: 100%; }
        button:hover { background-color: #218838; }
        .result-message { margin-top: 15px; font-weight: bold; min-height: 1.2em; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>

<!-- ==========================================
 前端 HTML 呈現區塊
 ========================================== -->
<div class="captcha-container">
    <h2>請輸入下方驗證碼</h2>
    
    <!-- 驗證碼圖片：點擊圖片可呼叫 JS 重新整理 -->
    <img src="?action=generate_image" alt="驗證碼圖片" class="captcha-image" id="captchaImg" onclick="refreshCaptcha()" title="點擊重新整理">
    <span class="refresh-link" onclick="refreshCaptcha()">看不清楚？點擊換一張</span>

    <!-- 輸入框與驗證按鈕 -->
    <div class="input-group">
        <input type="text" id="captchaInput" name="captcha_input" placeholder="輸入驗證碼 (不分大小寫)" autocomplete="off" maxlength="5">
    </div>
    <button onclick="submitVerification()">確認送出</button>

    <!-- 顯示驗證結果的區域 (由 JS 動態更新) -->
    <div class="result-message" id="resultMessage"></div>
</div>

<!-- ==========================================
 前端 JavaScript 邏輯區塊 (處理動態互動)
 ========================================== -->
<script>
    // 1. 重新整理驗證碼的函數
    function refreshCaptcha() {
        const imgElement = document.getElementById('captchaImg');
        // 在 URL 後面加上亂數參數 (timestamp)，強制瀏覽器重新載入圖片，避免快取
        imgElement.src = '?action=generate_image&t=' + new Date().getTime();
        document.getElementById('captchaInput').value = ''; // 清空輸入框
        document.getElementById('resultMessage').textContent = ''; // 清空訊息
    }

    // 2. 提交驗證的函數 (使用 AJAX 發送請求)
    function submitVerification() {
        const inputVal = document.getElementById('captchaInput').value;
        const resultMsg = document.getElementById('resultMessage');

        if (inputVal.length < 3) {
            resultMsg.textContent = '請輸入完整的驗證碼';
            resultMsg.className = 'result-message error';
            return;
        }

        // 建立 AJAX 物件
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true); // 發送到當前頁面
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        resultMsg.textContent = response.message;
                        resultMsg.className = 'result-message success';
                        // 驗證成功後，自動重新整理驗證碼 (選用)
                        setTimeout(refreshCaptcha, 2000);
                    } else {
                        resultMsg.textContent = response.message;
                        resultMsg.className = 'result-message error';
                        // 驗證失敗後，強制重新整理驗證碼
                        refreshCaptcha();
                    }
                } catch (e) {
                    console.error('解析回應失敗:', xhr.responseText);
                    resultMsg.textContent = '伺服器發生錯誤';
                    resultMsg.className = 'result-message error';
                }
            }
        };

        // 發送請求資料 (告訴後端這是 AJAX 驗證請求)
        xhr.send('action=verify_ajax&captcha_input=' + encodeURIComponent(inputVal));
    }
</script>

</body>
</html>