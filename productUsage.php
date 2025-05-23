<?php
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type");

$host = 'localhost';
$db   = 'tmf767';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Database connection error: " . $e->getMessage()]);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

if (in_array($method, ['GET', 'POST', 'DELETE', 'PATCH'])) {
    header('Content-Type: application/json');

    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['usageType'], $data['status'], $data['usageDate'], $data['characteristics'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO product_usage (usageType, status, usageDate, characteristics) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['usageType'],
            $data['status'],
            $data['usageDate'],
            json_encode($data['characteristics'])
        ]);
        echo json_encode(['message' => '✅ Created']);
        exit;
    }

    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM product_usage WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $row['characteristics'] = json_decode($row['characteristics'], true);
            echo json_encode($row ?: []);
        } else {
            $stmt = $pdo->query("SELECT * FROM product_usage ORDER BY id DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['characteristics'] = json_decode($r['characteristics'], true);
            }
            echo json_encode($rows ?: []);
        }
        exit;
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM product_usage WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => '🗑️ Deleted']);
        exit;
    }

    if ($method === 'PATCH' && $id) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing data to update']);
            exit;
        }

        $set = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key === 'characteristics') {
                $set[] = "characteristics = ?";
                $params[] = json_encode($value);
            } else {
                $set[] = "$key = ?";
                $params[] = $value;
            }
        }
        $params[] = $id;
        $sql = "UPDATE product_usage SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['message' => '✏️ Updated']);
        exit;
    }
}

// මෙතනින් පහලට HTML serve කරන කොටසයි (API calls වලින් exit වුනාම මෙතනට ආවෙ නෑ)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>TMF767 Product Usage</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
    .container { background: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
    input, textarea { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; }
    button { padding: 10px 20px; margin-top: 10px; background: #4CAF50; color: white; border: none; cursor: pointer; }
    button:hover { background: #45a049; }
    ul { list-style-type: none; padding-left: 0; }
    li { padding: 5px 0; border-bottom: 1px solid #ddd; }
</style>
</head>
<body>
<div class="container">
    <h2>Product Usage Entry</h2>
    <form id="usageForm">
        <input type="text" id="usageType" placeholder="Usage Type (e.g., VOICE)" required /><br />
        <input type="text" id="status" placeholder="Status (e.g., billed)" required /><br />
        <textarea id="characteristics" placeholder='{"volume": "1GB", "duration": "60min"}'></textarea><br />
        <button type="submit">Submit</button>
    </form>

    <h3>Submitted Product Usage</h3>
    <ul id="usageList"></ul>
</div>

<script>
document.getElementById('usageForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const usageType = document.getElementById('usageType').value.trim();
    const status = document.getElementById('status').value.trim();
    const characteristicsText = document.getElementById('characteristics').value.trim();

    let characteristics = {};
    if (characteristicsText) {
        try {
            characteristics = JSON.parse(characteristicsText);
        } catch (error) {
            alert("⚠️ Characteristics must be valid JSON!");
            return;
        }
    }

    const data = {
        usageType,
        status,
        usageDate: new Date().toISOString(),
        characteristics
    };

    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(errorText || 'Unknown error');
        }

        await loadUsages();
        this.reset();
    } catch (err) {
        alert("⚠️ Failed to submit usage: " + err.message);
    }
});

async function loadUsages() {
    try {
        const response = await fetch('');
        if (!response.ok) throw new Error('Failed to fetch usage data');
        const data = await response.json();

        const usageList = document.getElementById('usageList');
        usageList.innerHTML = '';

        if (!data.length) {
            usageList.innerHTML = '<li>No product usage entries found.</li>';
            return;
        }

        data.forEach(item => {
            const li = document.createElement('li');
            li.innerHTML = `<b>ID ${item.id}:</b> ${item.usageDate} - ${item.usageType} [${item.status}]`;
            usageList.appendChild(li);
        });
    } catch (err) {
        document.getElementById('usageList').innerHTML = `<li>⚠️ Error loading data: ${err.message}</li>`;
    }
}

window.onload = loadUsages;
</script>
</body>
</html>

