# Payment Documents Organization System

## Overview
This system organizes payment entry documents in a structured directory hierarchy for better file management and organization.

## Directory Structure

### Main Structure
```
uploads/payment_documents/
├── payment_1/
│   ├── recipient_1/
│   │   ├── document1_abc123.pdf
│   │   ├── document2_def456.jpg
│   │   └── splits/
│   │       ├── split_1_proof_xyz789.pdf
│   │       └── split_2_proof_abc456.jpg
│   └── recipient_2/
│       ├── invoice_ghi789.pdf
│       └── receipt_jkl012.png
├── payment_2/
│   └── recipient_3/
│       ├── bill_mno345.jpg
│       └── splits/
│           └── split_3_proof_pqr678.pdf
└── ...
```

### Directory Naming Convention
- **Payment Level**: `payment_{payment_id}/`
- **Recipient Level**: `recipient_{recipient_id}/`
- **Split Payments**: `splits/` (subdirectory under recipient)

## File Naming Convention

### Regular Documents
- Pattern: `{clean_filename}_{unique_id}.{extension}`
- Example: `invoice_document_doc123.pdf`

### Split Payment Documents
- Pattern: `split_{split_id}_{clean_filename}_{unique_id}.{extension}`
- Example: `split_5_proof_doc456.jpg`

## Benefits

1. **Organization**: Easy to locate files by payment ID and recipient
2. **Scalability**: Prevents single directory from having too many files
3. **Maintenance**: Easier backup, cleanup, and file management
4. **Security**: Better access control at directory level
5. **Performance**: Faster file system operations with smaller directories

## Implementation Features

### File Validation
- **Size Limit**: 5MB per file (configurable)
- **File Types**: JPG, PNG, PDF, DOC, DOCX, XLS, XLSX
- **Security**: Filename sanitization and validation

### Error Handling
- Comprehensive validation before file operations
- Transaction rollback on file upload failures
- Detailed error logging

### Backward Compatibility
- Migration script provided for existing files
- Database paths updated automatically
- Old file cleanup after successful migration

## Usage

### For Developers

#### Including Helper Functions
```php
require_once '../utils/payment_document_helpers.php';
```

#### Creating Directory Structure
```php
$uploadDir = createPaymentDocumentDirectory($paymentId, $recipientId, $isSplit);
```

#### Generating Organized Filename
```php
$filename = generateOrganizedFilename($originalName, $documentId, $prefix);
```

#### Getting Relative Path for Database
```php
$relativePath = getPaymentDocumentRelativePath($paymentId, $recipientId, $filename, $isSplit);
```

#### File Validation
```php
$validation = validatePaymentDocumentFile($fileData);
if ($validation !== true) {
    // Handle error: $validation['error']
}
```

## Migration

### Running the Organization Script
1. Access: `http://your-domain/hr/utils/organize_payment_documents.php`
2. The script will:
   - Analyze existing files
   - Create new directory structure
   - Move files to organized locations
   - Update database paths
   - Provide detailed progress report

### Backup Before Migration
The system automatically creates backups, but manual backup is recommended:
```bash
cp -r uploads/payment_documents uploads/payment_documents_backup_manual
```

## Maintenance

### Log Files
- Location: `logs/payment_documents.log`
- Contains: Upload operations, file movements, errors
- Format: `[timestamp] operation - details`

### Cleanup Old Files
After successful migration, old unorganized files can be removed manually.

### Directory Permissions
Ensure web server has write permissions to upload directories:
```bash
chmod 755 uploads/payment_documents
```

## Security Considerations

1. **File Type Validation**: Only allow specific file types
2. **Filename Sanitization**: Remove dangerous characters
3. **Size Limits**: Prevent large file uploads
4. **Directory Traversal**: Path validation prevents directory escaping
5. **Access Control**: Implement proper authentication for file access

## API Integration

The organized structure is fully integrated with:
- Payment entry creation (`save_payment_entry.php`)
- Document viewing (`get_payment_entry_details.php`)
- File download and preview functionality

All existing functionality remains unchanged from the user perspective while providing better backend organization.