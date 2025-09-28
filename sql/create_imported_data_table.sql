-- SQL script to create a table for storing imported Excel data
-- This is an example table structure that could be used to store imported data

CREATE TABLE IF NOT EXISTS imported_excel_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    column1 VARCHAR(255),
    column2 VARCHAR(255),
    column3 VARCHAR(255),
    column4 VARCHAR(255),
    column5 VARCHAR(255),
    column6 VARCHAR(255),
    column7 VARCHAR(255),
    column8 VARCHAR(255),
    column9 VARCHAR(255),
    column10 VARCHAR(255),
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imported_by INT,
    FOREIGN KEY (imported_by) REFERENCES users(id)
);

-- Add indexes for better performance
CREATE INDEX idx_import_date ON imported_excel_data(import_date);
CREATE INDEX idx_imported_by ON imported_excel_data(imported_by);

-- Example of how to insert data (this would be done via PHP)
-- INSERT INTO imported_excel_data (column1, column2, column3, column4, imported_by) 
-- VALUES ('EMP001', 'John Smith', 'john.smith@company.com', 'Engineering', 1);