#!/bin/bash

# Payment Entry System Database Setup
# This script creates all required tables from the payment_entry_tables.sql file

DB_NAME="crm"
DB_USER="root"
DB_PASS="root"
DB_HOST="localhost"

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Payment Entry System - Database Setup${NC}"
echo -e "${YELLOW}========================================${NC}"

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}MySQL client not found. Please install MySQL client.${NC}"
    exit 1
fi

echo -e "${YELLOW}Connecting to database: $DB_NAME${NC}"

# Import the SQL schema
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < payment_entry_tables.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database schema imported successfully!${NC}"
else
    echo -e "${RED}✗ Failed to import database schema${NC}"
    exit 1
fi

# Verify tables were created
echo -e "${YELLOW}Verifying table creation...${NC}"

TABLES=(
    "tbl_payment_entry_master_records"
    "tbl_payment_acceptance_methods_primary"
    "tbl_payment_entry_line_items_detail"
    "tbl_payment_acceptance_methods_line_items"
    "tbl_payment_entry_file_attachments_registry"
    "tbl_payment_entry_audit_activity_log"
    "tbl_payment_entry_summary_totals"
    "tbl_payment_entry_status_transition_history"
    "tbl_payment_entry_rejection_reasons_detail"
    "tbl_payment_entry_approval_records_final"
)

for table in "${TABLES[@]}"; do
    result=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SHOW TABLES LIKE '$table';" 2>/dev/null)
    if [ ! -z "$result" ]; then
        echo -e "${GREEN}✓ $table${NC}"
    else
        echo -e "${RED}✗ $table${NC}"
    fi
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Database setup completed!${NC}"
echo -e "${GREEN}========================================${NC}"
