<!DOCTYPE html>
<html lang="en" style="height: 100%; overflow: auto;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Vendor Management - HR System</title>
    <style>
        :root {
            --primary-color: #2c7be5;
            --secondary-color: #333333;
            --success-color: #00d97e;
            --danger-color: #ff5733;
            --warning-color: #f6c343;
            --light-color: #f5f7fb;
            --dark-color: #34495e;
            --white: #ffffff;
            --gray: #95a5a6;
            --border-color: #eef2f7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        }

        html, body {
            height: 100%;
            min-height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
            background-color: #f8fafc;
        }

        body {
            background-color: #f8fafc;
            color: var(--dark-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 25px;
            margin-left: 0;
            transition: margin-left 0.3s ease;
            width: 100%;
            margin-top: 0;
            position: relative;
        }

        header {
            background-color: var(--white);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: -0.5px;
        }

        .logo span {
            color: var(--secondary-color);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-profile img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .dashboard {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            margin-top: 0;
            padding-top: 0;
            width: 100%;
        }

        .main-content {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-top: 0;
            width: 100%;
            margin-left: 0;
            border: 1px solid var(--border-color);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-header div {
            display: flex;
            gap: 12px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--secondary-color);
            letter-spacing: -0.3px;
        }

        .btn {
            padding: 11px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            letter-spacing: 0.2px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: #1a68d1;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(44, 123, 229, 0.2);
        }

        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }

        .btn-success:hover {
            background-color: #00b86f;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 217, 126, 0.2);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #e04a2d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 87, 51, 0.2);
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: var(--white);
        }

        .btn-warning:hover {
            background-color: #e6b739;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(246, 195, 67, 0.2);
        }

        .btn-info {
            background-color: #5b6a87;
            color: var(--white);
        }

        .btn-info:hover {
            background-color: #4a5870;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(91, 106, 135, 0.2);
        }

        .action-btn {
            padding: 7px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        
        .action-btn.btn-info {
            background-color: #e9f7fe;
            color: #17a2b8;
            border: 1px solid #d1ecf1;
        }
        
        .action-btn.btn-info:hover {
            background-color: #b3e5fc;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(25, 152, 183, 0.1);
        }
        
        .action-btn.btn-primary {
            background-color: #e3f2fd;
            color: var(--primary-color);
            border: 1px solid #bbdefb;
        }
        
        .action-btn.btn-primary:hover {
            background-color: #bbdefb;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(44, 123, 229, 0.1);
        }
        
        .action-btn.btn-danger {
            background-color: #ffebee;
            color: var(--danger-color);
            border: 1px solid #ffcdd2;
        }
        
        .action-btn.btn-danger:hover {
            background-color: #ffcdd2;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(255, 87, 51, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }

        th, td {
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #ffffff;
            font-weight: 600;
            color: #6c757d;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .badge-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background-color: #fff8e1;
            color: #f57f17;
        }

        .badge-danger {
            background-color: #ffebee;
            color: #c62828;
        }

        .badge-info {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .vendor-details-container {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0;
            margin: 15px 0 25px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        
        .vendor-details-header {
            background-color: #f8fafc;
            padding: 18px 25px;
            border-bottom: 1px solid var(--border-color);
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .vendor-details-header i {
            color: var(--primary-color);
            font-size: 20px;
        }
        
        .vendor-details-content {
            padding: 25px;
        }
        
        .vendor-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .detail-section {
            padding: 0;
            border-bottom: none;
        }
        
        .detail-section h6 {
            border-bottom: none;
            padding: 0 0 15px 0;
            margin: 0 0 20px 0;
            color: var(--secondary-color);
            font-size: 16px;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .detail-section h6 i {
            font-size: 18px;
            color: var(--primary-color);
            background-color: #e3f2fd;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .detail-section p {
            margin: 12px 0;
            font-size: 15px;
            line-height: 1.6;
            display: flex;
            align-items: flex-start;
        }
        
        .detail-section strong {
            color: #6c757d;
            font-weight: 500;
            min-width: 170px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-section strong i {
            font-size: 14px;
            width: 20px;
            text-align: center;
            color: var(--primary-color);
        }
        
        .detail-section span {
            color: #212529;
            font-weight: 500;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            width: 550px;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #95a5a6;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .close-btn:hover {
            background-color: #f1f3f4;
            color: #333333;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background-color: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.15);
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .search-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 25px;
        }

        .search-box {
            flex: 1;
            max-width: 450px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 13px 15px 13px 45px;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            background-color: #ffffff;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.15);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 16px;
        }

        .filter-dropdown {
            position: relative;
        }

        .filter-btn {
            padding: 13px 18px;
            background-color: var(--white);
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            font-weight: 500;
            color: #495057;
        }

        .filter-btn:hover {
            border-color: #d1d1d1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .filter-options {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--white);
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 8px 0;
            min-width: 220px;
            z-index: 100;
            display: none;
            margin-top: 8px;
        }

        .filter-options.show {
            display: block;
        }

        .filter-option {
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.15s ease;
            font-weight: 500;
            color: #495057;
        }

        .filter-option:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }

        .stats-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
            overflow-x: auto;
            padding: 5px 0 15px 0;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 180px;
            border: 1px solid #f1f3f4;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .stat-card i {
            font-size: 24px;
            margin-bottom: 15px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f8f9fa;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        .card-primary i {
            color: var(--primary-color);
            background-color: #e3f2fd;
        }

        .card-success i {
            color: var(--success-color);
            background-color: #e8f5e9;
        }

        .card-warning i {
            color: var(--warning-color);
            background-color: #fff8e1;
        }

        .card-danger i {
            color: var(--danger-color);
            background-color: #ffebee;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background-color: #f8f9fa;
        }

        .page-link.active {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        
        /* New Professional Pagination Styles */
        .vendor-pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            padding: 20px 0;
        }

        .pagination-controls {
            display: flex;
            list-style: none;
            gap: 8px;
            padding: 0;
            margin: 0;
            align-items: center;
        }

        .pagination-item {
            list-style: none;
        }

        .pagination-link {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .pagination-link:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            border-color: #d1d1d1;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .pagination-link.active {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            box-shadow: 0 4px 8px rgba(44, 123, 229, 0.2);
        }

        .pagination-link.active:hover {
            background-color: #1a68d1;
            transform: translateY(-2px);
        }
        
        .pagination-link.disabled {
            background-color: #f1f3f4;
            color: #95a5a6;
            border-color: #e1e5eb;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .pagination-link.disabled:hover {
            background-color: #f1f3f4;
            color: #95a5a6;
            border-color: #e1e5eb;
            transform: none;
            box-shadow: none;
        }
        
        @media (max-width: 992px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                flex-wrap: nowrap;
                padding-bottom: 20px;
            }

            .search-filter {
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }
            
            .container {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
                padding-bottom: 20px;
            }
            
            .page-header div {
                width: 100%;
                justify-content: space-between;
            }
            
            .main-content {
                padding: 25px 20px;
                border-radius: 10px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 13px;
                gap: 8px;
            }
            
            .table-container {
                margin: 0 -15px;
                width: calc(100% + 30px);
            }
            
            table {
                font-size: 13px;
            }
            
            th, td {
                padding: 12px 15px;
            }
            
            .mobile-vendor-card {
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 3px 8px rgba(0,0,0,0.05);
                padding: 20px;
                margin-bottom: 20px;
                border: 1px solid var(--border-color);
            }
            
            .mobile-vendor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid var(--border-color);
            }
            
            .mobile-vendor-name {
                font-weight: 700;
                font-size: 18px;
                color: var(--secondary-color);
            }
            
            .mobile-vendor-id {
                font-size: 14px;
                color: #6c757d;
                background-color: #f8f9fa;
                padding: 4px 10px;
                border-radius: 15px;
                font-weight: 600;
            }
            
            .mobile-vendor-info {
                font-size: 14px;
                margin-bottom: 20px;
            }
            
            .mobile-vendor-info p {
                margin: 8px 0;
                display: flex;
                align-items: center;
            }
            
            .mobile-vendor-info p span:first-child {
                font-weight: 600;
                width: 90px;
                color: #6c757d;
                font-size: 13px;
            }
            
            .mobile-vendor-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding-top: 15px;
                border-top: 1px solid var(--border-color);
            }
            
            .action-btn {
                padding: 8px 12px;
                font-size: 12px;
                gap: 5px;
            }
            
            .modal-content {
                width: 95%;
                border-radius: 10px;
            }
            
            .modal-title {
                font-size: 18px;
            }
            
            .form-control {
                padding: 10px;
                font-size: 13px;
            }
            
            /* Mobile Pagination */
            .vendor-pagination {
                margin-top: 25px;
                padding: 15px 0;
            }

            .pagination-controls {
                gap: 6px;
            }

            .pagination-link {
                padding: 8px 12px;
                font-size: 13px;
                min-width: 36px;
                border-radius: 6px;
            }
            
            .pagination-link.disabled {
                background-color: #f1f3f4;
                color: #95a5a6;
                border-color: #e1e5eb;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
                padding: 8px 12px;
                font-size: 13px;
                min-width: 36px;
                border-radius: 6px;
            }
            
            .pagination-link.disabled:hover {
                background-color: #f1f3f4;
                color: #95a5a6;
                border-color: #e1e5eb;
                transform: none;
                box-shadow: none;
            }
        }
        
        /* Extra small devices (phones, 375px and down) */
        @media (max-width: 375px) {
            .stats-cards {
                gap: 12px;
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            .stat-card {
                padding: 16px 12px;
                min-width: 150px;
            }
            
            .stat-card .value {
                font-size: 24px;
            }
            
            .stat-card i {
                font-size: 20px;
                width: 40px;
                height: 40px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
                gap: 6px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .mobile-title {
                font-size: 16px;
            }
            
            /* Optimize table for very small screens */
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 10px 12px;
            }
            
            /* Hide less important columns on very small screens */
            table th:nth-child(4), 
            table td:nth-child(4),
            table th:nth-child(5), 
            table td:nth-child(5) {
                display: none;
            }
            
            .mobile-vendor-card {
                padding: 16px;
            }
            
            .mobile-vendor-header {
                margin-bottom: 12px;
                padding-bottom: 12px;
            }
            
            .mobile-vendor-name {
                font-size: 16px;
            }
            
            .mobile-vendor-info {
                font-size: 12px;
            }
            
            .mobile-vendor-info p {
                margin: 6px 0;
            }
            
            .mobile-vendor-info p span:first-child {
                font-size: 11px;
                width: 75px;
            }
            
            .mobile-vendor-actions {
                gap: 8px;
                padding-top: 12px;
            }
            
            /* Make action buttons smaller */
            .action-btn {
                padding: 6px 10px;
                font-size: 11px;
                gap: 4px;
            }
            
            /* Adjust modal for small screens */
            .modal-content {
                width: 96%;
                padding: 0;
            }
            
            .modal-title {
                font-size: 16px;
            }
            
            .form-control {
                padding: 8px;
                font-size: 12px;
            }
            
            /* Mobile Pagination for Small Screens */
            .vendor-pagination {
                margin-top: 20px;
                padding: 12px 0;
            }

            .pagination-controls {
                gap: 4px;
            }

            .pagination-link {
                padding: 6px 10px;
                font-size: 12px;
                min-width: 32px;
                border-radius: 5px;
            }
            
            .pagination-link.disabled {
                background-color: #f1f3f4;
                color: #95a5a6;
                border-color: #e1e5eb;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
                padding: 6px 10px;
                font-size: 12px;
                min-width: 32px;
                border-radius: 5px;
            }
            
            .pagination-link.disabled:hover {
                background-color: #f1f3f4;
                color: #95a5a6;
                border-color: #e1e5eb;
                transform: none;
                box-shadow: none;
            }
        }
        
        /* Fix for content visibility on larger screens */
        html, body {
            height: 100%;
            min-height: 100%;
        }
        
        .dashboard {
            min-height: calc(100vh - 40px);
            margin-top: 0;
            padding-top: 0;
        }
        
        /* Force content to top of page */
        body {
            display: block !important;
        }
        


    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body onload="window.scrollTo(0,0);">

    <div class="container">
        <div class="dashboard">

            <div class="main-content" style="margin-left: 0;">
                <div class="page-header">
                    <h1 class="page-title">Vendor Management</h1>
                    <div>
                        <button class="btn btn-primary" id="addVendorBtn">
                            <i class="fas fa-plus"></i> Add Vendor
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                        <button class="btn btn-warning">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <div class="stats-cards">
                    <div class="stat-card card-primary">
                        <i class="fas fa-users"></i>
                        <div class="value" id="totalVendors">0</div>
                        <div class="label">Total Vendors</div>
                    </div>
                    <div class="stat-card card-success">
                        <i class="fas fa-check-circle"></i>
                        <div class="value" id="activeVendors">0</div>
                        <div class="label">Active Vendors</div>
                    </div>
                    <div class="stat-card card-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="value" id="pendingVendors">0</div>
                        <div class="label">Pending Review</div>
                    </div>
                    <div class="stat-card card-danger">
                        <i class="fas fa-times-circle"></i>
                        <div class="value" id="inactiveVendors">0</div>
                        <div class="label">Inactive Vendors</div>
                    </div>
                </div>

                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search vendors...">
                    </div>
                    <div class="filter-dropdown">
                        <button class="filter-btn" id="filterBtn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <div class="filter-options" id="filterOptions">
                            <div class="filter-option" data-filter="all">All Vendors</div>
                            <div class="filter-option" data-filter="active">Active Only</div>
                            <div class="filter-option" data-filter="pending">Pending Review</div>
                            <div class="filter-option" data-filter="inactive">Inactive</div>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="desktop-table">
                        <table id="vendorsTable">
                            <thead>
                                <tr>
                                    <th>Vendor ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Services</th>
                                    <th>Contract Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Vendor data will be populated here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="responsive-table-wrapper" style="display: none;">
                        <div id="mobileVendorCards">
                            <!-- Mobile vendor cards will be populated here by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="vendor-pagination">
                    <ul class="pagination-controls">
                        <li class="pagination-item"><a href="#" class="pagination-link">Previous</a></li>
                        <li class="pagination-item"><a href="#" class="pagination-link active">1</a></li>
                        <li class="pagination-item"><a href="#" class="pagination-link">2</a></li>
                        <li class="pagination-item"><a href="#" class="pagination-link">3</a></li>
                        <li class="pagination-item"><a href="#" class="pagination-link">Next</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vendor Modal -->
    <div class="modal" id="vendorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Vendor</h3>
                <button class="close-btn" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="vendorForm">
                    <input type="hidden" id="vendorId">
                    <div class="form-group">
                        <label for="vendorName">Vendor Name</label>
                        <input type="text" id="vendorName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="contactPerson">Contact Person</label>
                        <input type="text" id="contactPerson" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="services">Services</label>
                        <select id="services" class="form-control" multiple>
                            <option value="IT Services">IT Services</option>
                            <option value="Consulting">Consulting</option>
                            <option value="Logistics">Logistics</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Facilities">Facilities</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="contractDate">Contract Date</label>
                        <input type="date" id="contractDate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" id="cancelBtn">Cancel</button>
                <button class="btn btn-success" id="saveBtn">Save Vendor</button>
            </div>
        </div>
    </div>

    <script>
        // Fetch vendors from API
        let vendors = [];

        // DOM elements
        const vendorsTable = document.getElementById('vendorsTable').getElementsByTagName('tbody')[0];
        const addVendorBtn = document.getElementById('addVendorBtn');
        const vendorModal = document.getElementById('vendorModal');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const saveBtn = document.getElementById('saveBtn');
        const vendorForm = document.getElementById('vendorForm');
        const searchInput = document.getElementById('searchInput');
        const filterBtn = document.getElementById('filterBtn');
        const filterOptions = document.getElementById('filterOptions');
        const filterOptionItems = document.querySelectorAll('.filter-option');

        // Current filter
        let currentFilter = 'all';
        let isEditing = false;
        let currentVendorId = null;
        let currentPage = 1;
        const vendorsPerPage = 20;

        // Initialize the page
        function init() {
            fetchVendors();
            setupEventListeners();
        }
        
        // Check screen size and toggle between desktop and mobile view
        function checkScreenSize() {
            const desktopTable = document.querySelector('.desktop-table');
            const mobileTable = document.querySelector('.responsive-table-wrapper');
            
            if (window.innerWidth <= 575) {
                desktopTable.style.display = 'none';
                mobileTable.style.display = 'block';
            } else {
                desktopTable.style.display = 'block';
                mobileTable.style.display = 'none';
            }
        }
        
        // Listen for window resize events
        window.addEventListener('resize', checkScreenSize);

        // Render vendors to the table
        function renderVendors(vendorsToRender) {
            // Calculate pagination
            const totalPages = Math.ceil(vendorsToRender.length / vendorsPerPage);
            const startIndex = (currentPage - 1) * vendorsPerPage;
            const endIndex = Math.min(startIndex + vendorsPerPage, vendorsToRender.length);
            const vendorsForCurrentPage = vendorsToRender.slice(startIndex, endIndex);
            
            vendorsTable.innerHTML = '';
            const mobileVendorCards = document.getElementById('mobileVendorCards');
            mobileVendorCards.innerHTML = '';
            
            // Update stats
            document.getElementById('totalVendors').textContent = vendorsToRender.length;
            
            // Count vendors by status
            let activeCount = 0;
            let pendingCount = 0;
            let inactiveCount = 0;
            
            vendorsToRender.forEach(vendor => {
                // Count vendors by status
                if (vendor.status === 'Active') {
                    activeCount++;
                } else if (vendor.status === 'Pending') {
                    pendingCount++;
                } else {
                    inactiveCount++;
                }
            });
            
            // Render vendors for current page
            vendorsForCurrentPage.forEach(vendor => {
                // Format services
                const services = vendor.services.join(', ');
                
                // Format contract date
                const contractDate = new Date(vendor.contractDate);
                const formattedDate = contractDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                
                // Status badge
                let statusBadge = '';
                let statusClass = '';
                if (vendor.status === 'Active') {
                    statusBadge = `<span class="badge badge-success">${vendor.status}</span>`;
                    statusClass = 'badge-success';
                } else if (vendor.status === 'Pending') {
                    statusBadge = `<span class="badge badge-warning">${vendor.status}</span>`;
                    statusClass = 'badge-warning';
                } else {
                    statusBadge = `<span class="badge badge-danger">${vendor.status}</span>`;
                    statusClass = 'badge-danger';
                }
                
                // Desktop table row
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${vendor.id}</td>
                    <td>${vendor.name}</td>
                    <td>${vendor.contact}<br><small>${vendor.email}</small></td>
                    <td>${services}</td>
                    <td>${formattedDate}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="action-btns">
                            <button class="action-btn btn-info toggle-details-btn" data-id="${vendor.id}" title="Toggle Details">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <button class="action-btn btn-primary edit-btn" data-id="${vendor.id}" title="Edit Vendor">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-danger delete-btn" data-id="${vendor.id}" title="Delete Vendor">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                vendorsTable.appendChild(row);
                
                // Create details row (hidden by default)
                const detailsRow = document.createElement('tr');
                detailsRow.className = 'vendor-details-row';
                detailsRow.id = `vendor-details-${vendor.id}`;
                detailsRow.style.display = 'none';
                detailsRow.innerHTML = `
                    <td colspan="7">
                        <div class="vendor-details-container">
                            <div class="vendor-details-header">
                                <i class="fas fa-info-circle"></i>
                                Vendor Details
                            </div>
                            <div class="vendor-details-content">
                                <div class="vendor-details-grid">
                                    <div class="detail-section">
                                        <h6><i class="fas fa-address-card"></i> Contact Information</h6>
                                        <p><strong><i class="fas fa-phone"></i> Phone:</strong> <span>${vendor.details.phone_number || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-mobile-alt"></i> Alt Phone:</strong> <span>${vendor.details.alternative_number || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-envelope"></i> Email:</strong> <span>${vendor.details.email || 'N/A'}</span></p>
                                    </div>
                                    <div class="detail-section">
                                        <h6><i class="fas fa-university"></i> Banking Information</h6>
                                        <p><strong><i class="fas fa-building"></i> Bank Name:</strong> <span>${vendor.details.bank_name || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-credit-card"></i> Account Number:</strong> <span>${vendor.details.account_number_masked || vendor.details.account_number || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-barcode"></i> Routing Number:</strong> <span>${vendor.details.routing_number_masked || vendor.details.routing_number || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-piggy-bank"></i> Account Type:</strong> <span>${vendor.details.account_type || 'N/A'}</span></p>
                                    </div>
                                    <div class="detail-section">
                                        <h6><i class="fas fa-map-marker-alt"></i> Address</h6>
                                        <p><strong><i class="fas fa-road"></i> Street:</strong> <span>${vendor.details.street_address || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-city"></i> City:</strong> <span>${vendor.details.city || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-flag"></i> State:</strong> <span>${vendor.details.state || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-mail-bulk"></i> ZIP:</strong> <span>${vendor.details.zip_code || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-globe-americas"></i> Country:</strong> <span>${vendor.details.country || 'N/A'}</span></p>
                                    </div>
                                    <div class="detail-section">
                                        <h6><i class="fas fa-file-invoice"></i> GST Information</h6>
                                        <p><strong><i class="fas fa-id-card"></i> GST Number:</strong> <span>${vendor.details.gst_number || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-calendar-alt"></i> Registration Date:</strong> <span>${vendor.details.gst_registration_date || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-map-pin"></i> State:</strong> <span>${vendor.details.gst_state || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-tags"></i> Type:</strong> <span>${vendor.details.gst_type || 'N/A'}</span></p>
                                    </div>
                                    <div class="detail-section">
                                        <h6><i class="fas fa-info-circle"></i> Additional Information</h6>
                                        <p><strong><i class="fas fa-user-tag"></i> Vendor Type:</strong> <span>${vendor.details.vendor_type || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-layer-group"></i> Category:</strong> <span>${vendor.details.vendor_category || 'N/A'}</span></p>
                                        <p><strong><i class="fas fa-sticky-note"></i> Notes:</strong> <span>${vendor.details.additional_notes || 'N/A'}</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                `;
                vendorsTable.appendChild(detailsRow);
                
                // Mobile card
                const mobileCard = document.createElement('div');
                mobileCard.className = 'mobile-vendor-card';
                mobileCard.innerHTML = `
                    <div class="mobile-vendor-header">
                        <div class="mobile-vendor-name">${vendor.name}</div>
                        <div class="mobile-vendor-id">${vendor.id}</div>
                    </div>
                    <div class="mobile-vendor-info">
                        <p><span>Contact:</span> <span>${vendor.contact}</span></p>
                        <p><span>Email:</span> <span>${vendor.email}</span></p>
                        <p><span>Phone:</span> <span>${vendor.phone}</span></p>
                        <p><span>Status:</span> <span class="badge ${statusClass}">${vendor.status}</span></p>
                    </div>
                    <div class="mobile-vendor-actions">
                        <button class="btn btn-info toggle-details-btn" data-id="${vendor.id}" title="Toggle Details">
                            <i class="fas fa-chevron-down"></i> Details
                        </button>
                        <button class="btn btn-primary edit-btn" data-id="${vendor.id}" title="Edit Vendor">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger delete-btn" data-id="${vendor.id}" title="Delete Vendor">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                mobileVendorCards.appendChild(mobileCard);
                
                // Create separate details element for mobile (hidden by default)
                const mobileDetails = document.createElement('div');
                mobileDetails.className = 'mobile-vendor-details';
                mobileDetails.id = `mobile-vendor-details-${vendor.id}`;
                mobileDetails.style.display = 'none';
                mobileDetails.style.marginTop = '20px';
                mobileDetails.innerHTML = `
                    <div class="vendor-details-container">
                        <div class="vendor-details-header">
                            <i class="fas fa-info-circle"></i>
                            Vendor Details
                        </div>
                        <div class="vendor-details-content">
                            <div class="vendor-details-grid">
                                <div class="detail-section">
                                    <h6><i class="fas fa-address-card"></i> Contact Information</h6>
                                    <p><strong><i class="fas fa-phone"></i> Phone:</strong> <span>${vendor.details.phone_number || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-mobile-alt"></i> Alt Phone:</strong> <span>${vendor.details.alternative_number || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-envelope"></i> Email:</strong> <span>${vendor.details.email || 'N/A'}</span></p>
                                </div>
                                <div class="detail-section">
                                    <h6><i class="fas fa-university"></i> Banking Information</h6>
                                    <p><strong><i class="fas fa-building"></i> Bank Name:</strong> <span>${vendor.details.bank_name || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-credit-card"></i> Account Number:</strong> <span>${vendor.details.account_number_masked || vendor.details.account_number || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-barcode"></i> Routing Number:</strong> <span>${vendor.details.routing_number_masked || vendor.details.routing_number || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-piggy-bank"></i> Account Type:</strong> <span>${vendor.details.account_type || 'N/A'}</span></p>
                                </div>
                                <div class="detail-section">
                                    <h6><i class="fas fa-map-marker-alt"></i> Address</h6>
                                    <p><strong><i class="fas fa-road"></i> Street:</strong> <span>${vendor.details.street_address || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-city"></i> City:</strong> <span>${vendor.details.city || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-flag"></i> State:</strong> <span>${vendor.details.state || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-mail-bulk"></i> ZIP:</strong> <span>${vendor.details.zip_code || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-globe-americas"></i> Country:</strong> <span>${vendor.details.country || 'N/A'}</span></p>
                                </div>
                                <div class="detail-section">
                                    <h6><i class="fas fa-file-invoice"></i> GST Information</h6>
                                    <p><strong><i class="fas fa-id-card"></i> GST Number:</strong> <span>${vendor.details.gst_number || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-calendar-alt"></i> Registration Date:</strong> <span>${vendor.details.gst_registration_date || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-map-pin"></i> State:</strong> <span>${vendor.details.gst_state || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-tags"></i> Type:</strong> <span>${vendor.details.gst_type || 'N/A'}</span></p>
                                </div>
                                <div class="detail-section">
                                    <h6><i class="fas fa-info-circle"></i> Additional Information</h6>
                                    <p><strong><i class="fas fa-user-tag"></i> Vendor Type:</strong> <span>${vendor.details.vendor_type || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-layer-group"></i> Category:</strong> <span>${vendor.details.vendor_category || 'N/A'}</span></p>
                                    <p><strong><i class="fas fa-sticky-note"></i> Notes:</strong> <span>${vendor.details.additional_notes || 'N/A'}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                mobileVendorCards.appendChild(mobileDetails);
            });
            
            // Update status counts
            document.getElementById('activeVendors').textContent = activeCount;
            document.getElementById('pendingVendors').textContent = pendingCount;
            document.getElementById('inactiveVendors').textContent = inactiveCount;
            
            // Add event listeners to edit and delete buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const vendorId = e.currentTarget.getAttribute('data-id');
                    editVendor(vendorId);
                });
            });
            
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const vendorId = e.currentTarget.getAttribute('data-id');
                    deleteVendor(vendorId);
                });
            });
            
            // Add event listeners to toggle details buttons
            document.querySelectorAll('.toggle-details-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const vendorId = e.currentTarget.getAttribute('data-id');
                    toggleVendorDetails(vendorId);
                });
            });
            
            // Render pagination controls
            renderPagination(totalPages);
            
            // Toggle between desktop and mobile view based on screen width
            checkScreenSize();
        }

        // Render pagination controls
        function renderPagination(totalPages) {
            const paginationContainer = document.querySelector('.vendor-pagination');
            const paginationControls = paginationContainer.querySelector('.pagination-controls');
            
            // Clear existing pagination
            paginationControls.innerHTML = '';
            
            // Previous button
            const prevItem = document.createElement('li');
            prevItem.className = 'pagination-item';
            prevItem.innerHTML = `<a href="#" class="pagination-link ${currentPage === 1 ? 'disabled' : ''}" data-page="prev">Previous</a>`;
            paginationControls.appendChild(prevItem);
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageItem = document.createElement('li');
                pageItem.className = 'pagination-item';
                pageItem.innerHTML = `<a href="#" class="pagination-link ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
                paginationControls.appendChild(pageItem);
            }
            
            // Next button
            const nextItem = document.createElement('li');
            nextItem.className = 'pagination-item';
            nextItem.innerHTML = `<a href="#" class="pagination-link ${currentPage === totalPages ? 'disabled' : ''}" data-page="next">Next</a>`;
            paginationControls.appendChild(nextItem);
            
            // Add event listeners to pagination links
            document.querySelectorAll('.pagination-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = e.target.getAttribute('data-page');
                    
                    if (page === 'prev' && currentPage > 1) {
                        currentPage--;
                    } else if (page === 'next' && currentPage < totalPages) {
                        currentPage++;
                    } else if (!isNaN(page)) {
                        currentPage = parseInt(page);
                    }
                    
                    // Re-render vendors with new page
                    renderVendors(vendors);
                });
            });
        }

        // Fetch vendors from API
        function fetchVendors() {
            fetch('api/get_all_vendors.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        vendors = data.vendors.map(vendor => ({
                            id: 'VEN' + (vendor.vendor_id < 10 ? '00' + vendor.vendor_id : vendor.vendor_id < 100 ? '0' + vendor.vendor_id : vendor.vendor_id),
                            name: vendor.full_name,
                            contact: vendor.full_name,
                            email: vendor.email,
                            phone: vendor.phone_number,
                            services: [vendor.vendor_type],
                            contractDate: vendor.created_at.split(' ')[0],
                            status: 'Active',
                            // Store all vendor details for the toggle functionality
                            details: vendor
                        }));
                        renderVendors(vendors);
                    } else {
                        console.error('Error fetching vendors:', data.message);
                        // Show error message to user
                        alert('Failed to load vendors: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching vendors:', error);
                    // Show error message to user
                    alert('Failed to load vendors. Please try again later.');
                });
        }

        // Set up event listeners
        function setupEventListeners() {
            // Add vendor button
            addVendorBtn.addEventListener('click', () => {
                isEditing = false;
                currentVendorId = null;
                document.getElementById('modalTitle').textContent = 'Add New Vendor';
                vendorForm.reset();
                vendorModal.style.display = 'flex';
            });
            
            // Close modal buttons
            closeModal.addEventListener('click', () => {
                vendorModal.style.display = 'none';
            });
            
            cancelBtn.addEventListener('click', () => {
                vendorModal.style.display = 'none';
            });
            
            // Save vendor
            saveBtn.addEventListener('click', saveVendor);
            
            // Search functionality
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                filterVendors(searchTerm, currentFilter);
            });
            
            // Filter dropdown
            filterBtn.addEventListener('click', () => {
                filterOptions.classList.toggle('show');
            });
            
            // Filter options
            filterOptionItems.forEach(option => {
                option.addEventListener('click', () => {
                    currentFilter = option.getAttribute('data-filter');
                    filterBtn.innerHTML = `<i class="fas fa-filter"></i> ${option.textContent}`;
                    filterOptions.classList.remove('show');
                    filterVendors(searchInput.value.toLowerCase(), currentFilter);
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target === vendorModal) {
                    vendorModal.style.display = 'none';
                }
            });
        }

        // Filter vendors based on search term and status filter
        function filterVendors(searchTerm, filter) {
            let filteredVendors = vendors;
            
            // Apply status filter
            if (filter !== 'all') {
                filteredVendors = filteredVendors.filter(vendor => vendor.status === filter || 
                    (filter === 'pending' && vendor.status === 'Pending') ||
                    (filter === 'active' && vendor.status === 'Active') ||
                    (filter === 'inactive' && vendor.status === 'Inactive'));
            }
            
            // Apply search filter
            if (searchTerm) {
                filteredVendors = filteredVendors.filter(vendor => 
                    vendor.name.toLowerCase().includes(searchTerm) ||
                    vendor.contact.toLowerCase().includes(searchTerm) ||
                    vendor.email.toLowerCase().includes(searchTerm) ||
                    vendor.phone.toLowerCase().includes(searchTerm) ||
                    vendor.id.toLowerCase().includes(searchTerm) ||
                    vendor.services.some(service => service.toLowerCase().includes(searchTerm))
                );
            }
            
            renderVendors(filteredVendors);
        }

        // Edit vendor
        function editVendor(vendorId) {
            isEditing = true;
            currentVendorId = vendorId;
            document.getElementById('modalTitle').textContent = 'Edit Vendor';
            
            const vendor = vendors.find(v => v.id === vendorId);
            if (vendor) {
                document.getElementById('vendorId').value = vendor.id;
                document.getElementById('vendorName').value = vendor.name;
                document.getElementById('contactPerson').value = vendor.contact;
                document.getElementById('email').value = vendor.email;
                document.getElementById('phone').value = vendor.phone;
                
                // Set services (for a real app, you'd need more complex handling for multiple select)
                const servicesSelect = document.getElementById('services');
                Array.from(servicesSelect.options).forEach(option => {
                    option.selected = vendor.services.includes(option.value);
                });
                
                document.getElementById('contractDate').value = vendor.contractDate;
                document.getElementById('status').value = vendor.status;
                
                vendorModal.style.display = 'flex';
            }
        }

        // Delete vendor
        function deleteVendor(vendorId) {
            if (confirm('Are you sure you want to delete this vendor?')) {
                const index = vendors.findIndex(v => v.id === vendorId);
                if (index !== -1) {
                    vendors.splice(index, 1);
                    renderVendors(vendors);
                    alert('Vendor deleted successfully');
                }
            }
        }
        
        // Toggle vendor details
        function toggleVendorDetails(vendorId) {
            // Toggle desktop view details
            const detailsRow = document.getElementById(`vendor-details-${vendorId}`);
            const toggleBtns = document.querySelectorAll(`.toggle-details-btn[data-id="${vendorId}"]`);
            let icon;
            
            // Toggle mobile view details
            const mobileDetails = document.getElementById(`mobile-vendor-details-${vendorId}`);
            
            // Check if details are currently hidden
            const isHidden = detailsRow && (detailsRow.style.display === 'none' || detailsRow.style.display === '');
            
            if (isHidden) {
                // Show details for both desktop and mobile
                if (detailsRow) {
                    detailsRow.style.display = 'table-row';
                }
                if (mobileDetails) {
                    mobileDetails.style.display = 'block';
                }
                
                // Update all toggle buttons for this vendor
                toggleBtns.forEach(btn => {
                    icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                    btn.innerHTML = '<i class="fas fa-chevron-up"></i> Details';
                });
            } else {
                // Hide details for both desktop and mobile
                if (detailsRow) {
                    detailsRow.style.display = 'none';
                }
                if (mobileDetails) {
                    mobileDetails.style.display = 'none';
                }
                
                // Update all toggle buttons for this vendor
                toggleBtns.forEach(btn => {
                    icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    }
                    btn.innerHTML = '<i class="fas fa-chevron-down"></i> Details';
                });
            }
        }

        // Save vendor (add or update)
        function saveVendor() {
            // Get form values
            const vendorName = document.getElementById('vendorName').value;
            const contactPerson = document.getElementById('contactPerson').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            // Get selected services (simplified for this example)
            const servicesSelect = document.getElementById('services');
            const selectedServices = [];
            Array.from(servicesSelect.options).forEach(option => {
                if (option.selected) {
                    selectedServices.push(option.value);
                }
            });
            
            const contractDate = document.getElementById('contractDate').value;
            const status = document.getElementById('status').value;
            
            // Validate
            if (!vendorName || !contactPerson || !email || !phone || selectedServices.length === 0 || !contractDate) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (isEditing) {
                // Update existing vendor
                const vendor = vendors.find(v => v.id === currentVendorId);
                if (vendor) {
                    vendor.name = vendorName;
                    vendor.contact = contactPerson;
                    vendor.email = email;
                    vendor.phone = phone;
                    vendor.services = selectedServices;
                    vendor.contractDate = contractDate;
                    vendor.status = status;
                    
                    alert('Vendor updated successfully');
                }
            } else {
                // Add new vendor
                const newId = `VEN${String(vendors.length + 1).padStart(3, '0')}`;
                const newVendor = {
                    id: newId,
                    name: vendorName,
                    contact: contactPerson,
                    email: email,
                    phone: phone,
                    services: selectedServices,
                    contractDate: contractDate,
                    status: status
                };
                
                vendors.push(newVendor);
                alert('Vendor added successfully');
            }
            
            // Refresh the table and close modal
            renderVendors(vendors);
            vendorModal.style.display = 'none';
            
            // In a real implementation, you would send the data to the server
            // fetch('../api/save_vendor.php', {
            //     method: 'POST',
            //     body: JSON.stringify({vendorData}),
            //     headers: {
            //         'Content-Type': 'application/json'
            //     }
            // })
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', init);
        

    </script>
</body>
</html>