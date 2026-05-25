<?php
// whatsapp_sales_api/list_templates.php
require_once __DIR__ . '/WhatsAppClient.php';

$client = new SalesWhatsAppClient();
$templates = $client->getTemplates();

$templateList = [];
if (isset($templates['data'])) {
    $templateList = $templates['data'];
} else {
    $error = isset($templates['error']['message']) ? $templates['error']['message'] : (isset($templates['error']) ? $templates['error'] : 'Unknown error');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Templates | ArchitectsHive</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-color: #e74c3c;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-main: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --status-approved: #27ae60;
            --status-rejected: #e74c3c;
            --status-pending: #f39c12;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            padding-bottom: 40px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--accent-color);
        }

        .refresh-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .refresh-btn:hover {
            opacity: 0.9;
        }

        /* Error Banner */
        .error-banner {
            background-color: #fce4e4;
            border-left: 4px solid var(--status-rejected);
            color: var(--status-rejected);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Templates Grid */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .template-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .template-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .template-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
            word-break: break-all;
        }

        .template-category {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.approved {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--status-approved);
        }

        .status-badge.rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--status-rejected);
        }

        .status-badge.pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--status-pending);
        }

        .card-body {
            padding: 20px;
            flex-grow: 1;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .info-label {
            color: var(--text-secondary);
        }

        .info-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .components-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .component-chip {
            font-size: 11px;
            background: #f0f2f5;
            color: var(--text-secondary);
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
        }

        .card-footer {
            padding: 16px 20px;
            background: #f8f9fa;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-secondary);
            font-family: monospace;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .copy-icon {
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s;
        }

        .copy-icon:hover {
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #d1d5db;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        
        <!-- Header -->
        <div class="page-header">
            <div class="page-title">
                <i class="fab fa-whatsapp"></i>
                Sales Templates
            </div>
            <button class="refresh-btn" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>

        <!-- Error Handling -->
        <?php if (isset($error)): ?>
            <div class="error-banner">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Connection Error</strong><br>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <?php 
            $total = count($templateList);
            $approved = 0;
            $pending = 0;
            $rejected = 0;

            foreach ($templateList as $t) {
                $s = strtolower($t['status']);
                if ($s === 'approved') $approved++;
                elseif ($s === 'rejected') $rejected++;
                else $pending++;
            }
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total; ?></div>
                <div class="stat-label">Total Templates</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--status-approved)"><?php echo $approved; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--status-pending)"><?php echo $pending; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>

        <!-- Grid -->
        <div class="templates-grid">
            <?php if (empty($templateList)): ?>
                <div class="empty-state">
                    <i class="far fa-folder-open"></i>
                    <h3>No Templates Found</h3>
                    <p>Create your first template in the Meta Business Manager.</p>
                </div>
            <?php else: ?>
                <?php foreach ($templateList as $template): ?>
                    <div class="template-card">
                        <div class="card-header">
                            <div>
                                <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                <div class="template-category"><?php echo htmlspecialchars($template['category']); ?></div>
                            </div>
                            <div class="status-badge <?php echo strtolower($template['status']); ?>">
                                <?php echo htmlspecialchars($template['status']); ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Language</span>
                                <span class="info-value">
                                    <i class="fas fa-globe-americas" style="font-size: 12px; margin-right: 4px;"></i>
                                    <?php echo htmlspecialchars($template['language']); ?>
                                </span>
                            </div>
                            
                            <div class="components-list">
                                <?php foreach ($template['components'] as $comp): ?>
                                    <span class="component-chip">
                                        <?php if($comp['type'] == 'HEADER') echo '<i class="fas fa-heading"></i> '; ?>
                                        <?php if($comp['type'] == 'BODY') echo '<i class="fas fa-align-left"></i> '; ?>
                                        <?php if($comp['type'] == 'FOOTER') echo '<i class="fas fa-shoe-prints"></i> '; ?>
                                        <?php if($comp['type'] == 'BUTTONS') echo '<i class="fas fa-hand-pointer"></i> '; ?>
                                        <?php echo htmlspecialchars($comp['type']); ?>
                                        <?php if(isset($comp['format'])) echo ' (' . htmlspecialchars($comp['format']) . ')'; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <span title="<?php echo htmlspecialchars($template['id']); ?>">
                                ID: <?php echo substr($template['id'], 0, 15) . '...'; ?>
                            </span>
                            <i class="far fa-copy copy-icon" title="Copy ID" onclick="navigator.clipboard.writeText('<?php echo $template['id']; ?>'); alert('Copied ID!')"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>