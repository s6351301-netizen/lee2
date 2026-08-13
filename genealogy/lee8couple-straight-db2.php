<?php
// 資料庫連線設定
$host = 'localhost';
$dbname = 'li_genealogy';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 查詢全部家族成員資料
    $stmt = $pdo->query("SELECT member_id, name, wife_name, generation_level, branch_info, parent_id FROM family_members");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}

// 將資料轉換為 D3.js 樹狀結構所需的巢狀 JSON
// 頂部文字邏輯 (動態讀取資料庫字段)
$map = [];
foreach ($members as &$member) {
    $member['wifeName'] = $member['wife_name']; // 對應前端屬性
    $member['level_val'] = $member['generation_level'];
    $map[$member['member_id']] = &$member;
    $map[$member['member_id']]['children'] = [];
}

$treeData = null;
foreach ($members as &$member) {
    if ($member['parent_id'] === null) {
        $treeData = &$map[$member['member_id']];
    } else {
        if (isset($map[$member['parent_id']])) {
            $map[$member['parent_id']]['children'][] = &$map[$member['member_id']];
        }
    }
}
unset($member);

$jsonTreeData = json_encode($treeData, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>李氏家族八代宗親夫妻並列族譜</title>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        body {
            font-family: 'Microsoft JhengHei', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            overflow: hidden;
        }
        .node circle.husband {
            fill: #fff;
            stroke: #1e88e5;
            stroke-width: 2.5px;
            cursor: pointer;
        }
        .node circle.wife {
            fill: #fff;
            stroke: #e91e63;
            stroke-width: 2.5px;
        }
        .node text {
            font-size: 12px;
        }
        .text-husband {
            fill: #333;
            font-weight: bold;
        }
        .text-wife {
            fill: #c2185b;
        }
        .text-gen-info {
            fill: #666;
            font-size: 11px;
        }
        .spouse-line {
            stroke: #999;
            stroke-width: 1.5px;
            stroke-dasharray: 3,3;
        }
        .link {
            fill: none;
            stroke: #ccc;
            stroke-width: 1.5px;
        }
        #tree-container {
            width: 100vw;
            height: 100vh;
        }
    </style>
</head>
<body>
    <div id="tree-container"></div>
    <script>
        const treeData = <?php echo $jsonTreeData; ?>;

        const width = window.innerWidth;
        const height = window.innerHeight;

        const svg = d3.select("#tree-container").append("svg")
            .attr("width", width)
            .attr("height", height)
            .call(d3.zoom().on("zoom", (event) => {
                gMain.attr("transform", event.transform);
            }))
            .append("g");

        const gMain = svg.append("g")
            .attr("transform", "translate(50, 50)");

        const treemap = d3.tree().nodeSize([120, 180]);

        let root = d3.hierarchy(treeData, d => d.children);
        root.x0 = width / 2;
        root.y0 = 0;

        // 預設收合部分節點
        root.children.forEach(collapse);

        update(root);

        function collapse(d) {
            if (d.children) {
                d._children = d.children;
                d._children.forEach(collapse);
                d.children = null;
            }
        }

        function update(source) {
            const treeDataResult = treemap(root);
            const nodes = treeDataResult.descendants();
            const links = treeDataResult.links();

            nodes.forEach(d => { d.y = d.depth * 140; });

            const node = gMain.selectAll('g.node')
                .data(nodes, d => d.data.member_id || (d.data.member_id = ++i));

            const nodeEnter = node.enter().append('g')
                .attr('class', 'node')
                .attr("transform", d => `translate(${source.x0 + width/2}, ${source.y0 + 150})`)
                .on('click', click);

            const genTextGroup = nodeEnter.append('text').attr('class', 'text-gen-info').attr("x", -10).attr("text-anchor", "end");
            
            genTextGroup.append('tspan').attr("x", -10).attr("dy", "-3.5em")
                .text(d => d.depth === 0 ? d.data.branch_info.split(' / ')[0] : `No.${d.data.member_id}`);
            
            genTextGroup.append('tspan').attr("x", -10).attr("dy", "1.2em")
                .text(d => d.depth === 0 ? d.data.branch_info.split(' / ')[1] : d.data.branch_info);
            
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
            nodeUpdate.select('line.spouse-line').attr('x1', 0).attr('y1', 0).attr('x2', 28).attr('y2', 0);

            const nodeExit = node.exit().transition().duration(750)
                .attr("transform", d => `translate(${source.x + width/2}, ${source.y + 150})`).remove();

            nodeExit.select('circle').attr('r', 1e-6);

            const link = gMain.selectAll('path.link').data(links, d => d.target.data.member_id);

            link.enter().insert('path', 'g').attr("class", "link")
                .attr("d", d => {
                    const o = {x: source.x0 + width/2, y: source.y0 + 150};
                    return diagonal({source: o, target: o});
                })
                .merge(link).transition().duration(750)
                .attr('d', d => `M ${d.source.x + width/2} ${d.source.y + 150} 
                                 L ${d.source.x + width/2} ${(d.source.y + d.target.y)/2 + 150} 
                                 L ${d.target.x + width/2} ${(d.source.y + d.target.y)/2 + 150} 
                                 L ${d.target.x + width/2} ${d.target.y + 150}`);

            link.exit().transition().duration(750)
                .attr("d", d => {
                    const o = {x: source.x + width/2, y: source.y + 150};
                    return diagonal({source: o, target: o});
                })
                .remove();

            nodes.forEach(d => {
                d.x0 = d.x;
                d.y0 = d.y;
            });
        }

        function diagonal(s) {
            return `M ${s.source.x} ${s.source.y}
                    C ${s.source.x} ${(s.source.y + s.target.y) / 2},
                      ${s.target.x} ${(s.source.y + s.target.y) / 2},
                      ${s.target.x} ${s.target.y}`;
        }

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