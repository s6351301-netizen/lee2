<?php
// ==========================================
// 資料庫連線設定：主機 localhost、帳號 root、密碼無、資料庫 calender
// ==========================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'calender';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}

// 1. 從 calendar_years_meta 讀取所有支援的年份與設定
// （假設資料表欄位包含：year, minguo_year, emperor_era(皇帝/年號分類), era_name, ganzhi, theme, animal）
$stmt = $pdo->query("SELECT * FROM calendar_years_meta ORDER BY year ASC");
$years_meta_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$years_meta = [];
$grouped_years = [];

foreach ($years_meta_raw as $meta) {
    $year = $meta['year'];
    // 假設資料庫欄位有 'emperor_era' 代表所屬年號分類（例如：'民國', '明治', '大正', '昭和' 等）
    // 若無此欄位，可根據您的資料結構調整判斷條件
    $era = isset($meta['emperor_era']) ? $meta['emperor_era'] : '民國'; 

    $years_meta[$year] = [
        'minchu'   => $meta['minguo_year'],
        'era_name' => isset($meta['era_name']) ? $meta['era_name'] : '',
        'ganzhi'   => $meta['ganzhi'],
        'theme'    => $meta['theme'],
        'animal'   => $meta['animal'],
        'era'      => $era
    ];

    // 依照皇帝/年號分類歸納
    $grouped_years[$era][$year] = $years_meta[$year];
}

// 取得使用者透過下拉選單選擇的年份（預設為 2026）
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : 2026;
if (!array_key_exists($selected_year, $years_meta)) {
    $selected_year = 2026; // 若超出範圍則預設回 2026
}

$meta = $years_meta[$selected_year];
$current_theme = $meta['theme'];
$current_animal = $meta['animal'];
$minguo_year = $meta['minchu'];
$era_name_val = $meta['era_name'];
$ganzhi_year = $meta['ganzhi'];

// 2. 從 calendar_holidays 讀取該年度的假日與特殊假資料
$holiday_stmt = $pdo->prepare("SELECT holiday_date, title FROM calendar_holidays WHERE YEAR(holiday_date) = ?");
$holiday_stmt->execute([$selected_year]);
$holidays_raw = $holiday_stmt->fetchAll(PDO::FETCH_ASSOC);
$holidays = [];
foreach ($holidays_raw as $h) {
    $holidays[$h['holiday_date']] = $h['title'];
}

// 3. 從 calendar_event_details 與 calendar_event_categories 讀取該年度的事件與詳細內容
$event_stmt = $pdo->prepare("
    SELECT ed.event_date, ed.title, ed.content, ec.category_name 
    FROM calendar_event_details ed 
    JOIN calendar_event_categories ec ON ed.category_id = ec.id 
    WHERE YEAR(ed.event_date) = ?
");
$event_stmt->execute([$selected_year]);
$events_raw = $event_stmt->fetchAll(PDO::FETCH_ASSOC);
$events = [];
foreach ($events_raw as $ev) {
    $events[$ev['event_date']][] = [
        'category' => $ev['category_name'],
        'title'    => $ev['title'],
        'content'  => $ev['content']
    ];
}

// 4. 動態產生各月份月曆資料
$calendar_data = [];
$lunar_terms = ['初一', '初二', '初三', '初四', '初五', '初六', '初七', '初八', '初九', '初十', 
                '十一', '十二', '十三', '十四', '十五', '十六', '十七', '十八', '十九', '二十', 
                '廿一', '廿二', '廿三', '廿四', '廿五', '廿六', '廿七', '廿八', '廿九', '三十'];

for ($m = 1; $m <= 12; $m++) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $m, $selected_year);
    $month_array = [];
    
    for ($d = 1; $d <= $days_in_month; $d++) {
        $date_str = sprintf('%04d-%02d-%02d', $selected_year, $m, $d);
        $lunar_day = $lunar_terms[($d - 1) % 30];
        
        $w = date('w', strtotime($date_str));
        $is_weekend = ($w == 0 || $w == 6);

        $note = '宜行事';
        if (isset($holidays[$date_str])) {
            $note = $holidays[$date_str];
        } elseif ($is_weekend) {
            $note = ($w == 0) ? '星期日' : '星期六';
        }

        $day_events = isset($events[$date_str]) ? $events[$date_str] : [];

        $month_array[] = [
            'day' => $d,
            'lunar' => $lunar_day,
            'note' => $note,
            'events' => $day_events
        ];
    }
    $calendar_data[$m] = $month_array;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>台灣萬年曆黃曆 - 生肖浮水印藝術版</title>
    <style>
        /* 12 生肖專屬主題配色 */
        body.theme-rat { --main-color: #4B6584; --accent-color: #ff4757; --bg-color: #f1f2f6; }
        body.theme-ox { --main-color: #845EC2; --accent-color: #ff6f91; --bg-color: #f3f0ff; }
        body.theme-tiger { --main-color: #D65A31; --accent-color: #ff5722; --bg-color: #fff5f0; }
        body.theme-rabbit { --main-color: #FF6F91; --accent-color: #ff1493; --bg-color: #fff0f5; }
        body.theme-dragon { --main-color: #00818A; --accent-color: #d63031; --bg-color: #e8f8f9; }
        body.theme-snake { --main-color: #2E8B57; --accent-color: #228b22; --bg-color: #f0fff0; }
        body.theme-horse { --main-color: #C0392B; --accent-color: #e74c3c; --bg-color: #fdfefe; }
        body.theme-goat { --main-color: #D4AC0D; --accent-color: #b7950b; --bg-color: #fef9e7; }
        body.theme-monkey { --main-color: #D35400; --accent-color: #e67e22; --bg-color: #fef5e7; }
        body.theme-rooster { --main-color: #884EA0; --accent-color: #9b59b6; --bg-color: #f4ecf7; }
        body.theme-dog { --main-color: #795548; --accent-color: #a1887f; --bg-color: #efebe9; }
        body.theme-pig { --main-color: #E91E63; --accent-color: #c2185b; --bg-color: #fce4ec; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            background-color: var(--bg-color, #f9f9f9);
            margin: 0;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            transition: background-color 0.6s ease;
            font-size: 1.15rem;
        }

        .zodiac-bg-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 550px;
            height: 550px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.08;
            z-index: -1;
            pointer-events: none;
            animation: watermarkAnim 25s infinite alternate ease-in-out;
        }

        body.theme-rat .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%234B6584'%3E鼠%3C/text%3E%3C/svg%3E"); }
        body.theme-ox .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23845EC2'%3E牛%3C/text%3E%3C/svg%3E"); }
        body.theme-tiger .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23D65A31'%3E虎%3C/text%3E%3C/svg%3E"); }
        body.theme-rabbit .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23FF6F91'%3E兔%3C/text%3E%3C/svg%3E"); }
        body.theme-dragon .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%2300818A'%3E龍%3C/text%3E%3C/svg%3E"); }
        body.theme-snake .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%232E8B57'%3E蛇%3C/text%3E%3C/svg%3E"); }
        body.theme-horse .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23C0392B'%3E馬%3C/text%3E%3C/svg%3E"); }
        body.theme-goat .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23D4AC0D'%3E羊%3C/text%3E%3C/svg%3E"); }
        body.theme-monkey .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23D35400'%3E猴%3C/text%3E%3C/svg%3E"); }
        body.theme-rooster .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23884EA0'%3E雞%3C/text%3E%3C/svg%3E"); }
        body.theme-dog .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23795548'%3E狗%3C/text%3E%3C/svg%3E"); }
        body.theme-pig .zodiac-bg-watermark { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-weight='bold' font-size='220' fill='%23E91E63'%3E豬%3C/text%3E%3C/svg%3E"); }

        @keyframes watermarkAnim {
            0% { transform: translate(-50%, -50%) rotate(-8deg) scale(0.95); }
            50% { transform: translate(-50%, -50%) rotate(8deg) scale(1.08); }
            100% { transform: translate(-50%, -50%) rotate(-8deg) scale(0.95); }
        }

        @keyframes pageEntrance {
            0% { opacity: 0; transform: translateY(20px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .main-container {
            animation: pageEntrance 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            max-width: 95%;
            margin: 0 auto;
        }

        h1 {
            color: var(--main-color, #333);
            transition: color 0.5s ease;
            font-size: 2.6rem;
            margin-bottom: 5px;
        }

        .year-select-section {
            margin-bottom: 25px;
            font-size: 1.2rem;
            color: #444;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .era-title {
            font-weight: bold;
            color: var(--main-color);
            margin-right: 10px;
        }

        .zodiac-badge {
            display: inline-block;
            padding: 6px 16px;
            background: var(--main-color);
            color: #fff;
            border-radius: 20px;
            font-size: 1.1rem;
            font-weight: bold;
            margin-left: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: background 0.5s ease;
        }

        select {
            font-size: 1.15rem;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 6px;
            border: 2px solid var(--main-color);
            background: #fff;
            color: #333;
            outline: none;
            transition: all 0.3s ease;
        }
        select:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .months-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 25px;
            width: 100%;
            margin: 0 auto;
            align-items: start;
        }

        .month-box {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
            width: 100%;
        }
        .month-box:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }

        .month-box h3 {
            margin: 5px 0 15px 0;
            border-bottom: 2px solid var(--main-color);
            padding-bottom: 6px;
            color: var(--main-color);
            font-size: 1.6rem;
            transition: color 0.5s ease, border-color 0.5s ease;
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 6px;
            font-size: 1.05rem;
            width: 100%;
        }

        .header-day {
            font-weight: bold;
            background: rgba(0, 0, 0, 0.04);
            padding: 8px 0;
            border-radius: 4px;
            color: #555;
            font-size: 1.05rem;
        }

        .day-cell {
            background: #fafafa;
            padding: 6px 4px;
            min-height: 80px;
            height: auto;
            border: 1px solid #eee;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: background 0.2s;
            box-sizing: border-box;
            overflow: hidden;
        }
        .day-cell:hover {
            background: #f0f0f0;
        }

        .day-num {
            font-weight: bold;
            display: block;
            color: #222;
            font-size: 1.15rem;
        }

        .day-lunar {
            font-size: 0.85rem;
            color: #666;
            display: block;
            margin-bottom: 2px;
        }

        .holiday-note {
            font-size: 0.8rem;
            color: #fff;
            background-color: var(--accent-color);
            border-radius: 3px;
            padding: 2px 4px;
            margin-top: 2px;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .holiday-note.weekend-note {
            background-color: #556B2F;
        }

        .event-badge {
            font-size: 0.8rem;
            color: #fff;
            background-color: #2980b9;
            border-radius: 3px;
            padding: 2px 4px;
            margin-top: 2px;
            display: block;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .event-badge:hover {
            background-color: #1f618d;
        }

        /* 手機觀看響應式調整：單欄顯示、寬度 95% 且格寬自動彈性調整 */
        @media screen and (max-width: 768px) {
            .main-container {
                max-width: 95% !important;
                width: 95% !important;
                margin: 0 auto;
            }
            .months-container {
                grid-template-columns: 1fr !important;
                width: 100% !important;
            }
            .month-box {
                width: 100% !important;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body class="theme-<?= $current_theme ?>">

    <div class="zodiac-bg-watermark"></div>

    <div class="main-container">
        <h1>台灣萬年曆黃曆 - <?= $current_animal ?>年主題</h1>
        
        <!-- 依照不同皇帝/年號動態產生多個分類下拉式選單 -->
        <div class="year-select-section">
            <div>
                <strong><?= $selected_year ?> 年 (<?= $minguo_year ?> <?= $era_name_val ?>)</strong> 
                <span class="zodiac-badge">歲次 <?= $ganzhi_year ?></span>
            </div>

            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;">
                <?php foreach ($grouped_years as $era_name =>$era_years): ?>
                    <div class="era-dropdown-group">
                        <span class="era-title">年號：</span>
                        <select onchange="changeYear(this.value)">
                            <?php foreach ($era_years as $y =>$m_data): ?>
                                <option value="<?= $y ?>" <?= ($y ==$selected_year) ? 'selected' : '' ?>>
                                    <?= $y ?> 年 (<?= $m_data['minchu'] ?><?= $m_data['era_name'] ?> - <?=$m_data['ganzhi'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <hr style="width: 80%; border: 0; border-top: 1px solid rgba(0,0,0,0.15); margin: 25px auto;">

        <div class="months-container">
            <?php foreach ($calendar_data as $month_num =>$days): ?>
                <div class="month-box">
                    <h3><?= $month_num ?> 月</h3>
                    <div class="days-grid">
                        <div class="header-day">日</div>
                        <div class="header-day">一</div>
                        <div class="header-day">二</div>
                        <div class="header-day">三</div>
                        <div class="header-day">四</div>
                        <div class="header-day">五</div>
                        <div class="header-day">六</div>

                        <?php
                        $first_day_of_week = date('w', strtotime("$selected_year-$month_num-01"));

                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            echo '<div></div>';
                        }

                        foreach ($days as$day_info) {
                            $d =$day_info['day'];
                            $lunar =$day_info['lunar'];
                            $note = $day_info['note'];$events = $day_info['events'];$is_real_holiday = ($note !== '宜行事' && $note !== '星期六' && $note !== '星期日');
                            $cell_style =$is_real_holiday ? 'background-color: rgba(255,0,0,0.03); border-color: var(--accent-color);' : '';
                            
                            echo "<div class='day-cell' style='{$cell_style}'>";
                            echo "<span class='day-num'>{$d}</span>";
                            echo "<span class='day-lunar'>{$lunar}</span>";
                            
                            if ($note !== '宜行事') {
                                $note_class = ($note === '星期六' || $note === '星期日') ? 'holiday-note weekend-note' : 'holiday-note';
                                echo "<span class='{$note_class}' title='{$note}'>{$note}</span>";
                            }

                            foreach ($events as$ev) {
                                $tooltip = "【" . $ev['category'] . "】" . $ev['title'] . "\n詳細內容：" . $ev['content'];
                                echo "<span class='event-badge' title='{$tooltip}' onclick=\"alert('{$tooltip}')\">" . htmlspecialchars($ev['title']) . "</span>";
                            }

                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function changeYear(year) {
            window.location.href = "?year=" + year;
        }
    </script>

</body>
</html>