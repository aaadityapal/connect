 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project-Based Manager Payouts | Finance Dashboard</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --warning-color: #fca311;
            --danger-color: #ef233c;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 100%;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: margin 0.3s ease;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .page-title i {
            font-size: 32px;
            color: var(--primary-color);
        }

        .actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #3a9a38;
            transform: translateY(-2px);
        }

        .dashboard {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
        }

        .sidebar {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            height: fit-content;
        }

        .sidebar h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-title {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eaedf2;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group h4 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .filter-group h4:hover {
            color: var(--primary-color);
        }
        
        .filter-group h4 i:first-child {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .filter-group h4 i:last-child {
            font-size: 0.8rem;
            color: #999;
            transition: transform 0.3s ease;
        }
        
        .filter-group.collapsed h4 i:last-child {
            transform: rotate(-90deg);
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-option {
            padding: 6px 8px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
            cursor: pointer;
            justify-content: space-between;
        }
        
        .filter-option:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .filter-option input {
            accent-color: var(--primary-color);
        }

        .form-check-input {
            width: 16px;
            height: 16px;
            margin-top: 0;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            cursor: pointer;
            margin-right: auto;
            color: #555;
        }
        
        .badge-count {
            background-color: #f0f0f0;
            color: #666;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .badge-count.paid {
            background-color: rgba(75, 181, 67, 0.15);
            color: var(--success-color);
        }
        
        .badge-count.pending {
            background-color: rgba(252, 163, 17, 0.15);
            color: var(--warning-color);
        }
        
        .badge-count.processing {
            background-color: rgba(67, 97, 238, 0.15);
            color: var(--primary-color);
        }
        
        .date-range-inputs {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .date-input-group label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 500;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .date-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }
        
        .range-slider {
            padding: 0 5px;
        }
        
        .range-values {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 8px;
        }
        
        .form-range {
            width: 100%;
            height: 6px;
            -webkit-appearance: none;
            appearance: none;
            background: #e0e0e0;
            border-radius: 3px;
            outline: none;
            margin: 10px 0;
        }
        
        .form-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        .form-range::-webkit-slider-thumb:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .filter-badge i {
            cursor: pointer;
            font-size: 0.7rem;
            opacity: 0.7;
            transition: all 0.2s ease;
        }
        
        .filter-badge:hover {
            background-color: rgba(67, 97, 238, 0.15);
        }
        
        .filter-badge i:hover {
            opacity: 1;
        }
        
        .filter-actions {
            display: flex;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #eaedf2;
        }
        
        .filter-actions button {
            padding: 7px 15px;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .filter-actions .btn-outline-secondary {
            color: #6c757d;
            border: 1px solid #ced4da;
            background-color: white;
        }
        
        .filter-actions .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #495057;
        }
        
        .filter-actions .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }
        
        .filter-actions .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .content-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-card .header i {
            font-size: 24px;
            color: var(--primary-color);
            padding: 10px;
            border-radius: 50%;
            background-color: rgba(67, 97, 238, 0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .stat-card .change {
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .change.positive {
            color: var(--success-color);
        }

        .change.negative {
            color: var(--danger-color);
        }

        .payouts-table {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
            background-color: var(--primary-color);
        }

        td {
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }

        th:first-child, td:first-child {
            padding-left: 25px;
        }

        th:last-child, td:last-child {
            padding-right: 25px;
        }

        thead {
            background-color: var(--primary-color);
            color: white;
        }

        th i {
            margin-left: 5px;
        }

        tbody tr {
            border-bottom: 1px solid #eee;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status.paid {
            background-color: rgba(75, 181, 67, 0.1);
            color: var(--success-color);
        }

        .status.pending {
            background-color: rgba(252, 163, 17, 0.1);
            color: var(--warning-color);
        }

        .status.processing {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-color);
            font-size: 16px;
            margin: 0 5px;
        }

        .pagination {
            display: flex;
            justify-content: flex-end;
            padding: 15px;
            background-color: white;
            border-top: 1px solid #eee;
        }

        .pagination button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: none;
            margin: 0 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pagination button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination button:hover:not(.active) {
            background-color: #f0f0f0;
        }

        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: 250px 1fr;
            }
        }

        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .dashboard .sidebar {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 768px) {
            .payouts-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
            
            .sidebar#sidebar {
                transform: translateX(-100%);
            }

            .container {
                margin-left: 0;
                padding: 15px;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar#sidebar.show {
                transform: translateX(0);
            }
            
            th, td {
                padding: 10px;
            }
            
            th:first-child, td:first-child {
                padding-left: 15px;
            }

            th:last-child, td:last-child {
                padding-right: 15px;
            }

            .content-section {
                padding: 15px;
            }
            
            .section-header h2 {
                font-size: 20px;
            }
        }

        /* Left Sidebar Styles from hr_dashboard.php */
        .sidebar#sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar#sidebar.collapsed {
            transform: translateX(-100%);
        }

        .container.expanded {
            margin-left: 0;
        }

        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary-color);
            color: white;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link {
            color: var(--dark-color);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 1rem;
            color: #dc3545!important;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Original responsive styles */
        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .actions {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Fix conflict with existing sidebar class */
        .dashboard .sidebar {
            position: static;
            width: 250px;
            height: auto;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .sidebar#sidebar {
                transform: translateX(-100%);
            }

            .container {
                margin-left: 0;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar#sidebar.show {
                transform: translateX(0);
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow: auto;
            padding: 50px 0;
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            max-width: 800px;
            margin: 1.75rem auto;
            position: relative;
        }
        
        .modal-content {
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .btn-close {
            background: transparent;
            border: 0;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #000;
            opacity: 0.5;
            cursor: pointer;
        }
        
        .btn-close:hover {
            opacity: 0.75;
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            gap: 0.5rem;
        }
        
        /* Form Styles */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -0.5rem;
            margin-left: -0.5rem;
        }
        
        .col-md-4, .col-md-6 {
            position: relative;
            width: 100%;
            padding-right: 0.5rem;
            padding-left: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .col-md-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }
        
        .mb-3 {
            margin-bottom: 1rem;
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            display: block;
            font-weight: 500;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            color: #495057;
            background-color: #fff;
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            width: 100%;
        }
        
        .input-group-text {
            display: flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            text-align: center;
            white-space: nowrap;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0.25rem 0 0 0.25rem;
        }
        
        .input-group > .form-control {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            color: #fff;
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
        }
        
        .is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        body.modal-open {
            overflow: hidden;
        }
        
        /* Projects Table Styles */
        .projects-table {
            overflow-x: auto;
            width: 100%;
        }
        
        .project-type {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .project-type.architecture {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .project-type.interior {
            background-color: rgba(75, 181, 67, 0.1);
            color: var(--success-color);
        }
        
        .project-type.construction {
            background-color: rgba(252, 163, 17, 0.1);
            color: var(--warning-color);
        }
        
        .status.active {
            background-color: rgba(75, 181, 67, 0.1);
            color: var(--success-color);
        }
        
        .status.completed {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .status.on-hold {
            background-color: rgba(252, 163, 17, 0.1);
            color: var(--warning-color);
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .progress-bar {
            flex-grow: 1;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
        }
        
        tr:nth-child(1) .progress-fill, 
        tr:nth-child(4) .progress-fill {
            background-color: var(--primary-color);
        }
        
        tr:nth-child(2) .progress-fill, 
        tr:nth-child(5) .progress-fill {
            background-color: var(--warning-color);
        }
        
        tr:nth-child(3) .progress-fill {
            background-color: var(--success-color);
        }
        
        .progress-text {
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
            min-width: 40px;
            text-align: right;
        }
        
        /* Project Details Modal Styles */
        .detail-label {
            color: #6c757d;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .section-divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 1.5rem 0;
        }
        
        .section-subheading {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .budget-overview {
            display: flex;
            gap: 20px;
            margin-bottom: 1rem;
        }
        
        .budget-card {
            flex: 1;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
        }
        
        .budget-card h6 {
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .budget-amount {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        #detailBudget {
            color: var(--dark-color);
        }
        
        #detailPaid {
            color: var(--success-color);
        }
        
        #detailRemaining {
            color: var(--warning-color);
        }
        
        .payout-table {
            margin-top: 1rem;
            overflow-x: auto;
        }
        
        .payout-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payout-table th {
            background-color: #f8f9fa;
            color: var(--dark-color);
            font-size: 0.8rem;
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .payout-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .manager-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .avatar {
            width: 30px;
            height: 30px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status.upcoming {
            background-color: rgba(149, 164, 252, 0.1);
            color: #6366f1;
        }

        /* Enhanced Modal Styles */
        .modal-content {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .modal-header {
            background-color: #f8f9fa;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eaedf2;
        }
        
        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .btn-close {
            background: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            opacity: 1;
            color: #6c757d;
            font-size: 1.1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .btn-close:hover {
            background-color: #f3f4f6;
            transform: rotate(90deg);
            color: var(--danger-color);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            background-color: #f8f9fa;
            padding: 1rem 1.5rem;
            border-top: 1px solid #eaedf2;
        }
        
        .project-info-header {
            position: relative;
            background-color: #fff;
            border-radius: 12px;
            padding: 5px 0;
        }
        
        .detail-label {
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #212529;
            margin-bottom: 1.5rem;
        }
        
        .budget-overview {
            gap: 15px;
            margin-top: 0.5rem;
        }
        
        .budget-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.2rem;
            text-align: center;
            border: 1px solid #eaedf2;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .budget-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .budget-card h6 {
            font-size: 0.8rem;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 0.75rem;
        }
        
        .budget-amount {
            font-size: 1.5rem;
            font-weight: 600;
            line-height: 1.2;
        }
        
        #detailBudget {
            color: #212529;
        }
        
        #detailPaid {
            color: var(--success-color);
        }
        
        #detailRemaining {
            color: var(--warning-color);
        }
        
        .section-divider {
            height: 1px;
            background-color: #eaedf2;
            margin: 2rem 0;
        }
        
        .section-subheading {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-subheading i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .payout-table {
            margin-top: 1.25rem;
        }
        
        .payout-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .payout-table th {
            background-color: #f8f9fa;
            color: #495057;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #eaedf2;
        }
        
        .payout-table td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 0.95rem;
            color: #495057;
        }
        
        .payout-table tr:last-child td {
            border-bottom: none;
        }
        
        .payout-table tr:hover td {
            background-color: #f8f9fa;
        }
        
        .manager-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .avatar {
            width: 36px;
            height: 36px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status:before {
            content: "";
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status.paid {
            background-color: rgba(75, 181, 67, 0.1);
            color: var(--success-color);
        }
        
        .status.paid:before {
            background-color: var(--success-color);
        }
        
        .status.pending {
            background-color: rgba(252, 163, 17, 0.1);
            color: var(--warning-color);
        }
        
        .status.pending:before {
            background-color: var(--warning-color);
        }
        
        .status.upcoming {
            background-color: rgba(149, 164, 252, 0.1);
            color: #6366f1;
        }
        
        .status.upcoming:before {
            background-color: #6366f1;
        }
        
        .status.on-hold {
            background-color: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .status.on-hold:before {
            background-color: #6b7280;
        }
        
        .status.active {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .status.active:before {
            background-color: #3b82f6;
        }
        
        .status.completed {
            background-color: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }
        
        .status.completed:before {
            background-color: #6366f1;
        }
        
        .project-type {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .project-type:before {
            content: "";
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .project-type.architecture {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .project-type.architecture:before {
            background-color: var(--primary-color);
        }
        
        .project-type.interior {
            background-color: rgba(75, 181, 67, 0.1);
            color: var(--success-color);
        }
        
        .project-type.interior:before {
            background-color: var(--success-color);
        }
        
        .project-type.construction {
            background-color: rgba(252, 163, 17, 0.1);
            color: var(--warning-color);
        }
        
        .project-type.construction:before {
            background-color: var(--warning-color);
        }
        
        .modal-footer .btn {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .modal-footer .btn-primary {
            background-color: var(--primary-color);
            border: none;
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.15);
        }
        
        .modal-footer .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(67, 97, 238, 0.2);
        }
        
        .modal-footer .btn-secondary {
            background-color: #fff;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .modal-footer .btn-secondary:hover {
            background-color: #f8f9fa;
            color: #495057;
        }
        
        /* Modal Animation */
        .modal.show .modal-dialog {
            animation: modalFadeIn 0.3s ease-out forwards;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="payouts.php" class="nav-link active">
                <i class="bi bi-cash-coin"></i>
                Manager Payouts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="construction_site_overview.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Added Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Add toggle sidebar button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <div class="container">
        <header>
            <div class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                <h1>Managers Payouts by Projects</h1>
            </div>
            <div class="actions">
                <button class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Project For Payout
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-download"></i> Export Report
                </button>
                <button class="btn btn-outline">
                    <i class="fas fa-filter"></i> Advanced Filters
                </button>
            </div>
        </header>

        <div class="dashboard">
            <div class="sidebar">
                <div class="filter-title">
                    <i class="fas fa-sliders-h"></i> Smart Filters
                </div>
                
                <div class="filter-group">
                    <h4><i class="far fa-calendar-alt"></i> Date Range <i class="fas fa-chevron-down"></i></h4>
                    <div class="date-range-inputs">
                        <div class="date-input-group">
                            <label>From</label>
                            <input type="date" class="form-control date-input" placeholder="Start Date">
                        </div>
                        <div class="date-input-group">
                            <label>To</label>
                            <input type="date" class="form-control date-input" placeholder="End Date">
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <h4><i class="fas fa-project-diagram"></i> Project Status <i class="fas fa-chevron-down"></i></h4>
                    <div class="filter-options">
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="all-projects" checked>
                            <span class="form-check-label">All Projects</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="active-projects">
                            <span class="form-check-label">Active</span>
                            <span class="badge-count">4</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="completed-projects">
                            <span class="form-check-label">Completed</span>
                            <span class="badge-count">1</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="onhold-projects">
                            <span class="form-check-label">On Hold</span>
                            <span class="badge-count">1</span>
                        </label>
                    </div>
                </div>

                <div class="filter-group">
                    <h4><i class="fas fa-money-check-alt"></i> Payout Status <i class="fas fa-chevron-down"></i></h4>
                    <div class="filter-options">
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="all-statuses" checked>
                            <span class="form-check-label">All Statuses</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="paid-status">
                            <span class="form-check-label">Paid</span>
                            <span class="badge-count paid">7</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="pending-status">
                            <span class="form-check-label">Pending</span>
                            <span class="badge-count pending">3</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" class="form-check-input" id="processing-status">
                            <span class="form-check-label">Processing</span>
                            <span class="badge-count processing">2</span>
                        </label>
                    </div>
                </div>
                
                <div class="filter-group">
                    <h4><i class="fas fa-rupee-sign"></i> Amount Range <i class="fas fa-chevron-down"></i></h4>
                    <div class="range-slider">
                        <div class="range-values">
                            <span>₹0</span>
                            <span>₹5,00,00,000</span>
                        </div>
                        <input type="range" class="form-range" min="0" max="50000000" value="50000000">
                    </div>
                </div>

                <div class="active-filters">
                    <div class="filter-badge">
                        Active <i class="fas fa-times"></i>
                    </div>
                    <div class="filter-badge">
                        Paid <i class="fas fa-times"></i>
                    </div>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-sm btn-outline-secondary">Reset</button>
                    <button class="btn btn-sm btn-primary">Apply Filters</button>
                </div>
            </div>

            <div class="main-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2>Payment Statistics</h2>
                    </div>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="header">
                            <h3>Total Payouts</h3>
                            <i class="fas fa-wallet"></i>
                        </div>
                            <div class="value">₹1,24,580</div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 12% from last month
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="header">
                            <h3>Active Projects</h3>
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="value">18</div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 3 new projects
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="header">
                            <h3>Pending Payouts</h3>
                            <i class="fas fa-clock"></i>
                        </div>
                            <div class="value">₹32,450</div>
                        <div class="change negative">
                            <i class="fas fa-arrow-down"></i> 8% from last month
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="header">
                            <h3>Managers</h3>
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="value">24</div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 2 new managers
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Projects Details</h2>
                    </div>
                    <div class="projects-table">
                        <table>
                            <thead>
                                <tr>
                                                                <th>Project Name</th>
                            <th>Project Type</th>
                            <th>Amount (₹)</th>
                            <th>Progress</th>
                            <th>Stage</th>
                            <th>Actions</th>
                        </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Godrej Central Park</td>
                                    <td>Residential</td>
                                    <td>1,25,00,000</td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 65%"></div>
                                            </div>
                                            <div class="progress-text">65%</div>
                                        </div>
                                    </td>
                                                                <td>Stage 7/10</td>
                            <td class="actions-cell">
                                        <button class="action-btn" title="View Details"><i class="bi bi-eye"></i></button>
                                        <button class="action-btn" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="action-btn" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DLF Cyber City Phase 3</td>
                                    <td>Commercial</td>
                                    <td>3,50,00,000</td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 30%"></div>
                                            </div>
                                            <div class="progress-text">30%</div>
                                        </div>
                                    </td>
                                                                <td>Stage 3/10</td>
                            <td class="actions-cell">
                                        <button class="action-btn" title="View Details"><i class="bi bi-eye"></i></button>
                                        <button class="action-btn" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="action-btn" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Oberoi Sky Heights</td>
                                    <td>Residential</td>
                                    <td>75,50,000</td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                            </div>
                                            <div class="progress-text">100%</div>
                                        </div>
                                    </td>
                                                                <td>Stage 10/10</td>
                            <td class="actions-cell">
                                        <button class="action-btn" title="View Details"><i class="bi bi-eye"></i></button>
                                        <button class="action-btn" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="action-btn" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Prestige Lakeside Habitat</td>
                                    <td>Residential</td>
                                    <td>2,15,00,000</td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 45%"></div>
                                            </div>
                                            <div class="progress-text">45%</div>
                                        </div>
                                    </td>
                                                                <td>Stage 5/10</td>
                            <td class="actions-cell">
                                        <button class="action-btn" title="View Details"><i class="bi bi-eye"></i></button>
                                        <button class="action-btn" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="action-btn" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Lodha World Towers</td>
                                    <td>Mixed Use</td>
                                    <td>5,75,00,000</td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: 78%"></div>
                                            </div>
                                            <div class="progress-text">78%</div>
                                        </div>
                                    </td>
                                                                <td>Stage 8/10</td>
                            <td class="actions-cell">
                                        <button class="action-btn" title="View Details"><i class="bi bi-eye"></i></button>
                                        <button class="action-btn" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="action-btn" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="pagination">
                            <button><i class="fas fa-chevron-left"></i></button>
                            <button class="active">1</button>
                            <button>2</button>
                            <button>3</button>
                            <button><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Manager Payouts</h2>
                    </div>
                <div class="payouts-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Manager <i class="fas fa-sort"></i></th>
                                <th>Project <i class="fas fa-sort"></i></th>
                                <th>Payout Amount <i class="fas fa-sort"></i></th>
                                <th>Payout Date <i class="fas fa-sort"></i></th>
                                <th>Status <i class="fas fa-sort"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        Sarah Johnson
                                    </div>
                                </td>
                                <td>Phoenix Marketing Campaign</td>
                                    <td>₹12,500</td>
                                <td>Jun 15, 2023</td>
                                <td><span class="status paid">Paid</span></td>
                                <td>
                                    <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn" title="Download Invoice"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        Michael Chen
                                    </div>
                                </td>
                                <td>Global ERP Implementation</td>
                                    <td>₹18,750</td>
                                <td>Jun 18, 2023</td>
                                <td><span class="status processing">Processing</span></td>
                                <td>
                                    <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn" title="Download Invoice"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        David Rodriguez
                                    </div>
                                </td>
                                <td>Mobile App Development</td>
                                    <td>₹9,800</td>
                                <td>Jun 20, 2023</td>
                                <td><span class="status pending">Pending</span></td>
                                <td>
                                    <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn" title="Download Invoice"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        Emily Wilson
                                    </div>
                                </td>
                                <td>Website Redesign</td>
                                    <td>₹7,200</td>
                                <td>Jun 22, 2023</td>
                                <td><span class="status paid">Paid</span></td>
                                <td>
                                    <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn" title="Download Invoice"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        Robert Kim
                                    </div>
                                </td>
                                <td>Data Migration Project</td>
                                    <td>₹15,300</td>
                                <td>Jun 25, 2023</td>
                                <td><span class="status processing">Processing</span></td>
                                <td>
                                    <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn" title="Download Invoice"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pagination">
                        <button><i class="fas fa-chevron-left"></i></button>
                        <button class="active">1</button>
                        <button>2</button>
                        <button>3</button>
                        <button><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add this JavaScript function to populate the project details modal
        document.addEventListener('DOMContentLoaded', function() {
            // Sample project data
            const projectData = {
                name: "Godrej Central Park",
                type: "Residential Complex",
                location: "Sector 33, Gurugram",
                status: "active",
                budget: 12500000,
                paid: 8125000,
                remaining: 4375000,
                payouts: [
                    {
                        manager: { initials: "RK", name: "Rahul Kumar" },
                        amount: 2500000,
                        date: "Jan 25, 2023",
                        method: "Bank Transfer",
                        status: "paid"
                    },
                    {
                        manager: { initials: "AS", name: "Ananya Singh" },
                        amount: 3125000,
                        date: "Mar 15, 2023",
                        method: "UPI",
                        status: "paid"
                    },
                    {
                        manager: { initials: "VP", name: "Vikram Patel" },
                        amount: 2500000,
                        date: "May 10, 2023",
                        method: "Net Banking",
                        status: "paid"
                    },
                    {
                        manager: { initials: "RK", name: "Rahul Kumar" },
                        amount: 1875000,
                        date: "July 05, 2023",
                        method: "Bank Transfer",
                        status: "pending"
                    },
                    {
                        manager: { initials: "VP", name: "Vikram Patel" },
                        amount: 2500000,
                        date: "Sep 15, 2023",
                        method: "UPI",
                        status: "upcoming"
                    }
                ]
            };
            
            // Function to format currency as Indian Rupees
            function formatIndianRupees(amount) {
                const formatter = new Intl.NumberFormat('en-IN', {
                    style: 'currency',
                    currency: 'INR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
                return formatter.format(amount).replace('₹', '₹ ');
            }
            
            // Function to update project details modal with data
            function updateProjectDetailsModal(project) {
                // Set basic project info
                document.getElementById('detailProjectName').textContent = project.name;
                document.getElementById('detailProjectType').textContent = project.type;
                document.getElementById('detailLocation').textContent = project.location;
                
                // Set status with appropriate class
                const statusElement = document.getElementById('detailStatus');
                statusElement.textContent = project.status.charAt(0).toUpperCase() + project.status.slice(1);
                statusElement.className = `status ${project.status}`;
                
                // Set budget information
                document.getElementById('detailBudget').textContent = formatIndianRupees(project.budget);
                document.getElementById('detailPaid').textContent = formatIndianRupees(project.paid);
                document.getElementById('detailRemaining').textContent = formatIndianRupees(project.remaining);
                
                // Populate payout history table
                const payoutHistoryTable = document.getElementById('payoutHistoryTable');
                payoutHistoryTable.innerHTML = '';
                
                project.payouts.forEach(payout => {
                    const row = document.createElement('tr');
                    
                    // Manager column
                    const managerCell = document.createElement('td');
                    managerCell.innerHTML = `
                        <div class="manager-info">
                            <div class="avatar">${payout.manager.initials}</div>
                            <div>${payout.manager.name}</div>
                        </div>
                    `;
                    
                    // Amount column
                    const amountCell = document.createElement('td');
                    amountCell.textContent = formatIndianRupees(payout.amount);
                    
                    // Date column
                    const dateCell = document.createElement('td');
                    dateCell.textContent = payout.date;
                    
                    // Method column
                    const methodCell = document.createElement('td');
                    methodCell.textContent = payout.method;
                    
                    // Status column
                    const statusCell = document.createElement('td');
                    statusCell.innerHTML = `<span class="status ${payout.status}">${payout.status.charAt(0).toUpperCase() + payout.status.slice(1)}</span>`;
                    
                    // Append cells to row
                    row.appendChild(managerCell);
                    row.appendChild(amountCell);
                    row.appendChild(dateCell);
                    row.appendChild(methodCell);
                    row.appendChild(statusCell);
                    
                    // Append row to table
                    payoutHistoryTable.appendChild(row);
                });
            }
            
            // Get all view detail buttons
            const viewDetailButtons = document.querySelectorAll('.btn-eye');
            
            // Add click event listener to each button
            viewDetailButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // In a real application, you would fetch project data based on project ID
                    // For this example, we'll use the sample data
                    updateProjectDetailsModal(projectData);
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('projectDetailsModal'));
                    modal.show();
                });
            });
            
            // Add click event listener to "Schedule New Payout" button
            document.getElementById('schedulePayoutBtn').addEventListener('click', function() {
                // In a real application, this would open a form to schedule a new payout
                // For this example, we'll just close the current modal and open the add project modal
                const currentModal = bootstrap.Modal.getInstance(document.getElementById('projectDetailsModal'));
                currentModal.hide();
                
                // Wait for the current modal to close before opening the new one
                setTimeout(() => {
                    const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'));
                    addProjectModal.show();
                }, 500);
            });
        });
    </script>
</body>
</html>