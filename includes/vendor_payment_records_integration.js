/**
 * Vendor Payment Records Integration
 * 
 * Integrates payment records into vendor details modal
 * Fetches from: tbl_payment_entry_master_records, tbl_payment_entry_line_items_detail
 * Displays in: vendor_details_modal.php Payment Records section
 */

/**
 * Fetch vendor payment records from backend
 * @param {number} vendorId - The vendor ID to fetch records for
 * @returns {Promise} - Promise that resolves with payment records array
 */
async function fetchVendorPaymentRecords(vendorId) {
    try {
        const response = await fetch(`fetch_vendor_payment_records.php?vendor_id=${vendorId}`);
        
        if (!response.ok) {
            console.error(`HTTP Error: ${response.status}`);
            return { success: false, data: [], message: 'Failed to fetch payment records' };
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching vendor payment records:', error);
        return { success: false, data: [], message: 'Error fetching payment records' };
    }
}

/**
 * Populate payment records in vendor details modal
 * @param {array} paymentRecords - Array of payment record objects
 * @returns {string} - HTML string for payment records section
 */
function generatePaymentRecordsHTML(paymentRecords) {
    if (!paymentRecords || paymentRecords.length === 0) {
        return `
            <div class="empty-state" style="text-align: center; padding: 30px 20px; color: #a0aec0;">
                <i class="fas fa-history" style="font-size: 2em; color: #cbd5e0; margin-bottom: 10px; display: block;"></i>
                <p>No payment records found</p>
            </div>
        `;
    }

    let html = `
        <table class="payment-records-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f7fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #2a4365; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Date</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #2a4365; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Project</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #2a4365; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Amount</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #2a4365; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Mode</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #2a4365; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Status</th>
                </tr>
            </thead>
            <tbody>
    `;

    paymentRecords.forEach((record, index) => {
        const recordDate = new Date(record.payment_date_logged).toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        const amount = record.line_item_amount ? 
            parseFloat(record.line_item_amount).toFixed(2) : 
            parseFloat(record.payment_amount_base).toFixed(2);

        const project = record.project_name_reference || record.project_type_category || 'N/A';

        const paymentMode = record.line_item_payment_mode || record.payment_mode_selected || 'N/A';

        const status = record.entry_status_current || record.line_item_status || 'pending';
        const statusClass = getStatusBadgeClass(status);
        const statusDisplay = status.charAt(0).toUpperCase() + status.slice(1);

        const hoverBg = index % 2 === 0 ? 'white' : '#f9fafb';

        html += `
            <tr style="border-bottom: 1px solid #e2e8f0; background-color: ${hoverBg}; transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f0f4f8'" onmouseout="this.style.backgroundColor='${hoverBg}'">
                <td style="padding: 12px; color: #2a4365; font-size: 0.9em;">${recordDate}</td>
                <td style="padding: 12px; color: #2a4365; font-size: 0.9em;">
                    <small style="display: block; color: #718096; font-size: 0.85em;">${project}</small>
                </td>
                <td style="padding: 12px; color: #2a4365; font-size: 0.9em; font-weight: 500;">₹${amount}</td>
                <td style="padding: 12px; color: #718096; font-size: 0.9em;">
                    <small>${paymentMode}</small>
                </td>
                <td style="padding: 12px;">
                    <span class="payment-status-badge ${statusClass}" style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75em; font-weight: 600; text-transform: capitalize;">
                        ${statusDisplay}
                    </span>
                </td>
            </tr>
        `;

        // Add acceptance methods details if available
        if (record.acceptance_methods && record.acceptance_methods.length > 0) {
            html += `
                <tr style="background-color: #f7fafc; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="5" style="padding: 12px; border-left: 4px solid #3182ce;">
                        <div style="font-size: 0.85em; color: #2a4365; margin-bottom: 8px;">
                            <strong>Payment Methods:</strong>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            `;

            record.acceptance_methods.forEach(method => {
                const methodAmount = parseFloat(method.amount).toFixed(2);
                html += `
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; font-size: 0.85em;">
                        <div style="color: #718096; font-size: 0.8em;">
                            ${method.method_type} - ₹${methodAmount}
                        </div>
                        ${method.reference ? `<div style="color: #a0aec0; font-size: 0.75em;">Ref: ${method.reference}</div>` : ''}
                    </div>
                `;
            });

            html += `
                        </div>
                    </td>
                </tr>
            `;
        }
    });

    html += `
            </tbody>
        </table>
    `;

    return html;
}

/**
 * Get CSS class for status badge
 * @param {string} status - Payment status
 * @returns {string} - CSS class name
 */
function getStatusBadgeClass(status) {
    const statusMap = {
        'draft': 'draft',
        'submitted': 'submitted',
        'pending': 'pending',
        'approved': 'approved',
        'rejected': 'rejected',
        'verified': 'approved',
        'active': 'approved',
        'inactive': 'rejected'
    };

    return statusMap[status?.toLowerCase()] || 'pending';
}

/**
 * Inject payment records into vendor details modal
 * @param {number} vendorId - The vendor ID
 * @param {HTMLElement} container - Container element for payment records
 */
async function injectPaymentRecordsIntoModal(vendorId, container) {
    if (!container) return;

    // Show loading state
    container.innerHTML = `
        <div style="text-align: center; padding: 30px 20px;">
            <i class="fas fa-spinner" style="font-size: 2em; color: #2a4365; animation: spin 1s linear infinite; display: block; margin-bottom: 15px;"></i>
            <p style="color: #a0aec0;">Loading payment records...</p>
        </div>
    `;

    // Fetch payment records
    const result = await fetchVendorPaymentRecords(vendorId);

    if (result.success && result.data && result.data.length > 0) {
        // Generate and inject HTML
        const html = generatePaymentRecordsHTML(result.data);
        container.innerHTML = html;

        // Inject CSS styles for payment status badges
        if (!document.getElementById('payment-status-badge-styles')) {
            const style = document.createElement('style');
            style.id = 'payment-status-badge-styles';
            style.textContent = `
                .payment-status-badge.draft {
                    background-color: #e0e7ff;
                    color: #3730a3;
                }
                
                .payment-status-badge.submitted {
                    background-color: #fef3c7;
                    color: #92400e;
                }
                
                .payment-status-badge.pending {
                    background-color: #dbeafe;
                    color: #0c4a6e;
                }
                
                .payment-status-badge.approved {
                    background-color: #dcfce7;
                    color: #166534;
                }
                
                .payment-status-badge.rejected {
                    background-color: #fee2e2;
                    color: #991b1b;
                }
            `;
            document.head.appendChild(style);
        }
    } else {
        // Show empty state
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 30px 20px; color: #a0aec0;">
                <i class="fas fa-history" style="font-size: 2em; color: #cbd5e0; margin-bottom: 10px; display: block;"></i>
                <p>No payment records found</p>
            </div>
        `;
    }
}

/**
 * Hook into existing displayVendorDetails function
 * Wraps the original to add payment records
 */
const originalDisplayVendorDetails = window.displayVendorDetails;

if (typeof originalDisplayVendorDetails === 'function') {
    window.displayVendorDetails = function(vendor) {
        // Call original function
        originalDisplayVendorDetails.call(this, vendor);

        // After original function completes, fetch and inject payment records
        setTimeout(() => {
            const container = document.querySelector('.vendor-details-section.collapsed:nth-last-child(2) .vendor-details-grid');
            
            if (container) {
                injectPaymentRecordsIntoModal(vendor.vendor_id, container);
            }
        }, 100);
    };
}

/**
 * Alternative hook if original function needs to be modified
 * Use this if the above approach doesn't work
 */
function initializePaymentRecordsIntegration() {
    // This function can be called manually if the hook above doesn't work
    // It sets up the integration after modal is opened
    
    const observerConfig = { 
        subtree: true, 
        childList: true, 
        attributes: false 
    };

    const observer = new MutationObserver((mutations) => {
        const modal = document.getElementById('vendorDetailsModal');
        
        if (modal && modal.classList.contains('active')) {
            const vendorIdElement = document.querySelector('[data-vendor-id]');
            
            if (vendorIdElement) {
                const vendorId = vendorIdElement.getAttribute('data-vendor-id');
                const paymentSection = document.querySelector('[data-payment-section-container]');
                
                if (paymentSection && !paymentSection.dataset.loaded) {
                    injectPaymentRecordsIntoModal(vendorId, paymentSection);
                    paymentSection.dataset.loaded = 'true';
                }
            }
        }
    });

    observer.observe(document.body, observerConfig);
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePaymentRecordsIntegration);
} else {
    initializePaymentRecordsIntegration();
}
