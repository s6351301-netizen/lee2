<?php
// 注意：這個檔案的第一個字元必須是 <?php，前面不可以有空白行或 BOM，
// 否則 PHP 會先把那些空白送到瀏覽器，導致 session_start() 無法寫入 Cookie，
// 跨分頁的勾選紀錄就會整個失效。
session_start();

// 這段 PHP 程式用來處理 CSV 檔案上傳與匯入資料庫的流程。
$message = ''; // 宣告訊息變數，用來顯示匯入結果。
$importedCount = 0; // 宣告已匯入的資料筆數。
$dbName = 'db_csv'; // 指定要使用的資料庫名稱。
$tableName = 'last_name'; // 指定要匯入的資料表名稱。
$sessionKey = 'selected_last_name_ids'; // 存放勾選 ID 的 Session 鍵值。
$pdo = null; // 宣告 PDO 連線物件。

// 這個函式用來把不同格式的選取 ID 轉成統一陣列，方便跨分頁與下載流程共用。
function normalizeSelectedIds($input): array
{
    $rawValues = [];
    if (is_array($input)) {
        $rawValues = $input;
    } elseif (is_string($input) && $input !== '') {
        $rawValues = preg_split('/[\s,]+/u', trim($input)) ?: [];
    }

    $ids = [];
    foreach ($rawValues as $value) {
        if (is_array($value)) {
            continue;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

// 這個函式用來組合分頁與篩選的查詢字串，讓換頁時不會遺失關鍵字。
function buildPageUrl(int $page, string $keyword): string
{
    $params = ['page' => $page];
    if ($keyword !== '') {
        $params['keyword'] = $keyword;
    }
    return '?' . http_build_query($params);
}

// 這個函式用來讀出目前 Session 內已勾選的 ID。
function readSelectedIds(string $sessionKey): array
{
    if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
        return [];
    }
    return normalizeSelectedIds($_SESSION[$sessionKey]);
}

try { // 嘗試建立資料庫連線與資料庫。
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', ''); // 使用 root 帳號連線到 MySQL 伺服器。
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // 設定 PDO 為例外模式。
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); // 若資料庫不存在，就建立它。
    $pdo = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", 'root', ''); // 重新連線到指定的資料庫。
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // 再次啟用例外模式。
} catch (Exception $e) { // 如果連線失敗，就捕捉錯誤。
    $pdo = null; // 連線失敗時把物件清空，後面才不會誤用。
    $message = '資料庫連線失敗：' . $e->getMessage(); // 將錯誤訊息存入訊息變數。
}

$keyword = trim((string) ($_GET['keyword'] ?? '')); // 取得搜尋關鍵字。
$page = max(1, (int) ($_GET['page'] ?? 1)); // 取得目前頁碼，預設為第一頁。

// 這段處理表格送出的勾選同步（不依賴 JavaScript 也能運作），處理完後改用轉址避免重複送出。
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selection_form'])) {
    $action = (string) ($_POST['action'] ?? '');
    $targetPage = max(1, (int) ($_POST['page'] ?? 1)); // 分頁按鈕會把目標頁碼送過來。
    $keyword = trim((string) ($_POST['keyword'] ?? '')); // 換頁時一併帶著關鍵字。

    if ($action === 'clear') { // 按下「清除已選擇」就把整個 Session 清空。
        $_SESSION[$sessionKey] = [];
    } else {
        $pageIds = normalizeSelectedIds($_POST['page_ids'] ?? ''); // 這一頁畫面上出現過的所有 ID。
        $checkedIds = array_intersect(normalizeSelectedIds($_POST['selected_ids'] ?? []), $pageIds); // 這一頁被勾選的 ID。
        // 先把本頁的舊紀錄清掉，再寫回目前勾選的，其他分頁的紀錄則完整保留。
        $keptIds = array_diff(readSelectedIds($sessionKey), $pageIds);
        $_SESSION[$sessionKey] = array_values(array_unique(array_merge($keptIds, $checkedIds)));
    }

    header('Location: ' . basename(__FILE__) . buildPageUrl($targetPage, $keyword));
    exit;
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) { // 檢查是否收到有效的上傳檔案。
    $uploadDir = __DIR__ . '/upload/'; // 設定上傳檔案存放的資料夾。
    if (!is_dir($uploadDir)) { // 如果上傳資料夾不存在，就建立它。
        mkdir($uploadDir, 0777, true); // 建立多層目錄。
    }
    $storedFileName = time() . '_' . basename($_FILES['file']['name']); // 產生一個不重複的檔名。

    $uploadPath = $uploadDir . $storedFileName; // 組合完整的檔案路徑。
    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) { // 把暫存檔移到正式上傳位置。
        $logPath = $uploadDir . 'import.log'; // 設定匯入記錄檔檔名。
        $logHandle = fopen($logPath, 'a'); // 開啟記錄檔，準備寫入。
        if ($logHandle) { // 如果記錄檔成功開啟。
            fwrite($logHandle, date('Y-m-d H:i:s') . ' 開始匯入檔案：' . $_FILES['file']['name'] . PHP_EOL); // 寫入匯入開始的訊息。
            fclose($logHandle); // 關閉記錄檔。
        }
        $csvHandle = fopen($uploadPath, 'r'); // 開啟上傳的 CSV 檔案以讀取內容。
        if ($csvHandle) { // 如果 CSV 檔案成功開啟。
            $headers = fgetcsv($csvHandle); // 讀取第一列作為欄位名稱。
            if ($headers !== false && count($headers) > 0) { // 若讀到欄位名稱，才繼續處理。
                // 政府開放資料的 CSV 常常帶有 UTF-8 BOM，要先拿掉，
                // 否則第一個欄位名稱會變成「_month」之類的怪名字，關鍵字搜尋就找不到欄位。
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

                $columnNames = []; // 建立欄位名稱陣列。
                $usedNames = []; // 建立已使用欄位名稱清單，避免重複欄位名。
                foreach ($headers as $index => $header) { // 依序處理每一個欄位標題。
                    $columnName = preg_replace('/[^A-Za-z0-9_]/u', '_', trim((string) $header)); // 將欄位名稱整理成 SQL 可接受的格式。
                    if ($columnName === '' || $columnName === null) { // 如果整理後是空白，就用預設欄位名。
                        $columnName = 'col_' . $index; // 給予預設欄位名稱。
                    }
                    $baseName = $columnName;
                    $finalName = $baseName;
                    $counter = 1;
                    while (in_array($finalName, $usedNames, true)) { // 如果欄位名稱已存在，就補上序號。
                        $finalName = $baseName . '_' . $counter;
                        $counter++;
                    }
                    $usedNames[] = $finalName;
                    $columnNames[] = $finalName; // 把整理後欄位名稱加入陣列。
                }

                try {
                    // 如果資料表已經存在但欄位跟這次的 CSV 不一樣，就先刪掉重建，避免匯入時整批失敗。
                    $existingColumns = [];
                    $existingStmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
                    if ($existingStmt && $existingStmt->rowCount() > 0) {
                        $existingColumns = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
                        $existingColumns = array_values(array_diff($existingColumns, ['id']));
                        if ($existingColumns !== $columnNames) {
                            $pdo->exec("DROP TABLE `$tableName`");
                            $message = '偵測到 CSV 欄位與舊資料表不同，已重建資料表。';
                            $_SESSION[$sessionKey] = []; // 資料表重建後，舊的勾選 ID 已經沒有意義。
                        }
                    }

                    $createSql = "CREATE TABLE IF NOT EXISTS `$tableName` ("; // 建立匯入資料表的 SQL 開頭。
                    $createSql .= '`id` INT AUTO_INCREMENT PRIMARY KEY,'; // 新增自動編號主鍵欄位。
                    foreach ($columnNames as $index => $columnName) { // 依序加入每個 CSV 欄位。
                        $createSql .= "`$columnName` VARCHAR(255) NULL"; // 將欄位設定為可空字串型態。
                        if ($index < count($columnNames) - 1) { // 如果還有下一個欄位，就加入逗號。
                            $createSql .= ','; // 加入欄位分隔符號。
                        }
                    }
                    $createSql .= ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'; // 完成資料表建立 SQL。
                    $pdo->exec($createSql); // 執行建立資料表。

                    // 改用預備語法逐列寫入，比每次組字串安全也快很多。
                    $insertSql = "INSERT INTO `$tableName` (`" . implode('`,`', $columnNames) . '`) VALUES ('
                        . implode(',', array_fill(0, count($columnNames), '?')) . ')';
                    $insertStmt = $pdo->prepare($insertSql);

                    $pdo->beginTransaction();
                    while (($row = fgetcsv($csvHandle)) !== false) { // 一直讀到檔案結尾為止。
                        if ($row === [null] || count(array_filter($row, static function ($value) {
                            return trim((string) $value) !== '';
                        })) === 0) { // 整列都是空白就跳過。
                            continue;
                        }
                        $values = []; // 建立每列資料的數值陣列。
                        for ($i = 0; $i < count($columnNames); $i++) { // 依序整理每個欄位值。
                            $values[] = trim((string) ($row[$i] ?? ''));
                        }
                        $insertStmt->execute($values); // 執行 INSERT 指令。
                        $importedCount++; // 累加已匯入資料筆數。
                    }
                    $pdo->commit();

                    $message = trim($message . " CSV 匯入完成，共匯入 $importedCount 筆資料。"); // 設定成功訊息。
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = '匯入資料時發生錯誤：' . $e->getMessage();
                }
            } else { // 如果欄位讀取失敗。
                $message = 'CSV 檔案格式錯誤，請確認第一列為欄位名稱。'; // 設定格式錯誤訊息。
            }
            fclose($csvHandle); // 關閉 CSV 檔案。
        } else { // 如果 CSV 檔案無法開啟。
            $message = '無法讀取上傳的 CSV 檔案。'; // 設定檔案讀取錯誤訊息。
        }
    } else { // 如果檔案移動失敗。
        $message = '檔案上傳失敗，請重新嘗試。'; // 設定上傳失敗訊息。
    }
    $page = 1; // 匯入完成後回到第一頁。
}

// 這段處理已勾選資料的狀態，讓勾選可以跨頁保存。
$selectedIdsSession = readSelectedIds($sessionKey);

$perPage = 50; // 每頁顯示 50 筆資料。
$totalRows = 0;
$rows = [];
$hasTable = false;
$totalPages = 1;

if ($pdo) { // 如果資料庫連線成功，就開始查詢資料表內容。
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
        $hasTable = $tableCheck && $tableCheck->rowCount() > 0;

        if ($hasTable) {
            // 先讀出資料表實際的欄位，關鍵字才不會搜尋到不存在的欄位而讓 SQL 出錯。
            $tableColumns = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
            $searchColumns = array_values(array_diff($tableColumns, ['id']));

            $whereSql = '';
            $queryParams = [];
            if ($keyword !== '' && !empty($searchColumns)) { // 如果有關鍵字，就針對所有資料欄位進行模糊搜尋。
                $conditions = [];
                foreach ($searchColumns as $columnName) {
                    $conditions[] = "`$columnName` LIKE :keyword";
                }
                $whereSql = ' WHERE ' . implode(' OR ', $conditions);
                $queryParams[':keyword'] = '%' . $keyword . '%';
            }

            $countSql = "SELECT COUNT(*) FROM `$tableName`" . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            foreach ($queryParams as $name => $value) {
                $countStmt->bindValue($name, $value);
            }
            $countStmt->execute();
            $totalRows = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalRows / $perPage));
            $page = min($page, $totalPages); // 頁碼超過總頁數時拉回最後一頁，避免出現空白表格。

            $offset = ($page - 1) * $perPage;
            $selectSql = "SELECT * FROM `$tableName`" . $whereSql . ' ORDER BY `id` ASC LIMIT :limit OFFSET :offset';
            $selectStmt = $pdo->prepare($selectSql);
            $selectStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $selectStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            foreach ($queryParams as $name => $value) {
                $selectStmt->bindValue($name, $value);
            }
            $selectStmt->execute();
            $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $message = '讀取資料表失敗：' . $e->getMessage();
    }
}

$pageIds = array_map(static function ($row) {
    return (int) $row['id'];
}, $rows); // 這一頁畫面上出現的所有 ID，送出時用來判斷哪些是被取消勾選的。
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>文字檔案匯入</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1 class="header">文字檔案匯入練習</h1>

    <?php if ($message !== ''): ?>
        <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form class="upload-form" action="?" method="post" enctype="multipart/form-data">
        <div>請選擇要匯入的 CSV 檔案，然後按下「上傳檔案」按鈕。</div>

        <div class="form-group">
            <label for="file">選擇文字檔案</label>
            <input type="file" name="file" id="file" accept=".txt,.csv,.log" required>
        </div>
        <button type="submit" class="btn-submit">上傳檔案</button>
    </form>

    <?php if ($hasTable && !empty($rows)): ?>
        <form class="filter-form" action="?" method="get">
            <div class="form-group">
                <label for="keyword">關鍵字搜尋</label>
                <input type="text" name="keyword" id="keyword" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="輸入任一欄位的內容">
            </div>
            <button type="submit" class="btn-submit">套用篩選</button>
        </form>

        <!-- 表格、勾選與分頁都放在同一個表單裡，換頁時才能把這一頁的勾選狀態一起送回伺服器。 -->
        <form class="upload-form data-form" action="<?php echo htmlspecialchars(basename(__FILE__), ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <input type="hidden" name="selection_form" value="1">
            <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="page_ids" value="<?php echo htmlspecialchars(implode(',', $pageIds), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-actions">
                <a href="download_selected.php" class="btn-submit">下載所選資料為 CSV</a>
                <button type="submit" name="action" value="clear" class="btn-submit btn-clear">清除已選擇</button>
                <button type="submit" name="page" value="<?php echo $page; ?>" class="btn-submit">儲存本頁勾選</button>
            </div>

            <p>已選擇 <span id="selection-count"><?php echo count($selectedIdsSession); ?></span> 筆資料（共 <?php echo $totalRows; ?> 筆符合條件）</p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-page" title="勾選/取消本頁全部"></th>
                        <?php $displayColumns = array_keys($rows[0]); ?>
                        <?php foreach ($displayColumns as $columnName): ?>
                            <th><?php echo htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8'); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="selection-checkbox" name="selected_ids[]" value="<?php echo (int) $row['id']; ?>" <?php echo in_array((int) $row['id'], $selectedIdsSession, true) ? 'checked' : ''; ?>>
                            </td>
                            <?php foreach ($displayColumns as $columnName): ?>
                                <td><?php echo htmlspecialchars((string) ($row[$columnName] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <button type="submit" name="page" value="1" class="btn-submit" <?php echo $page <= 1 ? 'disabled' : ''; ?>>第一頁</button>
                <button type="submit" name="page" value="<?php echo max(1, $page - 1); ?>" class="btn-submit" <?php echo $page <= 1 ? 'disabled' : ''; ?>>上一頁</button>
                <span>第 <?php echo $page; ?> / <?php echo $totalPages; ?> 頁</span>
                <button type="submit" name="page" value="<?php echo min($totalPages, $page + 1); ?>" class="btn-submit" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>下一頁</button>
                <button type="submit" name="page" value="<?php echo $totalPages; ?>" class="btn-submit" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>最後一頁</button>
            </div>
        </form>
    <?php elseif ($hasTable): ?>
        <p class="message">目前沒有符合條件的資料。</p>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = Array.prototype.slice.call(document.querySelectorAll('.selection-checkbox'));
        const countLabel = document.getElementById('selection-count');
        const selectPage = document.getElementById('select-page');

        // 即時把勾選狀態送回伺服器，這樣就算直接點「下載」也會拿到最新的選擇。
        function syncSelection(ids, action) {
            return fetch('selection_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ ids: ids, action: action })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (countLabel && data && typeof data.count !== 'undefined') {
                    countLabel.textContent = data.count;
                }
            })
            .catch(function () {
                // 同步失敗時保留畫面上的數字，改由送出表單（換頁或「儲存本頁勾選」）補寫入。
            });
        }

        function updateSelectPageState() {
            if (!selectPage) {
                return;
            }
            const checkedCount = checkboxes.filter(function (item) {
                return item.checked;
            }).length;
            selectPage.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
            selectPage.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                updateSelectPageState();
                syncSelection([this.value], this.checked ? 'add' : 'remove');
            });
        });

        if (selectPage) {
            selectPage.addEventListener('change', function () {
                const checked = this.checked;
                const ids = checkboxes.map(function (item) {
                    item.checked = checked;
                    return item.value;
                });
                updateSelectPageState();
                syncSelection(ids, checked ? 'add' : 'remove');
            });
        }

        updateSelectPageState();
    });
    </script>
</body>
</html>
