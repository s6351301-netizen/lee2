<?php
// 取得使用者輸入的 LotCode，預設帶入 B113652
$lotCode = isset($_POST['lotCode']) ? trim($_POST['lotCode']) : "B113652";

// 呼叫內政部地籍圖詮釋資料 API
$apiUrl = "https://lisp.land.moi.gov.tw/MMS/Handle/MapMetadataService.asmx/RequestService?LotCode=" . urlencode($lotCode);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$metadata = null;
if ($response) {
    $xmlObj = simplexml_load_string($response);
    if ($xmlObj) {
        $innerXml = simplexml_load_string((string)$xmlObj);
        if ($innerXml && isset($innerXml->Response->MapMetadata)) {
            $metadata = $innerXml->Response->MapMetadata;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>地籍圖詮釋資料查詢系統 - 完整欄位串接</title>
    <style>
        body { font-family: sans-serif; margin: 20px; max-width: 1000px; margin-left: auto; margin-right: auto; background-color: #f9f9f9; }
        h2 { color: #2c3e50; }
        .form-group { background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        input[type="text"], button { padding: 8px 12px; font-size: 16px; margin-right: 10px; }
        button, .btn-link { background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; padding: 8px 12px; font-size: 16px; margin-right: 5px; }
        button:hover, .btn-link:hover { background-color: #2980b9; }
        .btn-secondary { background-color: #27ae60; }
        .btn-secondary:hover { background-color: #219653; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #ddd; padding: 10px 14px; text-align: left; vertical-align: middle; }
        th { background-color: #f2f2f2; width: 28%; color: #333; }
        .section-header { background-color: #eaf2f8; text-align: center; font-weight: bold; color: #2980b9; }
        .hint { font-size: 13px; color: #666; margin-top: 5px; }
        .query-links { margin-bottom: 10px; }
    </style>
    <script>
        // 開啟新視窗查詢代碼頁面
        function openCodeQuery(type) {
            var url = "";
            if (type === 'lot') {
                url = "https://lisp.land.moi.gov.tw/MMS/WIN_Sec_search.aspx"; // 段名代碼表頁面
            } else if (type === 'city') {
                url = "https://lisp.land.moi.gov.tw/MMS/WIN_CityTown_search.aspx"; // 縣市及鄉鎮市區代碼表頁面
            } else if (type === 'recode') {
                url = "https://lisp.land.moi.gov.tw/MMS/WIN_ChangeCode_search.aspx"; // 代碼重編縣市對照表頁面
            }
            
            // 若網址結構需依官方開啟方式微調，此處以標準彈出視窗開啟
            window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
        }

        // 提供給子視窗或使用者手動回傳代碼的函數（可透過 window.opener 或自訂機制呼叫，此處提供標準帶回欄位函式）
        function setLotCode(code) {
            if(code) {
                document.getElementById('lotCode').value = code;
                document.getElementById('queryForm').submit();
            }
        }
    </script>
</head>
<body>

    <h2>地籍圖詮釋資料查詢系統 - 完整欄位 API 串接</h2>
    <p><a href="https://lisp.land.moi.gov.tw/MMS/MMSpage.aspx#gobox01">https://lisp.land.moi.gov.tw/MMS/MMSpage.aspx#gobox01</a></p>
    <p><span style="font-family: Arial, 微軟正黑體, 新細明體; font-size: 15px;">詮釋資料（Metadata）最常見的解釋是「描述資料的資料（Data about Data）」是指對數位資訊 之內容、格式、結構、使用方式…等等之說明與描述，以作為電腦系統在存取、使用該數位 資訊之依據。</span></p>
    <p style="padding-top: 5px; padding-bottom: 5px; font-family: Arial, 微軟正黑體, 新細明體; font-size: 15px;">每張地籍圖也都有相對應的詮釋資料，主要用來了解地籍圖本身的一些相關資訊，便於使用者 可以快速查詢並運用它。</p>
    <p><span style="font-family: Arial, 微軟正黑體, 新細明體; font-size: 15px;">主要有下列幾個功用：幫助地籍圖的查詢/幫助地籍圖的識別/便於地籍圖資管理</span></p>

    <div class="form-group">
        <!-- 查詢段代碼相關功能按鈕（新開視窗） -->
        <div class="query-links">
            <label><strong>快速查詢代碼工具：</strong></label>
            <a href="https://lisp.land.moi.gov.tw/MMS/MMSpage.aspx#gobox02" target="_blank" class="btn-link btn-secondary">段名代碼表</a>
            <a href="https://lisp.land.moi.gov.tw/MMS/MMSpage.aspx#gobox02" target="_blank" class="btn-link btn-secondary">縣市及鄉鎮市區代碼表</a>
            <a href="https://lisp.land.moi.gov.tw/MMS/MMSpage.aspx#gobox02" target="_blank" class="btn-link btn-secondary">代碼重編縣市對照表</a>
        </div>
        <hr style="border:0; border-top:1px solid #eee; margin: 15px 0;">

        <form id="queryForm" method="POST" action="">
            <label for="lotCode"><strong>請輸入 7 碼地段代碼 (LotCode)：</strong></label>
            <input type="text" id="lotCode" name="lotCode" value="<?php echo htmlspecialchars($lotCode); ?>" placeholder="例如: B113652" required maxlength="7">
            <button type="submit">查詢資料</button>
            <div class="hint">提示：輸入 7 碼代碼，即可完整對應官方網頁的所有欄位資訊。可點擊上方按鈕前往官方頁查詢代碼後複製填入。</div>
        </form>
    </div>

    <?php if ($metadata): ?>
        <table>
            <tr>
                <th>圖層名稱</th>
                <td><?php echo htmlspecialchars($metadata->MapName); ?></td>
            </tr>
            <tr>
                <th>地段代碼編號</th>
                <td><?php echo htmlspecialchars($metadata->LotCode); ?></td>
            </tr>
            <tr>
                <th>資料摘要</th>
                <td><?php echo htmlspecialchars($metadata->ExcerptType); ?></td>
            </tr>
            <tr>
                <th>主題關鍵字</th>
                <td><?php echo htmlspecialchars($metadata->Keyword); ?></td>
            </tr>
            <tr>
                <th>資料完成(公告)日期</th>
                <td><?php echo htmlspecialchars($metadata->CompletiveTime); ?></td>
            </tr>
            <tr>
                <th>詮釋資料建置時間</th>
                <td><?php echo htmlspecialchars($metadata->CreateTime); ?></td>
            </tr>
            <tr>
                <th>圖檔坐標系統</th>
                <td><?php echo htmlspecialchars($metadata->CoordinateSystem); ?></td>
            </tr>
            <tr>
                <th>比例尺分母</th>
                <td><?php echo htmlspecialchars($metadata->MapScale); ?></td>
            </tr>
            <tr>
                <th>資料品質<br>評估方法描述</th>
                <td><?php echo htmlspecialchars($metadata->DescriptionOfQuality); ?></td>
            </tr>
            <tr>
                <th>資料歷程描述</th>
                <td><?php echo htmlspecialchars($metadata->DescriptionOfTrack); ?></td>
            </tr>
            <tr>
                <th>申購指引</th>
                <td><?php echo htmlspecialchars($metadata->Subscription); ?></td>
            </tr>
            <tr>
                <th>用途限制</th>
                <td><?php echo htmlspecialchars($metadata->UseLimitation); ?></td>
            </tr>
            <tr>
                <th>標準申購<br>程序資訊_費用</th>
                <td>
                    <?php 
                        $orderProcess = (string)$metadata->StandardOrderProcess;
                        if (preg_match('/(http[s]?:\/\/[^\s]+)/', $orderProcess, $matches)) {
                            $url = $matches[1];
                            $text = str_replace($url, '', $orderProcess);
                            echo htmlspecialchars(trim($text)) . '<br><a href="' . htmlspecialchars($url) . '" target="_blank">' . htmlspecialchars($url) . '</a>';
                        } else {
                            echo htmlspecialchars($orderProcess);
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <th>圖層取用限制</th>
                <td><?php echo htmlspecialchars($metadata->AccessRestriction); ?></td>
            </tr>

            <!-- 詮釋資料聯絡資訊 -->
            <tr><td colspan="2" class="section-header">詮釋資料聯絡資訊</td></tr>
            <tr>
                <th>單位名稱</th>
                <td><?php echo htmlspecialchars($metadata->Holder_UnitName); ?></td>
            </tr>
            <tr>
                <th>聯絡者姓名</th>
                <td><?php echo htmlspecialchars($metadata->Holder_ContactName); ?></td>
            </tr>
            <tr>
                <th>聯絡者職稱</th>
                <td><?php echo htmlspecialchars($metadata->Holder_ContactTitle); ?></td>
            </tr>
            <tr>
                <th>聯絡者電話</th>
                <td><?php echo htmlspecialchars($metadata->Holder_ContactTel); ?></td>
            </tr>
            <tr>
                <th>傳真電話</th>
                <td><?php echo htmlspecialchars($metadata->Holder_ContactFax); ?></td>
            </tr>
            <tr>
                <th>聯絡地址</th>
                <td><?php echo htmlspecialchars($metadata->Holder_ContactAddress); ?></td>
            </tr>
            <tr>
                <th>電子信箱</th>
                <td><?php echo htmlspecialchars($metadata->Holder_ContactEmail); ?></td>
            </tr>
            <tr>
                <th>線上資訊</th>
                <td>
                    <?php if (!empty($metadata->Holder_ContactOnlineInfo)): ?>
                        <a href="<?php echo htmlspecialchars($metadata->Holder_ContactOnlineInfo); ?>" target="_blank"><?php echo htmlspecialchars($metadata->Holder_ContactOnlineInfo); ?></a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- 生產單位聯絡資訊 -->
            <tr><td colspan="2" class="section-header">生產單位聯絡資訊</td></tr>
            <tr>
                <th>單位名稱</th>
                <td><?php echo htmlspecialchars($metadata->Maker_UnitName); ?></td>
            </tr>
            <tr>
                <th>聯絡者姓名</th>
                <td><?php echo htmlspecialchars($metadata->Maker_ContactName); ?></td>
            </tr>
            <tr>
                <th>聯絡者職稱</th>
                <td><?php echo htmlspecialchars($metadata->Maker_ContactTitle); ?></td>
            </tr>
            <tr>
                <th>聯絡者電話</th>
                <td><?php echo htmlspecialchars($metadata->Maker_ContactTel); ?></td>
            </tr>
            <tr>
                <th>傳真電話</th>
                <td><?php echo htmlspecialchars($metadata->Maker_ContactFax); ?></td>
            </tr>
            <tr>
                <th>聯絡地址</th>
                <td><?php echo htmlspecialchars($metadata->Maker_ContactAddress); ?></td>
            </tr>
            <tr>
                <th>電子信箱</th>
                <td><?php echo htmlspecialchars($metadata->Maker_ContactEmail); ?></td>
            </tr>
            <tr>
                <th>線上資訊</th>
                <td>
                    <?php if (!empty($metadata->Maker_ContactOnlineInfo)): ?>
                        <a href="<?php echo htmlspecialchars($metadata->Maker_ContactOnlineInfo); ?>" target="_blank"><?php echo htmlspecialchars($metadata->Maker_ContactOnlineInfo); ?></a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- 供應單位聯絡資訊 -->
            <tr><td colspan="2" class="section-header">供應單位聯絡資訊</td></tr>
            <tr>
                <th>單位名稱</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_UnitName); ?></td>
            </tr>
            <tr>
                <th>聯絡者姓名</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_ContactName); ?></td>
            </tr>
            <tr>
                <th>聯絡者職稱</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_ContactTitle); ?></td>
            </tr>
            <tr>
                <th>聯絡者電話</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_ContactTel); ?></td>
            </tr>
            <tr>
                <th>傳真電話</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_ContactFax); ?></td>
            </tr>
            <tr>
                <th>聯絡地址</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_ContactAddress); ?></td>
            </tr>
            <tr>
                <th>電子信箱</th>
                <td><?php echo htmlspecialchars($metadata->Supplier_ContactEmail); ?></td>
            </tr>
            <tr>
                <th>線上資訊</th>
                <td>
                    <?php if (!empty($metadata->Supplier_ContactOnlineInfo)): ?>
                        <a href="<?php echo htmlspecialchars($metadata->Supplier_ContactOnlineInfo); ?>" target="_blank"><?php echo htmlspecialchars($metadata->Supplier_ContactOnlineInfo); ?></a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- 權責資料聯絡資訊 -->
            <tr><td colspan="2" class="section-header">權責資料聯絡資訊</td></tr>
            <tr>
                <th>單位名稱</th>
                <td><?php echo htmlspecialchars($metadata->Authority_UnitName); ?></td>
            </tr>
            <tr>
                <th>聯絡者姓名</th>
                <td><?php echo htmlspecialchars($metadata->Authority_ContactName); ?></td>
            </tr>
            <tr>
                <th>聯絡者職稱</th>
                <td><?php echo htmlspecialchars($metadata->Authority_ContactTitle); ?></td>
            </tr>
            <tr>
                <th>聯絡者電話</th>
                <td><?php echo htmlspecialchars($metadata->Authority_ContactTel); ?></td>
            </tr>
            <tr>
                <th>傳真電話</th>
                <td><?php echo htmlspecialchars($metadata->Authority_ContactFax); ?></td>
            </tr>
            <tr>
                <th>聯絡地址</th>
                <td><?php echo htmlspecialchars($metadata->Authority_ContactAddress); ?></td>
            </tr>
            <tr>
                <th>電子信箱</th>
                <td><?php echo htmlspecialchars($metadata->Authority_ContactEmail); ?></td>
            </tr>
            <tr>
                <th>線上資訊</th>
                <td>
                    <?php if (!empty($metadata->Authority_ContactOnlineInfo)): ?>
                        <a href="<?php echo htmlspecialchars($metadata->Authority_ContactOnlineInfo); ?>" target="_blank"><?php echo htmlspecialchars($metadata->Authority_ContactOnlineInfo); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php else: ?>
        <p style="color: red; margin-top: 20px;">無法取得資料或查無此代碼（<?php echo htmlspecialchars($lotCode); ?>）的資料，請確認代碼是否正確。</p>
    <?php endif; ?>

</body>
</html>