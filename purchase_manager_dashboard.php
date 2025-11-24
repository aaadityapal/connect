<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user has Purchase Manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Purchase Manager') {
    header("Location: unauthorized.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 20px 30px;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 2em;
            font-weight: 400;
            color: #2a4365;
            margin-bottom: 5px;
            letter-spacing: -0.3px;
        }

        .header p {
            font-size: 0.9em;
            color: #718096;
            font-weight: 400;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-header h3 {
            font-size: 1em;
            color: #2a4365;
            font-weight: 600;
            margin: 0;
        }

        .toggle-filter-btn {
            background: #2a4365;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .toggle-filter-btn:hover {
            background: #1a365d;
            transform: translateY(-1px);
        }

        .toggle-filter-btn i {
            font-size: 0.8em;
            transition: transform 0.3s ease;
        }

        .toggle-filter-btn.active i {
            transform: rotate(180deg);
        }

        .filter-content {
            max-height: 500px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .filter-content.collapsed {
            max-height: 0;
            padding: 0;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.75em;
            color: #2a4365;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 0.9em;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #2a4365;
            box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        }

        .date-range-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .date-range-container .filter-group {
            margin: 0;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        .filter-btn {
            padding: 10px 18px;
            border: none;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-btn.apply {
            background: #2a4365;
            color: white;
        }

        .filter-btn.apply:hover {
            background: #1a365d;
        }

        .filter-btn.reset {
            background: #e2e8f0;
            color: #2a4365;
        }

        .filter-btn.reset:hover {
            background: #cbd5e0;
        }

        .records-section {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .records-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .records-header h3 {
            font-size: 1em;
            color: #2a4365;
            font-weight: 600;
            margin: 0;
        }

        .toggle-records-btn {
            background: #2a4365;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .toggle-records-btn:hover {
            background: #1a365d;
            transform: translateY(-1px);
        }

        .toggle-records-btn i {
            font-size: 0.8em;
            transition: transform 0.3s ease;
        }

        .toggle-records-btn.active i {
            transform: rotate(180deg);
        }

        .records-content {
            max-height: 500px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .records-content.collapsed {
            max-height: 0;
            padding: 0;
        }

        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }

        .record-btn {
            background: white;
            color: #2a4365;
            border: 2px solid #e2e8f0;
            padding: 15px 12px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-decoration: none;
        }

        .record-btn:hover {
            border-color: #2a4365;
            background-color: #f8f9fa;
            box-shadow: 0 2px 8px rgba(42, 67, 101, 0.1);
        }

        .record-btn i {
            font-size: 1.4em;
            opacity: 0.8;
        }

        .record-btn span {
            display: block;
            text-align: center;
        }

        /* Different colors for each button */
        #addVendorBtn {
            border-color: #3182ce;
        }

        #addVendorBtn:hover {
            border-color: #3182ce;
            color: #3182ce;
            background-color: #ebf8ff;
        }

        #addVendorBtn i {
            color: #3182ce;
        }

        #addLabourBtn {
            border-color: #d69e2e;
        }

        #addLabourBtn:hover {
            border-color: #d69e2e;
            color: #d69e2e;
            background-color: #fef5e7;
        }

        #addLabourBtn i {
            color: #d69e2e;
        }

        #addPaymentBtn {
            border-color: #38a169;
        }

        #addPaymentBtn:hover {
            border-color: #38a169;
            color: #38a169;
            background-color: #e6fffa;
        }

        #addPaymentBtn i {
            color: #38a169;
        }

        #viewReportBtn {
            border-color: #805ad5;
        }

        #viewReportBtn:hover {
            border-color: #805ad5;
            color: #805ad5;
            background-color: #faf5ff;
        }

        #viewReportBtn i {
            color: #805ad5;
        }

        .recent-records-section {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .recent-records-header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .recent-records-title-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-records-toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 4px;
            color: #4a5568;
            font-size: 0.9em;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .recent-records-toggle-btn:hover {
            background-color: #edf2f7;
            color: #2a4365;
        }

        .recent-records-toggle-btn i {
            transition: transform 0.3s ease;
        }

        .recent-records-toggle-btn.collapsed i {
            transform: rotate(-90deg);
        }

        .recent-records-header {
            font-size: 1em;
            color: #2a4365;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
        }

        .records-date-filter-minimal {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .mini-date-input {
            padding: 7px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 0.8em;
            font-family: inherit;
            width: 130px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .mini-date-input:focus {
            outline: none;
            border-color: #2a4365;
            box-shadow: 0 0 0 2px rgba(42, 67, 101, 0.08);
        }

        .mini-filter-btn {
            padding: 7px 8px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            background: white;
            color: #2a4365;
            font-size: 0.8em;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            min-width: unset;
        }

        .mini-filter-btn:hover {
            border-color: #2a4365;
        }

        .mini-filter-btn.apply:hover {
            background: #e6f2ff;
            color: #2a4365;
        }

        .mini-filter-btn.reset:hover {
            background: #fff5f5;
            color: #e53e3e;
        }

        .tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 15px;
            transition: max-height 0.3s ease, opacity 0.3s ease;
        }

        .tabs-container.collapsed {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            margin-bottom: 0;
        }

        .tab-content.collapsed,
        [id$="Container"].collapsed {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            margin-top: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 16px;
            font-size: 0.8em;
            font-weight: 600;
            color: #a0aec0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tab-btn:hover {
            color: #2a4365;
        }

        .tab-btn.active {
            color: #2a4365;
            border-bottom-color: #2a4365;
        }

        .tab-btn i {
            display: inline-block;
            margin-right: 6px;
            font-size: 0.8em;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .empty-state {
            text-align: center;
            padding: 20px 15px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 2em;
            color: #cbd5e0;
            margin-bottom: 10px;
            display: block;
        }

        .empty-state p {
            font-size: 0.85em;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table thead {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .records-table th {
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: #2a4365;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .records-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 0.85em;
        }

        .records-table tbody tr:hover {
            background-color: #f7fafc;
        }

        .vendor-table-wrapper {
            overflow-x: auto;
        }

        .vendor-row {
            display: grid;
            grid-template-columns: 1.2fr 1.3fr 0.8fr 0.9fr 0.9fr 0.8fr 0.8fr 0.7fr 0.7fr;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            align-items: start;
            font-size: 0.85em;
            transition: background-color 0.2s ease;
        }

        .vendor-row:hover {
            background-color: #f9fafb;
        }

        .vendor-row-header {
            display: grid;
            grid-template-columns: 1.2fr 1.3fr 0.8fr 0.9fr 0.9fr 0.8fr 0.8fr 0.7fr 0.7fr;
            gap: 12px;
            padding: 12px;
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #2a4365;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vendor-row-header > div:last-child {
            text-align: center;
        }

        .vendor-cell {
            word-break: break-word;
            font-weight: 600;
        }

        .vendor-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: capitalize;
        }

        .vendor-status.active {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .vendor-status.inactive {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .vendor-status.suspended {
            background-color: #feebc8;
            color: #7c2d12;
        }

        .vendor-status.archived {
            background-color: #cbd5e0;
            color: #2d3748;
        }

        /* Payment Entry Status Badges */
        .vendor-status.draft {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .vendor-status.submitted {
            background-color: #fef3c7;
            color: #92400e;
        }

        .vendor-status.pending {
            background-color: #dbeafe;
            color: #0c4a6e;
        }

        .vendor-status.approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .vendor-status.rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .vendor-actions {
            display: flex;
            gap: 6px;
            justify-content: center;
        }

        .vendor-actions button {
            background: none;
            border: none;
            color: #2a4365;
            cursor: pointer;
            font-size: 0.9em;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 4px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
        }

        .vendor-actions button:hover {
            transform: scale(1.1);
        }

        .vendor-actions .view-btn {
            color: #3182ce;
        }

        .vendor-actions .view-btn:hover {
            background-color: #ebf8ff;
        }

        .vendor-actions .edit-btn {
            color: #d69e2e;
        }

        .vendor-actions .edit-btn:hover {
            background-color: #fef5e7;
        }

        .vendor-actions .delete-btn {
            color: #e53e3e;
        }

        .vendor-actions .delete-btn:hover {
            background-color: #fff5f5;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
        }

        .loading-spinner i {
            font-size: 2em;
            color: #2a4365;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .pagination-info {
            color: #718096;
            font-size: 0.8em;
            margin-right: 10px;
        }

        .pagination-btn {
            background: white;
            border: 1px solid #e2e8f0;
            color: #2a4365;
            padding: 6px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.2s ease;
            min-width: 32px;
            text-align: center;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
            font-weight: 700;
        }

        .content-section {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .content-section h3 {
            font-size: 1.1em;
            color: #2a4365;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .quick-action-btn {
            padding: 14px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #2a4365;
            font-size: 0.8em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .quick-action-btn i {
            font-size: 1.4em;
            color: #2a4365;
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            border-color: #2a4365;
            background: #f8f9fa;
            box-shadow: 0 4px 12px rgba(42, 67, 101, 0.15);
            transform: translateY(-1px);
        }

        .quick-action-btn:hover i {
            transform: scale(1.1);
            color: #1a365d;
        }

        .quick-action-btn:active {
            transform: translateY(0);
        }

        .action-list {
            list-style: none;
        }

        .action-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            transition: padding-left 0.2s ease;
            font-size: 0.9em;
        }

        .action-list li:last-child {
            border-bottom: none;
        }

        .action-list li:hover {
            padding-left: 8px;
        }

        .action-list i {
            color: #2a4365;
            margin-right: 12px;
            width: 18px;
            text-align: center;
        }

        .action-list a {
            color: #2a4365;
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.2s ease;
        }

        .action-list a:hover {
            color: #1a365d;
            text-decoration: underline;
        }

        .project-filter-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .project-filter-btn {
            background: none;
            border: none;
            color: #2a4365;
            cursor: pointer;
            font-size: 0.9em;
            padding: 0;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
        }

        .project-filter-btn:hover {
            color: #1a365d;
        }

        .project-filter-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            min-width: 200px;
            display: none;
            margin-top: 8px;
        }

        .project-filter-dropdown.active {
            display: block;
        }

        .filter-option {
            padding: 10px 15px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: 0.9em;
            color: #2a4365;
        }

        .filter-option:last-child {
            border-bottom: none;
        }

        .filter-option:hover {
            background-color: #f7fafc;
        }

        .filter-option.active {
            background-color: #ebf8ff;
            color: #3182ce;
            font-weight: 600;
        }

        .filter-option input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
        }

        /* Excel-style Filter Dropdown */
        .excel-filter-dropdown {
            width: 280px !important;
            min-width: 280px !important;
        }

        .excel-filter-header {
            padding: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .excel-filter-search {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 0.85em;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .excel-filter-search:focus {
            outline: none;
            border-color: #2a4365;
            box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        }

        .excel-filter-actions {
            display: flex;
            gap: 6px;
        }

        .excel-filter-apply-btn,
        .excel-filter-clear-btn {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
        }

        .excel-filter-apply-btn {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
        }

        .excel-filter-apply-btn:hover {
            background: #1a365d;
            border-color: #1a365d;
        }

        .excel-filter-clear-btn {
            background: white;
            color: #e53e3e;
            border-color: #e53e3e;
        }

        .excel-filter-clear-btn:hover {
            background: #fff5f5;
        }

        .excel-filter-list {
            max-height: 350px;
            overflow-y: auto;
            padding: 8px 0;
        }

        .excel-filter-list .filter-option {
            padding: 10px 15px;
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .excel-filter-list .filter-option:hover {
            background-color: #f7fafc;
        }

        .excel-filter-list .filter-option.active {
            background-color: #ebf8ff;
        }

        .excel-filter-list .filter-option input[type="checkbox"] {
            margin-right: 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .paid-to-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .paid-to-item {
            display: inline-block;
            padding: 3px 8px;
            background-color: #f0f4f8;
            border-radius: 3px;
            font-size: 0.8em;
            color: #2a4365;
            border-left: 2px solid #2a4365;
        }

        .paid-to-item.vendor {
            border-left-color: #3182ce;
        }

        .paid-to-item.labour {
            border-left-color: #d69e2e;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .welcome-section,
            .content-section {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .recent-records-header-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .records-date-filter-minimal {
                width: 100%;
                gap: 6px;
            }

            .mini-date-input {
                flex: 1;
                min-width: 100px;
            }

            .mini-filter-btn {
                flex-shrink: 0;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 10px;
            }

            .quick-action-btn {
                padding: 15px 10px;
                font-size: 0.8em;
            }

            .quick-action-btn i {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include the side panel -->
        <?php include 'includes/manager_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Expense Tracker</h1>
                <p>Monitor and manage all expenses</p>
            </div>

            <!-- Filter Section (Header + Content Combined) -->
            <div class="filter-section" id="filterSection">
                <div class="filter-header">
                    <h3>Filters</h3>
                    <button class="toggle-filter-btn" id="toggleFilterBtn">
                        <span>Filter</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="filter-content" id="filterContent">
                    <div class="filter-grid">
                        <!-- Date Range Filter -->
                        <div class="date-range-container">
                            <div class="filter-group">
                                <label for="dateFrom">From Date</label>
                                <input type="date" id="dateFrom" name="dateFrom">
                            </div>
                            <div class="filter-group">
                                <label for="dateTo">To Date</label>
                                <input type="date" id="dateTo" name="dateTo">
                            </div>
                        </div>

                        <!-- Payment Type Filter -->
                        <div class="filter-group">
                            <label for="paymentType">Payment Type</label>
                            <select id="paymentType" name="paymentType">
                                <option value="">Select Payment Type</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Select Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="filter-actions">
                        <button class="filter-btn apply" id="applyFilterBtn">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <button class="filter-btn reset" id="resetFilterBtn">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Records Section -->
            <div class="records-section" id="recordsSection">
                <div class="records-header">
                    <h3>Add Records</h3>
                    <button class="toggle-records-btn" id="toggleRecordsBtn">
                        <span>Records</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="records-content" id="recordsContent">
                    <div class="records-grid">
                        <button class="record-btn" id="addVendorBtn">
                            <i class="fas fa-user-tie"></i>
                            <span>Add Vendor</span>
                        </button>
                        <button class="record-btn" id="addLabourBtn">
                            <i class="fas fa-hard-hat"></i>
                            <span>Add Labour</span>
                        </button>
                        <button class="record-btn" id="addPaymentBtn">
                            <i class="fas fa-credit-card"></i>
                            <span>Add Payment Entry</span>
                        </button>
                        <button class="record-btn" id="viewReportBtn">
                            <i class="fas fa-chart-bar"></i>
                            <span>View Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recently Added Records Section -->
            <div class="recent-records-section">
                <div class="recent-records-header-container">
                    <div class="recent-records-title-wrapper">
                        <button class="recent-records-toggle-btn" id="recentRecordsToggleBtn" title="Collapse/Expand Records">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <h3 class="recent-records-header">Recently Added Records</h3>
                    </div>
                    
                    <!-- Minimalist Date Range Filter -->
                    <div class="records-date-filter-minimal">
                        <input type="date" id="recordsDateFrom" name="recordsDateFrom" class="mini-date-input" placeholder="From">
                        <input type="date" id="recordsDateTo" name="recordsDateTo" class="mini-date-input" placeholder="To">
                        <button class="mini-filter-btn apply" id="applyRecordsFilterBtn" title="Apply Filter">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="mini-filter-btn reset" id="resetRecordsFilterBtn" title="Reset Filter">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Tabs Navigation - Only Recent Entries -->
                <div class="tabs-container">
                    <button class="tab-btn active" data-tab="entries-tab">
                        <i class="fas fa-receipt"></i>Recent Entries
                    </button>
                </div>

                <!-- Tab Contents - Only Recent Entries -->
                <div class="tab-content active" id="entries-tab">
                    <div id="entriesContainer">
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>Loading payment entries...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" style="margin-top: 40px;">
                <h3>Quick Actions</h3>
                
                <!-- Quick Action Buttons -->
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="scrollToTab('vendors-tab')" title="View all vendors in the system">
                        <i class="fas fa-users"></i>
                        <span>View Vendors</span>
                    </button>
                    <button class="quick-action-btn" onclick="scrollToTab('labours-tab')" title="View all labour records">
                        <i class="fas fa-hard-hat"></i>
                        <span>View Labours</span>
                    </button>
                    <button class="quick-action-btn" onclick="scrollToTab('entries-tab')" title="View recent payment entries">
                        <i class="fas fa-receipt"></i>
                        <span>View Payment Entries</span>
                    </button>
                    <button class="quick-action-btn" onclick="alert('Budget Overview feature coming soon')" title="View budget and spending analytics">
                        <i class="fas fa-chart-pie"></i>
                        <span>Budget Overview</span>
                    </button>
                </div>
            </div>

            <!-- Vendors, Labours and Reports Section -->
            <div class="recent-records-section" style="margin-top: 40px;">
                <div class="recent-records-header-container">
                    <h3 class="recent-records-header">Management</h3>
                </div>

                <!-- Tabs Navigation -->
                <div class="tabs-container">
                    <button class="tab-btn" data-tab="vendors-tab">
                        <i class="fas fa-user-tie"></i>Vendors
                    </button>
                    <button class="tab-btn" data-tab="labours-tab">
                        <i class="fas fa-hard-hat"></i>Labours
                    </button>
                    <button class="tab-btn" data-tab="reports-tab">
                        <i class="fas fa-chart-bar"></i>Reports
                    </button>
                </div>

                <!-- Tab Contents -->
                <div class="tab-content" id="vendors-tab">
                    <div id="vendorsContainer">
                        <div class="empty-state">
                            <i class="fas fa-user-tie"></i>
                            <p>Click on the Vendors tab to load data</p>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="labours-tab">
                    <div id="laboursContainer">
                        <div class="empty-state">
                            <i class="fas fa-hard-hat"></i>
                            <p>Loading labours...</p>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="reports-tab">
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No reports available yet. Click "View Report" to generate one.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Vendor Details Modal -->
    <?php include 'modals/vendor_details_modal.php'; ?>

    <!-- Include Vendor Edit Modal -->
    <?php include 'modals/vendor_edit_modal.php'; ?>

    <!-- Include Labour Details Modal -->
    <?php include 'modals/labour_details_modal.php'; ?>

    <!-- Include Labour Edit Modal -->
    <?php include 'modals/labour_edit_modal.php'; ?>

    <!-- Include Add Vendor Modal -->
    <?php include 'modals/add_vendor_modal.php'; ?>

    <!-- Include Add Labour Modal -->
    <?php include 'modals/add_labour_modal.php'; ?>

    <!-- Include Add Payment Entry Modal -->
    <?php include 'modals/add_payment_entry_modal.php'; ?>

    <!-- Include Payment Entry Details Modal -->
    <?php include 'modals/payment_entry_details_modal.php'; ?>

    <!-- Include Payment Entry Files Registry Modal -->
    <?php include 'modals/payment_entry_files_registry_modal.php'; ?>

    <!-- Include Recipient Files Modal -->
    <?php include 'modals/recipient_files_modal.php'; ?>

    <script>
        // Vendor action functions
        function viewVendor(vendorId) {
            console.log('Viewing vendor:', vendorId);
            // Open vendor details modal
            openVendorDetailsModal(vendorId);
        }

        function editVendor(vendorId) {
            console.log('Editing vendor:', vendorId);
            // Redirect to vendor edit page
            window.location.href = `edit_vendor.php?id=${vendorId}`;
        }

        function deleteVendor(vendorId) {
            if (confirm('Are you sure you want to delete this vendor? This action cannot be undone.')) {
                console.log('Deleting vendor:', vendorId);
                fetch(`delete_vendor.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ vendor_id: vendorId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Vendor deleted successfully');
                        loadVendors(10, 0, '', ''); // Reload vendors
                    } else {
                        alert('Error deleting vendor: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting vendor');
                });
            }
        }

        // Labour action functions
        function viewLabour(labourId) {
            console.log('Viewing labour:', labourId);
            // Open labour details modal
            openLabourDetailsModal(labourId);
        }

        function editLabour(labourId) {
            console.log('Editing labour:', labourId);
            // Redirect to labour edit page
            window.location.href = `edit_labour.php?id=${labourId}`;
        }

        function deleteLabour(labourId) {
            if (confirm('Are you sure you want to delete this labour record? This action cannot be undone.')) {
                console.log('Deleting labour:', labourId);
                fetch(`delete_labour.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ labour_id: labourId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Labour record deleted successfully');
                        loadLabours(10, 1, '', ''); // Reload labours
                    } else {
                        alert('Error deleting labour record: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting labour record');
                });
            }
        }

        // Global state for pagination
        let vendorPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: ''
        };

        // Global state for labour pagination
        let labourPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: ''
        };

        // Global state for payment entries pagination
        let entriesPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: '',
            dateFrom: '',
            dateTo: '',
            projectType: '',
            vendorCategory: '',
            paidBy: ''
        };

        // Function to fetch and display vendors
        function loadVendors(limit = 10, page = 1, search = '', status = '', dateFrom = '', dateTo = '') {
            vendorPaginationState.limit = limit;
            vendorPaginationState.currentPage = page;
            vendorPaginationState.search = search;
            vendorPaginationState.status = status;

            const offset = (page - 1) * limit;
            const vendorsContainer = document.getElementById('vendorsContainer');
            
            // Show loading state
            vendorsContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading vendors...</p>
                </div>
            `;

            // Build query parameters
            const params = new URLSearchParams({
                limit: limit,
                offset: offset,
                search: search,
                status: status,
                dateFrom: dateFrom,
                dateTo: dateTo
            });

            // Fetch vendors from API
            fetch(`get_vendors.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '<div class="vendor-table-wrapper">';
                        html += '<div class="vendor-row-header">';
                        html += '<div>Vendor Code</div>';
                        html += '<div>Name</div>';
                        html += '<div>Email</div>';
                        html += '<div>Phone</div>';
                        html += '<div>Type</div>';
                        html += '<div>Status</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        data.data.forEach(vendor => {
                            const statusClass = vendor.vendor_status.toLowerCase();
                            html += '<div class="vendor-row">';
                            html += `<div class="vendor-cell">${vendor.vendor_unique_code}</div>`;
                            html += `<div class="vendor-cell">${vendor.vendor_full_name}</div>`;
                            html += `<div class="vendor-cell"><small>${vendor.vendor_email_address}</small></div>`;
                            html += `<div class="vendor-cell">${vendor.vendor_phone_primary}</div>`;
                            html += `<div class="vendor-cell"><small>${vendor.vendor_type_category}</small></div>`;
                            html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${vendor.vendor_status}</span></div>`;
                            html += '<div class="vendor-actions">';
                            html += `<button class="view-btn" title="View Details" onclick="viewVendor(${vendor.vendor_id})"><i class="fas fa-eye"></i></button>`;
                            html += `<button class="edit-btn" title="Edit" onclick="editVendor(${vendor.vendor_id})"><i class="fas fa-edit"></i></button>`;
                            html += `<button class="delete-btn" title="Delete" onclick="deleteVendor(${vendor.vendor_id})"><i class="fas fa-trash"></i></button>`;
                            html += '</div>';
                            html += '</div>';
                        });

                        html += '</div>';

                        // Add pagination
                        if (data.pagination.totalPages > 1) {
                            html += '<div class="pagination-container">';
                            html += `<div class="pagination-info">Page ${data.pagination.currentPage} of ${data.pagination.totalPages} (Total: ${data.pagination.total} vendors)</div>`;
                            
                            // Previous button
                            html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} onclick="loadVendors(10, ${page > 1 ? page - 1 : 1})">
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>`;

                            // Page numbers
                            let startPage = Math.max(1, page - 2);
                            let endPage = Math.min(data.pagination.totalPages, page + 2);

                            if (startPage > 1) {
                                html += `<button class="pagination-btn" onclick="loadVendors(10, 1)">1</button>`;
                                if (startPage > 2) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadVendors(10, ${i})">${i}</button>`;
                            }

                            if (endPage < data.pagination.totalPages) {
                                if (endPage < data.pagination.totalPages - 1) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                                html += `<button class="pagination-btn" onclick="loadVendors(10, ${data.pagination.totalPages})">${data.pagination.totalPages}</button>`;
                            }

                            // Next button
                            html += `<button class="pagination-btn" ${page === data.pagination.totalPages ? 'disabled' : ''} onclick="loadVendors(10, ${page < data.pagination.totalPages ? page + 1 : page})">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>`;

                            html += '</div>';
                        }

                        vendorsContainer.innerHTML = html;
                        vendorPaginationState.totalPages = data.pagination.totalPages;
                    } else if (data.success) {
                        vendorsContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-user-tie"></i>
                                <p>No vendors added yet. Click "Add Vendor" to get started.</p>
                            </div>
                        `;
                    } else {
                        vendorsContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading vendors. Please try again.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading vendors:', error);
                    vendorsContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading vendors. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Function to fetch and display labours
        function loadLabours(limit = 10, page = 1, search = '', status = '', dateFrom = '', dateTo = '') {
            labourPaginationState.limit = limit;
            labourPaginationState.currentPage = page;
            labourPaginationState.search = search;
            labourPaginationState.status = status;

            const offset = (page - 1) * limit;
            const laboursContainer = document.getElementById('laboursContainer');
            
            // Show loading state
            laboursContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading labours...</p>
                </div>
            `;

            // Build query parameters
            const params = new URLSearchParams({
                limit: limit,
                offset: offset,
                search: search,
                status: status,
                dateFrom: dateFrom,
                dateTo: dateTo
            });

            // Fetch labours from API
            fetch(`get_labours.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '<div class="vendor-table-wrapper">';
                        html += '<div class="vendor-row-header">';
                        html += '<div>Labour Code</div>';
                        html += '<div>Name</div>';
                        html += '<div>Contact</div>';
                        html += '<div>Labour Type</div>';
                        html += '<div>Salary/Day</div>';
                        html += '<div>Status</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        data.data.forEach(labour => {
                            const statusClass = labour.status.toLowerCase();
                            const salary = labour.daily_salary ? '₹' + parseFloat(labour.daily_salary).toFixed(2) : 'N/A';
                            html += '<div class="vendor-row">';
                            html += `<div class="vendor-cell">${labour.labour_unique_code}</div>`;
                            html += `<div class="vendor-cell">${labour.full_name}</div>`;
                            html += `<div class="vendor-cell"><small>${labour.contact_number}</small></div>`;
                            html += `<div class="vendor-cell"><small>${labour.labour_type}</small></div>`;
                            html += `<div class="vendor-cell">${salary}</div>`;
                            html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${labour.status}</span></div>`;
                            html += '<div class="vendor-actions">';
                            html += `<button class="view-btn" title="View Details" onclick="viewLabour(${labour.id})"><i class="fas fa-eye"></i></button>`;
                            html += `<button class="edit-btn" title="Edit" onclick="editLabour(${labour.id})"><i class="fas fa-edit"></i></button>`;
                            html += `<button class="delete-btn" title="Delete" onclick="deleteLabour(${labour.id})"><i class="fas fa-trash"></i></button>`;
                            html += '</div>';
                            html += '</div>';
                        });

                        html += '</div>';

                        // Add pagination
                        if (data.pagination.totalPages > 1) {
                            html += '<div class="pagination-container">';
                            html += `<div class="pagination-info">Page ${data.pagination.currentPage} of ${data.pagination.totalPages} (Total: ${data.pagination.total} labours)</div>`;
                            
                            // Previous button
                            html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} onclick="loadLabours(10, ${page > 1 ? page - 1 : 1})">
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>`;

                            // Page numbers
                            let startPage = Math.max(1, page - 2);
                            let endPage = Math.min(data.pagination.totalPages, page + 2);

                            if (startPage > 1) {
                                html += `<button class="pagination-btn" onclick="loadLabours(10, 1)">1</button>`;
                                if (startPage > 2) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadLabours(10, ${i})">${i}</button>`;
                            }

                            if (endPage < data.pagination.totalPages) {
                                if (endPage < data.pagination.totalPages - 1) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                                html += `<button class="pagination-btn" onclick="loadLabours(10, ${data.pagination.totalPages})">${data.pagination.totalPages}</button>`;
                            }

                            // Next button
                            html += `<button class="pagination-btn" ${page === data.pagination.totalPages ? 'disabled' : ''} onclick="loadLabours(10, ${page < data.pagination.totalPages ? page + 1 : page})">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>`;

                            html += '</div>';
                        }

                        laboursContainer.innerHTML = html;
                        labourPaginationState.totalPages = data.pagination.totalPages;
                    } else if (data.success) {
                        laboursContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-hard-hat"></i>
                                <p>No labour records added yet. Click "Add Labour" to get started.</p>
                            </div>
                        `;
                    } else {
                        laboursContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading labour records. Please try again.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading labours:', error);
                    laboursContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading labour records. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Function to fetch and display payment entries
        function loadPaymentEntries(limit = 10, page = 1, search = '', status = '', dateFrom = '', dateTo = '', projectType = '', vendorCategory = '', paidBy = '') {
            entriesPaginationState.limit = limit;
            entriesPaginationState.currentPage = page;
            entriesPaginationState.search = search;
            entriesPaginationState.status = status;
            entriesPaginationState.dateFrom = dateFrom;
            entriesPaginationState.dateTo = dateTo;
            entriesPaginationState.projectType = projectType;
            entriesPaginationState.vendorCategory = vendorCategory;
            entriesPaginationState.paidBy = paidBy;

            const offset = (page - 1) * limit;
            const entriesContainer = document.getElementById('entriesContainer');
            
            // Show loading state
            entriesContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading payment entries...</p>
                </div>
            `;

            // Build query parameters
            const params = new URLSearchParams({
                limit: limit,
                offset: offset,
                search: search,
                status: status,
                dateFrom: dateFrom,
                dateTo: dateTo,
                projectType: projectType,
                vendorCategory: vendorCategory,
                paidBy: paidBy
            });

            // Fetch payment entries from API
            fetch(`get_payment_entries.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        let html = '<div class="vendor-table-wrapper">';
                        html += '<div class="vendor-row-header">';
                        html += '<div class="project-filter-container"><span>Project Name</span><button class="project-filter-btn" id="projectFilterToggle" title="Filter by Project Type"><i class="fas fa-filter"></i></button><div class="project-filter-dropdown excel-filter-dropdown" id="projectFilterDropdown"><div class="excel-filter-header"><input type="text" class="excel-filter-search" placeholder="Search..." id="projectFilterSearch"><div class="excel-filter-actions"><button class="excel-filter-apply-btn" id="projectFilterApply">Apply</button><button class="excel-filter-clear-btn" id="projectFilterClear">Clear</button></div></div><div class="excel-filter-list"><div class="filter-option" data-type="">All Projects</div><div class="filter-option" data-type="Architecture"><input type="checkbox"> Architecture</div><div class="filter-option" data-type="Interior"><input type="checkbox"> Interior</div><div class="filter-option" data-type="Construction"><input type="checkbox"> Construction</div></div></div></div>';
                        html += '<div class="project-filter-container"><span>Paid To</span><button class="project-filter-btn" id="vendorCategoryFilterToggle" title="Filter by Vendor Category"><i class="fas fa-filter"></i></button><div class="project-filter-dropdown excel-filter-dropdown" id="vendorCategoryFilterDropdown"><div class="excel-filter-header"><input type="text" class="excel-filter-search" placeholder="Search..." id="vendorCategoryFilterSearch"><div class="excel-filter-actions"><button class="excel-filter-apply-btn" id="vendorCategoryFilterApply">Apply</button><button class="excel-filter-clear-btn" id="vendorCategoryFilterClear">Clear</button></div></div><div class="excel-filter-list"><div class="filter-option" data-vendor-category="">All Categories</div></div></div></div>';
                        html += '<div class="project-filter-container"><span>Paid By</span><button class="project-filter-btn" id="paidByFilterToggle" title="Filter by User"><i class="fas fa-filter"></i></button><div class="project-filter-dropdown excel-filter-dropdown" id="paidByFilterDropdown"><div class="excel-filter-header"><input type="text" class="excel-filter-search" placeholder="Search..." id="paidByFilterSearch"><div class="excel-filter-actions"><button class="excel-filter-apply-btn" id="paidByFilterApply">Apply</button><button class="excel-filter-clear-btn" id="paidByFilterClear">Clear</button></div></div><div class="excel-filter-list"><div class="filter-option" data-paid-by="">All Users</div></div></div></div>';
                        html += '<div>Payment Date</div>';
                        html += '<div>Grand Total</div>';
                        html += '<div>Status</div>';
                        html += '<div>Payment Mode</div>';
                        html += '<div>Files</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        data.data.forEach(entry => {
                            const statusClass = entry.status.toLowerCase();
                            const grandTotal = '₹' + parseFloat(entry.grand_total).toFixed(2);
                            const paymentDate = entry.payment_date ? new Date(entry.payment_date).toLocaleDateString('en-GB', {year: 'numeric', month: '2-digit', day: '2-digit'}) : 'N/A';
                            
                            // Build Paid To list
                            let paidToHtml = '<div class="paid-to-list">';
                            if (entry.paid_to && entry.paid_to.length > 0) {
                                entry.paid_to.forEach(recipient => {
                                    const categoryBracket = recipient.vendor_category ? ` [${recipient.vendor_category}]` : '';
                                    paidToHtml += `<div class="paid-to-item ${recipient.type}">${recipient.type === 'vendor' ? '👤' : '👷'} ${recipient.name}${categoryBracket}</div>`;
                                });
                            } else {
                                paidToHtml += '<div class="paid-to-item" style="border-left-color: #a0aec0;">No data</div>';
                            }
                            paidToHtml += '</div>';
                            
                            html += '<div class="vendor-row">';
                            html += `<div class="vendor-cell">${entry.project_name}</div>`;
                            html += `<div class="vendor-cell">${paidToHtml}</div>`;
                            html += `<div class="vendor-cell"><small style="background: #e0e7ff; padding: 4px 8px; border-radius: 4px; display: inline-block; color: #3730a3; font-weight: 600;">${entry.authorized_by || 'N/A'}</small></div>`;
                            html += `<div class="vendor-cell"><small>${paymentDate}</small></div>`;
                            html += `<div class="vendor-cell" style="font-weight: 700; color: #38a169; font-size: 0.95em;">${grandTotal}</div>`;
                            html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${entry.status.toUpperCase()}</span></div>`;
                            html += `<div class="vendor-cell"><small style="background: #f0f4f8; padding: 4px 8px; border-radius: 4px; display: inline-block;">${entry.payment_mode.replace(/_/g, ' ').toUpperCase()}</small></div>`;
                            html += `<div class="vendor-cell"><span style="background: #edf2f7; color: #2a4365; padding: 6px 10px; border-radius: 4px; font-size: 0.85em; font-weight: 600; cursor: pointer;" onclick="openPaymentFilesModal(${entry.payment_entry_id})"><i class="fas fa-file"></i> ${entry.files_attached}</span></div>`;
                            html += '<div class="vendor-actions">';
                            html += `<button class="expand-btn" title="Expand Details" onclick="togglePaymentEntryExpand(${entry.payment_entry_id})" style="background: none; border: none; color: #718096; cursor: pointer; padding: 8px; font-size: 1.1em; transition: all 0.3s;"><i class="fas fa-chevron-down"></i></button>`;
                            html += `<button class="view-btn" title="View Details" onclick="viewPaymentEntry(${entry.payment_entry_id})" style="background: #ebf8ff; color: #3182ce; padding: 8px 12px; border-radius: 6px;"><i class="fas fa-eye"></i></button>`;
                            html += `<button class="edit-btn" title="Edit" onclick="editPaymentEntry(${entry.payment_entry_id})" style="background: #fef5e7; color: #d69e2e; padding: 8px 12px; border-radius: 6px;"><i class="fas fa-edit"></i></button>`;
                            html += `<button class="delete-btn" title="Delete" onclick="deletePaymentEntry(${entry.payment_entry_id})" style="background: #fff5f5; color: #e53e3e; padding: 8px 12px; border-radius: 6px;"><i class="fas fa-trash"></i></button>`;
                            html += '</div>';
                            html += '</div>';
                            
                            // Add expandable details row - Minimalistic Design
                            html += `<div class="entry-details-container" id="entry-details-${entry.payment_entry_id}" style="display: grid; grid-column: 1 / -1; background: #f9fafb; border: 3px solid #2a4365; border-radius: 8px; padding: 20px; margin: 8px 0; box-shadow: 0 2px 8px rgba(42, 67, 101, 0.1);">`;
                            
                            // Top row with main details
                            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px;">';
                            
                            // Project Name
                            html += '<div style="border-left: 3px solid #3182ce; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">PROJECT NAME</div>`;
                            html += `<div style="font-weight: 700; color: #1a365d; font-size: 0.9em;">${entry.project_name || 'N/A'}</div>`;
                            html += '</div>';
                            
                            // Project Type
                            html += '<div style="border-left: 3px solid #38a169; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">PROJECT TYPE</div>`;
                            html += `<div style="font-weight: 700; color: #276749; font-size: 0.9em; text-transform: capitalize;">${entry.project_type || 'N/A'}</div>`;
                            html += '</div>';
                            
                            // Main Amount
                            html += '<div style="border-left: 3px solid #d69e2e; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">MAIN AMOUNT</div>`;
                            html += `<div style="font-weight: 700; color: #7c2d12; font-size: 0.9em;">₹${parseFloat(entry.grand_total || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>`;
                            html += '</div>';
                            
                            // Payment Date
                            html += '<div style="border-left: 3px solid #d53f8c; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">PAYMENT DATE</div>`;
                            html += `<div style="font-weight: 700; color: #6b2142; font-size: 0.9em;">${entry.payment_date ? new Date(entry.payment_date).toLocaleDateString('en-GB', {year: 'numeric', month: '2-digit', day: '2-digit'}) : 'N/A'}</div>`;
                            html += '</div>';
                            
                            // Status
                            html += '<div style="border-left: 3px solid #9333ea; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">STATUS</div>`;
                            html += `<div><span class="vendor-status ${statusClass}" style="display: inline-block;">${entry.status.toUpperCase()}</span></div>`;
                            html += '</div>';
                            
                            html += '</div>';
                            
                            // Paid To section with small items
                            if (entry.paid_to && entry.paid_to.length > 0) {
                                entry.paid_to.forEach((recipient, index) => {
                                    html += '<div style="display: grid; grid-template-columns: 1fr 0.8fr 1.2fr 0.9fr 0.8fr 0.9fr 0.7fr; gap: 12px; margin-bottom: 12px; align-items: center; background: white; padding: 12px; border-radius: 6px; border: 2px solid #e2e8f0; transition: all 0.2s ease;">';
                                    
                                    // Paid To Name
                                    html += '<div style="border-left: 4px solid #6b5b95; padding: 10px 12px; background: #f9fafb; border-radius: 4px;">';
                                    html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">PAID TO</div>`;
                                    html += `<div style="font-weight: 700; color: #2d1b3d; font-size: 0.9em;">${recipient.name || 'N/A'}</div>`;
                                    html += '</div>';
                                    
                                    // Type
                                    html += '<div style="border-left: 4px solid #0284c7; padding: 10px 12px; background: #f9fafb; border-radius: 4px;">';
                                    html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">TYPE</div>`;
                                    html += `<div style="font-weight: 700; color: #0a4a6f; font-size: 0.9em; text-transform: capitalize;">${recipient.type || 'N/A'}</div>`;
                                    html += '</div>';
                                    
                                    // Amount
                                    html += '<div style="border-left: 4px solid #16a34a; padding: 10px 12px; background: #f9fafb; border-radius: 4px;">';
                                    html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">AMOUNT PAID</div>`;
                                    html += `<div style="font-weight: 700; color: #15803d; font-size: 0.95em;">₹${parseFloat(recipient.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>`;
                                    html += '</div>';
                                    
                                    // Category
                                    html += '<div style="border-left: 4px solid #d53f8c; padding: 10px 12px; background: #f9fafb; border-radius: 4px;">';
                                    html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">CATEGORY</div>`;
                                    html += `<div style="font-weight: 700; color: #6b2142; font-size: 0.9em; text-transform: capitalize;">${recipient.category || recipient.vendor_category || 'N/A'}</div>`;
                                    html += '</div>';
                                    
                                    // Payment Mode - Show acceptance methods if multiple, otherwise main mode
                                    const paymentModes = (recipient.acceptance_methods && recipient.acceptance_methods.length > 0) 
                                        ? recipient.acceptance_methods.join(', ') 
                                        : (entry.payment_mode ? entry.payment_mode.replace(/_/g, ' ') : 'N/A');
                                    
                                    html += '<div style="border-left: 4px solid #ea580c; padding: 10px 12px; background: #f9fafb; border-radius: 4px;">';
                                    html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">PAYMENT MODE</div>`;
                                    html += `<div style="font-weight: 700; color: #7c2d12; font-size: 0.9em; text-transform: capitalize;">${paymentModes}</div>`;
                                    html += '</div>';
                                    
                                    // Paid By User
                                    html += '<div style="border-left: 4px solid #7c3aed; padding: 10px 12px; background: #f9fafb; border-radius: 4px;">';
                                    html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">PAID BY</div>`;
                                    html += `<div style="font-weight: 700; color: #5b21b6; font-size: 0.9em;">${recipient.paid_by_user || 'N/A'}</div>`;
                                    html += '</div>';
                                    
                                    // View Proofs Button
                                    html += '<div style="padding: 8px; text-align: center;">';
                                    const recipientJsonStr = JSON.stringify(recipient).replace(/"/g, '&quot;');
                                    html += `<button onclick="openRecipientFilesModal(${entry.payment_entry_id}, ${index}, '${recipientJsonStr}');" style="background: #fbbf24; color: #78350f; border: 2px solid #f59e0b; padding: 8px 12px; border-radius: 4px; font-size: 0.8em; font-weight: 700; cursor: pointer; transition: all 0.2s; white-space: nowrap; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);"><i class="fas fa-paperclip"></i> Proofs</button>`;
                                    html += '</div>';
                                    
                                    html += '</div>';
                                });
                            }
                            
                            // Payment Mode (kept for reference, but hidden in expanded view since it's now per recipient)
                            // Removed from here as it's now displayed for each recipient
                            
                            html += '</div>';
                        });

                        html += '</div>';

                        // Add pagination
                        if (data.pagination.totalPages > 1) {
                            html += '<div class="pagination-container">';
                            html += `<div class="pagination-info"><strong>Page ${data.pagination.currentPage} of ${data.pagination.totalPages}</strong> (Total: <strong>${data.pagination.total}</strong> entries)</div>`;
                            
                            // Encode filter parameters safely
                            const encodedSearch = encodeURIComponent(search || '');
                            const encodedStatus = encodeURIComponent(status || '');
                            const encodedDateFrom = encodeURIComponent(dateFrom || '');
                            const encodedDateTo = encodeURIComponent(dateTo || '');
                            const encodedProjectType = encodeURIComponent(projectType || '');
                            const encodedVendorCategory = encodeURIComponent(vendorCategory || '');
                            const encodedPaidBy = encodeURIComponent(paidBy || '');
                            
                            // Previous button
                            html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} data-page="${page > 1 ? page - 1 : 1}" data-search="${encodedSearch}" data-status="${encodedStatus}" data-datefrom="${encodedDateFrom}" data-dateto="${encodedDateTo}" data-projecttype="${encodedProjectType}" data-vendorcategory="${encodedVendorCategory}" data-paidby="${encodedPaidBy}" class="pagination-btn pagination-prev">
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>`;

                            // Page numbers
                            let startPage = Math.max(1, page - 2);
                            let endPage = Math.min(data.pagination.totalPages, page + 2);

                            if (startPage > 1) {
                                html += `<button class="pagination-btn" data-page="1" data-search="${encodedSearch}" data-status="${encodedStatus}" data-datefrom="${encodedDateFrom}" data-dateto="${encodedDateTo}" data-projecttype="${encodedProjectType}" data-vendorcategory="${encodedVendorCategory}" data-paidby="${encodedPaidBy}">1</button>`;
                                if (startPage > 2) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" data-page="${i}" data-search="${encodedSearch}" data-status="${encodedStatus}" data-datefrom="${encodedDateFrom}" data-dateto="${encodedDateTo}" data-projecttype="${encodedProjectType}" data-vendorcategory="${encodedVendorCategory}" data-paidby="${encodedPaidBy}">${i}</button>`;
                            }

                            if (endPage < data.pagination.totalPages) {
                                if (endPage < data.pagination.totalPages - 1) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                                html += `<button class="pagination-btn" data-page="${data.pagination.totalPages}" data-search="${encodedSearch}" data-status="${encodedStatus}" data-datefrom="${encodedDateFrom}" data-dateto="${encodedDateTo}" data-projecttype="${encodedProjectType}" data-vendorcategory="${encodedVendorCategory}" data-paidby="${encodedPaidBy}">${data.pagination.totalPages}</button>`;
                            }

                            // Next button
                            html += `<button class="pagination-btn" ${page === data.pagination.totalPages ? 'disabled' : ''} data-page="${page < data.pagination.totalPages ? page + 1 : page}" data-search="${encodedSearch}" data-status="${encodedStatus}" data-datefrom="${encodedDateFrom}" data-dateto="${encodedDateTo}" data-projecttype="${encodedProjectType}" data-vendorcategory="${encodedVendorCategory}" data-paidby="${encodedPaidBy}" class="pagination-btn pagination-next">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>`;

                            html += '</div>';
                        }

                        entriesContainer.innerHTML = html;
                        entriesPaginationState.totalPages = data.pagination.totalPages;

                        // Initialize expanded buttons to show chevron in rotated state
                        initializeExpandButtons();

                        // Initialize Excel-style filter for Project Type
                        const projectFilterToggle = document.getElementById('projectFilterToggle');
                        const projectFilterDropdown = document.getElementById('projectFilterDropdown');
                        const projectFilterSearch = document.getElementById('projectFilterSearch');
                        const projectFilterApply = document.getElementById('projectFilterApply');
                        const projectFilterClear = document.getElementById('projectFilterClear');
                        const projectFilterList = projectFilterDropdown.querySelector('.excel-filter-list');
                        const projectFilterOptions = projectFilterList.querySelectorAll('.filter-option[data-type]');

                        if (projectFilterToggle && projectFilterDropdown) {
                            // Toggle dropdown
                            projectFilterToggle.addEventListener('click', function(e) {
                                e.stopPropagation();
                                projectFilterDropdown.classList.toggle('active');
                                projectFilterSearch.focus();
                            });

                            // Search functionality
                            projectFilterSearch.addEventListener('keyup', function(e) {
                                const searchTerm = this.value.toLowerCase();
                                projectFilterOptions.forEach(option => {
                                    const text = option.textContent.toLowerCase();
                                    option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                                });
                            });

                            // Apply filter
                            projectFilterApply.addEventListener('click', function(e) {
                                e.stopPropagation();
                                const selectedTypes = Array.from(projectFilterOptions)
                                    .filter(opt => opt.querySelector('input[type="checkbox"]')?.checked && opt.getAttribute('data-type') !== '')
                                    .map(opt => opt.getAttribute('data-type'))
                                    .join(',');
                                
                                const mainSearch = entriesPaginationState.search || '';
                                const mainStatus = entriesPaginationState.status || '';
                                const mainDateFrom = entriesPaginationState.dateFrom || '';
                                const mainDateTo = entriesPaginationState.dateTo || '';
                                const mainVendorCategory = entriesPaginationState.vendorCategory || '';
                                const mainPaidBy = entriesPaginationState.paidBy || '';
                                
                                loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, selectedTypes, mainVendorCategory, mainPaidBy);
                                projectFilterDropdown.classList.remove('active');
                            });

                            // Clear filter
                            projectFilterClear.addEventListener('click', function(e) {
                                e.stopPropagation();
                                projectFilterOptions.forEach(opt => {
                                    opt.querySelector('input[type="checkbox"]').checked = false;
                                    opt.classList.remove('active');
                                    opt.style.display = 'flex';
                                });
                                projectFilterSearch.value = '';
                                
                                const mainSearch = entriesPaginationState.search || '';
                                const mainStatus = entriesPaginationState.status || '';
                                const mainDateFrom = entriesPaginationState.dateFrom || '';
                                const mainDateTo = entriesPaginationState.dateTo || '';
                                const mainVendorCategory = entriesPaginationState.vendorCategory || '';
                                const mainPaidBy = entriesPaginationState.paidBy || '';
                                
                                loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, '', mainVendorCategory, mainPaidBy);
                                projectFilterDropdown.classList.remove('active');
                            });

                            // Checkbox click handler - just toggle checkbox, don't apply yet
                            projectFilterOptions.forEach(option => {
                                option.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const checkbox = this.querySelector('input[type="checkbox"]');
                                    if (checkbox) {
                                        checkbox.checked = !checkbox.checked;
                                        this.classList.toggle('active');
                                    }
                                });
                            });

                            // Close dropdown when clicking outside
                            document.addEventListener('click', function(e) {
                                if (!e.target.closest('.project-filter-container') && e.target.id !== 'projectFilterToggle') {
                                    projectFilterDropdown.classList.remove('active');
                                }
                            });
                        }

                        // Load vendor categories for filter
                        fetch('get_vendor_categories.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.data && data.data.length > 0) {
                                    const vendorCategoryDropdown = document.getElementById('vendorCategoryFilterDropdown');
                                    const vendorCategoryFilterList = vendorCategoryDropdown.querySelector('.excel-filter-list');
                                    
                                    // Clear existing options except the first one
                                    while (vendorCategoryFilterList.children.length > 1) {
                                        vendorCategoryFilterList.removeChild(vendorCategoryFilterList.lastChild);
                                    }

                                    data.data.forEach(category => {
                                        const option = document.createElement('div');
                                        option.className = 'filter-option';
                                        option.setAttribute('data-vendor-category', category);
                                        option.innerHTML = `<input type="checkbox"> ${category}`;
                                        vendorCategoryFilterList.appendChild(option);
                                    });

                                    // Initialize Excel-style filter for Vendor Category
                                    const vendorCategoryFilterToggle = document.getElementById('vendorCategoryFilterToggle');
                                    const vendorCategoryFilterSearch = document.getElementById('vendorCategoryFilterSearch');
                                    const vendorCategoryFilterApply = document.getElementById('vendorCategoryFilterApply');
                                    const vendorCategoryFilterClear = document.getElementById('vendorCategoryFilterClear');
                                    const vendorCategoryFilterOptions = vendorCategoryFilterList.querySelectorAll('.filter-option[data-vendor-category]');

                                    if (vendorCategoryFilterToggle) {
                                        // Toggle dropdown
                                        vendorCategoryFilterToggle.addEventListener('click', function(e) {
                                            e.stopPropagation();
                                            vendorCategoryDropdown.classList.toggle('active');
                                            vendorCategoryFilterSearch.focus();
                                        });

                                        // Search functionality
                                        vendorCategoryFilterSearch.addEventListener('keyup', function(e) {
                                            const searchTerm = this.value.toLowerCase();
                                            vendorCategoryFilterOptions.forEach(option => {
                                                const text = option.textContent.toLowerCase();
                                                option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                                            });
                                        });

                                        // Apply filter
                                        vendorCategoryFilterApply.addEventListener('click', function(e) {
                                            e.stopPropagation();
                                            const selectedCategories = Array.from(vendorCategoryFilterOptions)
                                                .filter(opt => opt.querySelector('input[type="checkbox"]')?.checked && opt.getAttribute('data-vendor-category') !== '')
                                                .map(opt => opt.getAttribute('data-vendor-category'))
                                                .join(',');
                                            
                                            const mainSearch = entriesPaginationState.search || '';
                                            const mainStatus = entriesPaginationState.status || '';
                                            const mainDateFrom = entriesPaginationState.dateFrom || '';
                                            const mainDateTo = entriesPaginationState.dateTo || '';
                                            const mainProjectType = entriesPaginationState.projectType || '';
                                            const mainPaidBy = entriesPaginationState.paidBy || '';
                                            
                                            loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, mainProjectType, selectedCategories, mainPaidBy);
                                            vendorCategoryDropdown.classList.remove('active');
                                        });

                                        // Clear filter
                                        vendorCategoryFilterClear.addEventListener('click', function(e) {
                                            e.stopPropagation();
                                            vendorCategoryFilterOptions.forEach(opt => {
                                                opt.querySelector('input[type="checkbox"]').checked = false;
                                                opt.classList.remove('active');
                                                opt.style.display = 'flex';
                                            });
                                            vendorCategoryFilterSearch.value = '';
                                            
                                            const mainSearch = entriesPaginationState.search || '';
                                            const mainStatus = entriesPaginationState.status || '';
                                            const mainDateFrom = entriesPaginationState.dateFrom || '';
                                            const mainDateTo = entriesPaginationState.dateTo || '';
                                            const mainProjectType = entriesPaginationState.projectType || '';
                                            const mainPaidBy = entriesPaginationState.paidBy || '';
                                            
                                            loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, mainProjectType, '', mainPaidBy);
                                            vendorCategoryDropdown.classList.remove('active');
                                        });

                                        // Checkbox click handler - just toggle checkbox, don't apply yet
                                        vendorCategoryFilterOptions.forEach(option => {
                                            option.addEventListener('click', function(e) {
                                                e.stopPropagation();
                                                const checkbox = this.querySelector('input[type="checkbox"]');
                                                if (checkbox) {
                                                    checkbox.checked = !checkbox.checked;
                                                    this.classList.toggle('active');
                                                }
                                            });
                                        });

                                        // Close dropdown when clicking outside
                                        document.addEventListener('click', function(e) {
                                            if (!e.target.closest('.project-filter-container') && e.target.id !== 'vendorCategoryFilterToggle') {
                                                vendorCategoryDropdown.classList.remove('active');
                                            }
                                        });
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error loading vendor categories:', error);
                            });

                        // Load users for Paid By filter - fetch from database (ALL users, not just visible ones)
                        const paidByFilterDropdown = document.getElementById('paidByFilterDropdown');
                        const paidByFilterList = paidByFilterDropdown.querySelector('.excel-filter-list');

                        // Fetch all unique paid-by users from database
                        fetch('get_paid_by_users.php')
                            .then(response => response.json())
                            .then(result => {
                                if (result.success && result.data && result.data.length > 0) {
                                    // Clear existing options except the first one (ALL USERS)
                                    while (paidByFilterList.children.length > 1) {
                                        paidByFilterList.removeChild(paidByFilterList.lastChild);
                                    }

                                    // Add each user from database
                                    result.data.forEach(user => {
                                        const option = document.createElement('div');
                                        option.className = 'filter-option';
                                        option.setAttribute('data-paid-by', user.username);
                                        option.innerHTML = `<input type="checkbox"> ${user.username}`;
                                        paidByFilterList.appendChild(option);
                                    });

                                    // Initialize Excel-style filter for Paid By
                                    const paidByFilterToggle = document.getElementById('paidByFilterToggle');
                                    const paidByFilterSearch = document.getElementById('paidByFilterSearch');
                                    const paidByFilterApply = document.getElementById('paidByFilterApply');
                                    const paidByFilterClear = document.getElementById('paidByFilterClear');
                                    const paidByFilterOptions = paidByFilterList.querySelectorAll('.filter-option[data-paid-by]');

                                    if (paidByFilterToggle) {
                                        // Toggle dropdown
                                        paidByFilterToggle.addEventListener('click', function(e) {
                                            e.stopPropagation();
                                            paidByFilterDropdown.classList.toggle('active');
                                            paidByFilterSearch.focus();
                                        });

                                        // Search functionality
                                        paidByFilterSearch.addEventListener('keyup', function(e) {
                                            const searchTerm = this.value.toLowerCase();
                                            paidByFilterOptions.forEach(option => {
                                                const text = option.textContent.toLowerCase();
                                                option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                                            });
                                        });

                                        // Apply filter
                                        paidByFilterApply.addEventListener('click', function(e) {
                                            e.stopPropagation();
                                            const selectedUsers = Array.from(paidByFilterOptions)
                                                .filter(opt => opt.querySelector('input[type="checkbox"]')?.checked && opt.getAttribute('data-paid-by') !== '')
                                                .map(opt => opt.getAttribute('data-paid-by'))
                                                .join(',');
                                            
                                            const mainSearch = entriesPaginationState.search || '';
                                            const mainStatus = entriesPaginationState.status || '';
                                            const mainDateFrom = entriesPaginationState.dateFrom || '';
                                            const mainDateTo = entriesPaginationState.dateTo || '';
                                            const mainProjectType = entriesPaginationState.projectType || '';
                                            const mainVendorCategory = entriesPaginationState.vendorCategory || '';
                                            
                                            loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, mainProjectType, mainVendorCategory, selectedUsers);
                                            paidByFilterDropdown.classList.remove('active');
                                        });

                                        // Clear filter
                                        paidByFilterClear.addEventListener('click', function(e) {
                                            e.stopPropagation();
                                            paidByFilterOptions.forEach(opt => {
                                                opt.querySelector('input[type="checkbox"]').checked = false;
                                                opt.classList.remove('active');
                                                opt.style.display = 'flex';
                                            });
                                            paidByFilterSearch.value = '';
                                            
                                            const mainSearch = entriesPaginationState.search || '';
                                            const mainStatus = entriesPaginationState.status || '';
                                            const mainDateFrom = entriesPaginationState.dateFrom || '';
                                            const mainDateTo = entriesPaginationState.dateTo || '';
                                            const mainProjectType = entriesPaginationState.projectType || '';
                                            const mainVendorCategory = entriesPaginationState.vendorCategory || '';
                                            
                                            loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, mainProjectType, mainVendorCategory, '');
                                            paidByFilterDropdown.classList.remove('active');
                                        });

                                        // Checkbox click handler - just toggle checkbox, don't apply yet
                                        paidByFilterOptions.forEach(option => {
                                            option.addEventListener('click', function(e) {
                                                e.stopPropagation();
                                                const checkbox = this.querySelector('input[type="checkbox"]');
                                                if (checkbox) {
                                                    checkbox.checked = !checkbox.checked;
                                                    this.classList.toggle('active');
                                                }
                                            });
                                        });

                                        // Close dropdown when clicking outside
                                        document.addEventListener('click', function(e) {
                                            if (!e.target.closest('.project-filter-container') && e.target.id !== 'paidByFilterToggle') {
                                                paidByFilterDropdown.classList.remove('active');
                                            }
                                        });
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error loading paid-by users:', error);
                            });

                        // Add event listeners for pagination buttons
                        document.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                const pageNum = parseInt(this.getAttribute('data-page'));
                                const searchVal = decodeURIComponent(this.getAttribute('data-search') || '');
                                const statusVal = decodeURIComponent(this.getAttribute('data-status') || '');
                                const dateFromVal = decodeURIComponent(this.getAttribute('data-datefrom') || '');
                                const dateToVal = decodeURIComponent(this.getAttribute('data-dateto') || '');
                                const projectTypeVal = decodeURIComponent(this.getAttribute('data-projecttype') || '');
                                const vendorCategoryVal = decodeURIComponent(this.getAttribute('data-vendorcategory') || '');
                                const paidByVal = decodeURIComponent(this.getAttribute('data-paidby') || '');
                                
                                loadPaymentEntries(10, pageNum, searchVal, statusVal, dateFromVal, dateToVal, projectTypeVal, vendorCategoryVal, paidByVal);
                            });
                        });
                    } else if (data.success) {
                        // Add event listeners for pagination buttons
                        document.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                const pageNum = parseInt(this.getAttribute('data-page'));
                                const searchVal = decodeURIComponent(this.getAttribute('data-search') || '');
                                const statusVal = decodeURIComponent(this.getAttribute('data-status') || '');
                                const dateFromVal = decodeURIComponent(this.getAttribute('data-datefrom') || '');
                                const dateToVal = decodeURIComponent(this.getAttribute('data-dateto') || '');
                                const projectTypeVal = decodeURIComponent(this.getAttribute('data-projecttype') || '');
                                const vendorCategoryVal = decodeURIComponent(this.getAttribute('data-vendorcategory') || '');
                                const paidByVal = decodeURIComponent(this.getAttribute('data-paidby') || '');
                                
                                loadPaymentEntries(10, pageNum, searchVal, statusVal, dateFromVal, dateToVal, projectTypeVal, vendorCategoryVal, paidByVal);
                            });
                        });
                    } else if (data.success) {
                        // success but no data
                        entriesContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-receipt"></i>
                                <p>No payment entries added yet. Click "Add Payment Entry" to get started.</p>
                            </div>
                        `;
                    } else {
                        // API error
                        console.error('API Error:', data);
                        entriesContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading payment entries. ${data.message || 'Please try again.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading payment entries:', error);
                    entriesContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading payment entries. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Toggle payment entry expand/collapse
        function togglePaymentEntryExpand(entryId) {
            const detailsContainer = document.getElementById(`entry-details-${entryId}`);
            const expandBtn = event.target.closest('.expand-btn');
            
            if (detailsContainer) {
                const isHidden = detailsContainer.style.display === 'none';
                
                if (isHidden) {
                    // Expand
                    detailsContainer.style.display = 'grid';
                    if (expandBtn) {
                        expandBtn.style.transform = 'rotate(180deg)';
                        expandBtn.querySelector('i').style.transform = 'rotate(180deg)';
                    }
                } else {
                    // Collapse
                    detailsContainer.style.display = 'none';
                    if (expandBtn) {
                        expandBtn.style.transform = 'rotate(0deg)';
                        expandBtn.querySelector('i').style.transform = 'rotate(0deg)';
                    }
                }
            }
        }

        // Initialize all chevrons to expanded state on page load
        function initializeExpandButtons() {
            const expandBtns = document.querySelectorAll('.expand-btn');
            expandBtns.forEach(btn => {
                btn.style.transform = 'rotate(180deg)';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.style.transform = 'rotate(180deg)';
                }
            });
        }

        // Payment Entry action functions
        function viewPaymentEntry(entryId) {
            console.log('Viewing payment entry:', entryId);
            openPaymentEntryDetailsModal(entryId);
        }

        function editPaymentEntry(entryId) {
            console.log('Editing payment entry:', entryId);
            alert('Edit payment entry for ID: ' + entryId);
            // TODO: Open payment entry edit modal
        }

        function deletePaymentEntry(entryId) {
            if (confirm('Are you sure you want to delete this payment entry? This action cannot be undone.')) {
                console.log('Deleting payment entry:', entryId);
                fetch(`delete_payment_entry.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ payment_entry_id: entryId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment entry deleted successfully');
                        // Reload entries with current filter state
                        loadPaymentEntries(
                            entriesPaginationState.limit,
                            entriesPaginationState.currentPage,
                            entriesPaginationState.search,
                            entriesPaginationState.status,
                            entriesPaginationState.dateFrom,
                            entriesPaginationState.dateTo,
                            entriesPaginationState.projectType,
                            entriesPaginationState.vendorCategory
                        );
                    } else {
                        alert('Error deleting payment entry: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting payment entry');
                });
            }
        }

        // Global state for records date filters
        let recordsDateFilterState = {
            dateFrom: '',
            dateTo: ''
        };

        // Function to scroll to Recently Added Records section and activate a tab
        function scrollToTab(tabName) {
            // Find and scroll to the recent-records-section
            const recentRecordsSection = document.querySelector('.recent-records-section');
            if (recentRecordsSection) {
                recentRecordsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Wait for scroll to complete, then activate the tab
                setTimeout(function() {
                    const tabBtn = document.querySelector(`[data-tab="${tabName}"]`);
                    if (tabBtn) {
                        // Trigger click to activate the tab
                        tabBtn.click();
                    }
                }, 500);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Recently Added Records Toggle Functionality
            const recentRecordsToggleBtn = document.getElementById('recentRecordsToggleBtn');
            if (recentRecordsToggleBtn) {
                // Track toggle state
                let entriesExpanded = true;
                
                recentRecordsToggleBtn.addEventListener('click', function() {
                    const recordsSection = this.closest('.recent-records-section');
                    if (recordsSection) {
                        const expandedRecords = recordsSection.querySelectorAll('.entry-details-container');
                        const expandButtons = recordsSection.querySelectorAll('.expand-entry-btn');
                        
                        if (entriesExpanded) {
                            // Close all expanded records
                            expandedRecords.forEach(record => {
                                record.style.display = 'none';
                            });
                            
                            // Close all expand buttons
                            expandButtons.forEach(btn => {
                                btn.classList.remove('expanded');
                                btn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                            });
                            
                            entriesExpanded = false;
                        } else {
                            // Expand all records
                            expandedRecords.forEach(record => {
                                record.style.display = 'grid';
                            });
                            
                            // Open all expand buttons
                            expandButtons.forEach(btn => {
                                btn.classList.add('expanded');
                                btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                            });
                            
                            entriesExpanded = true;
                        }
                    }
                });
            }

            // Load payment entries on page load (Recent Entries tab)
            loadPaymentEntries(10, 1, '', '', '', '');

            // Attach click event listeners to all tabs
            const allTabBtns = document.querySelectorAll('.tab-btn');
            allTabBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabName = this.getAttribute('data-tab');
                    
                    // Find the parent section for this tab
                    const parentSection = this.closest('.recent-records-section');
                    if (!parentSection) return;
                    
                    // Remove active class from all buttons and contents in this section only
                    parentSection.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    parentSection.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    const tabElement = document.getElementById(tabName);
                    if (tabElement) {
                        // Clear the container before loading new data
                        const container = tabElement.querySelector('[id$="Container"]');
                        if (container) {
                            container.innerHTML = `
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner"></i>
                                    <p>Loading...</p>
                                </div>
                            `;
                        }
                        tabElement.classList.add('active');
                    }
                    
                    // Load data based on tab name
                    if (tabName === 'vendors-tab') {
                        loadVendors(10, 1, '', '', '', '');
                    } else if (tabName === 'labours-tab') {
                        loadLabours(10, 1, '', '', '', '');
                    } else if (tabName === 'entries-tab') {
                        loadPaymentEntries(10, 1, '', '', '', '');
                    }
                });
            });

            const toggleFilterBtn = document.getElementById('toggleFilterBtn');
            const filterContent = document.getElementById('filterContent');
            const applyFilterBtn = document.getElementById('applyFilterBtn');
            const resetFilterBtn = document.getElementById('resetFilterBtn');

            // Toggle filter section visibility
            toggleFilterBtn.addEventListener('click', function() {
                filterContent.classList.toggle('collapsed');
                toggleFilterBtn.classList.toggle('active');
            });

            // Apply filter functionality
            applyFilterBtn.addEventListener('click', function() {
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;
                const paymentType = document.getElementById('paymentType').value;
                const status = document.getElementById('status').value;

                // Log filter values (can be replaced with actual API call)
                console.log({
                    dateFrom,
                    dateTo,
                    paymentType,
                    status
                });

                // Show success message
                alert('Filters applied successfully');
            });

            // Reset filter functionality
            resetFilterBtn.addEventListener('click', function() {
                document.getElementById('dateFrom').value = '';
                document.getElementById('dateTo').value = '';
                document.getElementById('paymentType').value = '';
                document.getElementById('status').value = '';

                console.log('Filters reset');
                alert('Filters have been reset');
            });

            // Toggle records section visibility
            const toggleRecordsBtn = document.getElementById('toggleRecordsBtn');
            const recordsContent = document.getElementById('recordsContent');

            if (toggleRecordsBtn) {
                toggleRecordsBtn.addEventListener('click', function() {
                    recordsContent.classList.toggle('collapsed');
                    toggleRecordsBtn.classList.toggle('active');
                });
            }

            // Records button click handlers
            const addVendorBtn = document.getElementById('addVendorBtn');
            const addLabourBtn = document.getElementById('addLabourBtn');
            const addPaymentBtn = document.getElementById('addPaymentBtn');
            const viewReportBtn = document.getElementById('viewReportBtn');

            if (addVendorBtn) {
                addVendorBtn.addEventListener('click', function() {
                    console.log('Add Vendor clicked');
                    const modal = document.getElementById('addVendorModal');
                    if (modal) {
                        modal.classList.add('active');
                    }
                });
            }

            if (addLabourBtn) {
                addLabourBtn.addEventListener('click', function() {
                    console.log('Add Labour clicked');
                    // Try to open the Add Labour modal (provided by modals/add_labour_modal.php)
                    const modal = document.getElementById('addLabourModal');
                    if (modal) {
                        modal.classList.add('active');
                    } else if (typeof window.openAddLabourModal === 'function') {
                        // fallback if modal exposes an open function
                        window.openAddLabourModal();
                    } else {
                        // fallback behavior: redirect to add_labour page (uncomment if you have a page)
                        // window.location.href = 'add_labour.php';
                        alert('Add Labour modal not found. Please create or include modals/add_labour_modal.php');
                    }
                });
            }

            if (addPaymentBtn) {
                addPaymentBtn.addEventListener('click', function() {
                    console.log('Add Payment Entry clicked');
                    if (typeof window.openPaymentEntryModal === 'function') {
                        window.openPaymentEntryModal();
                    } else {
                        alert('Payment Entry modal not found. Please include modals/add_payment_entry_modal.php');
                    }
                });
            }

            if (viewReportBtn) {
                viewReportBtn.addEventListener('click', function() {
                    console.log('View Report clicked');
                    alert('Redirecting to View Report page');
                    // window.location.href = 'view_report.php';
                });
            }

            // Records date filter functionality
            const applyRecordsFilterBtn = document.getElementById('applyRecordsFilterBtn');
            const resetRecordsFilterBtn = document.getElementById('resetRecordsFilterBtn');
            const recordsDateFromInput = document.getElementById('recordsDateFrom');
            const recordsDateToInput = document.getElementById('recordsDateTo');

            if (applyRecordsFilterBtn) {
                applyRecordsFilterBtn.addEventListener('click', function() {
                    const dateFrom = recordsDateFromInput.value;
                    const dateTo = recordsDateToInput.value;

                    // Validate dates
                    if (dateFrom && dateTo && dateFrom > dateTo) {
                        alert('From Date cannot be after To Date');
                        return;
                    }

                    // Store filter state
                    recordsDateFilterState.dateFrom = dateFrom;
                    recordsDateFilterState.dateTo = dateTo;

                    // Get active tab and reload data with filters
                    const activeTab = document.querySelector('.tab-btn.active');
                    if (activeTab) {
                        const tabName = activeTab.getAttribute('data-tab');
                        
                        if (tabName === 'vendors-tab') {
                            loadVendors(10, 1, '', '', dateFrom, dateTo);
                        } else if (tabName === 'labours-tab') {
                            loadLabours(10, 1, '', '', dateFrom, dateTo);
                        } else if (tabName === 'entries-tab') {
                            loadPaymentEntries(10, 1, '', '', dateFrom, dateTo);
                        }
                    }

                    console.log('Records filter applied:', { dateFrom, dateTo });
                });
            }

            if (resetRecordsFilterBtn) {
                resetRecordsFilterBtn.addEventListener('click', function() {
                    recordsDateFromInput.value = '';
                    recordsDateToInput.value = '';

                    recordsDateFilterState.dateFrom = '';
                    recordsDateFilterState.dateTo = '';

                    // Get active tab and reload data without filters
                    const activeTab = document.querySelector('.tab-btn.active');
                    if (activeTab) {
                        const tabName = activeTab.getAttribute('data-tab');
                        
                        if (tabName === 'vendors-tab') {
                            loadVendors(10, 1, '', '', '', '');
                        } else if (tabName === 'labours-tab') {
                            loadLabours(10, 1, '', '', '', '');
                        } else if (tabName === 'entries-tab') {
                            loadPaymentEntries(10, 1, '', '', '', '');
                        }
                    }

                    console.log('Records filter reset');
                });
            }
        });

    </script>
