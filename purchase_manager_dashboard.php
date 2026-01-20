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

        .management-search-container {
            position: relative;
            flex: 0 0 auto;
        }

        .management-search-wrapper {
            position: relative;
            width: 320px;
        }

        .management-search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
            font-size: 14px;
            pointer-events: none;
        }

        .management-search-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .management-search-input:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }

        .management-search-results {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .management-search-results.active {
            display: block;
        }

        .search-result-category {
            padding: 8px 12px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4a5568;
            letter-spacing: 0.5px;
        }

        .search-result-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f7fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-result-item:hover {
            background: #ebf8ff;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .search-result-icon.vendor {
            background: #bee3f8;
            color: #2c5282;
        }

        .search-result-icon.labour {
            background: #feebc8;
            color: #7c2d12;
        }

        .search-result-info {
            flex: 1;
        }

        .search-result-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .search-result-details {
            font-size: 12px;
            color: #718096;
        }

        .search-no-results {
            padding: 20px;
            text-align: center;
            color: #718096;
            font-size: 14px;
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

        .mini-filter-btn.export {
            width: auto;
            padding: 7px 14px;
            background: #10b981;
            color: white;
            border-color: #10b981;
            font-weight: 600;
            gap: 6px;
        }

        .mini-filter-btn.export:hover {
            background: #059669;
            border-color: #059669;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .mini-filter-btn.export:active {
            background: #047857;
        }

        .mini-filter-btn.export i {
            font-size: 0.9em;
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
            overflow: visible;
            position: relative;
            z-index: 10;
        }

        .filter-header-cell {
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-icon {
            cursor: pointer;
            color: #718096;
            font-size: 11px;
            padding: 3px;
            border-radius: 3px;
            transition: all 0.2s ease;
        }

        .filter-icon:hover {
            background-color: #e2e8f0;
            color: #2d3748;
        }

        .filter-icon.active {
            color: #3182ce;
            background-color: #bee3f8;
        }

        .filter-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            max-width: 300px;
            z-index: 1000;
            margin-top: 5px;
        }

        .filter-search-box {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-search-box input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 13px;
        }

        .filter-options {
            max-height: 250px;
            overflow-y: auto;
            padding: 5px 0;
        }

        .filter-option {
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            transition: background 0.2s;
        }

        .filter-option:hover {
            background-color: #f7fafc;
        }

        .filter-option input[type="checkbox"] {
            cursor: pointer;
        }

        .filter-actions {
            padding: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .filter-actions button {
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-actions button:first-child {
            background-color: #3182ce;
            color: white;
        }

        .filter-actions button:first-child:hover {
            background-color: #2c5282;
        }

        .filter-actions button:last-child {
            background-color: #e2e8f0;
            color: #2d3748;
        }

        .filter-actions button:last-child:hover {
            background-color: #cbd5e0;
        }

        .vendor-row-header>div:last-child {
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

        .vendor-actions .excel-btn {
            color: #10b981;
        }

        .vendor-actions .excel-btn:hover {
            background-color: #d1fae5;
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
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            min-width: 250px;
            display: none;
            margin-top: 8px;
            overflow: visible;
        }

        .project-filter-dropdown.active {
            display: block;
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Optgroup styling for categorized filters */
        .filter-optgroup {
            display: flex;
            flex-direction: column;
            margin-bottom: 8px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        .filter-optgroup:first-child {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }

        .filter-optgroup-label {
            font-weight: 700;
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2a4365;
            padding: 8px 12px 6px 12px;
            background-color: #f0f4f8;
            margin: 0 -12px 6px -12px;
            padding-left: 12px;
            padding-right: 12px;
        }

        .filter-optgroup .filter-option {
            padding-left: 24px;
            background-color: transparent;
            border: none;
            border-bottom: none;
        }

        .filter-optgroup .filter-option:hover {
            background-color: #f7fafc;
            border-radius: 4px;
            margin: 0 4px;
            padding-left: 20px;
        }

        .filter-optgroup .filter-option.active {
            background-color: #ebf8ff;
            border-radius: 4px;
            margin: 0 4px;
            padding-left: 20px;
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
                        <button class="recent-records-toggle-btn" id="recentRecordsToggleBtn"
                            title="Collapse/Expand Records">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <h3 class="recent-records-header">Recently Added Records</h3>
                    </div>

                    <!-- Minimalist Date Range Filter -->
                    <div class="records-date-filter-minimal">
                        <input type="date" id="recordsDateFrom" name="recordsDateFrom" class="mini-date-input"
                            placeholder="From">
                        <input type="date" id="recordsDateTo" name="recordsDateTo" class="mini-date-input"
                            placeholder="To">
                        <button class="mini-filter-btn apply" id="applyRecordsFilterBtn" title="Apply Filter">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="mini-filter-btn reset" id="resetRecordsFilterBtn" title="Reset Filter">
                            <i class="fas fa-times"></i>
                        </button>
                        <button class="mini-filter-btn export" id="exportToExcelBtn"
                            title="Export Payment Entries to Excel">
                            <i class="fas fa-file-excel"></i> Export to Excel
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
                    <button class="quick-action-btn" onclick="scrollToTab('vendors-tab')"
                        title="View all vendors in the system">
                        <i class="fas fa-users"></i>
                        <span>View Vendors</span>
                    </button>
                    <button class="quick-action-btn" onclick="scrollToTab('labours-tab')"
                        title="View all labour records">
                        <i class="fas fa-hard-hat"></i>
                        <span>View Labours</span>
                    </button>
                    <button class="quick-action-btn" onclick="scrollToTab('entries-tab')"
                        title="View recent payment entries">
                        <i class="fas fa-receipt"></i>
                        <span>View Payment Entries</span>
                    </button>
                    <button class="quick-action-btn" onclick="alert('Budget Overview feature coming soon')"
                        title="View budget and spending analytics">
                        <i class="fas fa-chart-pie"></i>
                        <span>Budget Overview</span>
                    </button>
                </div>
            </div>

            <!-- Vendors, Labours and Reports Section -->
            <div class="recent-records-section" style="margin-top: 40px;">
                <div class="recent-records-header-container">
                    <h3 class="recent-records-header">Management</h3>
                    <!-- Smart Search Box -->
                    <div class="management-search-container">
                        <div class="management-search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="managementSearchInput" class="management-search-input"
                                placeholder="Search vendors or labours..." autocomplete="off">
                            <div id="managementSearchResults" class="management-search-results"></div>
                        </div>
                    </div>
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

    <!-- Include Payment Entry Edit Modal (Comprehensive) -->
    <?php include 'modals/payment_entry_edit_modal_comprehensive_v2.php'; ?>

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

        // Export Vendor Payment History to Excel
        function exportVendorPaymentHistory(vendorId, vendorName) {
            // Show loading indicator
            const loadingMsg = document.createElement('div');
            loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); z-index: 10000; font-size: 16px; font-weight: 600;';
            loadingMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Excel file...';
            document.body.appendChild(loadingMsg);

            // Call export API
            window.location.href = `export_vendor_payment_history.php?vendor_id=${vendorId}&vendor_name=${encodeURIComponent(vendorName)}`;

            // Remove loading message after a delay
            setTimeout(() => {
                document.body.removeChild(loadingMsg);
            }, 2000);
        }

        // Export Labour Payment History to Excel
        function exportLabourPaymentHistory(labourId, labourName) {
            // Show loading indicator
            const loadingMsg = document.createElement('div');
            loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); z-index: 10000; font-size: 16px; font-weight: 600;';
            loadingMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Excel file...';
            document.body.appendChild(loadingMsg);

            // Call export API
            window.location.href = `export_labour_payment_history.php?labour_id=${labourId}&labour_name=${encodeURIComponent(labourName)}`;

            // Remove loading message after a delay
            setTimeout(() => {
                document.body.removeChild(loadingMsg);
            }, 2000);
        }

        // Global state for pagination
        let vendorPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: '',
            nameFilter: [],
            typeFilter: []
        };

        // Global state for labour pagination
        let labourPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: '',
            nameFilter: [],
            typeFilter: []
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
                        html += `
                            <div class="filter-header-cell">
                                Name
                                <span class="filter-icon" onclick="toggleVendorNameFilter(event)">
                                    <i class="fas fa-filter"></i>
                                </span>
                                <div id="vendorNameFilterDropdown" class="filter-dropdown" style="display: none;">
                                    <div class="filter-search-box">
                                        <input type="text" id="vendorNameFilterSearch" placeholder="Search names..." onkeyup="filterVendorNameOptions()">
                                    </div>
                                    <div class="filter-options" id="vendorNameFilterOptions"></div>
                                    <div class="filter-actions">
                                        <button onclick="applyVendorNameFilter()">Apply</button>
                                        <button onclick="clearVendorNameFilter()">Clear</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        html += '<div>Email</div>';
                        html += '<div>Phone</div>';
                        html += `
                            <div class="filter-header-cell">
                                Type
                                <span class="filter-icon" onclick="toggleVendorTypeFilter(event)">
                                    <i class="fas fa-filter"></i>
                                </span>
                                <div id="vendorTypeFilterDropdown" class="filter-dropdown" style="display: none;">
                                    <div class="filter-search-box">
                                        <input type="text" id="vendorTypeFilterSearch" placeholder="Search types..." onkeyup="filterVendorTypeOptions()">
                                    </div>
                                    <div class="filter-options" id="vendorTypeFilterOptions"></div>
                                    <div class="filter-actions">
                                        <button onclick="applyVendorTypeFilter()">Apply</button>
                                        <button onclick="clearVendorTypeFilter()">Clear</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        html += '<div>Status</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        // Apply client-side filters
                        let filteredVendors = data.data;

                        // Filter by name
                        if (vendorPaginationState.nameFilter && vendorPaginationState.nameFilter.length > 0) {
                            filteredVendors = filteredVendors.filter(vendor =>
                                vendorPaginationState.nameFilter.includes(vendor.vendor_full_name)
                            );
                        }

                        // Filter by type
                        if (vendorPaginationState.typeFilter && vendorPaginationState.typeFilter.length > 0) {
                            filteredVendors = filteredVendors.filter(vendor =>
                                vendorPaginationState.typeFilter.includes(vendor.vendor_type_category)
                            );
                        }

                        filteredVendors.forEach(vendor => {
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
                            html += `<button class="excel-btn" title="Export Payment History" onclick="exportVendorPaymentHistory(${vendor.vendor_id}, '${vendor.vendor_full_name.replace(/'/g, "\\'")}')"><i class="fas fa-file-excel"></i></button>`;
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
                        html += `
                            <div class="filter-header-cell">
                                Name
                                <span class="filter-icon" onclick="toggleLabourNameFilter(event)">
                                    <i class="fas fa-filter"></i>
                                </span>
                                <div id="labourNameFilterDropdown" class="filter-dropdown" style="display: none;">
                                    <div class="filter-search-box">
                                        <input type="text" id="labourNameFilterSearch" placeholder="Search names..." onkeyup="filterLabourNameOptions()">
                                    </div>
                                    <div class="filter-options" id="labourNameFilterOptions"></div>
                                    <div class="filter-actions">
                                        <button onclick="applyLabourNameFilter()">Apply</button>
                                        <button onclick="clearLabourNameFilter()">Clear</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        html += '<div>Contact</div>';
                        html += `
                            <div class="filter-header-cell">
                                Labour Type
                                <span class="filter-icon" onclick="toggleLabourTypeFilter(event)">
                                    <i class="fas fa-filter"></i>
                                </span>
                                <div id="labourTypeFilterDropdown" class="filter-dropdown" style="display: none;">
                                    <div class="filter-search-box">
                                        <input type="text" id="labourTypeFilterSearch" placeholder="Search types..." onkeyup="filterLabourTypeOptions()">
                                    </div>
                                    <div class="filter-options" id="labourTypeFilterOptions"></div>
                                    <div class="filter-actions">
                                        <button onclick="applyLabourTypeFilter()">Apply</button>
                                        <button onclick="clearLabourTypeFilter()">Clear</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        html += '<div>Salary/Day</div>';
                        html += '<div>Status</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        // Apply client-side filters
                        let filteredLabours = data.data;
                        
                        // Filter by name
                        if (labourPaginationState.nameFilter && labourPaginationState.nameFilter.length > 0) {
                            filteredLabours = filteredLabours.filter(labour => 
                                labourPaginationState.nameFilter.includes(labour.full_name)
                            );
                        }
                        
                        // Filter by type
                        if (labourPaginationState.typeFilter && labourPaginationState.typeFilter.length > 0) {
                            filteredLabours = filteredLabours.filter(labour => 
                                labourPaginationState.typeFilter.includes(labour.labour_type)
                            );
                        }

                        filteredLabours.forEach(labour => {
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
                            html += `<button class="excel-btn" title="Export Payment History" onclick="exportLabourPaymentHistory(${labour.id}, '${labour.full_name.replace(/'/g, "\\'")}')"><i class="fas fa-file-excel"></i></button>`;
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

        // Function to setup Vendor Category Filter with robust event delegation
        function setupVendorCategoryFilter(vendorCategoryDropdown) {
            if (!vendorCategoryDropdown) {
                return;
            }

            // Get the parent container which has both the button and the dropdown
            const filterContainer = vendorCategoryDropdown.parentElement;
            if (!filterContainer) {
                return;
            }

            const vendorCategoryFilterToggle = filterContainer.querySelector('.project-filter-btn');
            const vendorCategoryFilterSearch = vendorCategoryDropdown.querySelector('.excel-filter-search');
            const vendorCategoryFilterApply = vendorCategoryDropdown.querySelector('.excel-filter-apply-btn');
            const vendorCategoryFilterClear = vendorCategoryDropdown.querySelector('.excel-filter-clear-btn');
            const vendorCategoryFilterList = vendorCategoryDropdown.querySelector('.excel-filter-list');

            if (!vendorCategoryFilterToggle || !vendorCategoryFilterSearch || !vendorCategoryFilterApply || !vendorCategoryFilterClear) {
                return;
            }

            // Toggle dropdown visibility
            vendorCategoryFilterToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                vendorCategoryDropdown.classList.toggle('active');
                // Focus search input
                setTimeout(() => {
                    vendorCategoryFilterSearch.focus();
                }, 100);
            });

            // Search functionality
            vendorCategoryFilterSearch.addEventListener('keyup', function (e) {
                e.stopPropagation();
                const searchTerm = this.value.toLowerCase();
                const options = vendorCategoryFilterList.querySelectorAll('.filter-option[data-vendor-category]');
                const optgroups = vendorCategoryFilterList.querySelectorAll('.filter-optgroup');

                let anyVisibleInGroup;
                optgroups.forEach(optgroup => {
                    anyVisibleInGroup = false;
                    const groupOptions = optgroup.querySelectorAll('.filter-option[data-vendor-category]');

                    groupOptions.forEach(option => {
                        const text = option.textContent.toLowerCase();
                        const isVisible = text.includes(searchTerm);
                        option.style.display = isVisible ? 'flex' : 'none';
                        if (isVisible) anyVisibleInGroup = true;
                    });

                    // Hide optgroup if no options match
                    optgroup.style.display = anyVisibleInGroup ? 'flex' : 'none';
                });

                // Also handle standalone options (if any)
                options.forEach(option => {
                    if (!option.closest('.filter-optgroup')) {
                        const text = option.textContent.toLowerCase();
                        option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                    }
                });
            });

            // Apply filter
            vendorCategoryFilterApply.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const vendorCategoryFilterOptions = vendorCategoryFilterList.querySelectorAll('.filter-option[data-vendor-category]');
                // Get individual contractor IDs (these are the specific vendor/labour IDs)
                const selectedContractors = Array.from(vendorCategoryFilterOptions)
                    .filter(opt => {
                        const checkbox = opt.querySelector('input[type="checkbox"]');
                        return checkbox && checkbox.checked;
                    })
                    .map(opt => opt.getAttribute('data-vendor-category'))
                    .join(',');

                // Store selected contractors in pagination state for UI restoration
                entriesPaginationState.selectedVendorCategories = selectedContractors;

                // Also store in vendorCategory for API filtering (send contractor IDs, not category types)
                entriesPaginationState.vendorCategory = selectedContractors;

                // Get current filter state
                const mainSearch = entriesPaginationState.search || '';
                const mainStatus = entriesPaginationState.status || '';
                const mainDateFrom = entriesPaginationState.dateFrom || '';
                const mainDateTo = entriesPaginationState.dateTo || '';
                const mainProjectType = entriesPaginationState.projectType || '';
                const mainPaidBy = entriesPaginationState.paidBy || '';

                // Reload payment entries with specific contractor IDs (not category types)
                loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, mainProjectType, selectedContractors, mainPaidBy);
                vendorCategoryDropdown.classList.remove('active');
            });

            // Clear filter
            vendorCategoryFilterClear.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const vendorCategoryFilterOptions = vendorCategoryFilterList.querySelectorAll('.filter-option[data-vendor-category]');
                const optgroups = vendorCategoryFilterList.querySelectorAll('.filter-optgroup');

                vendorCategoryFilterOptions.forEach(opt => {
                    const checkbox = opt.querySelector('input[type="checkbox"]');
                    if (checkbox) checkbox.checked = false;
                    opt.classList.remove('active');
                    opt.style.display = 'flex';
                });

                // Show all optgroups
                optgroups.forEach(optgroup => {
                    optgroup.style.display = 'flex';
                });

                vendorCategoryFilterSearch.value = '';

                // Get current filter state
                const mainSearch = entriesPaginationState.search || '';
                const mainStatus = entriesPaginationState.status || '';
                const mainDateFrom = entriesPaginationState.dateFrom || '';
                const mainDateTo = entriesPaginationState.dateTo || '';
                const mainProjectType = entriesPaginationState.projectType || '';
                const mainPaidBy = entriesPaginationState.paidBy || '';

                // Clear vendor category from state
                entriesPaginationState.selectedVendorCategories = '';
                entriesPaginationState.vendorCategory = '';

                // Reload without vendor category filter
                loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, mainProjectType, '', mainPaidBy);
                vendorCategoryDropdown.classList.remove('active');
            });

            // Checkbox/Option click handler using event delegation
            vendorCategoryFilterList.addEventListener('click', function (e) {
                e.stopPropagation();
                const option = e.target.closest('.filter-option[data-vendor-category]');
                if (!option) return;

                const checkbox = option.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    option.classList.toggle('active');
                }
            });

            // Close dropdown when clicking outside
            const closeDropdownHandler = function (e) {
                const isClickInsideDropdown = e.target.closest('#vendorCategoryFilterDropdown');
                const isClickOnToggle = e.target.closest('.project-filter-btn') && filterContainer.contains(e.target);

                if (!isClickInsideDropdown && !isClickOnToggle) {
                    vendorCategoryDropdown.classList.remove('active');
                }
            };

            // Remove old listener if it exists
            document.removeEventListener('click', closeDropdownHandler);
            document.addEventListener('click', closeDropdownHandler);
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
                        html += '<div class="project-filter-container"><span>Project Name</span><button class="project-filter-btn" id="projectFilterToggle" title="Filter by Project Name"><i class="fas fa-filter"></i></button><div class="project-filter-dropdown excel-filter-dropdown" id="projectFilterDropdown"><div class="excel-filter-header"><input type="text" class="excel-filter-search" placeholder="Search..." id="projectFilterSearch"><div class="excel-filter-actions"><button class="excel-filter-apply-btn" id="projectFilterApply">Apply</button><button class="excel-filter-clear-btn" id="projectFilterClear">Clear</button></div></div><div class="excel-filter-list"><div class="filter-option" data-project-id="">All Projects</div></div></div></div>';
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
                            const paymentDate = entry.payment_date ? new Date(entry.payment_date).toLocaleDateString('en-GB', { year: 'numeric', month: '2-digit', day: '2-digit' }) : 'N/A';

                            // Build Paid To list - filter by current vendor category filter if applied
                            let paidToHtml = '<div class="paid-to-list">';
                            if (entry.paid_to && entry.paid_to.length > 0) {
                                // Filter recipients based on current filter state
                                let filteredRecipients = entry.paid_to;

                                if (entriesPaginationState.vendorCategory && entriesPaginationState.vendorCategory.length > 0) {
                                    const selectedCategories = entriesPaginationState.vendorCategory.split(',').map(c => c.trim());
                                    filteredRecipients = entry.paid_to.filter(recipient => {
                                        // Check if recipient's ID matches any selected ID
                                        return selectedCategories.some(selectedId => {
                                            // Compare recipient ID with selected filter ID
                                            // Convert both to string to ensure type safety
                                            return recipient.id && recipient.id.toString() === selectedId.toString();
                                        });
                                    });
                                }

                                if (filteredRecipients.length > 0) {
                                    filteredRecipients.forEach(recipient => {
                                        const categoryBracket = recipient.vendor_category ? ` [${recipient.vendor_category}]` : '';
                                        paidToHtml += `<div class="paid-to-item ${recipient.type}">${recipient.type === 'vendor' ? '👤' : '👷'} ${recipient.name}${categoryBracket}</div>`;
                                    });
                                } else {
                                    paidToHtml += '<div class="paid-to-item" style="border-left-color: #a0aec0;">No data</div>';
                                }
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
                            html += `<div class="vendor-cell"><div style="display: flex; align-items: center; gap: 8px;"><small style="background: #f0f4f8; padding: 4px 8px; border-radius: 4px; display: inline-block;">${entry.payment_mode.replace(/_/g, ' ').toUpperCase()}</small><button style="background: none; border: none; cursor: pointer; font-size: 1.1em; color: #ea580c; transition: all 0.2s; padding: 4px 8px; border-radius: 4px;" title="View Attachments" onclick="openPaymentModeAttachmentsModal(${entry.payment_entry_id})"><i class="fas fa-paperclip"></i></button></div></div>`;
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
                            html += `<div style="font-weight: 700; color: #7c2d12; font-size: 0.9em;">₹${parseFloat(entry.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
                            html += '</div>';

                            // Payment Date
                            html += '<div style="border-left: 3px solid #d53f8c; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">PAYMENT DATE</div>`;
                            html += `<div style="font-weight: 700; color: #6b2142; font-size: 0.9em;">${entry.payment_date ? new Date(entry.payment_date).toLocaleDateString('en-GB', { year: 'numeric', month: '2-digit', day: '2-digit' }) : 'N/A'}</div>`;
                            html += '</div>';

                            // Status
                            html += '<div style="border-left: 3px solid #9333ea; padding: 8px 12px; background: white; border-radius: 3px;">';
                            html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">STATUS</div>`;
                            html += `<div><span class="vendor-status ${statusClass}" style="display: inline-block;">${entry.status.toUpperCase()}</span></div>`;
                            html += '</div>';

                            html += '</div>';

                            // Paid To section with small items
                            if (entry.paid_to && entry.paid_to.length > 0) {
                                // Filter recipients for detailed view if filter is active
                                let detailsRecipients = entry.paid_to;
                                if (entriesPaginationState.vendorCategory && entriesPaginationState.vendorCategory.length > 0) {
                                    const selectedCategoriesDetails = entriesPaginationState.vendorCategory.split(',').map(c => c.trim());
                                    detailsRecipients = entry.paid_to.filter(recipient => {
                                        return selectedCategoriesDetails.some(selectedId => {
                                            return recipient.id && recipient.id.toString() === selectedId.toString();
                                        });
                                    });
                                }

                                detailsRecipients.forEach((recipient, index) => {
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
                                    html += `<div style="font-weight: 700; color: #15803d; font-size: 0.95em;">₹${parseFloat(recipient.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
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

                        // Initialize Excel-style filter for Project Name
                        const projectFilterToggle = document.getElementById('projectFilterToggle');
                        const projectFilterDropdown = document.getElementById('projectFilterDropdown');
                        const projectFilterSearch = document.getElementById('projectFilterSearch');
                        const projectFilterApply = document.getElementById('projectFilterApply');
                        const projectFilterClear = document.getElementById('projectFilterClear');
                        const projectFilterList = projectFilterDropdown.querySelector('.excel-filter-list');

                        // Load project names dynamically
                        function loadProjectFilters() {
                            const params = new URLSearchParams({
                                search: entriesPaginationState.search || '',
                                status: entriesPaginationState.status || '',
                                dateFrom: entriesPaginationState.dateFrom || '',
                                dateTo: entriesPaginationState.dateTo || '',
                                vendorCategory: entriesPaginationState.vendorCategory || '',
                                paidBy: entriesPaginationState.paidBy || ''
                            });

                            fetch(`get_project_names.php?${params.toString()}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.projects && data.projects.length > 0) {
                                        // Clear existing options except the first one
                                        while (projectFilterList.children.length > 1) {
                                            projectFilterList.removeChild(projectFilterList.lastChild);
                                        }

                                        // Add project options
                                        data.projects.forEach(project => {
                                            const option = document.createElement('div');
                                            option.className = 'filter-option';
                                            option.setAttribute('data-project-id', project.id);

                                            const checkbox = document.createElement('input');
                                            checkbox.type = 'checkbox';
                                            option.appendChild(checkbox);

                                            const label = document.createElement('span');
                                            label.textContent = project.display_label;
                                            option.appendChild(label);

                                            projectFilterList.appendChild(option);
                                        });
                                    }
                                })
                                .catch(err => {
                                    console.error('Error loading project names:', err);
                                });
                        }

                        // Load projects on first load
                        loadProjectFilters();

                        if (projectFilterToggle && projectFilterDropdown) {
                            // Toggle dropdown
                            projectFilterToggle.addEventListener('click', function (e) {
                                e.stopPropagation();
                                projectFilterDropdown.classList.toggle('active');
                                projectFilterSearch.focus();
                            });

                            // Search functionality
                            projectFilterSearch.addEventListener('keyup', function (e) {
                                const searchTerm = this.value.toLowerCase();
                                const projectFilterOptions = projectFilterList.querySelectorAll('.filter-option[data-project-id]');
                                projectFilterOptions.forEach(option => {
                                    const text = option.textContent.toLowerCase();
                                    option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                                });
                            });

                            // Apply filter
                            projectFilterApply.addEventListener('click', function (e) {
                                e.stopPropagation();
                                const projectFilterOptions = projectFilterList.querySelectorAll('.filter-option[data-project-id]');
                                const selectedProjectIds = Array.from(projectFilterOptions)
                                    .filter(opt => opt.querySelector('input[type="checkbox"]')?.checked && opt.getAttribute('data-project-id') !== '')
                                    .map(opt => opt.getAttribute('data-project-id'))
                                    .join(',');

                                const mainSearch = entriesPaginationState.search || '';
                                const mainStatus = entriesPaginationState.status || '';
                                const mainDateFrom = entriesPaginationState.dateFrom || '';
                                const mainDateTo = entriesPaginationState.dateTo || '';
                                const mainVendorCategory = entriesPaginationState.vendorCategory || '';
                                const mainPaidBy = entriesPaginationState.paidBy || '';

                                loadPaymentEntries(10, 1, mainSearch, mainStatus, mainDateFrom, mainDateTo, selectedProjectIds, mainVendorCategory, mainPaidBy);
                                projectFilterDropdown.classList.remove('active');
                            });

                            // Clear filter
                            projectFilterClear.addEventListener('click', function (e) {
                                e.stopPropagation();
                                const projectFilterOptions = projectFilterList.querySelectorAll('.filter-option[data-project-id]');
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
                            projectFilterList.addEventListener('click', function (e) {
                                const option = e.target.closest('.filter-option[data-project-id]');
                                if (option) {
                                    e.stopPropagation();
                                    const checkbox = option.querySelector('input[type="checkbox"]');
                                    if (checkbox) {
                                        checkbox.checked = !checkbox.checked;
                                        option.classList.toggle('active');
                                    }
                                }
                            });

                            // Close dropdown when clicking outside
                            document.addEventListener('click', function (e) {
                                if (!e.target.closest('.project-filter-container') && e.target.id !== 'projectFilterToggle') {
                                    projectFilterDropdown.classList.remove('active');
                                }
                            });
                        }



                        // Load vendor and labour contractors for filter
                        fetch('get_all_recipients_grouped.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.groups && data.groups.length > 0) {
                                    const vendorCategoryDropdown = document.getElementById('vendorCategoryFilterDropdown');

                                    if (!vendorCategoryDropdown) {
                                        return;
                                    }

                                    const vendorCategoryFilterList = vendorCategoryDropdown.querySelector('.excel-filter-list');

                                    if (!vendorCategoryFilterList) {
                                        return;
                                    }

                                    // Clear existing options except the first one
                                    while (vendorCategoryFilterList.children.length > 1) {
                                        vendorCategoryFilterList.removeChild(vendorCategoryFilterList.lastChild);
                                    }

                                    // Create dynamic optgroups based on API response
                                    data.groups.forEach(group => {
                                        const groupDiv = document.createElement('div');
                                        groupDiv.className = 'filter-optgroup';
                                        groupDiv.setAttribute('data-group-type', group.type);

                                        const groupLabel = document.createElement('div');
                                        groupLabel.className = 'filter-optgroup-label';
                                        groupLabel.textContent = `${group.icon} ${group.display_name.toUpperCase()}`;
                                        groupDiv.appendChild(groupLabel);

                                        // Add contractors in this group
                                        group.contractors.forEach(contractor => {
                                            const option = document.createElement('div');
                                            option.className = 'filter-option';
                                            option.setAttribute('data-vendor-category', contractor.id);
                                            option.setAttribute('data-contractor-name', contractor.name);
                                            option.setAttribute('data-group-type', group.type);
                                            option.setAttribute('data-category-type', group.type); // Store actual category type for API
                                            option.innerHTML = `<input type="checkbox"> ${contractor.name}`;

                                            // Restore checked state if this specific contractor ID is in the current filter
                                            const selectedCategories = entriesPaginationState.selectedVendorCategories || '';
                                            if (selectedCategories) {
                                                // Split by comma and check for EXACT match (not substring)
                                                const selectedIds = selectedCategories.split(',').map(id => id.trim());
                                                const isSelected = selectedIds.find(id => id === contractor.id.toString());

                                                if (isSelected) {
                                                    const checkbox = option.querySelector('input[type="checkbox"]');
                                                    if (checkbox) {
                                                        checkbox.checked = true;
                                                        option.classList.add('active');
                                                    }
                                                }
                                            }

                                            groupDiv.appendChild(option);
                                        });

                                        vendorCategoryFilterList.appendChild(groupDiv);
                                    });

                                    // Setup Vendor Category filter handlers using event delegation
                                    setupVendorCategoryFilter(vendorCategoryDropdown);
                                }
                            })
                            .catch(error => {
                                console.error('Error loading recipients:', error);
                            });                        // Load users for Paid By filter - fetch from database (ALL users, not just visible ones)
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
                                        paidByFilterToggle.addEventListener('click', function (e) {
                                            e.stopPropagation();
                                            paidByFilterDropdown.classList.toggle('active');
                                            paidByFilterSearch.focus();
                                        });

                                        // Search functionality
                                        paidByFilterSearch.addEventListener('keyup', function (e) {
                                            const searchTerm = this.value.toLowerCase();
                                            paidByFilterOptions.forEach(option => {
                                                const text = option.textContent.toLowerCase();
                                                option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                                            });
                                        });

                                        // Apply filter
                                        paidByFilterApply.addEventListener('click', function (e) {
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
                                        paidByFilterClear.addEventListener('click', function (e) {
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
                                            option.addEventListener('click', function (e) {
                                                e.stopPropagation();
                                                const checkbox = this.querySelector('input[type="checkbox"]');
                                                if (checkbox) {
                                                    checkbox.checked = !checkbox.checked;
                                                    this.classList.toggle('active');
                                                }
                                            });
                                        });

                                        // Close dropdown when clicking outside
                                        document.addEventListener('click', function (e) {
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
                            btn.addEventListener('click', function (e) {
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
                                <p>No payment entries found matching your criteria.</p>
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
            openPaymentEditModal(entryId);
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

        // Open modal to show payment mode attachments
        function openPaymentModeAttachmentsModal(paymentEntryId) {
            // Create wrapper for modal
            const modalWrapper = document.createElement('div');
            modalWrapper.id = 'paymentModeAttachmentsWrapper';
            modalWrapper.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 10000;';

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.id = 'paymentModeAttachmentsModal';
            modalContent.style.cssText = 'background: white; border-radius: 12px; padding: 24px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);';

            modalContent.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #1a365d; font-size: 1.3em;">
                        <i class="fas fa-paperclip"></i> Payment Mode Attachments
                    </h3>
                    <button id="paymentModeAttachmentsCloseBtn" style="background: none; border: none; font-size: 1.5em; cursor: pointer; color: #718096; padding: 0; width: 30px; height: 30px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="paymentModeAttachmentsContent" style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #cbd5e0;"></i>
                    <p style="color: #718096; margin-top: 10px;">Loading attachments...</p>
                </div>
            `;

            modalWrapper.appendChild(modalContent);
            document.body.appendChild(modalWrapper);

            // Add close button event listener
            document.getElementById('paymentModeAttachmentsCloseBtn').addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closePaymentModeAttachmentsModal();
            });

            // Close modal when clicking outside the content
            modalWrapper.addEventListener('click', function (e) {
                if (e.target === modalWrapper) {
                    closePaymentModeAttachmentsModal();
                }
            });

            // Fetch acceptance methods and file attachments data for this payment entry
            fetch(`fetch_payment_acceptance_methods.php?payment_entry_id=${paymentEntryId}`)
                .then(response => response.json())
                .then(data => {
                    const contentDiv = document.getElementById('paymentModeAttachmentsContent');

                    let html = '';
                    let hasContent = false;

                    // Display acceptance methods first
                    if (data.acceptance_methods && data.acceptance_methods.length > 0) {
                        hasContent = true;
                        data.acceptance_methods.forEach((method, index) => {
                            html += `
                                <div style="background: #f7fafc; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid #ea580c; text-align: left;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; color: #1a365d; font-size: 0.95em;">
                                                <i class="fas fa-money-bill" style="margin-right: 8px;"></i>${method.payment_method_type.toUpperCase()}
                                            </div>
                                            <div style="font-size: 0.85em; color: #718096; margin-top: 4px;">
                                                Amount: <strong>₹${parseFloat(method.amount_received_value).toFixed(2)}</strong>
                                            </div>
                                            ${method.reference_number_cheque ? `
                                                <div style="font-size: 0.85em; color: #718096; margin-top: 2px;">
                                                    Reference: <strong>${method.reference_number_cheque}</strong>
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    ${method.supporting_document_path ? `
                                        <div style="display: flex; gap: 8px; margin-top: 10px;">
                                            <a href="${method.supporting_document_path}" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; background: #3182ce; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="${method.supporting_document_path}" download style="display: inline-flex; align-items: center; gap: 8px; background: #10b981; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600;">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        });
                    }

                    // Display file attachments from registry
                    if (data.file_attachments && data.file_attachments.length > 0) {
                        hasContent = true;
                        data.file_attachments.forEach((file, index) => {
                            const fileIcon = getFileIcon(file.attachment_file_extension);
                            const fileTypeLabel = file.attachment_type_category === 'proof_image' ? 'Payment Proof' : 'Supporting Document';

                            html += `
                                <div style="background: #f7fafc; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid #10b981; text-align: left;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; color: #1a365d; font-size: 0.95em;">
                                                <i class="${fileIcon}" style="margin-right: 8px;"></i>${fileTypeLabel}
                                            </div>
                                            <div style="font-size: 0.85em; color: #718096; margin-top: 4px;">
                                                File: <strong>${file.attachment_file_original_name}</strong>
                                            </div>
                                            <div style="font-size: 0.85em; color: #718096; margin-top: 2px;">
                                                Size: <strong>${formatFileSize(file.attachment_file_size_bytes)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                                        <a href="${file.attachment_file_stored_path}" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; background: #3182ce; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="${file.attachment_file_stored_path}" download style="display: inline-flex; align-items: center; gap: 8px; background: #10b981; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600;">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    if (!hasContent) {
                        contentDiv.innerHTML = `
                            <div style="text-align: center; padding: 20px;">
                                <i class="fas fa-inbox" style="font-size: 2em; color: #cbd5e0;"></i>
                                <p style="color: #718096; margin-top: 10px;">No payment mode attachments found for this entry</p>
                            </div>
                        `;
                    } else {
                        contentDiv.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error loading attachments:', error);
                    const contentDiv = document.getElementById('paymentModeAttachmentsContent');
                    contentDiv.innerHTML = `
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-exclamation-circle" style="font-size: 2em; color: #f56565;"></i>
                            <p style="color: #e53e3e; margin-top: 10px;">Error loading attachments</p>
                        </div>
                    `;
                });
        }

        // Helper function to get file icon based on extension
        function getFileIcon(extension) {
            const ext = extension.toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
                return 'fas fa-image';
            } else if (ext === 'pdf') {
                return 'fas fa-file-pdf';
            } else if (['doc', 'docx', 'txt'].includes(ext)) {
                return 'fas fa-file-word';
            } else if (['mp4', 'mov', 'avi', 'mkv'].includes(ext)) {
                return 'fas fa-video';
            } else {
                return 'fas fa-file';
            }
        }

        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Close payment mode attachments modal
        function closePaymentModeAttachmentsModal() {
            const wrapper = document.getElementById('paymentModeAttachmentsWrapper');
            if (wrapper) {
                wrapper.style.opacity = '0';
                wrapper.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    if (wrapper.parentElement) {
                        wrapper.parentElement.removeChild(wrapper);
                    }
                }, 300);
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
                setTimeout(function () {
                    const tabBtn = document.querySelector(`[data-tab="${tabName}"]`);
                    if (tabBtn) {
                        // Trigger click to activate the tab
                        tabBtn.click();
                    }
                }, 500);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Management Search Functionality
            let searchTimeout;
            const managementSearchInput = document.getElementById('managementSearchInput');
            const managementSearchResults = document.getElementById('managementSearchResults');

            if (managementSearchInput) {
                // Search on input with debounce
                managementSearchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();

                    if (query.length < 2) {
                        managementSearchResults.classList.remove('active');
                        managementSearchResults.innerHTML = '';
                        return;
                    }

                    // Debounce search
                    searchTimeout = setTimeout(() => {
                        performManagementSearch(query);
                    }, 300);
                });

                // Close results when clicking outside
                document.addEventListener('click', function (e) {
                    if (!managementSearchInput.contains(e.target) && !managementSearchResults.contains(e.target)) {
                        managementSearchResults.classList.remove('active');
                    }
                });

                // Focus input shows results if they exist
                managementSearchInput.addEventListener('focus', function () {
                    if (managementSearchResults.innerHTML) {
                        managementSearchResults.classList.add('active');
                    }
                });
            }

            function performManagementSearch(query) {
                // Show loading
                managementSearchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
                managementSearchResults.classList.add('active');

                // Search API
                fetch(`search_management.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayManagementSearchResults(data.vendors, data.labours);
                        } else {
                            managementSearchResults.innerHTML = '<div class="search-no-results">Error searching. Please try again.</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        managementSearchResults.innerHTML = '<div class="search-no-results">Error searching. Please try again.</div>';
                    });
            }

            function displayManagementSearchResults(vendors, labours) {
                let html = '';

                if (vendors.length === 0 && labours.length === 0) {
                    html = '<div class="search-no-results"><i class="fas fa-search"></i> No results found</div>';
                } else {
                    // Vendors section
                    if (vendors.length > 0) {
                        html += '<div class="search-result-category"><i class="fas fa-user-tie"></i> Vendors</div>';
                        vendors.forEach(vendor => {
                            html += `
                                <div class="search-result-item" onclick="selectVendor(${vendor.vendor_id})">
                                    <div class="search-result-icon vendor">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="search-result-info">
                                        <div class="search-result-name">${vendor.vendor_full_name}</div>
                                        <div class="search-result-details">${vendor.vendor_unique_code} • ${vendor.vendor_type_category}</div>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    // Labours section
                    if (labours.length > 0) {
                        html += '<div class="search-result-category"><i class="fas fa-hard-hat"></i> Labours</div>';
                        labours.forEach(labour => {
                            html += `
                                <div class="search-result-item" onclick="selectLabour(${labour.id})">
                                    <div class="search-result-icon labour">
                                        <i class="fas fa-hard-hat"></i>
                                    </div>
                                    <div class="search-result-info">
                                        <div class="search-result-name">${labour.full_name}</div>
                                        <div class="search-result-details">${labour.labour_unique_code} • ${labour.labour_type}</div>
                                    </div>
                                </div>
                            `;
                        });
                    }
                }

                managementSearchResults.innerHTML = html;
            }

            window.selectVendor = function (vendorId) {
                // Close search results
                managementSearchResults.classList.remove('active');
                managementSearchInput.value = '';

                // Open vendor details modal
                viewVendor(vendorId);
            };

            window.selectLabour = function (labourId) {
                // Close search results
                managementSearchResults.classList.remove('active');
                managementSearchInput.value = '';

                // Open labour details modal
                viewLabour(labourId);
            };

            // Recently Added Records Toggle Functionality
            const recentRecordsToggleBtn = document.getElementById('recentRecordsToggleBtn');
            if (recentRecordsToggleBtn) {
                // Track toggle state
                let entriesExpanded = true;

                recentRecordsToggleBtn.addEventListener('click', function () {
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
                btn.addEventListener('click', function (e) {
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
            toggleFilterBtn.addEventListener('click', function () {
                filterContent.classList.toggle('collapsed');
                toggleFilterBtn.classList.toggle('active');
            });

            // Apply filter functionality
            applyFilterBtn.addEventListener('click', function () {
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
            resetFilterBtn.addEventListener('click', function () {
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
                toggleRecordsBtn.addEventListener('click', function () {
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
                addVendorBtn.addEventListener('click', function () {
                    console.log('Add Vendor clicked');
                    const modal = document.getElementById('addVendorModal');
                    if (modal) {
                        modal.classList.add('active');
                    }
                });
            }

            if (addLabourBtn) {
                addLabourBtn.addEventListener('click', function () {
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
                addPaymentBtn.addEventListener('click', function () {
                    console.log('Add Payment Entry clicked');
                    if (typeof window.openPaymentEntryModal === 'function') {
                        window.openPaymentEntryModal();
                    } else {
                        alert('Payment Entry modal not found. Please include modals/add_payment_entry_modal.php');
                    }
                });
            }

            if (viewReportBtn) {
                viewReportBtn.addEventListener('click', function () {
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
                applyRecordsFilterBtn.addEventListener('click', function () {
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
                resetRecordsFilterBtn.addEventListener('click', function () {
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

            // Export to Excel functionality
            const exportToExcelBtn = document.getElementById('exportToExcelBtn');
            if (exportToExcelBtn) {
                exportToExcelBtn.addEventListener('click', function () {
                    // Get date range from filter inputs
                    const dateFrom = document.getElementById('recordsDateFrom').value;
                    const dateTo = document.getElementById('recordsDateTo').value;

                    // Validate dates
                    if (dateFrom && dateTo && dateFrom > dateTo) {
                        alert('From Date cannot be after To Date');
                        return;
                    }

                    // Show loading state
                    const originalText = exportToExcelBtn.innerHTML;
                    exportToExcelBtn.innerHTML = '<i class="fas fa-spinner"></i> Exporting...';
                    exportToExcelBtn.disabled = true;

                    // Build query parameters
                    let params = 'export_payment_entries_excel.php?';
                    if (dateFrom) {
                        params += 'dateFrom=' + encodeURIComponent(dateFrom) + '&';
                    }
                    if (dateTo) {
                        params += 'dateTo=' + encodeURIComponent(dateTo) + '&';
                    }

                    // Remove trailing &
                    params = params.replace(/&$/, '');

                    // Initiate download
                    window.location.href = params;

                    // Reset button after download starts
                    setTimeout(function () {
                        exportToExcelBtn.innerHTML = originalText;
                        exportToExcelBtn.disabled = false;
                    }, 1000);
                });
            }
        });

    </script>
    <script src="js/management_filters.js"></script>