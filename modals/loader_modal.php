<?php
/**
 * Loader Modal
 * This modal displays a loading spinner while actions are being processed
 */
?>

<!-- Loader Modal -->
<div class="loader-modal-overlay" id="loaderModal">
    <div class="loader-modal-content">
        <div class="loader-spinner"></div>
        <div class="loader-text" id="loaderText">Processing your request...</div>
    </div>
</div>

<style>
.loader-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10002;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loader-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.loader-modal-content {
    background-color: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 300px;
    padding: 30px 20px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.loader-modal-overlay.active .loader-modal-content {
    transform: scale(1);
}

.loader-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loader-text {
    font-size: 1.1rem;
    color: #2c3e50;
    font-weight: 500;
    margin: 0;
}

@media (max-width: 576px) {
    .loader-modal-content {
        width: 95%;
        max-width: 95%;
        padding: 25px 15px;
    }
    
    .loader-spinner {
        width: 40px;
        height: 40px;
        border-width: 4px;
    }
    
    .loader-text {
        font-size: 1rem;
    }
}
</style>

<script>
// Function to show loader modal
function showLoader(text = 'Processing your request...') {
    const modal = document.getElementById('loaderModal');
    const textElement = document.getElementById('loaderText');
    
    if (textElement) {
        textElement.textContent = text;
    }
    
    if (modal) {
        modal.classList.add('active');
    }
}

// Function to hide loader modal
function hideLoader() {
    const modal = document.getElementById('loaderModal');
    
    if (modal) {
        modal.classList.remove('active');
    }
}
</script>