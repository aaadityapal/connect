<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// AJAX endpoint to fetch test data
if (isset($_GET['action']) && $_GET['action'] === 'get_test_data') {
    $search_term = isset($_GET['term']) ? $conn->real_escape_string($_GET['term']) : '';
    $type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'all';
    
    // Prepare response array
    $response = [
        'success' => true,
        'data' => [],
        'debug' => [
            'user_id' => $user_id,
            'search_term' => $search_term,
            'type' => $type
        ]
    ];
    
    // Add static test data for fallback
    $test_data = [
        ['id' => 1, 'name' => 'Test Vendor 1', 'type' => 'POP'],
        ['id' => 2, 'name' => 'Test Vendor 2', 'type' => 'Carpenter'],
        ['id' => 3, 'name' => 'Test Vendor 3', 'type' => 'Electrician'],
        ['id' => 4, 'name' => 'Sample Company', 'type' => 'POP'],
        ['id' => 5, 'name' => 'Demo Supplier', 'type' => 'Material']
    ];
    
    // Query real database based on request type
    if ($conn) {
        try {
            // 1. Vendor data
            if ($type === 'all' || $type === 'vendor') {
                // Get unique vendors with their details from previous entries
                $vendor_query = "SELECT DISTINCT v.vendor_type, v.vendor_name, v.contact, v.work_description 
                                FROM site_vendors v 
                                JOIN site_updates s ON v.site_update_id = s.id 
                                WHERE s.user_id = ? AND 
                                (v.vendor_name LIKE ? OR v.vendor_type LIKE ?)
                                ORDER BY v.vendor_name
                                LIMIT 10";
                
                $stmt = $conn->prepare($vendor_query);
                $search_param = "%" . $search_term . "%";
                $stmt->bind_param("iss", $user_id, $search_param, $search_param);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $vendors = [];
                while ($row = $result->fetch_assoc()) {
                    $vendors[] = [
                        'type' => $row['vendor_type'],
                        'name' => $row['vendor_name'],
                        'contact' => $row['contact'],
                        'work_description' => $row['work_description']
                    ];
                }
                
                $response['vendors'] = $vendors;
                $response['vendors_count'] = count($vendors);
                
                // If we need sample data and have none from DB
                if (count($vendors) == 0 && count($response['data']) == 0) {
                    foreach ($test_data as $item) {
                        $response['data'][] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'type' => $item['type'],
                            'is_sample' => true
                        ];
                    }
                } else if (count($vendors) > 0) {
                    // Use real vendor data
                    foreach ($vendors as $vendor) {
                        $response['data'][] = [
                            'name' => $vendor['name'],
                            'type' => $vendor['type'],
                            'contact' => $vendor['contact'],
                            'work_description' => $vendor['work_description'],
                            'is_real' => true
                        ];
                    }
                }
            }
            
            // 2. Vendor Labour data
            if ($type === 'all' || $type === 'vendor_labour') {
                $vendor_type = isset($_GET['vendor_type']) ? $conn->real_escape_string($_GET['vendor_type']) : '';
                $vendor_name = isset($_GET['vendor_name']) ? $conn->real_escape_string($_GET['vendor_name']) : '';
                
                $labour_query = "SELECT DISTINCT vl.labour_name, vl.mobile, vl.wage 
                                FROM vendor_labours vl
                                JOIN site_vendors v ON vl.vendor_id = v.id
                                JOIN site_updates s ON v.site_update_id = s.id
                                WHERE s.user_id = ? AND 
                                ((v.vendor_type = ? OR v.vendor_name = ?) OR vl.labour_name LIKE ?)
                                ORDER BY vl.labour_name
                                LIMIT 10";
                
                $stmt = $conn->prepare($labour_query);
                $search_param = "%" . $search_term . "%";
                $stmt->bind_param("isss", $user_id, $vendor_type, $vendor_name, $search_param);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $labours = [];
                while ($row = $result->fetch_assoc()) {
                    $labours[] = [
                        'name' => $row['labour_name'],
                        'mobile' => $row['mobile'],
                        'wage' => floatval($row['wage'])
                    ];
                }
                
                $response['labours'] = $labours;
                $response['labours_count'] = count($labours);
                
                // If this is the only requested type, add to data
                if ($type === 'vendor_labour' && count($labours) > 0) {
                    foreach ($labours as $labour) {
                        $response['data'][] = $labour;
                    }
                }
            }
            
            // 3. Company Labour data
            if ($type === 'all' || $type === 'company_labour') {
                $cl_query = "SELECT DISTINCT cl.labour_name, cl.mobile, cl.wage 
                            FROM company_labours cl
                            JOIN site_updates s ON cl.site_update_id = s.id
                            WHERE s.user_id = ? AND cl.labour_name LIKE ?
                            ORDER BY cl.labour_name
                            LIMIT 10";
                
                $stmt = $conn->prepare($cl_query);
                $search_param = "%" . $search_term . "%";
                $stmt->bind_param("is", $user_id, $search_param);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $company_labours = [];
                while ($row = $result->fetch_assoc()) {
                    $company_labours[] = [
                        'name' => $row['labour_name'],
                        'mobile' => $row['mobile'],
                        'wage' => floatval($row['wage'])
                    ];
                }
                
                $response['company_labours'] = $company_labours;
                $response['company_labours_count'] = count($company_labours);
                
                // If this is the only requested type, add to data
                if ($type === 'company_labour' && count($company_labours) > 0) {
                    foreach ($company_labours as $labour) {
                        $response['data'][] = $labour;
                    }
                }
            }
        } catch (Exception $e) {
            $response['db_error'] = $e->getMessage();
        }
    }
    
    // Add database table check
    try {
        $response['table_info'] = [];
        
        // Check if tables exist
        $tables = ['site_updates', 'site_vendors', 'vendor_labours', 'company_labours'];
        foreach ($tables as $table) {
            $check_query = "SHOW TABLES LIKE '$table'";
            $result = $conn->query($check_query);
            $table_exists = ($result && $result->num_rows > 0);
            
            if ($table_exists) {
                // Count records
                $count_query = "SELECT COUNT(*) as count FROM $table";
                $count_result = $conn->query($count_query);
                $count = 0;
                if ($count_result && $count_result->num_rows > 0) {
                    $count = $count_result->fetch_assoc()['count'];
                }
                
                // Count records for current user
                $user_count = 0;
                if ($table === 'site_updates') {
                    $user_count_query = "SELECT COUNT(*) as count FROM $table WHERE user_id = $user_id";
                    $user_count_result = $conn->query($user_count_query);
                    if ($user_count_result && $user_count_result->num_rows > 0) {
                        $user_count = $user_count_result->fetch_assoc()['count'];
                    }
                }
                
                $response['table_info'][$table] = [
                    'exists' => true,
                    'total_records' => $count,
                    'user_records' => $user_count
                ];
            } else {
                $response['table_info'][$table] = [
                    'exists' => false
                ];
            }
        }
    } catch (Exception $e) {
        $response['table_check_error'] = $e->getMessage();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get site names for dropdown test
$site_names = [];
if ($conn) {
    $sites_query = "SELECT DISTINCT site_name FROM site_updates WHERE user_id = ? ORDER BY site_name";
    $stmt = $conn->prepare($sites_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $site_names[] = $row['site_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autocomplete Diagnostic Tool</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .debug-area {
            margin-top: 20px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 3px;
            font-family: monospace;
            height: 200px;
            overflow: auto;
        }
        /* Ensure autocomplete dropdown appears on top */
        .ui-autocomplete {
            z-index: 9999 !important;
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Style autocomplete items */
        .ui-menu-item {
            padding: 3px;
        }
        
        /* Debug tables */
        .debug-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .debug-table th, .debug-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .debug-table th {
            background-color: #f2f2f2;
        }
        .info-box {
            background-color: #e8f4f8;
            border-left: 4px solid #4a90e2;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Autocomplete Diagnostic Tool</h1>
        <div class="info-box">
            <p>This tool tests the autocomplete functionality with your actual database tables. Use the results to diagnose issues with site_expenses_updated.php.</p>
        </div>
        
        <div class="test-section">
            <h2>1. Basic jQuery UI Test</h2>
            <div class="form-group">
                <label for="static-test">Type anything (static data):</label>
                <input type="text" id="static-test" class="form-control" placeholder="Type 'a' to see options...">
            </div>
            <div class="selected-data">
                Selected: <span id="static-selected">None</span>
            </div>
        </div>
        
        <div class="test-section">
            <h2>2. Vendor Autocomplete Test</h2>
            <div class="form-group">
                <label for="vendor-test">Type vendor name:</label>
                <input type="text" id="vendor-test" class="form-control" placeholder="Type vendor name...">
            </div>
            <div class="selected-data">
                Selected: <span id="vendor-selected">None</span>
            </div>
        </div>
        
        <div class="test-section">
            <h2>3. Vendor Labour Test</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="vendor-type">Vendor Type:</label>
                        <select id="vendor-type" class="form-control">
                            <option value="">Select Type</option>
                            <option value="POP">POP</option>
                            <option value="Carpenter">Carpenter</option>
                            <option value="Electrician">Electrician</option>
                            <option value="Painter">Painter</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="vendor-name">Vendor Name:</label>
                        <input type="text" id="vendor-name" class="form-control">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="labour-test">Labour Name:</label>
                <input type="text" id="labour-test" class="form-control" placeholder="Type labour name...">
            </div>
            <div class="selected-data">
                Selected: <span id="labour-selected">None</span>
            </div>
        </div>
        
        <div class="test-section">
            <h2>4. Company Labour Test</h2>
            <div class="form-group">
                <label for="company-labour-test">Company Labour Name:</label>
                <input type="text" id="company-labour-test" class="form-control" placeholder="Type company labour name...">
            </div>
            <div class="selected-data">
                Selected: <span id="company-labour-selected">None</span>
            </div>
        </div>
        
        <div class="test-section">
            <h2>Database Information</h2>
            <div id="db-info">
                Loading database information...
            </div>
        </div>
        
        <div class="debug-area" id="debug-log">
            <p>Debug log will appear here...</p>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- jQuery UI -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function debugLog(message) {
            const logArea = document.getElementById('debug-log');
            const timestamp = new Date().toLocaleTimeString();
            logArea.innerHTML += `<div>[${timestamp}] ${message}</div>`;
            logArea.scrollTop = logArea.scrollHeight;
        }
        
        $(document).ready(function() {
            // Log environment info
            debugLog(`jQuery version: ${$.fn.jquery}`);
            if (typeof $.ui !== 'undefined') {
                debugLog(`jQuery UI version: ${$.ui.version}`);
                debugLog(`jQuery UI autocomplete available: ${typeof $.ui.autocomplete !== 'undefined'}`);
            } else {
                debugLog('ERROR: jQuery UI is not loaded!');
            }
            
            // 1. Static Data Test (Basic jQuery UI test)
            $('#static-test').autocomplete({
                source: ['Apple', 'Banana', 'Cherry', 'Date', 'Elderberry', 'Fig', 'Grape'],
                minLength: 1,
                select: function(event, ui) {
                    $('#static-selected').text(ui.item.value);
                    debugLog(`Static: Selected "${ui.item.value}"`);
                    return true;
                },
                open: function() {
                    debugLog('Static: Dropdown opened');
                },
                close: function() {
                    debugLog('Static: Dropdown closed');
                }
            });
            
            // 2. Vendor Autocomplete Test
            $('#vendor-test').autocomplete({
                source: function(request, response) {
                    debugLog(`Vendor: Searching for "${request.term}"`);
                    $.ajax({
                        url: 'autocomplete_test.php',
                        dataType: 'json',
                        data: {
                            action: 'get_test_data',
                            type: 'vendor',
                            term: request.term
                        },
                        success: function(data) {
                            debugLog(`Vendor: Got response with ${data.data ? data.data.length : 0} items`);
                            if (data.success) {
                                response($.map(data.data || [], function(item) {
                                    return {
                                        label: item.name + ' (' + item.type + ')',
                                        value: item.name,
                                        item: item
                                    };
                                }));
                            } else {
                                debugLog('Vendor: Request failed');
                                response([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            debugLog(`Vendor ERROR: ${error}`);
                            response([]);
                        }
                    });
                },
                minLength: 1,
                select: function(event, ui) {
                    $('#vendor-selected').text(ui.item.value);
                    debugLog(`Vendor: Selected "${ui.item.value}"`);
                    return true;
                },
                open: function() {
                    debugLog('Vendor: Dropdown opened');
                },
                close: function() {
                    debugLog('Vendor: Dropdown closed');
                }
            });
            
            // 3. Vendor Labour Test
            $('#labour-test').autocomplete({
                source: function(request, response) {
                    const vendorType = $('#vendor-type').val();
                    const vendorName = $('#vendor-name').val();
                    
                    debugLog(`Labour: Searching for "${request.term}" [Vendor Type: ${vendorType}, Vendor Name: ${vendorName}]`);
                    $.ajax({
                        url: 'autocomplete_test.php',
                        dataType: 'json',
                        data: {
                            action: 'get_test_data',
                            type: 'vendor_labour',
                            term: request.term,
                            vendor_type: vendorType,
                            vendor_name: vendorName
                        },
                        success: function(data) {
                            debugLog(`Labour: Got response with ${data.labours ? data.labours.length : 0} items`);
                            if (data.success) {
                                response($.map(data.labours || [], function(item) {
                                    return {
                                        label: item.name,
                                        value: item.name,
                                        item: item
                                    };
                                }));
                            } else {
                                debugLog('Labour: Request failed');
                                response([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            debugLog(`Labour ERROR: ${error}`);
                            response([]);
                        }
                    });
                },
                minLength: 1,
                select: function(event, ui) {
                    $('#labour-selected').text(ui.item.value);
                    debugLog(`Labour: Selected "${ui.item.value}"`);
                    return true;
                },
                open: function() {
                    debugLog('Labour: Dropdown opened');
                },
                close: function() {
                    debugLog('Labour: Dropdown closed');
                }
            });
            
            // 4. Company Labour Test
            $('#company-labour-test').autocomplete({
                source: function(request, response) {
                    debugLog(`Company Labour: Searching for "${request.term}"`);
                    $.ajax({
                        url: 'autocomplete_test.php',
                        dataType: 'json',
                        data: {
                            action: 'get_test_data',
                            type: 'company_labour',
                            term: request.term
                        },
                        success: function(data) {
                            debugLog(`Company Labour: Got response with ${data.company_labours ? data.company_labours.length : 0} items`);
                            if (data.success) {
                                response($.map(data.company_labours || [], function(item) {
                                    return {
                                        label: item.name,
                                        value: item.name,
                                        item: item
                                    };
                                }));
                            } else {
                                debugLog('Company Labour: Request failed');
                                response([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            debugLog(`Company Labour ERROR: ${error}`);
                            response([]);
                        }
                    });
                },
                minLength: 1,
                select: function(event, ui) {
                    $('#company-labour-selected').text(ui.item.value);
                    debugLog(`Company Labour: Selected "${ui.item.value}"`);
                    return true;
                },
                open: function() {
                    debugLog('Company Labour: Dropdown opened');
                },
                close: function() {
                    debugLog('Company Labour: Dropdown closed');
                }
            });
            
            // Get Database Information
            $.ajax({
                url: 'autocomplete_test.php',
                dataType: 'json',
                data: {
                    action: 'get_test_data',
                    type: 'all',
                    term: ''
                },
                success: function(data) {
                    if (data.table_info) {
                        let tableHtml = '<table class="debug-table">';
                        tableHtml += '<tr><th>Table</th><th>Exists</th><th>Total Records</th><th>User Records</th></tr>';
                        
                        for (const table in data.table_info) {
                            const info = data.table_info[table];
                            tableHtml += `<tr>
                                <td>${table}</td>
                                <td>${info.exists ? 'Yes' : 'No'}</td>
                                <td>${info.exists ? info.total_records : 'N/A'}</td>
                                <td>${(info.exists && info.user_records !== undefined) ? info.user_records : 'N/A'}</td>
                            </tr>`;
                        }
                        
                        tableHtml += '</table>';
                        
                        // Add user info
                        tableHtml += `<p>Current user ID: ${data.debug.user_id}</p>`;
                        
                        $('#db-info').html(tableHtml);
                    } else {
                        $('#db-info').html('<p>Error loading database information</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#db-info').html(`<p>Error: ${error}</p>`);
                }
            });
            
            debugLog('All autocomplete widgets initialized');
        });
    </script>
</body>
</html> 