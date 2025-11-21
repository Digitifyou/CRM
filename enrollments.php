<?php
// SECURITY CHECK: Ensure user is logged in
include 'header.php'; 
?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Enrollments Pipeline</h2>
            </div>

        <div class="kanban-board-container">
            <div class="kanban-board" id="kanban-board">
                </div>
        </div>
    

    <script src="/assets/js/enrollments.js" defer></script>

    <div class="modal fade" id="lostModal" tabindex="-1" aria-labelledby="lostModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lostModalLabel">Mark Enrollment as Lost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="lost-enrollment-form">
                    <input type="hidden" id="lost-enrollment-id" name="enrollment_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="lost_reason" class="form-label">Lost Reason <span class="text-danger">*</span></label>
                            <select class="form-select mb-2" id="lost_reason" name="lost_reason" required>
                                <option value="">-- Select Reason --</option>
                                <option value="Joined Competitor">Joined Competitor</option>
                                <option value="Not Interested Anymore">Not Interested Anymore</option>
                                <option value="Price/Budget Issue">Price/Budget Issue</option>
                                <option value="Unqualified Lead">Unqualified Lead</option>
                                <option value="No Response">No Response</option>
                            </select>
                            <textarea class="form-control" name="lost_reason_details" rows="3" placeholder="Add specific notes... (Optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Lost</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php include 'footer.php'; ?>