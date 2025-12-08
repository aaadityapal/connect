<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get current month and year for defaults
$currentMonth = date('m');
$currentYear = date('Y');

// Get selected filters from request
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;

// Validate inputs
$selectedMonth = intval($selectedMonth);
$selectedYear = intval($selectedYear);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            padding: 0;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        .dashboard-header {
            background: #ffffff;
            border-radius: 0;
            padding: 30px 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .header-title {
            font-size: 2rem;
            color: #1a202c;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: left;
        }

        .filter-section {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #ffffff;
            color: #2d3748;
        }

        .filter-group select:hover,
        .filter-group input:hover {
            border-color: #cbd5e0;
            background-color: #ffffff;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #2d3748;
            box-shadow: 0 0 0 2px rgba(45, 55, 72, 0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-filter {
            background: #2d3748;
            color: white;
        }

        .btn-filter:hover {
            background: #1a202c;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-reset {
            background: #f0f0f0;
            color: #2d3748;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .selected-filters {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            margin-top: 15px;
            display: none;
            font-size: 0.9rem;
        }

        .selected-filters.active {
            display: block;
        }

        .filter-badge {
            display: inline-block;
            background: #2d3748;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-right: 8px;
        }

        .content-section {
            background: #ffffff;
            border-radius: 0;
            padding: 40px;
            box-shadow: none;
            min-height: 400px;
            border-top: 1px solid #f0f0f0;
        }

        .data-display {
            text-align: center;
            color: #718096;
            padding: 60px 40px;
        }

        .data-display i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #cbd5e0;
        }

        .data-display h2 {
            font-size: 1.3rem;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .data-display p {
            font-size: 0.95rem;
        }

        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            overflow-x: auto;
            border: 1px solid #2d3748;
        }

        .analytics-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #2d3748;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .analytics-table th {
            padding: 14px 16px;
            text-align: center;
            font-weight: 600;
            color: #1a202c;
            font-size: 0.85rem;
            white-space: nowrap;
            border-right: 1px solid #2d3748;
            position: sticky;
            top: 0;
            background: #f8f9fa;
        }

        .analytics-table th:last-child {
            border-right: none;
        }

        .analytics-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #4a5568;
            font-size: 0.9rem;
            white-space: nowrap;
            text-align: center;
            border-right: 1px solid #e2e8f0;
        }

        .analytics-table td:last-child {
            border-right: none;
        }

        .analytics-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-primary {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: #2d3748;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .edit-btn {
            background: #4299e1;
        }

        .edit-btn:hover {
            background: #3182ce;
        }

        .paid-btn {
            background: #48bb78;
        }

        .paid-btn:hover {
            background: #38a169;
        }

        /* Penalty Button Styles */
        .penalty-btn {
            padding: 6px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f0f0f0;
            color: #2d3748;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
        }

        .penalty-btn:hover {
            background: #e0e0e0;
            border-color: #a0aec0;
            transform: scale(1.05);
        }

        .penalty-decrease {
            background: #fed7d7;
            color: #c53030;
            border-color: #fc8787;
        }

        .penalty-decrease:hover {
            background: #fc8787;
            color: white;
        }

        .penalty-increase {
            background: #c6f6d5;
            color: #22543d;
            border-color: #9ae6b4;
        }

        .penalty-increase:hover {
            background: #9ae6b4;
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 6% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        /* Present Days table specific styles */
        #presentDaysModal .modal-content {
            max-width: 1400px; /* wider modal for table */
            padding: 20px; /* slightly reduce padding for more room */
            box-sizing: border-box;
        }

        /* Responsive fallback for small screens */
        @media (max-width: 980px) {
            #presentDaysModal .modal-content { max-width: 95%; width: 95%; }
        }

        /* Ensure modal header has space below it so it doesn't overlap the table */
        #presentDaysModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #presentDaysTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #presentDaysTable th,
        #presentDaysTable td {
            padding: 12px 16px;
            text-align: left; /* align text to left */
            border-right: 1px solid #e2e8f0; /* vertical divider */
            vertical-align: middle;
            word-wrap: break-word;
        }

        #presentDaysTable th:last-child,
        #presentDaysTable td:last-child {
            border-right: none;
        }

        #presentDaysTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        /* Column width hints to avoid header overlap */
        #presentDaysTable th:nth-child(1), #presentDaysTable td:nth-child(1) { width: 16%; }
        #presentDaysTable th:nth-child(2), #presentDaysTable td:nth-child(2) { width: 18%; }
        #presentDaysTable th:nth-child(3), #presentDaysTable td:nth-child(3) { width: 16%; }
        #presentDaysTable th:nth-child(4), #presentDaysTable td:nth-child(4) { width: 16%; }
        #presentDaysTable th:nth-child(5), #presentDaysTable td:nth-child(5) { width: 18%; }
        #presentDaysTable th:nth-child(6), #presentDaysTable td:nth-child(6) { width: 16%; }

        /* Slightly larger padding for better separation */
        #presentDaysTable th,
        #presentDaysTable td {
            padding: 14px 18px;
        }

        /* Late Days table styles */
        #lateDaysModal .modal-content {
            max-width: 1400px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #lateDaysModal .modal-content { max-width: 95%; width: 95%; }
        }

        #lateDaysModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #lateDaysTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #lateDaysTable th,
        #lateDaysTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #lateDaysTable th:last-child,
        #lateDaysTable td:last-child {
            border-right: none;
        }

        #lateDaysTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        #lateDaysTable th:nth-child(1), #lateDaysTable td:nth-child(1) { width: 15%; }
        #lateDaysTable th:nth-child(2), #lateDaysTable td:nth-child(2) { width: 12%; }
        #lateDaysTable th:nth-child(3), #lateDaysTable td:nth-child(3) { width: 15%; }
        #lateDaysTable th:nth-child(4), #lateDaysTable td:nth-child(4) { width: 15%; }
        #lateDaysTable th:nth-child(5), #lateDaysTable td:nth-child(5) { width: 12%; }
        #lateDaysTable th:nth-child(6), #lateDaysTable td:nth-child(6) { width: 31%; }

        /* 1+ Hour Late Days table styles */
        #oneHourLateDaysModal .modal-content {
            max-width: 1400px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #oneHourLateDaysModal .modal-content { max-width: 95%; width: 95%; }
        }

        #oneHourLateDaysModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #oneHourLateDaysTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #oneHourLateDaysTable th,
        #oneHourLateDaysTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #oneHourLateDaysTable th:last-child,
        #oneHourLateDaysTable td:last-child {
            border-right: none;
        }

        #oneHourLateDaysTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        #oneHourLateDaysTable th:nth-child(1), #oneHourLateDaysTable td:nth-child(1) { width: 15%; }
        #oneHourLateDaysTable th:nth-child(2), #oneHourLateDaysTable td:nth-child(2) { width: 12%; }
        #oneHourLateDaysTable th:nth-child(3), #oneHourLateDaysTable td:nth-child(3) { width: 15%; }
        #oneHourLateDaysTable th:nth-child(4), #oneHourLateDaysTable td:nth-child(4) { width: 15%; }
        #oneHourLateDaysTable th:nth-child(5), #oneHourLateDaysTable td:nth-child(5) { width: 12%; }
        #oneHourLateDaysTable th:nth-child(6), #oneHourLateDaysTable td:nth-child(6) { width: 31%; }

        /* Leave Details table styles */
        #leaveDetailsModal .modal-content {
            max-width: 1400px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #leaveDetailsModal .modal-content { max-width: 95%; width: 95%; }
        }

        #leaveDetailsModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #leaveDetailsTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #leaveDetailsTable th,
        #leaveDetailsTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #leaveDetailsTable th:last-child,
        #leaveDetailsTable td:last-child {
            border-right: none;
        }

        #leaveDetailsTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        #leaveDetailsTable th:nth-child(1), #leaveDetailsTable td:nth-child(1) { width: 25%; }
        #leaveDetailsTable th:nth-child(2), #leaveDetailsTable td:nth-child(2) { width: 20%; }
        #leaveDetailsTable th:nth-child(3), #leaveDetailsTable td:nth-child(3) { width: 12%; }
        #leaveDetailsTable th:nth-child(4), #leaveDetailsTable td:nth-child(4) { width: 43%; }

        /* Leave Deduction Modal styles */
        #leaveDeductionModal .modal-content {
            max-width: 1000px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #leaveDeductionModal .modal-content { max-width: 95%; width: 95%; }
        }

        #leaveDeductionModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #leaveDeductionTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #leaveDeductionTable th,
        #leaveDeductionTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #leaveDeductionTable th:last-child,
        #leaveDeductionTable td:last-child {
            border-right: none;
        }

        #leaveDeductionTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
        }

        #leaveDeductionTable tbody tr:hover {
            background: #f8f9fa;
        }

        #leaveDeductionTable tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        #leaveDeductionTable th:nth-child(1), #leaveDeductionTable td:nth-child(1) { width: 25%; }
        #leaveDeductionTable th:nth-child(2), #leaveDeductionTable td:nth-child(2) { width: 12%; text-align: center; }
        #leaveDeductionTable th:nth-child(3), #leaveDeductionTable td:nth-child(3) { width: 18%; text-align: right; }
        #leaveDeductionTable th:nth-child(4), #leaveDeductionTable td:nth-child(4) { width: 45%; }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: #000;
        }

        .modal h2 {
            margin-top: 0;
            color: #1a202c;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #4a5568;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2d3748;
            box-shadow: 0 0 0 2px rgba(45, 55, 72, 0.1);
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save {
            background: #2d3748;
            color: white;
        }

        .btn-save:hover {
            background: #1a202c;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        .loading-spinner {
            display: none;
            text-align: center;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2d3748;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .info-icon {
            cursor: help;
            margin-left: 6px;
            font-size: 0.85rem;
            color: #4299e1;
            display: inline-block;
            position: relative;
            vertical-align: middle;
        }

        .info-icon:hover {
            color: #2d3748;
            transform: scale(1.15);
            transition: all 0.3s ease;
        }

        .info-tooltip {
            visibility: hidden;
            position: absolute;
            bottom: 140%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #2d3748;
            color: #ffffff;
            text-align: center;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .info-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #2d3748 transparent transparent transparent;
        }

        .info-icon:hover .info-tooltip {
            visibility: visible;
            opacity: 1;
        }

        @media (max-width: 1024px) {
            .filter-section {
                grid-template-columns: 1fr 1fr;
            }

            .button-group {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 20px;
            }

            .header-title {
                font-size: 1.8rem;
            }

            .filter-section {
                grid-template-columns: 1fr;
            }

            .button-group {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h1 class="header-title">
                <i class="fas fa-chart-line"></i> Monthly Analytics Dashboard
            </h1>

            <form method="GET" class="filter-form">
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="month">
                            <i class="fas fa-calendar-alt"></i> Select Month
                        </label>
                        <select id="month" name="month" required>
                            <option value="">-- Choose Month --</option>
                            <?php
                            $months = [
                                '01' => 'January',
                                '02' => 'February',
                                '03' => 'March',
                                '04' => 'April',
                                '05' => 'May',
                                '06' => 'June',
                                '07' => 'July',
                                '08' => 'August',
                                '09' => 'September',
                                '10' => 'October',
                                '11' => 'November',
                                '12' => 'December'
                            ];
                            foreach ($months as $num => $name) {
                                $selected = ($selectedMonth == $num) ? 'selected' : '';
                                echo "<option value=\"$num\" $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="year">
                            <i class="fas fa-calendar"></i> Select Year
                        </label>
                        <select id="year" name="year" required>
                            <option value="">-- Choose Year --</option>
                            <?php
                            $startYear = 2020;
                            $endYear = date('Y') + 2;
                            for ($year = $endYear; $year >= $startYear; $year--) {
                                $selected = ($selectedYear == $year) ? 'selected' : '';
                                echo "<option value=\"$year\" $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-filter">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="monthly_analytics_dashboard.php" class="btn btn-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <?php if ($selectedMonth && $selectedYear) : ?>
            <div class="selected-filters active">
                <strong>Filters Applied:</strong>
                <span class="filter-badge">
                    <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <?php if ($selectedMonth && $selectedYear) : ?>
        <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="color: #1a202c; font-size: 1.3rem; margin: 0;">Analytics for <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?></h2>
                        <button type="button" class="btn btn-filter" onclick="exportToExcel()" style="margin: 0;">
                            <i class="fas fa-download"></i> Export to Excel
                        </button>
                    </div>
                    
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Base Salary</th>
                                <th>Working Days</th>
                                <th>Present Days</th>
                                <th>Late Days</th>
                                <th>1+ Hour Late</th>
                                <th>Leave Taken</th>
                                <th>Leave Deduction</th>
                                <th>Late Deduction</th>
                                <th>1+ Leave Hour Late Deduction</th>
                                <th>4th Saturday Missing Deduction</th>
                                <th>Penalty</th>
                                <th>Salary Calculated Days
                                    <span class="info-icon" title="How it's calculated">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="info-tooltip">Calculated = present days + casual (full) + half-day (0.5) + compensate (0.5) - late deductions (every 3 late = 0.5 day) - 1+hr late (0.5 per) - 4th Saturday penalty (2 days)</span>
                                    </span>
                                </th>
                                <th>Net Salary</th>
                                <th>Overtime Hours</th>
                                <th>Overtime Amount</th>
                                <th>Final Salary</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="analyticsTableBody">
                            <tr>
                                <td colspan="15" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="data-display">
                    <i class="fas fa-filter"></i>
                    <h2>Select Filters to View Analytics</h2>
                    <p>Please select a month and year from the filters above to view the analytics data.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Salary Calculated Days Modal -->
    <div id="salaryCalcModal" class="modal">
        <div class="modal-content" style="max-width:800px; max-height:85vh; display:flex; flex-direction:column;">
            <span class="close-modal" onclick="closeSalaryCalcModal()" style="position:absolute; right:18px; top:10px; cursor:pointer;">&times;</span>
            <h2 style="margin-top:0; text-align:center;">Salary Calculated Days Breakdown</h2>
            <div id="salaryCalcContainer" style="background:#f8f9fa; padding:18px; border-radius:6px; overflow-y:auto; flex-grow:1;">
                <!-- Filled dynamically -->
            </div>
            <div class="modal-buttons" style="margin-top:12px;">
                <button class="btn-cancel" onclick="closeSalaryCalcModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Salary Modal -->
    <div id="editSalaryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2>Edit Base Salary</h2>
            
            <div id="employeeInfo" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p><strong>Employee ID:</strong> <span id="modalEmployeeId"></span></p>
                <p><strong>Name:</strong> <span id="modalEmployeeName"></span></p>
                <p><strong>Current Salary:</strong> <span id="modalCurrentSalary"></span></p>
            </div>

            <form id="editSalaryForm">
                <div class="form-group">
                    <label for="newBaseSalary">New Base Salary</label>
                    <input type="number" id="newBaseSalary" name="newBaseSalary" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="salaryRemarks">Remarks (Optional)</label>
                    <textarea id="salaryRemarks" name="salaryRemarks" rows="3" placeholder="Add any notes..."></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-save">
                        <span class="save-text">Save Changes</span>
                        <span class="loading-spinner" id="saveSpinner">
                            <div class="spinner"></div>
                        </span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>

            <div id="formMessage" style="margin-top: 15px; padding: 10px; border-radius: 6px; display: none;"></div>
        </div>
    </div>

    <!-- Working Days Details Modal -->
    <div id="workingDaysModal" class="modal">
        <div class="modal-content" style="max-width: 600px; max-height: 85vh; display: flex; flex-direction: column;">
            <span class="close-modal" onclick="closeWorkingDaysModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0;">Working Days Calculation Details</h2>
            
            <div id="workingDaysDetails" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p><strong>Employee:</strong> <span id="detailEmployeeName"></span></p>
                <p><strong>Month/Year:</strong> <span id="detailMonthYear"></span></p>
                <p><strong>Total Working Days:</strong> <span id="detailTotalWorkingDays" style="font-size: 1.2rem; color: #2d3748; font-weight: bold;"></span></p>
                
                <div style="margin-top: 20px;">
                    <h3 style="color: #1a202c; font-size: 1rem; margin-bottom: 15px;">Calculation Breakdown:</h3>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Total Days in Month:</strong> <span id="detailTotalDays"></span></p>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Weekly Off Days:</strong> <span id="detailWeeklyOffs"></span></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #718096;"><span id="detailWeeklyOffsDetails"></span></p>
                        <div id="weeklyOffsDates" style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f9fafb; display: none;">
                        </div>
                        <button type="button" id="toggleWeeklyOffDates" style="margin-top: 8px; padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: #4a5568; display: none;">Show Dates</button>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Office Holidays:</strong> <span id="detailHolidaysCount"></span></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #718096;"><span id="detailHolidaysList"></span></p>
                        <div id="holidaysDates" style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f9fafb; display: none;">
                        </div>
                        <button type="button" id="toggleHolidayDates" style="margin-top: 8px; padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: #4a5568; display: none;">Show Dates</button>
                    </div>
                    
                    <div style="background: #dcfce7; padding: 15px; border-radius: 6px; border-left: 4px solid #22c55e;">
                        <p style="margin: 0;"><strong style="color: #166534;">Working Days = Total Days - Weekly Offs - Holidays</strong></p>
                        <p style="margin: 8px 0 0 0; font-size: 0.9rem; color: #166534;">
                            <span id="detailCalculation"></span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeWorkingDaysModal()" style="flex: 1;">Close</button>
            </div>
        </div>
    </div>

    <!-- Present Days Modal -->
    <div id="presentDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closePresentDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Present Days</h2>

            <div id="presentDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="presentDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="presentDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Punch Out</th>
                            <th style="padding:8px; text-align:left;">Working Hr.</th>
                            <th style="padding:8px; text-align:left;">Overtime Hr.</th>
                        </tr>
                    </thead>
                    <tbody id="presentDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closePresentDaysModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Late Days Modal -->
    <div id="lateDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLateDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Late Days</h2>

            <div id="lateDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="lateDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="lateDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Shift Start</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Minutes Late</th>
                            <th style="padding:8px; text-align:left;">Short Leave</th>
                        </tr>
                    </thead>
                    <tbody id="lateDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLateDaysModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- 1+ Hour Late Modal -->
    <div id="oneHourLateDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeOneHourLateDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">1+ Hour Late Days</h2>

            <div id="oneHourLateDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="oneHourLateDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="oneHourLateDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Shift Start</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Minutes Late</th>
                            <th style="padding:8px; text-align:left;">Short Leave</th>
                        </tr>
                    </thead>
                    <tbody id="oneHourLateDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeOneHourLateDaysModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Leave Details Modal -->
    <div id="leaveDetailsModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLeaveDetailsModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Leave Details</h2>

            <div id="leaveDetailsContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="leaveDetailsUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="leaveDetailsTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date Range</th>
                            <th style="padding:8px; text-align:left;">Leave Type</th>
                            <th style="padding:8px; text-align:left;">Days</th>
                            <th style="padding:8px; text-align:left;">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="leaveDetailsTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLeaveDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Leave Deduction Details Modal -->
    <div id="leaveDeductionModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLeaveDeductionModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Leave Deduction Details</h2>

            <div id="leaveDeductionContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="leaveDeductionUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                
                <!-- Summary Section -->
                <div id="deductionSummary" style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #4CAF50;">
                    <h3 style="margin-top: 0; margin-bottom: 10px; color: #333;">Deduction Summary</h3>
                    <p style="margin: 5px 0;"><strong>Total Deduction:</strong> <span id="totalDeductionAmount" style="color: #d32f2f; font-weight: bold;">₹0</span></p>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">Based on approved leaves in the selected month</p>
                </div>

                <!-- Deduction Breakdown Table -->
                <table id="leaveDeductionTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Leave Type</th>
                            <th style="padding:8px; text-align:center;">Days</th>
                            <th style="padding:8px; text-align:right;">Deduction (₹)</th>
                            <th style="padding:8px; text-align:left;">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="leaveDeductionTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLeaveDeductionModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Overtime Details Modal -->
    <div id="overtimeDetailsModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 98%; max-width: 1200px; position: relative;">
            <span class="close-modal" onclick="closeOvertimeDetailsModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Overtime Details</h2>

            <div id="overtimeDetailsContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <div style="text-align:center; padding:20px;"><div class="spinner"></div><p>Loading overtime details...</p></div>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeOvertimeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Penalty Adjustment Modal -->
    <div id="penaltyModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-modal" onclick="closePenaltyModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px; text-align: center;">Adjust Penalty Days</h2>
            
            <div id="penaltyInfo" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p><strong>Employee:</strong> <span id="penaltyEmployeeName"></span></p>
                <p><strong>Current Penalty Days:</strong> <span id="penaltyCurrentValue" style="font-size: 1.2rem; color: #2d3748; font-weight: bold;"></span></p>
                <p><strong>Action:</strong> <span id="penaltyAction" style="font-size: 1.1rem; color: #4299e1; font-weight: 600;"></span></p>
            </div>

            <form id="penaltyAdjustmentForm">
                <div class="form-group">
                    <label for="penaltyReason">Reason for Adjustment (Minimum 10 words)</label>
                    <textarea id="penaltyReason" name="penaltyReason" rows="4" placeholder="Please provide at least 10 words explaining the reason for this penalty adjustment..." required style="resize: vertical;"></textarea>
                    <div style="font-size: 0.8rem; color: #718096; margin-top: 5px;">
                        Word count: <span id="wordCount">0</span>/10
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-save" id="penaltySubmitBtn" disabled>
                        Confirm Adjustment
                    </button>
                    <button type="button" class="btn-cancel" onclick="closePenaltyModal()">Cancel</button>
                </div>
            </form>

            <div id="penaltyMessage" style="margin-top: 15px; padding: 10px; border-radius: 6px; display: none;"></div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="modal" style="z-index: 9999;">
        <div class="modal-content" style="max-width: 500px; text-align: center; padding: 40px 30px;">
            <div id="notificationIcon" style="font-size: 3rem; margin-bottom: 15px;">
                <i class="fas fa-check-circle" style="color: #22c55e;"></i>
            </div>
            <h2 id="notificationTitle" style="margin: 15px 0; color: #1a202c; font-size: 1.3rem;">Success</h2>
            <p id="notificationMessage" style="color: #4a5568; font-size: 0.95rem; margin: 0; line-height: 1.6;"></p>
            <div style="margin-top: 25px;">
                <button onclick="closeNotificationModal()" class="btn-cancel" style="min-width: 100px; padding: 12px 24px; font-size: 1rem;">OK</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.filter-form');
            const monthSelect = document.getElementById('month');
            const yearSelect = document.getElementById('year');

            // Load data if filters are already selected
            const selectedMonth = monthSelect.value;
            const selectedYear = yearSelect.value;
            
            if (selectedMonth && selectedYear) {
                loadAnalyticsData(selectedMonth, selectedYear);
            }

            // Optional: Auto-submit when filters change
            // monthSelect.addEventListener('change', () => form.submit());
            // yearSelect.addEventListener('change', () => form.submit());
        });

        // Notification Modal Functions
        function showNotification(title, message, type = 'success') {
            const modal = document.getElementById('notificationModal');
            const icon = document.getElementById('notificationIcon');
            const titleEl = document.getElementById('notificationTitle');
            const messageEl = document.getElementById('notificationMessage');

            titleEl.textContent = title;
            messageEl.textContent = message;

            // Update icon based on type
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: #22c55e;"></i>';
            } else if (type === 'error') {
                icon.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>';
            } else if (type === 'warning') {
                icon.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>';
            } else if (type === 'info') {
                icon.innerHTML = '<i class="fas fa-info-circle" style="color: #3b82f6;"></i>';
            }

            modal.style.display = 'block';
        }

        function closeNotificationModal() {
            const modal = document.getElementById('notificationModal');
            modal.style.display = 'none';
        }

        // Close notification modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('notificationModal');
            if (event.target === modal) {
                closeNotificationModal();
            }
        });

        function loadAnalyticsData(month, year) {
            const tableBody = document.getElementById('analyticsTableBody');
            
            // Show loading state
            tableBody.innerHTML = `
                <tr>
                    <td colspan="13" style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading data...
                    </td>
                </tr>
            `;

            // Fetch data from backend
            fetch(`fetch_monthly_analytics_data.php?month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        populateTable(data.data);
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 40px; color: #a0aec0;">
                                    <i class="fas fa-inbox"></i> No data available for the selected period
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 40px; color: #e53e3e;">
                                <i class="fas fa-exclamation-circle"></i> Error loading data. Please try again.
                            </td>
                        </tr>
                    `;
                });
        }

        function populateTable(employees) {
            const tableBody = document.getElementById('analyticsTableBody');
            let html = '';

            employees.forEach(emp => {
                // store employee data for modal lookups
                window.analyticsDataById = window.analyticsDataById || {};
                window.analyticsDataById[emp.id] = emp;
                html += `
                    <tr data-user-id="${emp.id}">
                        <td>${emp.employee_id || 'N/A'}</td>
                        <td>${emp.name || 'N/A'}</td>
                        <td>${emp.role || 'N/A'}</td>
                        <td>₹${formatNumber(emp.base_salary || 0)}</td>
                        <td>
                            ${emp.working_days || 0}
                            <span class="info-icon" data-type="working-days" onclick="showWorkingDaysDetails(${emp.id}, '${emp.name}', ${emp.working_days}, 0)" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Click to see details</span>
                            </span>
                        </td>
                        <td>
                            ${emp.present_days || 0}
                            <span class="info-icon" data-type="present-days" onclick="showPresentDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Days with both punch in and punch out</span>
                            </span>
                        </td>
                        <td>
                            ${emp.late_days || 0}
                            <span class="info-icon" data-type="late-days" onclick="showLateDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Days late by more than 15 minutes</span>
                            </span>
                        </td>
                        <td>
                            ${emp.one_hour_late || 0}
                            <span class="info-icon" data-type="one-hour-late" onclick="showOneHourLateDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Days late by 1 hour or more</span>
                            </span>
                        </td>
                        <td>
                            ${emp.leave_taken || 0}
                            <span class="info-icon" data-type="leave-taken" onclick="showLeaveDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Approved leave days</span>
                            </span>
                        </td>
                        <td>
                            ₹${formatNumber(emp.leave_deduction || 0)}
                            <span class="info-icon" data-type="leave-deduction" onclick="showLeaveDeductionDetails(${emp.id}, '${emp.name}', ${emp.leave_deduction || 0})" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Leave deduction breakdown</span>
                            </span>
                        </td>
                        <td>₹${formatNumber(emp.late_deduction || 0)}</td>
                        <td>₹${formatNumber(emp.one_hour_late_deduction || 0)}</td>
                        <td>₹${formatNumber(emp.fourth_saturday_deduction || 0)}</td>
                        <td>
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <button type="button" class="penalty-btn penalty-decrease" onclick="openPenaltyModal(${emp.id}, '${emp.name}', 'decrease')" title="Decrease by 0.5 days">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span id="penalty-value-${emp.id}" style="min-width: 40px; text-align: center; font-weight: 600;">${(emp.penalty_days || 0).toFixed(1)}</span>
                                <button type="button" class="penalty-btn penalty-increase" onclick="openPenaltyModal(${emp.id}, '${emp.name}', 'increase')" title="Increase by 0.5 days">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            ${(emp.salary_calculated_days || 0).toFixed(2)}
                            <span class="info-icon" style="cursor:pointer; margin-left:6px;" onclick="showSalaryCalcDetails(${emp.id})">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        </td>
                        <td>
                            ₹${formatNumber((Number(emp.salary_calculated_days || 0) * (Number(emp.base_salary || 0) / Number(emp.working_days || 1))).toFixed(2))}
                        </td>
                        <td>
                            ${emp.overtime_hours || 0}
                            <span class="info-icon" data-type="overtime-hours" onclick="showOvertimeDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Overtime hours breakdown</span>
                            </span>
                        </td>
                        <td>
                            ₹${formatNumber(emp.overtime_amount || 0)}
                        </td>
                        <td>
                            ₹${formatNumber((Number(emp.salary_calculated_days || 0) * (Number(emp.base_salary || 0) / Number(emp.working_days || 1)) + Number(emp.overtime_amount || 0)).toFixed(2))}
                        </td>
                        <td style="display: flex; gap: 10px; justify-content: center;">
                            <button type="button" class="action-btn edit-btn" title="Edit" onclick="editEmployee('${emp.employee_id}', ${emp.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="action-btn paid-btn" title="Mark as Paid" onclick="markAsPaid('${emp.employee_id}')">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = html;
        }

        function formatNumber(num) {
            return Number(num).toLocaleString('en-IN');
        }

        function editEmployee(employeeId, userId) {
            // Find employee data from table
            const rows = document.querySelectorAll('.analytics-table tbody tr');
            let employeeData = null;

            rows.forEach(row => {
                const rowEmployeeId = row.cells[0].innerText;
                if (rowEmployeeId === employeeId) {
                    employeeData = {
                        id: rowEmployeeId,
                        name: row.cells[1].innerText,
                        role: row.cells[2].innerText,
                        currentSalary: row.cells[3].innerText
                    };
                }
            });

            if (employeeData) {
                // Populate modal with employee data
                document.getElementById('modalEmployeeId').innerText = employeeData.id;
                document.getElementById('modalEmployeeName').innerText = employeeData.name;
                document.getElementById('modalCurrentSalary').innerText = employeeData.currentSalary;
                
                // Extract numeric value from salary
                const salaryValue = parseFloat(employeeData.currentSalary.replace(/[₹,]/g, ''));
                document.getElementById('newBaseSalary').value = salaryValue;

                // Store employee data for form submission
                document.getElementById('editSalaryForm').dataset.employeeId = employeeId;
                document.getElementById('editSalaryForm').dataset.userId = userId; // Store actual user_id

                // Show modal
                document.getElementById('editSalaryModal').style.display = 'block';
            } else {
                showNotification('Error', 'Employee not found', 'error');
            }
        }

        function closeEditModal() {
            document.getElementById('editSalaryModal').style.display = 'none';
            document.getElementById('editSalaryForm').reset();
            document.getElementById('formMessage').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const editModal = document.getElementById('editSalaryModal');
            const workingDaysModal = document.getElementById('workingDaysModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === workingDaysModal) {
                closeWorkingDaysModal();
            }
        }

        // Handle salary form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editSalaryForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveSalaryRecord();
                });
            }
        });

        function saveSalaryRecord() {
            const form = document.getElementById('editSalaryForm');
            const employeeId = form.dataset.employeeId;
            const userId = parseInt(form.dataset.userId);
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const baseSalary = parseFloat(document.getElementById('newBaseSalary').value);
            const remarks = document.getElementById('salaryRemarks').value;

            if (!month || !year) {
                showFormMessage('Please select month and year', 'error');
                return;
            }

            if (baseSalary <= 0) {
                showFormMessage('Please enter a valid salary', 'error');
                return;
            }

            if (!userId || userId <= 0) {
                showFormMessage('Invalid user ID', 'error');
                return;
            }

            // Show loading spinner
            document.querySelector('.save-text').style.display = 'none';
            document.getElementById('saveSpinner').style.display = 'block';

            const payload = {
                employee_id: employeeId,
                user_id: userId,
                base_salary: baseSalary,
                month: parseInt(month),
                year: parseInt(year),
                remarks: remarks
            };

            console.log('Sending payload:', payload);

            fetch('save_salary_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                // Hide loading spinner
                document.querySelector('.save-text').style.display = 'inline';
                document.getElementById('saveSpinner').style.display = 'none';

                if (data.status === 'success') {
                    showFormMessage('Salary record saved successfully!', 'success');
                    setTimeout(() => {
                        closeEditModal();
                        // Reload table data
                        const month = document.getElementById('month').value;
                        const year = document.getElementById('year').value;
                        loadAnalyticsData(month, year);
                    }, 1500);
                } else {
                    showFormMessage(data.message || 'Error saving salary record', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.querySelector('.save-text').style.display = 'inline';
                document.getElementById('saveSpinner').style.display = 'none';
                showFormMessage('Error saving salary record. Please try again.', 'error');
            });
        }

        function showFormMessage(message, type) {
            const messageDiv = document.getElementById('formMessage');
            messageDiv.innerText = message;
            messageDiv.style.display = 'block';
            
            if (type === 'success') {
                messageDiv.style.background = '#c6f6d5';
                messageDiv.style.color = '#22543d';
                messageDiv.style.border = '1px solid #9ae6b4';
            } else {
                messageDiv.style.background = '#fed7d7';
                messageDiv.style.color = '#742a2a';
                messageDiv.style.border = '1px solid #fc8787';
            }
        }

        function showWorkingDaysDetails(userId, employeeName, month, year) {
            // Store current data for API call
            const currentMonth = document.getElementById('month').value;
            const currentYear = document.getElementById('year').value;
            
            // Fetch working days details from the backend
            fetch(`get_working_days_details.php?user_id=${userId}&month=${currentMonth}&year=${currentYear}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Populate modal with data
                        document.getElementById('detailEmployeeName').innerText = employeeName;
                        document.getElementById('detailMonthYear').innerText = data.monthYear;
                        document.getElementById('detailTotalWorkingDays').innerText = data.workingDays;
                        document.getElementById('detailTotalDays').innerText = data.totalDays;
                        
                        // Weekly offs
                        document.getElementById('detailWeeklyOffs').innerText = data.weeklyOffsCount;
                        let weeklyOffsText = data.weeklyOffs.length > 0 ? data.weeklyOffs.join(', ') : 'None';
                        let weeklyOffsBreakdown = data.weeklyOffsBreakdown || 'No breakdown available';
                        document.getElementById('detailWeeklyOffsDetails').innerText = `${weeklyOffsText} (${weeklyOffsBreakdown})`;
                        
                        // Populate weekly off dates
                        const weeklyOffsDatesDiv = document.getElementById('weeklyOffsDates');
                        const toggleWeeklyOffDatesBtn = document.getElementById('toggleWeeklyOffDates');
                        
                        if (data.weeklyOffDates && data.weeklyOffDates.length > 0) {
                            let datesHTML = '';
                            data.weeklyOffDates.forEach(dateObj => {
                                datesHTML += `<div style="padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                                    <strong>${dateObj.date}</strong> - ${dateObj.fullDate} (${dateObj.day})
                                </div>`;
                            });
                            weeklyOffsDatesDiv.innerHTML = datesHTML;
                            toggleWeeklyOffDatesBtn.style.display = 'inline-block';
                            
                            // Toggle button functionality
                            toggleWeeklyOffDatesBtn.onclick = function() {
                                if (weeklyOffsDatesDiv.style.display === 'none') {
                                    weeklyOffsDatesDiv.style.display = 'block';
                                    toggleWeeklyOffDatesBtn.innerText = 'Hide Dates';
                                    toggleWeeklyOffDatesBtn.style.background = '#cbd5e0';
                                } else {
                                    weeklyOffsDatesDiv.style.display = 'none';
                                    toggleWeeklyOffDatesBtn.innerText = 'Show Dates';
                                    toggleWeeklyOffDatesBtn.style.background = '#e2e8f0';
                                }
                            };
                        }
                        
                        // Holidays
                        document.getElementById('detailHolidaysCount').innerText = data.holidaysCount;
                        let holidaysList = data.holidays.length > 0 ? data.holidays.join(', ') : 'None';
                        document.getElementById('detailHolidaysList').innerText = holidaysList;
                        
                        // Populate holiday dates
                        const holidaysDatesDiv = document.getElementById('holidaysDates');
                        const toggleHolidayDatesBtn = document.getElementById('toggleHolidayDates');
                        
                        if (data.holidayDetailedDates && data.holidayDetailedDates.length > 0) {
                            let datesHTML = '';
                            data.holidayDetailedDates.forEach(holiday => {
                                datesHTML += `<div style="padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                                    <strong>${holiday.date}</strong> - ${holiday.fullDate} (${holiday.day}) - ${holiday.name}
                                </div>`;
                            });
                            holidaysDatesDiv.innerHTML = datesHTML;
                            toggleHolidayDatesBtn.style.display = 'inline-block';
                            
                            // Toggle button functionality
                            toggleHolidayDatesBtn.onclick = function() {
                                if (holidaysDatesDiv.style.display === 'none') {
                                    holidaysDatesDiv.style.display = 'block';
                                    toggleHolidayDatesBtn.innerText = 'Hide Dates';
                                    toggleHolidayDatesBtn.style.background = '#cbd5e0';
                                } else {
                                    holidaysDatesDiv.style.display = 'none';
                                    toggleHolidayDatesBtn.innerText = 'Show Dates';
                                    toggleHolidayDatesBtn.style.background = '#e2e8f0';
                                }
                            };
                        }
                        
                        // Calculation
                        document.getElementById('detailCalculation').innerText = 
                            `${data.totalDays} - ${data.weeklyOffsCount} - ${data.holidaysCount} = ${data.workingDays}`;
                        
                        // Show modal
                        document.getElementById('workingDaysModal').style.display = 'block';
                    } else {
                        showNotification('Error', 'Error fetching working days details: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error', 'Error loading working days details', 'error');
                });
        }

        function closeWorkingDaysModal() {
            document.getElementById('workingDaysModal').style.display = 'none';
        }

        function markAsPaid(employeeId) {
            showNotification('Info', `Mark as paid: ${employeeId}`, 'info');
            // Add your paid marking logic here
            console.log('Paid clicked for employee:', employeeId);
        }

        function exportToExcel() {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            
            if (!month || !year) {
                showNotification('Warning', 'Please select month and year', 'warning');
                return;
            }

            // Show loading state
            const exportBtn = event.target.closest('button');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;

            // Use a more reliable CDN URL for XLSX
            const xslxUrl = 'https://unpkg.com/xlsx@latest/dist/xlsx.full.min.js';
            
            // Check if SheetJS is loaded, if not load it
            if (!window.XLSX) {
                const script = document.createElement('script');
                script.src = xslxUrl;
                script.async = true;
                
                script.onload = () => {
                    console.log('XLSX library loaded successfully from:', xslxUrl);
                    performExport(month, year, exportBtn, originalText);
                };
                
                script.onerror = () => {
                    console.error('Failed to load XLSX library from:', xslxUrl);
                    showNotification('Error', 'Error loading Excel library. Please try again.', 'error');
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                };
                
                // Set a timeout to catch loading issues
                setTimeout(() => {
                    if (!window.XLSX) {
                        console.error('XLSX library failed to load within timeout');
                        showNotification('Error', 'Error: Excel library took too long to load. Please try again.', 'error');
                        exportBtn.innerHTML = originalText;
                        exportBtn.disabled = false;
                    }
                }, 15000);
                
                document.head.appendChild(script);
            } else {
                console.log('XLSX library already loaded');
                performExport(month, year, exportBtn, originalText);
            }
        }

        function performExport(month, year, exportBtn, originalText) {
            console.log('Starting export for month:', month, 'year:', year);
            
            // Fetch data from the export handler
            fetch(`export_monthly_analytics_excel_data_handler.php?month=${month}&year=${year}`)
                .then(response => {
                    console.log('Response received, status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP Error: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    
                    if (data.status !== 'success') {
                        throw new Error(data.message || 'Export failed');
                    }
                    
                    if (!data.data || data.data.length === 0) {
                        throw new Error('No data available for export');
                    }

                    console.log('Creating Excel workbook...');

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    
                    // Prepare data for worksheet
                    const headers = [
                        'Employee ID',
                        'Name',
                        'Role',
                        'Base Salary',
                        'Working Days',
                        'Present Days',
                        'Late Days',
                        '1+ Hour Late',
                        'Leave Taken',
                        'Leave Deduction',
                        'Late Deduction',
                        '1+ Hour Late Deduction',
                        '4th Saturday Deduction',
                        'Salary Calculated Days',
                        'Net Salary',
                        'Overtime Hours',
                        'Overtime Amount',
                        'Final Salary'
                    ];

                    // Create data rows
                    const worksheetData = [headers];
                    data.data.forEach(emp => {
                        worksheetData.push([
                            emp.employee_id || '',
                            emp.name || '',
                            emp.role || '',
                            emp.base_salary || 0,
                            emp.working_days || 0,
                            emp.present_days || 0,
                            emp.late_days || 0,
                            emp.one_hour_late || 0,
                            emp.leave_taken || 0,
                            emp.leave_deduction || 0,
                            emp.late_deduction || 0,
                            emp.one_hour_late_deduction || 0,
                            emp.fourth_saturday_deduction || 0,
                            emp.salary_calculated_days || 0,
                            emp.net_salary || 0,
                            emp.overtime_hours || 0,
                            emp.overtime_amount || 0,
                            emp.final_salary || 0
                        ]);
                    });

                    // Add empty row and summary section
                    worksheetData.push([]);
                    worksheetData.push(['SUMMARY']);
                    worksheetData.push(['Total Salary (Without Overtime)', data.summary.total_salary_without_overtime]);
                    worksheetData.push(['Total Salary (With Overtime)', data.summary.total_salary_with_overtime]);
                    worksheetData.push(['Total Overtime Amount', data.summary.total_overtime_amount]);
                    worksheetData.push(['Total Employees', data.summary.employee_count]);

                    console.log('Creating worksheet...');
                    // Create worksheet
                    const ws = XLSX.utils.aoa_to_sheet(worksheetData);

                    console.log('Applying header styling...');
                    // Color header row - Dark Blue (#366092)
                    for (let i = 0; i < headers.length; i++) {
                        const cellRef = XLSX.utils.encode_cell({ r: 0, c: i });
                        if (ws[cellRef]) {
                            ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FF366092' } };
                            ws[cellRef].font = { color: { rgb: 'FFFFFFFF' }, bold: true };
                        }
                    }

                    console.log('Applying alternating row colors...');
                    // Color data rows with alternating pattern - Light Gray
                    for (let i = 1; i < data.data.length + 1; i++) {
                        if (i % 2 === 0) {
                            for (let j = 0; j < headers.length; j++) {
                                const cellRef = XLSX.utils.encode_cell({ r: i, c: j });
                                if (ws[cellRef]) {
                                    ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FFF5F5F5' } };
                                }
                            }
                        }
                    }

                    console.log('Applying summary styling...');
                    // Color summary section
                    const summaryStartRow = data.data.length + 2;
                    
                    // Color SUMMARY header row - Dark Blue
                    for (let j = 0; j < headers.length; j++) {
                        const cellRef = XLSX.utils.encode_cell({ r: summaryStartRow, c: j });
                        if (ws[cellRef]) {
                            ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FF366092' } };
                            ws[cellRef].font = { color: { rgb: 'FFFFFFFF' }, bold: true };
                        }
                    }

                    // Color summary data rows - Light Blue
                    for (let i = summaryStartRow + 1; i < summaryStartRow + 5; i++) {
                        for (let j = 0; j < headers.length; j++) {
                            const cellRef = XLSX.utils.encode_cell({ r: i, c: j });
                            if (ws[cellRef]) {
                                ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FFE8F0F8' } };
                            }
                        }
                    }

                    console.log('Setting column widths...');
                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 },  // Employee ID
                        { wch: 20 },  // Name
                        { wch: 15 },  // Role
                        { wch: 15 },  // Base Salary
                        { wch: 12 },  // Working Days
                        { wch: 12 },  // Present Days
                        { wch: 12 },  // Late Days
                        { wch: 12 },  // 1+ Hour Late
                        { wch: 12 },  // Leave Taken
                        { wch: 15 },  // Leave Deduction
                        { wch: 15 },  // Late Deduction
                        { wch: 15 },  // 1+ Hour Late Deduction
                        { wch: 15 },  // 4th Saturday Deduction
                        { wch: 15 },  // Salary Calculated Days
                        { wch: 15 },  // Net Salary
                        { wch: 15 },  // Overtime Hours
                        { wch: 15 },  // Overtime Amount
                        { wch: 15 }   // Final Salary
                    ];

                    console.log('Appending sheet to workbook...');
                    // Add worksheet to workbook
                    XLSX.utils.book_append_sheet(wb, ws, 'Analytics');

                    // Generate filename
                    const monthName = document.querySelector('select[name="month"] option:checked').text;
                    const filename = `Analytics_${monthName}_${year}_${new Date().getTime()}.xlsx`;

                    console.log('Writing file:', filename);
                    // Write file
                    XLSX.writeFile(wb, filename);

                    console.log('Export completed successfully');
                    
                    // Reset button
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;

                    // Show success message
                    showNotification('Success', 'Export completed successfully!', 'success');
                })
                .catch(error => {
                    console.error('Error in export:', error);
                    showNotification('Error', 'Error exporting data: ' + error.message, 'error');
                    
                    // Reset button
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                });
        }

        // Present Days modal logic
        function showPresentDaysDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                showNotification('Warning', 'Please select month and year', 'warning');
                return;
            }

            // Clear previous data
            document.getElementById('presentDaysTbody').innerHTML = '';
            document.getElementById('presentDaysUserInfo').innerText = `Loading present days for ${employeeName}...`;

            fetch(`get_present_days.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('presentDaysUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const weeklyOffs = (data.weekly_offs || []).map(d => String(d).trim().toLowerCase());
                    const presentCount = records.length;
                    document.getElementById('presentDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${presentCount} present day(s)`;

                    // Build a map of records by date for quick lookup
                    const recMap = {};
                    records.forEach(r => { recMap[r.date] = r; });

                    const tbody = document.getElementById('presentDaysTbody');
                    let html = '';

                    // Determine last day of month
                    const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();
                    const monthIndex = parseInt(month) - 1;
                    const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

                    for (let day = 1; day <= lastDay; day++) {
                        const dt = new Date(parseInt(year), monthIndex, day);
                        const iso = dt.toISOString().slice(0,10);
                        const displayDate = (('0' + dt.getDate()).slice(-2)) + '-' + monthNames[dt.getMonth()] + '-' + dt.getFullYear();
                        const dayName = dt.toLocaleDateString('en-US', { weekday: 'long' });
                        const isWeekly = weeklyOffs.indexOf(dayName.toLowerCase()) !== -1;

                        const rec = recMap[iso];
                        if (rec) {
                            const highlight = rec.is_weekly_off ? 'background:#fff7ed;' : '';
                            const weeklyBadge = rec.is_weekly_off ? '<span class="badge badge-warning" style="margin-left:8px; background:#fef3c7; color:#92400e; padding:4px 6px; border-radius:4px; font-size:0.75rem;">Weekly Off</span>' : '';
                            const presentOnWeeklyOffNote = rec.is_weekly_off ? ' <strong style="color:#92400e; font-size:0.9rem;">(Present on weekly off)</strong>' : '';
                            html += `<tr style="border-bottom:1px solid #e2e8f0; ${highlight}"><td style="padding:8px">${displayDate} ${weeklyBadge}</td><td style="padding:8px">${dayName}${presentOnWeeklyOffNote}</td><td style="padding:8px">${rec.punch_in || '-' }${rec.punch_in_photo ? ` <a href="${rec.punch_in_photo}" target="_blank">📷</a>` : ''}</td><td style="padding:8px">${rec.punch_out || '-'}${rec.punch_out_photo ? ` <a href="${rec.punch_out_photo}" target="_blank">📷</a>` : ''}</td><td style="padding:8px">${rec.working_hours || '-'}</td><td style="padding:8px">${rec.overtime_hours || '-'}</td></tr>`;
                        } else {
                            // No record for this date
                            const weeklyBadge = isWeekly ? '<span class="badge" style="margin-left:8px; background:#f1f5f9; color:#4a5568; padding:3px 6px; border-radius:4px; font-size:0.75rem;">Weekly Off</span>' : '';
                            html += `<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:8px">${displayDate} ${weeklyBadge}</td><td style="padding:8px">${dayName}</td><td style="padding:8px">-</td><td style="padding:8px">-</td><td style="padding:8px">-</td><td style="padding:8px">-</td></tr>`;
                        }
                    }

                    tbody.innerHTML = html;
                    document.getElementById('presentDaysModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('presentDaysUserInfo').innerText = 'Error loading present days';
                });
        }

        function closePresentDaysModal() {
            document.getElementById('presentDaysModal').style.display = 'none';
        }

        // Late Days modal logic
        function showLateDaysDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                showNotification('Warning', 'Please select month and year', 'warning');
                return;
            }

            // Clear previous data
            document.getElementById('lateDaysTbody').innerHTML = '';
            document.getElementById('lateDaysUserInfo').innerText = `Loading late days for ${employeeName}...`;

            fetch(`get_late_days.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('lateDaysUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const lateCount = records.length;
                    document.getElementById('lateDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${lateCount} late day(s)`;

                    const tbody = document.getElementById('lateDaysTbody');
                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="padding:12px; text-align:center; color:#718096;">No late punch-in records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        records.forEach(r => {
                            const highlightRow = 'background:#fef3c7;';
                            html += `<tr style="border-bottom:1px solid #e2e8f0; ${highlightRow}"><td style="padding:12px 16px">${r.displayDate}</td><td style="padding:12px 16px">${r.day}</td><td style="padding:12px 16px">${r.shift_start_time}</td><td style="padding:12px 16px">${r.punch_in}</td><td style="padding:12px 16px">${r.minutes_late} min</td><td style="padding:12px 16px">${r.short_leave}</td></tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('lateDaysModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('lateDaysUserInfo').innerText = 'Error loading late days';
                });
        }

        function closeLateDaysModal() {
            document.getElementById('lateDaysModal').style.display = 'none';
        }

        function showOneHourLateDaysDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                showNotification('Warning', 'Please select month and year', 'warning');
                return;
            }

            // Clear previous data
            document.getElementById('oneHourLateDaysTbody').innerHTML = '';
            document.getElementById('oneHourLateDaysUserInfo').innerText = `Loading 1+ hour late days for ${employeeName}...`;

            fetch(`get_one_hour_late_days.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('oneHourLateDaysUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const lateCount = records.length;
                    document.getElementById('oneHourLateDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${lateCount} very late day(s)`;

                    const tbody = document.getElementById('oneHourLateDaysTbody');
                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="padding:12px; text-align:center; color:#718096;">No 1+ hour late punch-in records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        records.forEach(r => {
                            html += `<tr style="border-bottom:1px solid #e2e8f0; background:#fee2e2;"><td style="padding:12px 16px">${r.displayDate}</td><td style="padding:12px 16px">${r.day}</td><td style="padding:12px 16px">${r.shift_start_time}</td><td style="padding:12px 16px">${r.punch_in}</td><td style="padding:12px 16px">${r.minutes_late} min</td><td style="padding:12px 16px">${r.short_leave}</td></tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('oneHourLateDaysModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('oneHourLateDaysUserInfo').innerText = 'Error loading 1+ hour late days';
                });
        }

        function closeOneHourLateDaysModal() {
            document.getElementById('oneHourLateDaysModal').style.display = 'none';
        }

        function showLeaveDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                showNotification('Warning', 'Please select month and year', 'warning');
                return;
            }

            // Clear previous data
            document.getElementById('leaveDetailsTbody').innerHTML = '';
            document.getElementById('leaveDetailsUserInfo').innerText = `Loading leave details for ${employeeName}...`;

            fetch(`get_leave_records.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('leaveDetailsUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const leaveCount = records.length;
                    document.getElementById('leaveDetailsUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${leaveCount} leave application(s)`;

                    const tbody = document.getElementById('leaveDetailsTbody');
                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No approved leave records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        records.forEach(r => {
                            html += `<tr style="border-bottom:1px solid #e2e8f0; background:#fef9e7;"><td style="padding:12px 16px">${r.date_range}</td><td style="padding:12px 16px">${r.leave_type}</td><td style="padding:12px 16px text-align:center;">${r.num_days}</td><td style="padding:12px 16px">${r.reason}</td></tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('leaveDetailsModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('leaveDetailsUserInfo').innerText = 'Error loading leave details';
                });
        }

        function closeLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').style.display = 'none';
        }

        function showLeaveDeductionDetails(userId, employeeName, totalDeduction) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                showNotification('Warning', 'Please select month and year', 'warning');
                return;
            }

            // Clear previous data
            document.getElementById('leaveDeductionTbody').innerHTML = '';
            document.getElementById('leaveDeductionUserInfo').innerText = `Loading leave deduction details for ${employeeName}...`;
            document.getElementById('totalDeductionAmount').innerText = `₹${totalDeduction.toLocaleString('en-IN')}`;

            fetch(`calculate_leave_deductions.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('leaveDeductionUserInfo').innerText = 'Unable to load deduction details';
                        document.getElementById('leaveDeductionTbody').innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No deduction data available.</td></tr>';
                        document.getElementById('leaveDeductionModal').style.display = 'block';
                        return;
                    }

                    const deductions = data.deductions || {};
                    document.getElementById('leaveDeductionUserInfo').innerText = `${employeeName} — October 2025 Leave Deductions`;

                    const tbody = document.getElementById('leaveDeductionTbody');
                    const leaveDeductions = deductions.leave_deductions || [];

                    if (leaveDeductions.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No approved leave records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        leaveDeductions.forEach(item => {
                            const deductionAmount = Number(item.deduction).toLocaleString('en-IN', { 
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            html += `<tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:12px 16px">${item.leave_type || 'Unknown'}</td>
                                <td style="padding:12px 16px; text-align:center;">${item.num_days}</td>
                                <td style="padding:12px 16px; text-align:right;">₹${deductionAmount}</td>
                                <td style="padding:12px 16px; font-size:12px; color:#666;">${item.deduction_type || 'N/A'}</td>
                            </tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    // Update total deduction display
                    const totalDeductionValue = deductions.total_deduction || 0;
                    document.getElementById('totalDeductionAmount').innerText = `₹${Number(totalDeductionValue).toLocaleString('en-IN', { 
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}`;

                    document.getElementById('leaveDeductionModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('leaveDeductionUserInfo').innerText = 'Error loading deduction details';
                    document.getElementById('leaveDeductionTbody').innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#d32f2f;">Failed to load deduction data.</td></tr>';
                    document.getElementById('leaveDeductionModal').style.display = 'block';
                });
        }

        function closeLeaveDeductionModal() {
            document.getElementById('leaveDeductionModal').style.display = 'none';
        }

        function showOvertimeDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            const container = document.getElementById('overtimeDetailsContainer');
            container.innerHTML = '<div style="text-align:center; padding:20px;"><div class="spinner"></div><p>Loading overtime details...</p></div>';

            fetch(`fetch_user_overtime_detailed_breakdown.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        throw new Error(data.message || 'Failed to fetch overtime details');
                    }

                    const salaryInfo = data.salary_info;
                    const overtimeSummary = data.overtime_summary;
                    const records = data.overtime_records || [];

                    let html = `
                        <div style="background:#fff; padding:16px; border-radius:6px; margin-bottom:15px;">
                            <h3 style="margin:0 0 12px 0; color:#1a202c;">Employee Information</h3>
                            <p style="margin:4px 0;"><strong>Name:</strong> ${employeeName}</p>
                            <p style="margin:4px 0;"><strong>Base Salary:</strong> ₹${Number(salaryInfo.base_salary).toLocaleString('en-IN')}</p>
                            <p style="margin:4px 0;"><strong>Per Hour Salary:</strong> ₹${Number(salaryInfo.per_hour_salary).toLocaleString('en-IN')}</p>
                        </div>

                        <div style="background:#fff; padding:16px; border-radius:6px; margin-bottom:15px;">
                            <h3 style="margin:0 0 12px 0; color:#1a202c;">Salary Structure</h3>
                            <p style="margin:4px 0;"><strong>Daily Salary:</strong> ₹${Number(salaryInfo.per_day_salary).toLocaleString('en-IN')}</p>
                            <p style="margin:4px 0;"><strong>Shift Hours:</strong> ${Number(salaryInfo.shift_hours).toFixed(2)} hours</p>
                            <p style="margin:4px 0;"><strong>Working Days (Month):</strong> ${salaryInfo.working_days} days</p>
                        </div>

                        <div style="background:#fff; padding:16px; border-radius:6px; margin-bottom:15px;">
                            <h3 style="margin:0 0 12px 0; color:#1a202c;">Overtime Summary</h3>
                            <p style="margin:4px 0;"><strong>Total Overtime Hours:</strong> ${Number(overtimeSummary.total_hours).toFixed(2)} hours</p>
                            <p style="margin:4px 0; font-weight:600; color:#2d3748;"><strong>Total Overtime Amount:</strong> ₹${Number(overtimeSummary.total_amount).toLocaleString('en-IN')}</p>
                        </div>
                    `;

                    if (records.length > 0) {
                        html += `
                            <div style="background:#fff; padding:16px; border-radius:6px;">
                                <h3 style="margin:0 0 12px 0; color:#1a202c;">Overtime Records (${records.length} entries)</h3>
                                <table style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#f8f9fa; border-bottom:2px solid #2d3748;">
                                            <th style="padding:10px 12px; text-align:left; font-weight:600; color:#1a202c; border-right:1px solid #e2e8f0;">Date</th>
                                            <th style="padding:10px 12px; text-align:center; font-weight:600; color:#1a202c; border-right:1px solid #e2e8f0;">Hours</th>
                                            <th style="padding:10px 12px; text-align:right; font-weight:600; color:#1a202c; border-right:1px solid #e2e8f0;">Amount</th>
                                            <th style="padding:10px 12px; text-align:left; font-weight:600; color:#1a202c;">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        records.forEach((record, index) => {
                            const dateObj = new Date(record.date);
                            const displayDate = dateObj.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            const rowBg = index % 2 === 0 ? '#ffffff' : '#fafbfc';
                            
                            html += `
                                <tr style="background:${rowBg}; border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:10px 12px; border-right:1px solid #e2e8f0;">${displayDate}</td>
                                    <td style="padding:10px 12px; text-align:center; border-right:1px solid #e2e8f0;">${Number(record.hours).toFixed(2)}</td>
                                    <td style="padding:10px 12px; text-align:right; border-right:1px solid #e2e8f0; font-weight:500;">₹${Number(record.amount).toLocaleString('en-IN')}</td>
                                    <td style="padding:10px 12px;">
                                        ${record.description ? `<small style="color:#666;">${record.description}</small>` : '<small style="color:#aaa;">-</small>'}
                                    </td>
                                </tr>
                            `;
                        });

                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        html += `
                            <div style="background:#fee2e2; padding:16px; border-radius:6px; text-align:center; color:#991b1b;">
                                <p style="margin:0;">No overtime records found for this month</p>
                            </div>
                        `;
                    }

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching overtime details:', error);
                    container.innerHTML = `
                        <div style="background:#fee2e2; padding:16px; border-radius:6px;">
                            <h3 style="margin:0 0 10px 0; color:#991b1b;">Error Loading Details</h3>
                            <p style="margin:0; color:#991b1b; font-size:0.9rem;">${error.message}</p>
                            <p style="margin:8px 0 0 0; font-size:0.85rem; color:#7f1d1d;">Employee: ${employeeName} | Month: ${month}/${year}</p>
                        </div>
                    `;
                });

            document.getElementById('overtimeDetailsModal').style.display = 'block';
        }

        function closeOvertimeDetailsModal() {
            document.getElementById('overtimeDetailsModal').style.display = 'none';
        }

        // Penalty Modal Functions
        function openPenaltyModal(userId, employeeName, action) {
            const modal = document.getElementById('penaltyModal');
            const currentValue = document.getElementById(`penalty-value-${userId}`).innerText;
            
            document.getElementById('penaltyEmployeeName').innerText = employeeName;
            document.getElementById('penaltyCurrentValue').innerText = currentValue + ' days';
            
            const actionText = action === 'increase' ? 'Increase by 0.5 days' : 'Decrease by 0.5 days';
            document.getElementById('penaltyAction').innerText = actionText;
            document.getElementById('penaltyAction').style.color = action === 'increase' ? '#22543d' : '#c53030';
            
            // Store user and action info for form submission
            document.getElementById('penaltyAdjustmentForm').dataset.userId = userId;
            document.getElementById('penaltyAdjustmentForm').dataset.action = action;
            document.getElementById('penaltyAdjustmentForm').dataset.employeeName = employeeName;
            
            // Reset form
            document.getElementById('penaltyReason').value = '';
            document.getElementById('wordCount').innerText = '0';
            document.getElementById('penaltySubmitBtn').disabled = true;
            document.getElementById('penaltyMessage').style.display = 'none';
            
            modal.style.display = 'block';
        }

        function closePenaltyModal() {
            document.getElementById('penaltyModal').style.display = 'none';
            document.getElementById('penaltyAdjustmentForm').reset();
            document.getElementById('penaltyMessage').style.display = 'none';
        }

        // Word count validation for penalty reason
        document.addEventListener('DOMContentLoaded', function() {
            const reasonTextarea = document.getElementById('penaltyReason');
            const submitBtn = document.getElementById('penaltySubmitBtn');
            const wordCountDisplay = document.getElementById('wordCount');
            
            if (reasonTextarea) {
                reasonTextarea.addEventListener('input', function() {
                    const words = this.value.trim().split(/\s+/).filter(word => word.length > 0).length;
                    wordCountDisplay.innerText = words;
                    
                    // Enable submit button only if at least 10 words
                    submitBtn.disabled = words < 10;
                    
                    // Change color based on word count
                    if (words < 10) {
                        wordCountDisplay.style.color = '#c53030';
                    } else {
                        wordCountDisplay.style.color = '#22543d';
                    }
                });
            }
            
            // Handle penalty form submission
            const penaltyForm = document.getElementById('penaltyAdjustmentForm');
            if (penaltyForm) {
                penaltyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitPenaltyAdjustment();
                });
            }
        });

        function submitPenaltyAdjustment() {
            const form = document.getElementById('penaltyAdjustmentForm');
            const userId = form.dataset.userId;
            const action = form.dataset.action;
            const employeeName = form.dataset.employeeName;
            const reason = document.getElementById('penaltyReason').value.trim();
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            
            // Validate word count again
            const words = reason.split(/\s+/).filter(word => word.length > 0).length;
            if (words < 10) {
                showPenaltyMessage('Please provide at least 10 words for the reason', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('penaltySubmitBtn');
            const originalText = submitBtn.innerText;
            submitBtn.innerText = 'Processing...';
            submitBtn.disabled = true;
            
            // Calculate new penalty value
            const currentValue = parseFloat(document.getElementById(`penalty-value-${userId}`).innerText);
            const newValue = action === 'increase' ? currentValue + 0.5 : currentValue - 0.5;
            
            const payload = {
                user_id: parseInt(userId),
                action: action,
                current_penalty: currentValue,
                new_penalty: newValue,
                reason: reason,
                month: parseInt(month),
                year: parseInt(year)
            };
            
            console.log('Penalty adjustment payload:', payload);
            
            // Send to backend
            fetch('save_penalty_adjustment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.status === 'success') {
                    showPenaltyMessage(`Penalty adjustment saved successfully for ${employeeName}! New penalty: ${newValue.toFixed(1)} days`, 'success');
                    
                    // Update the UI immediately with new value
                    document.getElementById(`penalty-value-${userId}`).innerText = newValue.toFixed(1);
                    
                    // Reset button and close modal after delay
                    setTimeout(() => {
                        submitBtn.innerText = originalText;
                        submitBtn.disabled = false;
                        // Reload data to reflect changes in salary calculated days
                        const currentMonth = document.getElementById('month').value;
                        const currentYear = document.getElementById('year').value;
                        loadAnalyticsData(currentMonth, currentYear);
                        closePenaltyModal();
                    }, 1500);
                } else {
                    showPenaltyMessage(data.message || 'Error saving penalty adjustment', 'error');
                    submitBtn.innerText = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showPenaltyMessage('Error saving penalty adjustment. Please try again.', 'error');
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            });
        }

        function showPenaltyMessage(message, type) {
            const messageDiv = document.getElementById('penaltyMessage');
            messageDiv.innerText = message;
            messageDiv.style.display = 'block';
            
            if (type === 'success') {
                messageDiv.style.background = '#c6f6d5';
                messageDiv.style.color = '#22543d';
                messageDiv.style.border = '1px solid #9ae6b4';
            } else {
                messageDiv.style.background = '#fed7d7';
                messageDiv.style.color = '#742a2a';
                messageDiv.style.border = '1px solid #fc8787';
            }
        }

        function showSalaryCalcDetails(userId) {
            const emp = (window.analyticsDataById || {})[userId];
            if (!emp) return;

            const workingDays = Number(emp.working_days || 0);
            const baseSalary = Number(emp.base_salary || 0);
            const dailySalary = workingDays > 0 ? (baseSalary / workingDays) : 0;

            const presentDays = Number(emp.present_days || 0);
            const casual = Number(emp.casual_leave_days || 0);
            const half = Number(emp.half_day_leave_days || 0);
            const compensate = Number(emp.compensate_leave_days || 0);
            const leaveTaken = Number(emp.leave_taken || 0);

            const regularLateDays = Number(emp.late_days || 0);
            const regularLateDeductionDays = Math.floor(regularLateDays / 3) * 0.5;
            const oneHourLate = Number(emp.one_hour_late || 0);
            const oneHourLateDeductionDays = oneHourLate * 0.5;

            const fourthSatPenalty = Number(emp.fourth_saturday_deduction || 0) > 0 ? 2 : 0;

            const salaryCalc = Number(emp.salary_calculated_days || 0).toFixed(2);

            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            const container = document.getElementById('salaryCalcContainer');
            container.innerHTML = `<div style="text-align:center; padding:20px;"><div class="spinner"></div><p>Loading leave details...</p></div>`;

            // Fetch detailed leave records
            fetch(`get_leave_records.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => response.json())
                .then(data => {
                    let leaveDetailsHtml = '';
                    
                    if (data.status === 'success' && data.records && data.records.length > 0) {
                        leaveDetailsHtml = '<div style="background:#f0fdf4; border:1px solid #86efac; border-radius:4px; padding:8px; margin-bottom:8px;"><strong style="color:#166534;">Leave Details:</strong><ul style="margin:6px 0 0 20px; font-size:0.9rem; color:#166534;">';
                        
                        data.records.forEach(record => {
                            leaveDetailsHtml += `<li>${record.date_range} - ${record.leave_type}</li>`;
                        });
                        
                        leaveDetailsHtml += '</ul></div>';
                    } else {
                        leaveDetailsHtml = '<div style="background:#fef3c7; border:1px solid #fcd34d; border-radius:4px; padding:8px; margin-bottom:8px; font-size:0.9rem; color:#92400e;">No leave records for this period</div>';
                    }

                    container.innerHTML = `
                        <p style="font-weight:600; text-align:center;">${emp.name || ''} — Month: ${month} / ${year}</p>
                        <div style="background:white; padding:12px; border-radius:6px; margin-bottom:10px;">
                            <p style="margin:6px 0;"><strong>Base Salary:</strong> ₹${Number(baseSalary).toLocaleString('en-IN')}</p>
                            <p style="margin:6px 0;"><strong>Working Days:</strong> ${workingDays} &middot; <strong>Daily Salary:</strong> ₹${dailySalary.toFixed(2)}</p>
                        </div>

                        <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                            <h3 style="margin:6px 0;">Credits</h3>
                            <p style="margin:4px 0;"><strong>${presentDays} (present)</strong> ${casual ? `+ <strong>${casual} (casual)</strong>` : ''}</p>
                            ${leaveDetailsHtml}
                            <p style="margin:8px 0 0 0;"><strong>Total leave days (approved):</strong> ${leaveTaken}</p>
                        </div>

                        <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                            <h3 style="margin:6px 0;">Deductions</h3>
                            ${half ? `<p style="margin:4px 0;"><strong>Half-day deduction:</strong> ${half}×0.5 = ${(half*0.5).toFixed(2)} days</p>` : ''}
                            <p style="margin:4px 0;"><strong>Regular late deduction days:</strong> ${regularLateDeductionDays} (${regularLateDays} late days)</p>
                            <p style="margin:4px 0;"><strong>1+ hour late deduction days:</strong> ${oneHourLateDeductionDays} (${oneHourLate} 1+ hour late days)</p>
                            <p style="margin:4px 0;"><strong>4th Saturday penalty days:</strong> ${fourthSatPenalty}</p>
                        </div>

                        <div style="background:#f8f9fa; padding:12px; border-radius:6px;">
                            <p style="margin:6px 0;"><strong>Calculation:</strong></p>
                            <p style="margin:4px 0; font-weight:600;">[ Credits ] - [ Deductions ] = <span style="color:#2d3748;">${salaryCalc} days</span></p>
                            <p style="margin:4px 0; font-size:12px; color:#666;">Credits: ${presentDays} + ${casual} = ${(presentDays + casual).toFixed(2)}<br>Deductions: ${(half*0.5).toFixed(2)} (half-day) + ${regularLateDeductionDays} + ${oneHourLateDeductionDays} + ${fourthSatPenalty} = ${((half*0.5) + regularLateDeductionDays + oneHourLateDeductionDays + fourthSatPenalty).toFixed(2)}</p>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error fetching leave details:', error);
                    container.innerHTML = `
                        <p style="font-weight:600; text-align:center;">${emp.name || ''} — Month: ${month} / ${year}</p>
                        <div style="background:white; padding:12px; border-radius:6px; margin-bottom:10px;">
                            <p style="margin:6px 0;"><strong>Base Salary:</strong> ₹${Number(baseSalary).toLocaleString('en-IN')}</p>
                            <p style="margin:6px 0;"><strong>Working Days:</strong> ${workingDays} &middot; <strong>Daily Salary:</strong> ₹${dailySalary.toFixed(2)}</p>
                        </div>

                        <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                            <h3 style="margin:6px 0;">Credits</h3>
                            <p style="margin:4px 0;"><strong>${presentDays} (present)</strong> ${casual ? `+ <strong>${casual} (casual)</strong>` : ''}</p>
                            <p style="margin:4px 0;"><strong>Total leave days (approved):</strong> ${leaveTaken}</p>
                        </div>

                        <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                            <h3 style="margin:6px 0;">Deductions</h3>
                            ${half ? `<p style="margin:4px 0;"><strong>Half-day deduction:</strong> ${half}×0.5 = ${(half*0.5).toFixed(2)} days</p>` : ''}
                            <p style="margin:4px 0;"><strong>Regular late deduction days:</strong> ${regularLateDeductionDays} (${regularLateDays} late days)</p>
                            <p style="margin:4px 0;"><strong>1+ hour late deduction days:</strong> ${oneHourLateDeductionDays} (${oneHourLate} 1+ hour late days)</p>
                            <p style="margin:4px 0;"><strong>4th Saturday penalty days:</strong> ${fourthSatPenalty}</p>
                        </div>

                        <div style="background:#f8f9fa; padding:12px; border-radius:6px;">
                            <p style="margin:6px 0;"><strong>Calculation:</strong></p>
                            <p style="margin:4px 0; font-weight:600;">[ Credits ] - [ Deductions ] = <span style="color:#2d3748;">${salaryCalc} days</span></p>
                            <p style="margin:4px 0; font-size:12px; color:#666;">Credits: ${presentDays} + ${casual} = ${(presentDays + casual).toFixed(2)}<br>Deductions: ${(half*0.5).toFixed(2)} (half-day) + ${regularLateDeductionDays} + ${oneHourLateDeductionDays} + ${fourthSatPenalty} = ${((half*0.5) + regularLateDeductionDays + oneHourLateDeductionDays + fourthSatPenalty).toFixed(2)}</p>
                        </div>
                    `;
                });

            document.getElementById('salaryCalcModal').style.display = 'block';
        }

        function closeSalaryCalcModal() {
            document.getElementById('salaryCalcModal').style.display = 'none';
        }

        // Extend window.onclick to close present days modal when clicking outside
        const prevWindowOnclick = window.onclick;
        window.onclick = function(event) {
            try {
                const presentModal = document.getElementById('presentDaysModal');
                const lateDaysModal = document.getElementById('lateDaysModal');
                const oneHourLateDaysModal = document.getElementById('oneHourLateDaysModal');
                const leaveDetailsModal = document.getElementById('leaveDetailsModal');
                const leaveDeductionModal = document.getElementById('leaveDeductionModal');
                const overtimeDetailsModal = document.getElementById('overtimeDetailsModal');
                const editModal = document.getElementById('editSalaryModal');
                const workingDaysModal = document.getElementById('workingDaysModal');
                const penaltyModal = document.getElementById('penaltyModal');

                if (event.target === editModal) {
                    closeEditModal();
                }
                if (event.target === workingDaysModal) {
                    closeWorkingDaysModal();
                }
                if (event.target === presentModal) {
                    closePresentDaysModal();
                }
                if (event.target === lateDaysModal) {
                    closeLateDaysModal();
                }
                if (event.target === oneHourLateDaysModal) {
                    closeOneHourLateDaysModal();
                }
                if (event.target === leaveDetailsModal) {
                    closeLeaveDetailsModal();
                }
                if (event.target === leaveDeductionModal) {
                    closeLeaveDeductionModal();
                }
                if (event.target === overtimeDetailsModal) {
                    closeOvertimeDetailsModal();
                }
                if (event.target === penaltyModal) {
                    closePenaltyModal();
                }
            } catch (e) {
                // ignore
            }
            if (typeof prevWindowOnclick === 'function') prevWindowOnclick(event);
        }
    </script>
</body>
</html>
