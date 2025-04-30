<?php
// Read the site_expenses.php file
$file_content = file_get_contents('site_expenses.php');

// More precise pattern matching for the Edit Details button
$search_pattern = '<button type="button" class="btn btn-primary site-detail-edit-btn" id="siteDetailEditBtn" onclick="editSiteDetails()">
                        <i class="fas fa-edit"></i> Edit Details
                    </button>
                    <span class="close-modal site-detail-close-btn">&times;</span>';
$replace_with = '<button type="button" class="btn btn-primary site-detail-edit-btn" id="siteDetailEditBtn" onclick="editSiteDetails()">
                        <i class="fas fa-edit"></i> Edit Details
                    </button>
                    <button type="button" class="btn btn-success site-detail-export-btn" id="siteDetailExportBtn" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                    <span class="close-modal site-detail-close-btn">&times;</span>';
$file_content = str_replace($search_pattern, $replace_with, $file_content);

// Check if the export_excel.js script already exists
if (strpos($file_content, 'export_excel.js') === false) {
    // Add the export_excel.js script before the closing body tag
    $search_pattern = '    <!-- Include the autocomplete fix script -->
    <script src="autocomplete_fix.js"></script>
</body>';
    $replace_with = '    <!-- Include the autocomplete fix script -->
    <script src="autocomplete_fix.js"></script>
    <script src="export_excel.js"></script>
</body>';
    $file_content = str_replace($search_pattern, $replace_with, $file_content);
}

// Write the modified content back to site_expenses.php
file_put_contents('site_expenses.php', $file_content);

echo "Site expenses file has been updated successfully!";
?> 