<?php
// This file contains the new travel expense modal HTML structure
?>
<!-- Return Trip Confirmation Modal -->
<div class="modal fade" id="returnTripConfirmModal" tabindex="-1" role="dialog"
    aria-labelledby="returnTripConfirmModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnTripConfirmModalLabel">Add Return Trip?</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Would you like to add a return trip with the reversed route?</p>
                <div class="d-flex justify-content-between mt-3">
                    <div class="from-location"><strong>From:</strong> <span id="returnTripFrom"></span></div>
                    <div class="to-location"><strong>To:</strong> <span id="returnTripTo"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="returnTripNoBtn">No, Just One
                    Way</button>
                <button type="button" class="btn btn-primary" id="returnTripYesBtn">Yes, Add Return Trip</button>
            </div>
        </div>
    </div>
</div>

<!-- New Travel Expense Modal -->
<div class="modal fade" id="newTravelExpenseModal" tabindex="-1" role="dialog"
    aria-labelledby="newTravelExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newTravelExpenseModalLabel">Add Travel Expenses</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body expense-modal-body">
                <form id="newTravelExpenseForm">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="travelDate">Date<span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="travelDate" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="purposeOfTravel">Purpose<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="purposeOfTravel" placeholder="Enter purpose"
                                required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="fromLocation">From<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fromLocation" placeholder="Starting point"
                                required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="toLocation">To<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="toLocation" placeholder="Destination" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="modeOfTransport">Mode of Transport<span class="text-danger">*</span></label>
                            <select class="form-control" id="modeOfTransport" required>
                                <option value="">Select mode</option>
                                <option value="Bike">Bike</option>
                                <option value="Car">Car</option>
                                <option value="Taxi">Taxi</option>
                                <option value="Bus">Bus</option>
                                <option value="Train">Train</option>
                                <option value="Metro">Metro</option>
                                <option value="Aeroplane">Aeroplane</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="distance">Distance (km)<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="distance" placeholder="Distance in km" min="0"
                                step="0.1" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="amount">Amount (₹)<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="amount" placeholder="Amount in ₹" min="0"
                                step="0.01" required>
                        </div>
                    </div>

                    <!-- Meter Photos Section (initially hidden) -->
                    <div class="form-row meter-photos-container" id="meterPhotosContainer" style="display: none;">
                        <div class="form-group col-md-6">
                            <label for="meterStartPhoto">Meter Start Photo<span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="meterStartPhoto"
                                    accept=".jpg,.jpeg,.png">
                                <label class="custom-file-label" for="meterStartPhoto">Choose file...</label>
                            </div>
                            <div class="meter-photo-preview mt-2" id="meterStartPhotoPreview" style="display: none;">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="meter-file-name">No file selected</span>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-danger remove-meter-start-btn">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                        <div class="meter-thumbnail mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="meterEndPhoto">Meter End Photo<span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="meterEndPhoto"
                                    accept=".jpg,.jpeg,.png">
                                <label class="custom-file-label" for="meterEndPhoto">Choose file...</label>
                            </div>
                            <div class="meter-photo-preview mt-2" id="meterEndPhotoPreview" style="display: none;">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="meter-file-name">No file selected</span>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-danger remove-meter-end-btn">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                        <div class="meter-thumbnail mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bill upload container (initially hidden) -->
                    <div class="form-group bill-upload-container" id="billUploadContainer" style="display: none;">
                        <label for="billFile">Upload Bill (Required)<span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="billFile" accept=".jpg,.jpeg,.png,.pdf">
                            <label class="custom-file-label" for="billFile">Choose file...</label>
                        </div>
                        <small class="form-text text-muted">Please upload bill receipt (JPG, PNG, or PDF only)</small>
                        <div class="bill-preview mt-2" style="display: none;">
                            <div class="card">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="bill-file-name">No file selected</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-bill-btn">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                    <div class="bill-thumbnail mt-2" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" rows="2"
                            placeholder="Additional details (optional)"></textarea>
                    </div>
                </form>

                <div class="expenses-list-container mt-4">
                    <h5>Added Expenses</h5>
                    <div class="table-responsive expenses-table-container">
                        <table class="table table-striped table-sm" id="addedExpensesTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Purpose</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Mode</th>
                                    <th>Distance</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="no-expenses-row">
                                    <td colspan="8" class="text-center">No expenses added yet</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="6" class="text-right">Total:</th>
                                    <th id="totalExpenseAmount">₹0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="addExpenseBtn">Add Expense</button>
                <button type="button" class="btn btn-success" id="saveAllExpensesBtn">Save All Expenses</button>
            </div>
        </div>
    </div>
</div>