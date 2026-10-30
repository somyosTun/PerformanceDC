<?php
// ==========================================
// 1. DATABASE & INITIALIZATION (PHP 8.5)
// ==========================================
include 'conDBserver83.php';

$adate = date("Y-m-d");
$Xdate = date("d/m/Y");

if (isset($_POST["date"]) && $_POST["date"] != '') {
    $adate = $_POST["date"];
    $X = explode("-", $adate);
    if (count($X) === 3) {
        $Xdate = $X[2].'/'.$X[1].'/'.$X[0]; 
    }
}

// Check Database BOM
$ss = "SELECT TOP 1 ActivityDate FROM [DoubleCheckLoad].[dbo].[tblBOM] WHERE ActivityDate = '$Xdate'";
$qq = sqlsrv_query($cidN, $ss);
$dd = $qq ? sqlsrv_fetch_array($qq) : null;
$checkDateInBOM = isset($dd["ActivityDate"]) ? $dd["ActivityDate"] : '';

$sql_database = ($checkDateInBOM != '') ? "FROM [DoubleCheckLoad].[dbo].[tblBOM] " : "FROM [DoubleCheckLoad].[dbo].[tblBOM_Bak] ";

// WORK LOAD
$sql_workload = "SELECT TOP 1 SUM(Qty) AS Qty, SUM(OrderQty) AS OrderQty FROM [DoubleCheckLoad].[dbo].[tblBOM]";
$q_workload = sqlsrv_query($cidN, $sql_workload);
$ii = $q_workload ? sqlsrv_fetch_array($q_workload) : null;

$qty_ii = isset($ii["Qty"]) ? (float)$ii["Qty"] : 0;
$orqty_ii = isset($ii["OrderQty"]) ? (float)$ii["OrderQty"] : 0;
$percent_num = ($qty_ii > 0) ? ($orqty_ii / $qty_ii) * 100 : 0;
$percent = number_format($percent_num, 2) . '%';

// QUERY TABLE DATA
$sql_q = "SELECT TOP 30 ActivityDate, DATEPART(HOUR, Lastmodify) AS hr, COUNT(DISTINCT [IPAddress]) AS cnt_IP, SUM(OrderQty) AS sumScanQty,
          SUM(CASE WHEN Aisle IN ('19', '72', '73', 'H1') THEN OrderQty ELSE 0 END) AS m_3g,
          SUM(CASE WHEN Aisle IN ('3J', '22', '24', '25', '27', '28', '30', '31', '33', '34', '36', '37', '39', '40', '42', '43', 
                                  '45', '46', '48', '49', '51', '52', '54', '55', '57', '58', '60', '61', '63', '64', '66', '67', '69') THEN OrderQty ELSE 0 END) AS fc
          $sql_database
          WHERE ActivityDate = '$Xdate' AND DATEPART(HOUR, Lastmodify) IS NOT NULL
          GROUP BY DATEPART(MONTH, Lastmodify), DATEPART(DAY, Lastmodify), ActivityDate, DATEPART(HOUR, Lastmodify) 
          ORDER BY DATEPART(MONTH, Lastmodify) DESC, DATEPART(DAY, Lastmodify) DESC, DATEPART(HOUR, Lastmodify) DESC";
$query = odbc_exec($conn, $sql_q);

// QUERY ROLL CASE
$RollCaseFull = [];
$s_roll = "SELECT DATEPART(HOUR, Lastmodify) AS Hr, COUNT(DISTINCT PalletNo) AS PL
           $sql_database
           WHERE Aisle IN ('22', '24', '25', '27', '28', '30', '31', '33', '34', '36', '37', '39', '40', '42', '43', '45', '46', '48', '49', '51', '52', '54', '55', '57', '58', '60', '61', '63', '64', '66', '67', '69')
             AND ActivityDate = '$Xdate' AND Lastmodify IS NOT NULL
           GROUP BY DATEPART(HOUR, Lastmodify)";
$query_roll = odbc_exec($conn, $s_roll);
if ($query_roll) {
    while ($r_roll = odbc_fetch_array($query_roll)) {
        $RollCaseFull[$r_roll["Hr"]] = $r_roll["PL"];
    }
}

// QUERY QTY BY BLOCK
$BLOCK_Qty_A = []; $BLOCK_Qty_B = []; $BLOCK_Qty_C = []; $BLOCK_Qty_D = []; $BLOCK_Qty_E = [];
$ss_QtyByBlock = "SELECT DATEPART(HOUR, Lastmodify) AS Hr, Door, SUM(OrderQty) AS PL
                  $sql_database
                  WHERE Aisle IN ('22', '24', '25', '27', '28', '30', '31', '33', '34', '36', '37', '39', '40', '42', '43', '45', '46', '48', '49', '51', '52', '54', '55', '57', '58', '60', '61', '63', '64', '66', '67', '69')
                    AND ActivityDate = '$Xdate' AND Lastmodify IS NOT NULL    
                  GROUP BY DATEPART(HOUR, Lastmodify), Door
                  ORDER BY DATEPART(HOUR, Lastmodify) ASC, Door ASC";
$query_ss_QtyByBlock = odbc_exec($conn, $ss_QtyByBlock);
if ($query_ss_QtyByBlock) {
    while ($r_s_QtyByBlock = odbc_fetch_array($query_ss_QtyByBlock)) {
        $group_hr   = $r_s_QtyByBlock["Hr"] ?? 0;
        $GD         = (int)($r_s_QtyByBlock["Door"] ?? 0);
        $group_Qty  = (float)($r_s_QtyByBlock["PL"] ?? 0);

        if ($GD <= 9) { $BLOCK_Qty_A[$group_hr] = ($BLOCK_Qty_A[$group_hr] ?? 0) + $group_Qty; }
        elseif ($GD > 9 && $GD <= 16) { $BLOCK_Qty_B[$group_hr] = ($BLOCK_Qty_B[$group_hr] ?? 0) + $group_Qty; }
        elseif ($GD > 16 && $GD <= 23) { $BLOCK_Qty_C[$group_hr] = ($BLOCK_Qty_C[$group_hr] ?? 0) + $group_Qty; }
        elseif ($GD > 23 && $GD <= 30) { $BLOCK_Qty_D[$group_hr] = ($BLOCK_Qty_D[$group_hr] ?? 0) + $group_Qty; }
        elseif ($GD > 30) { $BLOCK_Qty_E[$group_hr] = ($BLOCK_Qty_E[$group_hr] ?? 0) + $group_Qty; }
    }
}

// QUERY ROLL BY BLOCK
$BLOCK_A = []; $BLOCK_B = []; $BLOCK_C = []; $BLOCK_D = []; $BLOCK_E = [];
$ss_roll = "SELECT DATEPART(HOUR, Lastmodify) AS Hr, Door, COUNT(DISTINCT PalletNo) AS PL
            $sql_database
            WHERE Aisle IN ('22', '24', '25', '27', '28', '30', '31', '33', '34', '36', '37', '39', '40', '42', '43', '45', '46', '48', '49', '51', '52', '54', '55', '57', '58', '60', '61', '63', '64', '66', '67', '69')
              AND ActivityDate = '$Xdate' AND Lastmodify IS NOT NULL    
            GROUP BY DATEPART(HOUR, Lastmodify), Door
            ORDER BY DATEPART(HOUR, Lastmodify) ASC, Door ASC";
$query_ss_roll = odbc_exec($conn, $ss_roll);
if ($query_ss_roll) {
    while ($r_s_roll = odbc_fetch_array($query_ss_roll)) {
        $group_hr   = $r_s_roll["Hr"] ?? 0;
        $GD         = (int)($r_s_roll["Door"] ?? 0);
        $group_Cnt  = (float)($r_s_roll["PL"] ?? 0);

        if ($GD <= 9) { $BLOCK_A[$group_hr] = ($BLOCK_A[$group_hr] ?? 0) + $group_Cnt; }
        elseif ($GD > 9 && $GD <= 16) { $BLOCK_B[$group_hr] = ($BLOCK_B[$group_hr] ?? 0) + $group_Cnt; }
        elseif ($GD > 16 && $GD <= 23) { $BLOCK_C[$group_hr] = ($BLOCK_C[$group_hr] ?? 0) + $group_Cnt; }
        elseif ($GD > 23 && $GD <= 30) { $BLOCK_D[$group_hr] = ($BLOCK_D[$group_hr] ?? 0) + $group_Cnt; }
        elseif ($GD > 30) { $BLOCK_E[$group_hr] = ($BLOCK_E[$group_hr] ?? 0) + $group_Cnt; }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>รายงานแผนกส่งมอบ (Load DC MC)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap 5 & Font Awesome 6 & Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-bg: #f8f9fa;
            --card-border: #e3e6f0;
            --header-bg: #1e293b;
            --accent-blue: #2563eb;
        }

        body {
            background-color: var(--primary-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #334155;
        }

        .navbar-custom {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .card-kpi {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .card-kpi:hover {
            transform: translateY(-3px);
        }

        .table-custom {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            background: #fff;
        }

        .table-custom thead {
            background-color: #0f172a;
            color: #fff;
            text-align: center;
            vertical-align: middle;
            font-size: 0.88rem;
        }

        .table-custom tbody tr:hover {
            background-color: #f1f5f9;
        }

        .block-qty-header { background-color: #1e40af !important; color: #fff; }
        .block-roll-header { background-color: #0369a1 !important; color: #fff; }

        .progress {
            height: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-custom navbar-dark py-3 mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-outline-light btn-sm rounded-pill" onclick="window.location='/Speedload';">
                <i class="fa-solid fa-arrow-left me-1"></i> หน้าหลัก
            </button>
            <h4 class="text-white mb-0 fw-bold"><i class="fa-solid fa-truck-ramp-box me-2 text-warning"></i> รายงานแผนกส่งมอบ (Load DC MC)</h4>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="SpeedToXls.php?pDate=<?php echo $adate; ?>" class="btn btn-success btn-sm rounded-pill">
                <i class="fa-solid fa-file-excel me-1"></i> ส่งออก Excel
            </a>
            <span class="badge bg-light text-dark px-3 py-2 rounded-pill"><i class="fa-regular fa-clock me-1"></i> <?php echo date("Y-m-d H:i:s"); ?></span>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">

    <!-- Filter & Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card card-kpi p-3 bg-white h-100">
                <form action="" method="POST" id="dateForm">
                    <label class="form-label text-muted fw-semibold small mb-1"><i class="fa-regular fa-calendar me-1"></i> เลือกวันที่ต้องการดูข้อมูล</label>
                    <select name="date" onchange="document.getElementById('dateForm').submit();" class="form-select fw-bold border-primary">
                        <option value="<?php echo $adate; ?>"><?php echo $adate; ?></option>
                        <option value="<?php echo date("Y-m-d"); ?>"><?php echo date("Y-m-d"); ?></option>
                        <option value="<?php echo date("Y-m-d", strtotime("-1 days")); ?>"><?php echo date("Y-m-d", strtotime("-1 days")); ?></option>
                        <option value="<?php echo date("Y-m-d", strtotime("-2 days")); ?>"><?php echo date("Y-m-d", strtotime("-2 days")); ?></option>
                        <option value="<?php echo date("Y-m-d", strtotime("-3 days")); ?>"><?php echo date("Y-m-d", strtotime("-3 days")); ?></option>
                        <option value="<?php echo date("Y-m-d", strtotime("-4 days")); ?>"><?php echo date("Y-m-d", strtotime("-4 days")); ?></option>
                    </select>
                </form>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card card-kpi p-3 bg-white h-100 border-start border-4 border-primary">
                <div class="text-muted small fw-semibold"><i class="fa-solid fa-boxes-stacked me-1 text-primary"></i> Work Load ทั้งหมด</div>
                <div class="h3 fw-bold text-dark mt-2 mb-0"><?php echo number_format($qty_ii); ?></div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card card-kpi p-3 bg-white h-100 border-start border-4 border-success">
                <div class="text-muted small fw-semibold"><i class="fa-solid fa-circle-check me-1 text-success"></i> ยอดที่ทำได้จริง (Actual)</div>
                <div class="h3 fw-bold text-success mt-2 mb-0"><?php echo number_format($orqty_ii); ?></div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card card-kpi p-3 bg-white h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small fw-semibold"><i class="fa-solid fa-chart-line me-1 text-info"></i> ความคืบหน้า (Progress)</span>
                    <span class="badge bg-info text-dark fw-bold"><?php echo $percent; ?></span>
                </div>
                <div class="progress mt-3">
                    <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo min($percent_num, 100); ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="card card-kpi p-4 mb-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-column me-2 text-primary"></i> กราฟเปรียบเทียบยอดสแกน (Module All / FullCase) รายชั่วโมง</h5>
        </div>
        <div style="height: 380px;">
            <canvas id="loadPerformanceChart"></canvas>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="card card-kpi p-3 mb-4 bg-white">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th rowspan="2">ชั่วโมง</th>
                        <th rowspan="2">จำนวน<br>เครื่อง</th>
                        <th rowspan="2" class="bg-primary text-white">สแกนรวม<br>(Module All)</th>
                        <th rowspan="2" class="bg-primary text-white">เฉลี่ย<br>/ ประตู</th>
                        <th rowspan="2">ยอดสแกน<br>(FullCase)</th>
                        <th rowspan="2">ยอดสแกน<br>น้ำ (FT)</th>
                        <th rowspan="2">จำนวนโรล<br>(FullCase)</th>
                        <th colspan="5" class="block-qty-header">ยอดงานแยกตาม BLOCK (Qty)</th>
                        <th colspan="5" class="block-roll-header">จำนวนโรลแยกตาม BLOCK (Roll)</th>
                    </tr>
                    <tr>
                        <th class="block-qty-header">A</th>
                        <th class="block-qty-header">B</th>
                        <th class="block-qty-header">C</th>
                        <th class="block-qty-header">D</th>
                        <th class="block-qty-header">E</th>
                        
                        <th class="block-roll-header">A</th>
                        <th class="block-roll-header">B</th>
                        <th class="block-roll-header">C</th>
                        <th class="block-roll-header">D</th>
                        <th class="block-roll-header">E</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Pre-define chart datasets arrays
                $chart_labels = [];
                $chart_scan_qty = [];
                $chart_fc = [];
                $chart_rolls = [];

                $total = 0; $total_roll = 0; $total_3G = 0; $total_fc = 0;
                $total_Qty_A = 0; $total_Qty_B = 0; $total_Qty_C = 0; $total_Qty_D = 0; $total_Qty_E = 0;
                $total_A = 0; $total_B = 0; $total_C = 0; $total_D = 0; $total_E = 0;

                if ($query) {
                    while ($result = odbc_fetch_array($query)) {
                        $hr         = $result["hr"];
                        $cnt_IP     = (int)$result["cnt_IP"];
                        $sumScanQty = (float)$result["sumScanQty"];
                        $Roll       = (float)($RollCaseFull[$hr] ?? 0);
                        $m_3g       = (float)$result["m_3g"];
                        $fc         = (float)$result["fc"];

                        $hr_label   = ($hr == date("H")) ? $hr.':'.date("i") : ($hr+1).':00';
                        $full_hr_label = $hr.':00 - '.$hr_label;

                        // Save for Chart JS
                        array_unshift($chart_labels, $hr.':00');
                        array_unshift($chart_scan_qty, $sumScanQty);
                        array_unshift($chart_fc, $fc);
                        array_unshift($chart_rolls, $Roll);

                        // Calculate Summary Totals
                        $total       += $sumScanQty;
                        $total_roll  += $Roll;
                        $total_3G    += $m_3g;
                        $total_fc    += $fc;

                        $total_Qty_A += $BLOCK_Qty_A[$hr] ?? 0;
                        $total_Qty_B += $BLOCK_Qty_B[$hr] ?? 0;
                        $total_Qty_C += $BLOCK_Qty_C[$hr] ?? 0;
                        $total_Qty_D += $BLOCK_Qty_D[$hr] ?? 0;
                        $total_Qty_E += $BLOCK_Qty_E[$hr] ?? 0;

                        $total_A     += $BLOCK_A[$hr] ?? 0;
                        $total_B     += $BLOCK_B[$hr] ?? 0;
                        $total_C     += $BLOCK_C[$hr] ?? 0;
                        $total_D     += $BLOCK_D[$hr] ?? 0;
                        $total_E     += $BLOCK_E[$hr] ?? 0;
                ?>
                    <tr class="text-center">
                        <td class="fw-bold text-secondary"><?php echo $full_hr_label; ?></td>
                        <td><span class="badge bg-secondary"><?php echo $cnt_IP; ?></span></td>
                        <td class="fw-bold text-primary bg-light"><?php echo number_format($sumScanQty); ?></td>
                        <td class="text-muted"><?php echo number_format(($cnt_IP > 0) ? $sumScanQty / $cnt_IP : 0); ?></td>
                        <td><?php echo number_format($fc); ?></td>
                        <td><?php echo number_format($m_3g); ?></td>
                        <td class="fw-semibold text-dark"><?php echo number_format($Roll); ?></td>

                        <td class="bg-light"><?php echo number_format($BLOCK_Qty_A[$hr] ?? 0); ?></td>
                        <td class="bg-light"><?php echo number_format($BLOCK_Qty_B[$hr] ?? 0); ?></td>
                        <td class="bg-light"><?php echo number_format($BLOCK_Qty_C[$hr] ?? 0); ?></td>
                        <td class="bg-light"><?php echo number_format($BLOCK_Qty_D[$hr] ?? 0); ?></td>
                        <td class="bg-light"><?php echo number_format($BLOCK_Qty_E[$hr] ?? 0); ?></td>

                        <td><?php echo number_format($BLOCK_A[$hr] ?? 0); ?></td>
                        <td><?php echo number_format($BLOCK_B[$hr] ?? 0); ?></td>
                        <td><?php echo number_format($BLOCK_C[$hr] ?? 0); ?></td>
                        <td><?php echo number_format($BLOCK_D[$hr] ?? 0); ?></td>
                        <td><?php echo number_format($BLOCK_E[$hr] ?? 0); ?></td>
                    </tr>
                <?php 
                    }
                } 
                ?>
                </tbody>
                <tfoot class="table-dark text-center fw-bold align-middle">
                    <tr>
                        <td colspan="2" class="text-end pe-3">TOTAL SUMMARY</td>
                        <td class="text-warning h6 mb-0"><?php echo number_format($total); ?></td>
                        <td>-</td>
                        <td><?php echo number_format($total_fc); ?></td>
                        <td><?php echo number_format($total_3G); ?></td>
                        <td><?php echo number_format($total_roll); ?></td>

                        <td><?php echo number_format($total_Qty_A); ?></td>
                        <td><?php echo number_format($total_Qty_B); ?></td>
                        <td><?php echo number_format($total_Qty_C); ?></td>
                        <td><?php echo number_format($total_Qty_D); ?></td>
                        <td><?php echo number_format($total_Qty_E); ?></td>

                        <td><?php echo number_format($total_A); ?></td>
                        <td><?php echo number_format($total_B); ?></td>
                        <td><?php echo number_format($total_C); ?></td>
                        <td><?php echo number_format($total_D); ?></td>
                        <td><?php echo number_format($total_E); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Legacy IFrames Section (If needed) -->
    <div class="row g-4 mb-4">
        <div class="col-12 text-center">
            <div class="card card-kpi p-2 bg-white">
                <iframe align="center" width="100%" height="700" src="TestChart2.php?Xdate=<?php echo urlencode($Xdate); ?>" frameborder="0"></iframe>
            </div>
        </div>
        <div class="col-12 text-center">
            <div class="card card-kpi p-2 bg-white">
                <iframe align="center" width="100%" height="600" src="ChartLoadVsFull.php?Xdate=<?php echo urlencode($Xdate); ?>" frameborder="0"></iframe>
            </div>
        </div>
    </div>

</div>

<!-- Render Interactive Chart JS -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('loadPerformanceChart').getContext('2d');
    
    const labels = <?php echo json_encode($chart_labels); ?>;
    const dataScanQty = <?php echo json_encode($chart_scan_qty); ?>;
    const dataFc = <?php echo json_encode($chart_fc); ?>;
    const dataRolls = <?php echo json_encode($chart_rolls); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'สแกนรวม (Module All)',
                    data: dataScanQty,
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1,
                    order: 2
                },
                {
                    label: 'ยอดสแกน (FullCase)',
                    data: dataFc,
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    order: 3
                },
                {
                    label: 'จำนวนโรล (FullCase)',
                    data: dataRolls,
                    type: 'line',
                    borderColor: '#f59e0b',
                    backgroundColor: '#f59e0b',
                    borderWidth: 3,
                    pointRadius: 4,
                    yAxisID: 'yRolls',
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    grid: { display: false },
                    title: { display: true, text: 'ชั่วโมง' }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'จำนวนชิ้น / สแกน' }
                },
                yRolls: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'จำนวนโรล (Rolls)' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + new Intl.NumberFormat().format(context.raw);
                        }
                    }
                }
            }
        }
    });
});
</script>

</body>
</html>