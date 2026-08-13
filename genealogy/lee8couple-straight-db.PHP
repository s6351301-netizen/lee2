<?php
// ==========================================
// 1. PHP 後端邏輯：資料庫連接與數據處理
// ==========================================

// 資料庫連線設定 (依照您的要求)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "li_genealogy";

$conn = new mysqli($servername,$username, $password,$dbname);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 獲取所有成員數據
$sql = "SELECT member_id, name, wife_name, generation_level, branch_info, parent_id FROM family_members ORDER BY member_id ASC";
$result = $conn->query($sql);

$members = [];
if ($result->num_rows > 0) {
    while($row =$result->fetch_assoc()) {
        $members[] =$row;
    }
}
$conn->close();

// 遞迴函式：將平面資料陣列轉換為 D3.js 所需的階層樹狀結構
function buildTree(array $elements, $parentId = null) {$branch = [];
    foreach ($elements as$element) {
        if ($element['parent_id'] ==$parentId) {
            // 創建一個新的節點，符合 D3.js hierarchy 的數據結構
            $children = buildTree($elements, $element['member_id']);$node = [
                'data' => [
                    'member_id' => $element['member_id'],
                    'name' => $element['name'],
                    'wifeName' => $element['wife_name'],
                    // 原始碼中沒有直接存儲 generation_level，它是動態計算的
                    // 這裡我們存儲原始值供前端使用
                    'level_val' => $element['generation_level'],
                    'branch_info' => $element['branch_info']
                ]
            ];
            // 如果有子節點，添加 children 屬性
            if ($children) {
                $node['children'] =$children;
            }
            // 重要：保留原始碼中用於收合的屬性結構
            // 在 PHP 中我們無法得知用戶在網頁上的點擊狀態，
            // 所以我們預設展開所有節點，或者您可以根據需要在這裡編寫邏輯來設置 _children
            
            $branch[] =$node;
        }
    }
    return $branch;
}

// 根據資料庫數據構建樹的根節點
$treeDataArray = buildTree($members, null);
// 網頁原始碼是單一根節點，所以我們取第一個元素
$treeDataObject =$treeDataArray[0] ?? ['data' => ['name' => '無資料']];

// 將 PHP 陣列轉換為 JSON 字串，供前端 JavaScript 使用
$familyDataJson = json_encode($treeDataObject, JSON_UNESCAPED_UNICODE);

// ==========================================
// 2. HTML 與 JavaScript 前端渲染部分
// ==========================================
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>李氏家族八代宗親夫妻並列族譜 (PHP + D3.js 實作版)</title>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        body { font-family: "PingFang TC", "Microsoft JhengHei", sans-serif; margin: 0; overflow: hidden; background-color: #f4f7f9; }
        #title-container { position: absolute; top: 15px; left: 20px; z-index: 10; background: rgba(255, 255, 255, 0.95); padding: 12px 24px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 5px solid #1a5276; }
        h1 { margin: 0 0 5px 0; font-size: 22px; color: #1a5276; }
        p { margin: 0; font-size: 13px; color: #555; line-height: 1.4; }
        .node circle.husband { fill: #e3f2fd; stroke: #1e88e5; stroke-width: 2px; cursor: pointer; }
        .node circle.wife { fill: #fce4ec; stroke: #d81b60; stroke-width: 2px; cursor: pointer; }
        .node text { font-size: 16px; font-weight: bold; fill: #222222; }
        .text-gen-info { font-size: 12px !important; font-weight: bold !important; fill: #566573 !important; }
        .spouse-line { stroke: #383838; stroke-width: 2.2px; stroke-dasharray: 2,2; }
        .link { fill: none; stroke: #566573; stroke-width: 2px; stroke-linejoin: round; }
        #hint { position: absolute; bottom: 15px; right: 20px; background: #333; color: #fff; padding: 6px 12px; font-size: 12px; border-radius: 4px; opacity: 0.8; }
    </style>
</head>
<body>
    <div id="title-container">
        <h1>隴西李氏家族族譜 - 直式夫妻並列版</h1>
        <p><strong>字輩順序：</strong>繩 ➔ 祖 ➔ 武 ➔ 德 ➔ 業 ➔ 貽 ➔ 孫 ➔ 謀</p>
    </div>
    <div id="hint">💡 提示：點擊任何圓圈可【展開/收合】子孫。滑鼠滾輪可【放大/縮小】、按住可【拖曳畫布】。</div>
    <div id="tree-container"></div>

    <script>
        // 從 PHP 讀取 JSON 格式的族譜數據
        const familyData = <?php echo $familyDataJson; ?>;

        // 以下是 D3.js 渲染邏輯，與原始碼幾乎相同，
        // 只是調整了從 d.data 對象讀取屬性的方式以適應資料庫結構。
        
        const width = window.innerWidth, height = window.innerHeight;
        const svg = d3.select("#tree-container").append("svg").attr("width", width).attr("height", height);
        const gMain = svg.append("g");
        svg.call(d3.zoom().scaleExtent([0.02, 3]).on("zoom", (e) => gMain.attr("transform", e.transform)));

        // 設置節點間距
        const tree = d3.tree().nodeSize([175, 190]);
        let i = 0, root = d3.hierarchy(familyData);
        root.x0 = width / 2; root.y0 = 0;

        // 原始碼中默認收合所有子孫。我們保持這個行為，
        // 但在 PHP 生成數據時為了簡單，我們設置了 children。
        // 在這裡我們再次將其收合以匹配原始網頁的初始狀態。
        root.children.forEach(function collapse(d) { 
            if(d.children){ 
                d._children = d.children; 
                d._children.forEach(collapse); 
                d.children = null; 
            } 
        });

        update(root);

        function update(source) {
            const nodes = tree(root).descendants();
            const links = tree(root).links();
            
            const node = gMain.selectAll('g.node').data(nodes, d => d.id || (d.id = ++i));

            const nodeEnter = node.enter().append('g').attr('class', 'node')
                .attr("transform", `translate(${source.x0 + width/2}, ${source.y0 + 150})`);

            const genTextGroup = nodeEnter.append('text').attr('class', 'text-gen-info').attr("x", -10).attr("text-anchor", "end");
            
            // 頂部文字邏輯 (修改為從資料庫字段讀取)
            genTextGroup.append('tspan').attr("x", -10).attr("dy", "-3.5em")
                .text(d => d.depth === 0 ? d.data.branch_info.split(' / ')[0] : `No.${d.data.member_id}`);
            
            genTextGroup.append('tspan').attr("x", -10).attr("dy", "1.2em")
                .text(d => d.depth === 0 ? d.data.branch_info.split(' / ')[1] : d.data.branch_info);
            
            // 計算世代 (原始碼邏輯是 19 + d.depth + 1)
            genTextGroup.append('tspan').attr("x", -10).attr("dy", "1.2em")
                .text(d => `${d.data.level_val}世${d.depth + 1}代`);

            nodeEnter.append('circle').attr('class', 'husband').attr('r', 1e-6).on('click', click);
            nodeEnter.append('text').attr('class', 'text-husband').attr("dy", ".35em").attr("x", -10).attr("text-anchor", "end").text(d => d.data.name);
            nodeEnter.append('line').attr('class', 'spouse-line');
            nodeEnter.append('circle').attr('class', 'wife').attr('r', 1e-6).on('click', click);
            nodeEnter.append('text').attr('class', 'text-wife').attr("dy", ".35em").attr("x", 40).text(d => d.data.wifeName);

            const nodeUpdate = nodeEnter.merge(node).transition().duration(750)
                .attr("transform", d => `translate(${d.x + width/2}, ${d.y + 150})`);

            nodeUpdate.select('circle.husband').attr('r', 6).style("fill", d => d._children ? "#1e88e5" : "#fff");
            nodeUpdate.select('circle.wife').attr('r', 6).attr('cx', 28);
            nodeUpdate.select('line.spouse-line').attr('x1', 6).attr('x2', 22);
            
            node.exit().transition().duration(750).remove();

            const link = gMain.selectAll('path.link').data(links, d => d.target.id);
            
            // 連結路徑邏輯 (與原始碼相同，完整補完)
        link.enter().insert('path', 'g').attr("class", "link")
            // 初始位置設置為父節點中心
            .attr("d", d => {
                const o = {x: source.x0 + width/2, y: source.y0 + 150};
                return diagonal({source: o, target: o});
            })
            // 合併更新與進入的連結
            .merge(link).transition().duration(750)
            // 設置最終路徑形狀 (與原始碼完全一致)
            .attr('d', d => `M ${d.source.x + width/2} ${d.source.y + 150} 
                             L ${d.source.x + width/2} ${(d.source.y + d.target.y)/2 + 150} 
                             L ${d.target.x + width/2} ${(d.source.y + d.target.y)/2 + 150} 
                             L ${d.target.x + width/2} ${d.target.y + 150}`);

        // 移除離開的連結
        link.exit().transition().duration(750)
            .attr("d", d => {
                const o = {x: source.x + width/2, y: source.y + 150};
                return diagonal({source: o, target: o});
            })
            .remove();

        // 儲存舊位置以供動畫參考
        nodes.forEach(d => {
            d.x0 = d.x;
            d.y0 = d.y;
        });
    }

    // 展開/收合點擊事件處理函式 (與原始碼相同)
    function click(e, d) {
        if (d.children) {
            d._children = d.children;
            d.children = null;
        } else {
            d.children = d._children;
            d._children = null;
        }
        update(d);
    }
    </script>
</body>
</html>