<?php
// Function to handle the DepEd Transmutation Logic
// Based on the standard table used in the Philippine K-12 system
function transmute($initial_grade) {
    if ($initial_grade >= 100) return 100;
    if ($initial_grade >= 98.40) return 99;
    if ($initial_grade >= 96.80) return 98;
    if ($initial_grade >= 95.20) return 97;
    if ($initial_grade >= 93.60) return 96;
    if ($initial_grade >= 92.00) return 95;
    if ($initial_grade >= 90.40) return 94;
    if ($initial_grade >= 88.80) return 93;
    if ($initial_grade >= 87.20) return 92;
    if ($initial_grade >= 85.60) return 91;
    if ($initial_grade >= 84.00) return 90;
    if ($initial_grade >= 82.40) return 89;
    if ($initial_grade >= 80.80) return 88;
    if ($initial_grade >= 79.20) return 87;
    if ($initial_grade >= 77.60) return 86;
    if ($initial_grade >= 76.00) return 85;
    if ($initial_grade >= 74.40) return 84;
    if ($initial_grade >= 72.80) return 83;
    if ($initial_grade >= 71.20) return 82;
    if ($initial_grade >= 69.60) return 81;
    if ($initial_grade >= 68.00) return 80;
    if ($initial_grade >= 66.40) return 79;
    if ($initial_grade >= 64.80) return 78;
    if ($initial_grade >= 63.20) return 77;
    if ($initial_grade >= 61.60) return 76;
    if ($initial_grade >= 60.00) return 75;
    return 60; // Below 60 initial grade usually transmutes to 60 or below
}

$result = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Written Works Calculation (30%)
    $ww_total = array_sum($_POST['ww']);
    $ww_hps = 80; // Highest Possible Score from your Excel
    $ww_ps = ($ww_total / $ww_hps) * 100;
    $ww_ws = $ww_ps * 0.30;

    // 2. Performance Tasks Calculation (50%)
    $pt_total = array_sum($_POST['pt']);
    $pt_hps = 80; // Highest Possible Score from your Excel
    $pt_ps = ($pt_total / $pt_hps) * 100;
    $pt_ws = $pt_ps * 0.50;

    // 3. Quarterly Assessment Calculation (20%)
    $qa_score = $_POST['qa'];
    $qa_hps = 40; // Highest Possible Score from your Excel
    $qa_ps = ($qa_score / $qa_hps) * 100;
    $qa_ws = $qa_ps * 0.20;

    // 4. Final Totals
    $initial_grade = $ww_ws + $pt_ws + $qa_ws;
    $quarterly_grade = transmute($initial_grade);

    $result = [
        'initial' => round($initial_grade, 2),
        'final' => $quarterly_grade
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AP 6 Class Record Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4 text-center">Class Record Calculator (AP 6)</h2>
    
    <form method="POST" class="bg-white p-4 shadow-sm rounded">
        <div class="row">
            <div class="col-md-4">
                <h5>Written Works (30%)</h5>
                <p class="text-muted small">Max Score: 80</p>
                <?php for($i=1; $i<=4; $i++): ?>
                    <div class="mb-2">
                        <label>WW <?=$i?> Score:</label>
                        <input type="number" name="ww[]" class="form-control" required min="0" max="20">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="col-md-4">
                <h5>Performance Tasks (50%)</h5>
                <p class="text-muted small">Max Score: 80</p>
                <?php for($i=1; $i<=4; $i++): ?>
                    <div class="mb-2">
                        <label>PT <?=$i?> Score:</label>
                        <input type="number" name="pt[]" class="form-control" required min="0" max="20">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="col-md-4">
                <h5>Quarterly Assessment (20%)</h5>
                <p class="text-muted small">Max Score: 40</p>
                <div class="mb-2">
                    <label>Exam Score:</label>
                    <input type="number" name="qa" class="form-control" required min="0" max="40">
                </div>
                
                <hr>
                <button type="submit" class="btn btn-primary w-100">Calculate Grade</button>
            </div>
        </div>
    </form>

    <?php if ($result): ?>
    <div class="mt-5 p-4 bg-dark text-white rounded text-center">
        <h3>Results</h3>
        <p>Initial Grade: <strong><?= $result['initial'] ?></strong></p>
        <h1 class="display-4 text-warning">Quarterly Grade: <?= $result['final'] ?></h1>
    </div>
    <?php endif; ?>
</div>
</body>
</html>