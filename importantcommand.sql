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