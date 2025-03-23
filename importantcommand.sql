-- Table for Official Documents
CREATE TABLE official_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    document_type ENUM(
        'offer_letter',
        'training_letter',
        'internship_letter',
        'completion_letter',
        'other'
    ) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    assigned_user_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    remarks TEXT,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for Personal Documents
CREATE TABLE personal_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    document_type ENUM(
        -- Identity Documents
        'aadhar_card',
        'pan_card',
        'voter_id',
        'passport',
        'driving_license',
        'ration_card',
        
        -- Educational Documents
        'tenth_certificate',
        'twelfth_certificate',
        'graduation_certificate',
        'post_graduation',
        'diploma_certificate',
        'other_education',
        
        -- Professional Documents
        'resume',
        'experience_certificate',
        'relieving_letter',
        'salary_slips',
        
        -- Financial Documents
        'bank_passbook',
        'cancelled_cheque',
        'form_16',
        'pf_documents',
        
        -- Other Documents
        'marriage_certificate',
        'caste_certificate',
        'disability_certificate',
        'other'
    ) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    assigned_user_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    document_number VARCHAR(100),
    issue_date DATE,
    expiry_date DATE,
    issuing_authority VARCHAR(255),
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_date TIMESTAMP NULL,
    verified_by INT,
    remarks TEXT,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better performance
CREATE INDEX idx_official_docs_user ON official_documents(assigned_user_id);
CREATE INDEX idx_official_docs_type ON official_documents(document_type);
CREATE INDEX idx_official_docs_status ON official_documents(status);
CREATE INDEX idx_personal_docs_user ON personal_documents(assigned_user_id);
CREATE INDEX idx_personal_docs_type ON personal_documents(document_type);
CREATE INDEX idx_personal_docs_verification ON personal_documents(verification_status);





















CREATE TABLE company_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM(
        'company_policy',
        'employee_handbook',
        'code_of_conduct',
        'safety_guidelines',
        'hr_policy',
        'leave_policy',
        'travel_policy',
        'it_policy',
        'benefits_policy',
        'other'
    ) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for frequently searched columns
CREATE INDEX idx_document_type ON company_documents(document_type);
CREATE INDEX idx_upload_date ON company_documents(upload_date);
CREATE INDEX idx_is_active ON company_documents(is_active);


-- Add status column to existing company_documents table
ALTER TABLE company_documents
    ADD COLUMN status ENUM('published', 'acknowledged', 'rejected') DEFAULT 'published' AFTER document_type;

-- Create table for tracking document responses
CREATE TABLE document_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    response_status ENUM('acknowledged', 'rejected') NOT NULL,
    response_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    comments TEXT,
    FOREIGN KEY (document_id) REFERENCES company_documents(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_response (document_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
