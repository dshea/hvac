<?php
/**
 * graph.php - Interactive HVAC data visualization using Plotly
 * 
 * Displays line graphs for:
 * - Temperature (°F) over time
 * - Humidity (%) over time  
 * - HVAC Stage (0-3) over time
 * 
 * Query parameters:
 * - hours: number of hours to display (default: 24)
 */

require_once "hvacUtils.php";

function aggrigateStage($timeWindow, $index, &$times, &$stages, &$stagesTimeOn) {

    $startTime = $times[$index] - $timeWindow;
    $endTime = $times[$index];

    $stagesTimeOn[0] = 0;
    $stagesTimeOn[1] = 0;
    $stagesTimeOn[2] = 0;
    $stagesTimeOn[3] = 0;

    // find index where time <= startTime
    $startFound = false;
    for ($i = $index; $i >= 0; $i--) {
        if ($times[$i] <= $startTime) {
            $startFound = true;
            break;
        }
    }

    // bail if do not have an timeWindow of data
    if (!$startFound) {
        return false;
    }

    $i += 1; // move to first index > startTime

    for (; $i <= $index; $i++) {

        // debug
        //echo "i = $i, stage = $stages[$i]\n";

        $deltaTime = $times[$i] - $times[$i - 1];

        $stagesTimeOn[0] += $deltaTime;
        if ($stages[$i - 1] == 1) {
            $stagesTimeOn[1] += $deltaTime;
        } else if ($stages[$i - 1] == 2) {
            $stagesTimeOn[1] += $deltaTime;
            $stagesTimeOn[2] += $deltaTime;
        } else if ($stages[$i - 1] == 3) {
            $stagesTimeOn[1] += $deltaTime;
            $stagesTimeOn[2] += $deltaTime;
            $stagesTimeOn[3] += $deltaTime;
        }
    }
    return true;
}

// Get hours parameter (default 24)
$hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;
if ($hours < 1 || $hours > 8760) {
    $hours = 24; // clamp to reasonable range (1 hour to 1 year)
}

// Optional end time parameter (ISO 8601 / datetime-local format). If provided,
// use it as the graph end point; otherwise use current time.
$endParam = isset($_GET['end']) ? $_GET['end'] : null;
if ($endParam) {
    $endTime = strtotime($endParam);
    if ($endTime === false) {
        // fallback to now if parsing failed
        $endTime = time();
    }
} else {
    $endTime = time();
}

// Calculate time range start
$startTime = $endTime - ($hours * 3600);

// debug
// echo "startTime = $startTime, endTime = $endTime\n";

// Query database
$db = open_database();
$result = $db->query("SELECT time, stage, temperature, humidity FROM hvac WHERE time >= $startTime AND time <= $endTime ORDER BY time ASC");

$times = [];
$temps = [];
$humidities = [];
$stages = [];

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $times[] = $row['time'];
    $temps[] = $row['temperature'];
    $humidities[] = $row['humidity'];
    $stages[] = $row['stage'];
}
close_database($db);

$total = count($stages);

//calculate stage percentages
$stage1Percent = [];
$stage2Percent = [];
$stage3Percent = [];

$timeWindow = 2 * 3600; // 1 hour in seconds
$stagesTimeOn = [];

for($i = 0; $i < $total; $i++) {
    if(aggrigateStage($timeWindow, $i, $times, $stages, $stagesTimeOn)) {
        $stage1Percent[$i] = $stagesTimeOn[1] / $stagesTimeOn[0] * 100;
        $stage2Percent[$i] = $stagesTimeOn[2] / $stagesTimeOn[0] * 100;
        $stage3Percent[$i] = $stagesTimeOn[3] / $stagesTimeOn[0] * 100;
    } else {
        $stage1Percent[$i] = 0;
        $stage2Percent[$i] = 0;
        $stage3Percent[$i] = 0;
    }
}

// Debug: print stage percentages
// for($i = 0; $i < $total; $i++) {
//     echo "Time: " . date('Y-m-d H:i:s', $times[$i]) . ", Stage: " . $stages[$i] . ", Stage1%: " . $stage1Percent[$i] . "\n";
// }


// Convert Unix timestamps to ISO 8601 for Plotly
$isoTimes = array_map(function($t) { return date('Y-m-d\TH:i:s', $t); }, $times);

// $stage1String = array_map(function($t) { return (float) $t; }, $stage1Percent);
// $stage2String = array_map(function($t) { return (float) $t; }, $stage2Percent);
// $stage3String = array_map(function($t) { return (float) $t; }, $stage3Percent);

// $stage1String = implode(",", $stage1Percent);
// $stage2String = implode(",", $stage2Percent);
// $stage3String = implode(",", $stage3Percent);

// $stage1Percent2 = explode(",", $stage1String);
// $stage2Percent2 = explode(",", $stage2String);
// $stage3Percent2 = explode(",", $stage3String);

// Build JSON data for Plotly
$plotData = [
    [
        'x' => $isoTimes,
        'y' => $stages,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Stage (0-3)',
        'yaxis' => 'y1',
        'line' => ['color' => '#6dff6dff'],
    ],
    [
        'x' => $isoTimes,
        'y' => $stage1Percent,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Stage 1 (%)',
        'yaxis' => 'y1',
        'line' => ['color' => '#000000ff'],
    ],
    [
        'x' => $isoTimes,
        'y' => $stage2Percent,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Stage 2 (%)',
        'yaxis' => 'y1',
        'line' => ['color' => '#726dffff'],
    ],
    [
        'x' => $isoTimes,
        'y' => $stage3Percent,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Stage 3 (%)',
        'yaxis' => 'y1',
        'line' => ['color' => '#ff6df0ff'],
    ],
    [
        'x' => $isoTimes,
        'y' => $temps,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Temp (°F)',
        'yaxis' => 'y2',
        'line' => ['color' => '#FF6B6B'],
    ],
    // [
    //     'x' => $isoTimes,
    //     'y' => $humidities,
    //     'type' => 'scatter',
    //     'mode' => 'lines',
    //     'name' => 'Humidity (%)',
    //     'yaxis' => 'y3',
    //     'line' => ['color' => '#4ECDC4'],
    // ],
];

$layout = [
    'title' => "HVAC Monitor - Last $hours Hours",
    'xaxis' => [
        'title' => 'Time',
    ],
    'yaxis' => [
        'title' => 'On Time (%)',
        'position' => 0,
    ],
    'yaxis2' => [
        'title' => 'Temperature (°F)',
        'position' => 1,
        'overlaying' => 'y',
        'side' => 'right',
    ],
    // 'yaxis3' => [
    //     'title' => 'Humidity (%)',
    //     'position' => 0.5,
    //     'overlaying' => 'y',
    //     'side' => 'left',
    // ],
    'hovermode' => 'x unified',
    'margin' => ['l' => 80, 'r' => 80, 'b' => 60, 't' => 80],
];

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HVAC Monitor - Graph</title>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }
        .controls {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .controls label {
            font-weight: 500;
            color: #333;
        }
        .controls input,
        .controls select,
        .controls button {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .controls button {
            background: #667eea;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .controls button:hover {
            background: #5568d3;
        }
        #graph {
            width: 100%;
            height: 600px;
        }
        .footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 0.9rem;
            color: #666;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
        }
        .stat-box {
            background: white;
            border-left: 4px solid #667eea;
            padding: 1rem;
            border-radius: 6px;
        }
        .stat-box label {
            display: block;
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }
        .stat-box .value {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>HVAC Monitor</h1>
            <p>Real-time temperature, humidity, and stage tracking</p>
        </div>

        <div class="controls">
                <label for="hoursInput">Hours to display:</label>
                <select id="hoursSelect">
                <option value="1" <?= $hours == 1 ? 'selected' : '' ?>>1 hour</option>
                <option value="6" <?= $hours == 6 ? 'selected' : '' ?>>6 hours</option>
                <option value="12" <?= $hours == 12 ? 'selected' : '' ?>>12 hours</option>
                <option value="24" <?= $hours == 24 ? 'selected' : '' ?>>24 hours</option>
                <option value="72" <?= $hours == 72 ? 'selected' : '' ?>>72 hours</option>
                <option value="168" <?= $hours == 168 ? 'selected' : '' ?>>7 days</option>
                <option value="720" <?= $hours == 720 ? 'selected' : '' ?>>30 days</option>
            </select>
                <label for="endInput">End time:</label>
                <input id="endInput" type="datetime-local" value="<?= date('Y-m-d\\TH:i', $endTime) ?>">
            <button onclick="updateGraph()">Update</button>
        </div>

        <div id="graph"></div>

        <div class="stats">
            <div class="stat-box">
                <label>Current Temperature</label>
                <span class="value" id="statTemp">--</span>
            </div>
            <div class="stat-box">
                <label>Current Humidity</label>
                <span class="value" id="statHumidity">--</span>
            </div>
            <div class="stat-box">
                <label>Current Stage</label>
                <span class="value" id="statStage">--</span>
            </div>
            <div class="stat-box">
                <label>Data Points</label>
                <span class="value" id="statCount">--</span>
            </div>
        </div>

        <div class="footer">
            <p>Data auto-refreshes every 60 seconds. Last update: <span id="lastUpdate">--</span></p>
        </div>
    </div>

    <script>
        const plotData = <?= json_encode($plotData, JSON_UNESCAPED_SLASHES) ?>;
        const layout = <?= json_encode($layout, JSON_UNESCAPED_SLASHES) ?>;

        const currentTemp = <?= (string) $temps[$total-1] ?>;
        const currentHumidity = <?= (string) $humidities[$total-1] ?>;
        const currentStage = <?= (string) $stages[$total-1] ?>;
        const dataPointCount = <?= (string) $total ?>;

        function renderGraph(data, layout) {
            Plotly.newPlot('graph', data, layout, {responsive: true});
        }

        function updateStats() {
            if (plotData.length >= 3) {
                document.getElementById('statTemp').textContent = currentTemp.toFixed(1) + '°F';
                document.getElementById('statHumidity').textContent = currentHumidity.toFixed(1) + '%';
                document.getElementById('statStage').textContent = Math.round(currentStage);
                document.getElementById('statCount').textContent = dataPointCount;
            }
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
        }

        function updateGraph() {
            const hours = document.getElementById('hoursSelect').value;
            const endVal = document.getElementById('endInput').value; // format: YYYY-MM-DDTHH:MM
            let qs = '?hours=' + encodeURIComponent(hours);
            if (endVal && endVal.length > 0) {
                qs += '&end=' + encodeURIComponent(endVal);
            }
            window.location.href = qs;
        }

        // Initial render
        renderGraph(plotData, layout);
        updateStats();

        // Auto-refresh every 60 seconds
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
