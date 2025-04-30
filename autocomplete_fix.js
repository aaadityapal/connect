/**
 * jQuery UI Autocomplete Fix for site_expenses.php
 * This script fixes issues with the autocomplete dropdowns not appearing
 */
$(document).ready(function() {
    console.log('Autocomplete fix script loaded');
    
    // Add CSS fix for the autocomplete dropdown
    $('<style>\
        .ui-autocomplete {\
            z-index: 9999 !important;\
            max-height: 200px;\
            overflow-y: auto;\
            overflow-x: hidden;\
        }\
        .ui-menu-item {\
            padding: 3px;\
        }\
    </style>').appendTo('head');
    
    // Log environment info
    console.log('jQuery version:', $.fn.jquery);
    if (typeof $.ui !== 'undefined') {
        console.log('jQuery UI version:', $.ui.version);
        console.log('jQuery UI autocomplete available:', (typeof $.ui.autocomplete !== 'undefined'));
    } else {
        console.error('jQuery UI is NOT loaded!');
    }
    
    // Override the default autocomplete open method to ensure dropdowns are visible
    if ($.ui && $.ui.autocomplete && $.ui.autocomplete.prototype) {
        const originalOpen = $.ui.autocomplete.prototype._open;
        $.ui.autocomplete.prototype._open = function(e) {
            originalOpen.apply(this, arguments);
            const menu = this.menu.element;
            menu.css('z-index', 9999);
            console.log('Autocomplete opened with enhanced z-index');
        };
    }
    
    // Add enhanced vendor autocomplete initialization
    if (typeof initVendorAutocomplete === 'function') {
        const originalVendorAutocomplete = initVendorAutocomplete;
        window.initVendorAutocomplete = function(vendorId) {
            console.log('Enhanced vendor autocomplete initialization for ID:', vendorId);
            const result = originalVendorAutocomplete(vendorId);
            
            // Add additional debugging
            const vendorNameInput = document.getElementById(`vendor-name-${vendorId}`);
            if (vendorNameInput) {
                $(vendorNameInput).on('focus', function() {
                    console.log('Vendor input focused');
                });
            }
            
            return result;
        };
    }
    
    // Add enhanced labour autocomplete initialization
    if (typeof initLabourAutocomplete === 'function') {
        const originalLabourAutocomplete = initLabourAutocomplete;
        window.initLabourAutocomplete = function(labourId, vendorId) {
            console.log('Enhanced labour autocomplete initialization for labourId:', labourId, 'vendorId:', vendorId);
            const result = originalLabourAutocomplete(labourId, vendorId);
            
            // Add additional debugging
            const labourNameInput = document.getElementById(`labour-name-${labourId}`);
            if (labourNameInput) {
                $(labourNameInput).on('focus', function() {
                    console.log('Labour input focused');
                });
            }
            
            return result;
        };
    }
    
    // Add enhanced company labour autocomplete initialization
    if (typeof initCompanyLabourAutocomplete === 'function') {
        const originalCompanyLabourAutocomplete = initCompanyLabourAutocomplete;
        window.initCompanyLabourAutocomplete = function(labourId) {
            console.log('Enhanced company labour autocomplete initialization for labourId:', labourId);
            const result = originalCompanyLabourAutocomplete(labourId);
            
            // Add additional debugging
            const labourNameInput = document.getElementById(`company-labour-name-${labourId}`);
            if (labourNameInput) {
                $(labourNameInput).on('focus', function() {
                    console.log('Company labour input focused');
                });
            }
            
            return result;
        };
    }
    
    // Test function to verify jQuery UI autocomplete works
    window.testAutocomplete = function() {
        // Create a test input element
        const testInput = $('<input>', {
            type: 'text',
            id: 'test-autocomplete-field',
            placeholder: 'Test autocomplete...',
            style: 'position: fixed; bottom: 10px; right: 10px; z-index: 10000; padding: 5px;'
        }).appendTo('body');
        
        // Initialize with static data
        testInput.autocomplete({
            source: ['Apple', 'Banana', 'Cherry', 'Date', 'Elderberry'],
            minLength: 1,
            select: function(event, ui) {
                console.log('Test autocomplete selected:', ui.item.value);
            }
        });
        
        console.log('Test autocomplete field added to page');
    };
    
    console.log('Autocomplete fix script initialized');
}); 