<?php ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// ini_set("error_log", "error_log");
// error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_NAME', 'ngshia5_db');
define('DB_USER', 'root');
define('DB_PASS', 'password');


define('INDIVIDUAL', 'individual');
define('HOUSEHOLD', 'household');
define('FORMAL', 'formal');
define('INFORMAL', 'informal');
define('CAPITATION_TABLE_NAME', 'capitations');
 define('CAPITATION_PAYMENT_TABLE_NAME', 'capitation_payments');

$db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
date_default_timezone_set('Africa/Lagos');




include_once('classes/class.user.php');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set("error_log", "error_log");

$userObject = new User();



// Define programme types based on your requirements
$programme_types = array(
    'BHCPF',
    'NiCare',
    'BHCPF-CF',
    'GAC',
    'NiCare-Formal',
    'Unicef'
);

// Handle AJAX request for loading payment details
if(isset($_POST['action']) && $_POST['action'] == 'load_payment_details') {
    $capitation_group_id = isset($_POST['capitation_group_id']) ? intval($_POST['capitation_group_id']) : 0;
    displayPaymentDetails($capitation_group_id, $programme_types);
    exit;
}

// Handle Excel export
if(isset($_POST['export_excel'])) {
    // Set headers for Excel file download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="capitation_payment_details_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Generate Excel content
    generateExcelContent($programme_types);
    exit;
}

function generateExcelContent($programme_types) {
    global $db;
    
    $capitation_group_id = isset($_POST['capitation_group_id']) ? intval($_POST['capitation_group_id']) : 0;
    
    if($capitation_group_id <= 0) {
        echo "No data available";
        exit;
    }
    
    // Get capitation group details from capitation_grouping table
    $group_query = "SELECT * FROM capitation_grouping WHERE id = ?";
    $group_stmt = $db->prepare($group_query);
    $group_stmt->bind_param("i", $capitation_group_id);
    $group_stmt->execute();
    $group_result = $group_stmt->get_result();
    $group_data = $group_result->fetch_assoc();
    
    if(!$group_data) {
        echo "Capitation group not found";
        exit;
    }
    
    // Build dynamic query based on programme types
    $select_columns = "";
    $grand_totals = array();
    
    foreach($programme_types as $programme) {
        $db_programme = str_replace(array('-', ' '), '_', $programme);
        $select_columns .= "SUM(CASE WHEN c.programme_type = '{$programme}' THEN c.total_cap ELSE 0 END) as {$db_programme}_total, ";
        $grand_totals[$db_programme] = 0;
    }
    
    // Get providers with their program totals from capitations table
    $query = "
        SELECT 
            p.id as provider_id,
            p.hcpname as provider_name,
            p.hcpcode as facility_code,
            l.lga_name,
            w.ward_name,
            {$select_columns}
            SUM(c.total_cap) as grand_total,
            SUM(c.total_enrolee) as total_enrollees
        FROM capitations c
        JOIN tbl_providers p ON c.provider_id = p.id
        LEFT JOIN lga l ON p.hcplga = l.id
        LEFT JOIN ward w ON p.hcpward = w.id
        WHERE c.group_id = ?
        GROUP BY p.id, p.hcpname, p.hcpcode, l.lga_name, w.ward_name
        ORDER BY p.hcpname
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $capitation_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Start Excel content
    echo "<table border='1'>";
    
    // Header row
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<th>S/N</th>";
    echo "<th>Provider Name</th>";
    echo "<th>Facility Code</th>";
    echo "<th>LGA</th>";
    echo "<th>Ward</th>";
    echo "<th>Total Enrollees</th>";
    
    foreach($programme_types as $programme) {
        echo "<th>" . $programme . "</th>";
    }
    
    echo "<th>Total Amount</th>";
    echo "</tr>";
    
    // Data rows
    $sn = 1;
    $overall_total = 0;
    $total_enrollees = 0;
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $sn++ . "</td>";
            echo "<td>" . htmlspecialchars($row['provider_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['facility_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['lga_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['ward_name']) . "</td>";
            echo "<td>" . number_format($row['total_enrollees']) . "</td>";
            
            $row_total = 0;
            
            foreach($programme_types as $programme) {
                $db_programme = str_replace(array('-', ' '), '_', $programme);
                $amount = $row[$db_programme . '_total'] ?? 0;
                echo "<td>" . number_format($amount, 2) . "</td>";
                $row_total += $amount;
                $grand_totals[$db_programme] += $amount;
            }
            
            echo "<td style='font-weight: bold;'>₦" . number_format($row['grand_total'], 2) . "</td>";
            echo "</tr>";
            
            $overall_total += $row['grand_total'];
            $total_enrollees += $row['total_enrollees'];
        }
    }
    
    // Footer row with totals
    echo "<tr style='background-color: #e8f4f8; font-weight: bold;'>";
    echo "<td colspan='5' style='text-align: right;'>GRAND TOTAL:</td>";
    echo "<td>" . number_format($total_enrollees) . "</td>";
    
    foreach($programme_types as $programme) {
        $db_programme = str_replace(array('-', ' '), '_', $programme);
        $total = $grand_totals[$db_programme] ?? 0;
        echo "<td>₦" . number_format($total, 2) . "</td>";
    }
    
    echo "<td>₦" . number_format($overall_total, 2) . "</td>";
    echo "</tr>";
    
    echo "</table>";
}

// Get capitation groups for dropdown from capitation_grouping table
function getCapitationGroups() {
    global $db;
    $query = "SELECT id, `name` as group_name, `year`, cap_year, `month`, month_full, enroled_on_before_date 
              FROM capitation_grouping 
              ORDER BY enroled_on_before_date DESC, `year` DESC, `month` DESC";
    $result = $db->query($query);
    return $result;
}

function displayPaymentDetails($capitation_group_id, $programme_types) {
    global $db;
    
    $capitation_group_id = intval($capitation_group_id);
    
    if($capitation_group_id <= 0) {
        echo '<div class="no-data">No capitation period selected</div>';
        return;
    }
    
    // Get capitation group details from capitation_grouping table
    $group_query = "SELECT * FROM capitation_grouping WHERE id = ?";
    $group_stmt = $db->prepare($group_query);
    $group_stmt->bind_param("i", $capitation_group_id);
    $group_stmt->execute();
    $group_result = $group_stmt->get_result();
    
    if($group_result->num_rows === 0) {
        echo '<div class="alert alert-warning">Capitation period not found</div>';
        return;
    }
    
    $group_data = $group_result->fetch_assoc();
    
    // Build dynamic query based on programme types
    $select_columns = "";
    $grand_totals = array();
    
    foreach($programme_types as $programme) {
        $db_programme = str_replace(array('-', ' '), '_', $programme);
        $select_columns .= "SUM(CASE WHEN c.programme_type = '{$programme}' THEN c.total_cap ELSE 0 END) as {$db_programme}_total, ";
        $grand_totals[$db_programme] = 0;
    }
    
    // Get providers with their program totals from capitations table
    $query = "
        SELECT 
            p.id as provider_id,
            p.hcpname as provider_name,
            p.hcpcode as facility_code,
            l.lga_name,
            w.ward_name,
            {$select_columns}
            SUM(c.total_cap) as grand_total,
            SUM(c.total_enrolee) as total_enrollees
        FROM capitations c
        JOIN tbl_providers p ON c.provider_id = p.id
        LEFT JOIN lga l ON p.hcplga = l.id
        LEFT JOIN ward w ON p.hcpward = w.id
        WHERE c.group_id = ?
        GROUP BY p.id, p.hcpname, p.hcpcode, l.lga_name, w.ward_name
        ORDER BY p.hcpname
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $capitation_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows === 0) {
        echo '<div class="no-data">No payment details found for this capitation period</div>';
        return;
    }
    
    // Calculate totals for summary
    $summary_query = "
        SELECT 
            COUNT(DISTINCT p.id) as total_providers,
            SUM(c.total_enrolee) as total_enrollees,
            SUM(c.total_cap) as total_amount
        FROM capitations c
        JOIN tbl_providers p ON c.provider_id = p.id
        WHERE c.group_id = ?
    ";
    
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->bind_param("i", $capitation_group_id);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary_data = $summary_result->fetch_assoc();
    
    // Group header
    echo '<div class="card mb-4">';
    echo '<div class="card-header">';
    echo '<h4 class="mb-0"><i class="fa fa-info-circle"></i> Capitation Period Details</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="row">';
    echo '<div class="col-md-3">';
    echo '<p><strong><i class="fa fa-tag"></i> Period Name:</strong> ' . htmlspecialchars($group_data['name']) . '</p>';
    echo '</div>';
    echo '<div class="col-md-3">';
    echo '<p><strong><i class="fa fa-calendar"></i> Month:</strong> ' . htmlspecialchars($group_data['month_full']) . '</p>';
    echo '</div>';
    echo '<div class="col-md-3">';
    echo '<p><strong><i class="fa fa-calendar-check"></i> Year:</strong> ' . htmlspecialchars($group_data['year']) . '</p>';
    echo '</div>';
    echo '<div class="col-md-3">';
    echo '<p><strong><i class="fa fa-clock"></i> Enrollment Cut-off:</strong> ' . date('d M, Y', strtotime($group_data['enroled_on_before_date'])) . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Summary Cards
    echo '<div class="row mb-4">';
    echo '<div class="col-md-3">';
    echo '<div class="card summary-card">';
    echo '<div class="card-body text-center">';
    echo '<h2 class="providers">' . number_format($summary_data['total_providers']) . '</h2>';
    echo '<p>Total Providers</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-3">';
    echo '<div class="card summary-card">';
    echo '<div class="card-body text-center">';
    echo '<h2 class="enrollees">' . number_format($summary_data['total_enrollees']) . '</h2>';
    echo '<p>Total Enrollees</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-3">';
    echo '<div class="card summary-card">';
    echo '<div class="card-body text-center">';
    echo '<h2 class="amount" style="color: #27ae60;">₦' . number_format($summary_data['total_amount'], 2) . '</h2>';
    echo '<p>Total Amount</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-3">';
    echo '<div class="card summary-card">';
    echo '<div class="card-body text-center">';
    echo '<h2 style="color: #e74c3c;">' . count($programme_types) . '</h2>';
    echo '<p>Programme Types</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="table-responsive">';
    echo '<table class="data-table table-striped">';
    
    // Header row
    echo '<thead>';
    echo '<tr>';
    echo '<th>S/N</th>';
    echo '<th>Provider Name</th>';
    echo '<th>Facility Code</th>';
    echo '<th>LGA</th>';
    echo '<th>Ward</th>';
    echo '<th>Total Enrollees</th>';
    
    foreach($programme_types as $programme) {
        echo '<th class="program-header">' . $programme . '</th>';
    }
    
    echo '<th class="program-header">Total Amount</th>';
    echo '</tr>';
    echo '</thead>';
    
    echo '<tbody>';
    
    $sn = 1;
    $overall_grand_total = 0;
    $total_enrollees = 0;
    
    while($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $sn++ . '</td>';
        echo '<td>' . htmlspecialchars($row['provider_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['facility_code']) . '</td>';
        echo '<td>' . htmlspecialchars($row['lga_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ward_name']) . '</td>';
        echo '<td style="text-align: center;">' . number_format($row['total_enrollees']) . '</td>';
        
        $row_total = 0;
        
        foreach($programme_types as $programme) {
            $db_programme = str_replace(array('-', ' '), '_', $programme);
            $amount = $row[$db_programme . '_total'] ?? 0;
            echo '<td style="text-align: right;">' . ($amount > 0 ? '₦' . number_format($amount, 2) : '-') . '</td>';
            $row_total += $amount;
            $grand_totals[$db_programme] += $amount;
        }
        
        echo '<td style="text-align: right; font-weight: bold; color: #2c3e50;">₦' . number_format($row['grand_total'], 2) . '</td>';
        echo '</tr>';
        
        $overall_grand_total += $row['grand_total'];
        $total_enrollees += $row['total_enrollees'];
    }
    
    // Footer row with totals
    echo '<tr class="total-row">';
    echo '<td colspan="5" style="text-align: right;"><strong>GRAND TOTAL:</strong></td>';
    echo '<td style="text-align: center; font-weight: bold;">' . number_format($total_enrollees) . '</td>';
    
    foreach($programme_types as $programme) {
        $db_programme = str_replace(array('-', ' '), '_', $programme);
        $total = $grand_totals[$db_programme] ?? 0;
        echo '<td style="text-align: right; font-weight: bold;">' . ($total > 0 ? '₦' . number_format($total, 2) : '-') . '</td>';
    }
    
    echo '<td style="text-align: right; font-weight: bold;">₦' . number_format($overall_grand_total, 2) . '</td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // Programme-wise summary
    echo '<div class="row mt-4">';
    echo '<div class="col-md-12">';
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0"><i class="fa fa-chart-pie"></i> Programme-wise Summary</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="row">';
    
    foreach($programme_types as $programme) {
        $db_programme = str_replace(array('-', ' '), '_', $programme);
        $total = $grand_totals[$db_programme] ?? 0;
        
        if($total > 0) {
            $percentage = $overall_grand_total > 0 ? round(($total / $overall_grand_total) * 100, 1) : 0;
            
            echo '<div class="col-md-4 mb-3">';
            echo '<div class="d-flex justify-content-between align-items-center p-3" style="background-color: #f8f9fa; border-radius: 8px;">';
            echo '<div>';
            echo '<h6 class="mb-0" style="color: #2c3e50;">' . $programme . '</h6>';
            echo '<small class="text-muted">' . $percentage . '% of total</small>';
            echo '</div>';
            echo '<div class="text-right">';
            echo '<h5 class="mb-0" style="color: #27ae60;">₦' . number_format($total, 2) . '</h5>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once('head.php'); ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        
        .content-main {
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .form-container {
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            color: #667eea;
        }
        
        .form-container label {
            color: #667eea;
            font-weight: bold;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 3px solid #1a252f;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
            transition: background-color 0.3s;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .total-row {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%) !important;
            color: white;
            font-weight: bold;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.3s;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .program-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            text-align: center;
            font-weight: 600;
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
            font-size: 18px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
        }
        
        .card-body {
            padding: 20px;
            background-color: white;
            border-radius: 0 0 8px 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 25px;
            font-weight: bold;
            transition: transform 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .select2-container--default .select2-selection--single {
            height: 45px !important;
            border-radius: 6px !important;
            border: 2px solid #e0e0e0 !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 45px !important;
            padding-left: 15px !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 45px !important;
        }
        
        .month-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #3498db;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .summary-card {
            height: 100%;
        }
        
        .summary-card h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .summary-card p {
            color: #7f8c8d;
            margin-bottom: 0;
        }
        
        .amount {
            font-size: 24px;
            font-weight: bold;
        }
        
        .enrollees {
            color: #3498db;
        }
        
        .providers {
            color: #9b59b6;
        }
        
        .loading-container {
            text-align: center;
            padding: 50px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
    <!-- Include Select2 for better dropdowns -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <div style='width: 98%; margin: 20px auto'>
        <div class="w3-card content-main">
            <div class="page-breadcrumb">
                <h2 style="color: #2c3e50;">
                    <i class="fa fa-money"></i> Capitation Payment Details
                </h2>
                <div style="float: right;">
                    <a href="capitation_appropriations.php" class="btn btn-sm btn-outline-danger">
                        <i class="fa fa-arrow-left"></i> Back to Appropriations
                    </a>
                </div>
            </div>
            
            <hr style="border-color: #e0e0e0;">
            
            <!-- Filter Form -->
            <div class="form-container">
                <form method="POST" action="" id="capitationForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="capitation_group"><i class="fa fa-filter"></i> Select Capitation Period:</label>
                                <select name="capitation_group_id" id="capitation_group" class="form-control select2" required style="width: 100%;">
                                    <option value="">-- Select Capitation Period --</option>
                                    <?php
                                    $groups = getCapitationGroups();
                                    $selected_group = isset($_POST['capitation_group_id']) ? $_POST['capitation_group_id'] : '';
                                    while($group = $groups->fetch_assoc()) {
                                        $selected = ($selected_group == $group['id']) ? 'selected' : '';
                                        $display_name = $group['group_name'] . " (" . $group['month_full'] . " " . $group['year'] . ")";
                                        echo "<option value='{$group['id']}' data-month='{$group['month_full']}' data-year='{$group['year']}' $selected>{$display_name}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" style="margin-top: 25px;">
                                <button type="button" class="btn btn-primary btn-lg" onclick="loadPaymentDetails()">
                                    <i class="fa fa-search"></i> Load Details
                                </button>
                                <button type="submit" name="export_excel" class="btn btn-success export-btn" id="exportBtn" style="display: none;">
                                    <i class="fa fa-file-excel-o"></i> Export Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Results Container -->
            <div id="resultsContainer">
                <?php
                // Display results on initial page load if a group is selected
                if(isset($_POST['capitation_group_id']) && intval($_POST['capitation_group_id']) > 0 && !isset($_POST['action'])) {
                    displayPaymentDetails($_POST['capitation_group_id'], $programme_types);
                }
                ?>
            </div>
        </div>
    </div>
    
    <?php
    include_once('scripts.php');
    include_once('modal.php');
    ?>
    
    <!-- Include Select2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize Select2 Select Capitation Period:
        $('.select2').select2({
            placeholder: "Select Capitation Period",
            allowClear: true,
            templateResult: formatCapitationGroup,
            templateSelection: formatCapitationGroup
        });
        
        // Initialize export button visibility
        <?php if(isset($_POST['capitation_group_id']) && intval($_POST['capitation_group_id']) > 0 && !isset($_POST['action'])): ?>
        $('#exportBtn').show();
        <?php endif; ?>
        
        // Load details on select change with debounce
        let debounceTimer;
        $('#capitation_group').on('change', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                if($('#capitation_group').val()) {
                    loadPaymentDetails();
                }
            }, 500);
        });
    });
    
    function formatCapitationGroup(capitation) {
        if (!capitation.id) {
            return capitation.text;
        }
        
        var $option = $(capitation.element);
        var month = $option.data('month');
        var year = $option.data('year');
        
        if (month && year) {
            return $('<span><strong>' + capitation.text + '</strong> <span class="month-badge">' + month + ' ' + year + '</span></span>');
        }
        
        return capitation.text;
    }
    
    function loadPaymentDetails() {
        var capitationGroupId = $('#capitation_group').val();
        
        if(capitationGroupId === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Selection Required',
                text: 'Please select a capitation period',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Show loading animation
        $('#resultsContainer').html(`
            <div class="loading-container">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <h4 class="mt-3" style="color: #2c3e50;">Loading payment details...</h4>
                <p style="color: #7f8c8d;">Please wait while we fetch the data</p>
            </div>
        `);
        
        // Update form action to include the selected group ID for export
        $('#capitationForm').append('<input type="hidden" name="capitation_group_id" value="' + capitationGroupId + '">');
        
        $.ajax({
            url: 'capitation_payment_details.php',
            method: 'POST',
            data: {
                action: 'load_payment_details',
                capitation_group_id: capitationGroupId
            },
            success: function(response) {
                $('#resultsContainer').html(response);
                $('#exportBtn').show();
                
                // Initialize DataTables if available
                if($.fn.DataTable) {
                    $('.data-table').DataTable({
                        "pageLength": 25,
                        "order": [[1, 'asc']],
                        "dom": '<"top"f>rt<"bottom"lip><"clear">',
                        "language": {
                            "search": "Search:",
                            "lengthMenu": "Show _MENU_ entries",
                            "info": "Showing _START_ to _END_ of _TOTAL_ entries"
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#resultsContainer').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h4><i class="fa fa-exclamation-triangle"></i> Error Loading Data</h4>
                        <p>Failed to load payment details. Please try again.</p>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `);
                console.error('Error:', error);
            }
        });
    }
    
    // Function to confirm Excel export
    $(document).on('click', '#exportBtn', function(e) {
        if($('#capitation_group').val() === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Selection Required',
                text: 'Please select a capitation period first',
                confirmButtonColor: '#3085d6'
            });
            return false;
        }
        
        // Show confirmation dialog
        e.preventDefault();
        Swal.fire({
            title: 'Export to Excel',
            text: 'Are you sure you want to export the data to Excel?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#27ae60',
            cancelButtonColor: '#7f8c8d',
            confirmButtonText: 'Yes, export it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit the form for export
                $('#capitationForm').append('<input type="hidden" name="export_excel" value="1">');
                $('#capitationForm')[0].submit();
            }
        });
    });
    </script>
</body>
</html>