// /assets/js/enrollments.js

const API_STAGES = '/api/v1/pipeline.php';
const API_ENROLLMENTS = '/api/v1/enrollments.php';

const kanbanBoard = document.getElementById('kanban-board');
const lostModal = new bootstrap.Modal(document.getElementById('lostModal'));
const lostEnrollmentForm = document.getElementById('lost-enrollment-form');
const lostEnrollmentId = document.getElementById('lost-enrollment-id');

let stages = [];
let enrollments = [];

/**
 * Main function to load and build the board
 */
async function buildKanbanBoard() {
    try {
        kanbanBoard.innerHTML = '<h5 class="text-muted">Loading board...</h5>';

        // 1. Fetch data in parallel
        const [stagesResponse, enrollmentsResponse] = await Promise.all([
            fetch(API_STAGES),
            fetch(API_ENROLLMENTS)
        ]);

        if (!stagesResponse.ok || !enrollmentsResponse.ok) {
            throw new Error('Failed to fetch board data');
        }

        stages = await stagesResponse.json();
        enrollments = await enrollmentsResponse.json();

        // 2. Clear loading state
        kanbanBoard.innerHTML = '';

        // 3. Build each column from the stages
        stages.forEach(stage => {
            const column = createColumn(stage);
            kanbanBoard.appendChild(column);
        });

        // 4. Populate columns with enrollment cards
        enrollments.forEach(enrollment => {
            const card = createCard(enrollment);
            const dropzone = document.getElementById(`stage-dropzone-${enrollment.pipeline_stage_id}`);

            if (dropzone) {
                dropzone.appendChild(card);
            } else {
                console.warn(`Could not find dropzone for stage ${enrollment.pipeline_stage_id}`);
            }
        });

        // 5. Update counts on column titles
        updateColumnCounts();

        // 6. Add drag-and-drop listeners
        setupDragAndDrop();

    } catch (error) {
        console.error('Error building Kanban board:', error);
        kanbanBoard.innerHTML = '<h5 class="text-danger">Failed to load board. Please refresh.</h5>';
    }
}

/**
 * Creates the HTML for a single column
 * @param {object} stage - { stage_id, stage_name }
 * @returns {HTMLElement}
 */
function createColumn(stage) {
    const column = document.createElement('div');
    column.className = 'kanban-column';
    column.id = `stage-column-${stage.stage_id}`;

    column.innerHTML = `
        <h5 class="kanban-column-title" id="stage-title-${stage.stage_id}">
            ${stage.stage_name} (0)
        </h5>
        <div class="kanban-dropzone" id="stage-dropzone-${stage.stage_id}" data-stage-id="${stage.stage_id}">
            </div>
    `;
    return column;
}

/**
 * Creates the HTML for a single enrollment card
 * @param {object} enrollment - { enrollment_id, student_name, ... }
 * @returns {HTMLElement}
 */
function createCard(enrollment) {
    const card = document.createElement('div');
    card.className = 'kanban-card';
    card.draggable = true;
    card.id = `enrollment-${enrollment.enrollment_id}`;
    card.dataset.id = enrollment.enrollment_id; // Store ID on the element

    const followUp = enrollment.next_follow_up_date ?
        new Date(enrollment.next_follow_up_date).toLocaleDateString() :
        'No follow up';

    // NEW: Win/Loss Buttons
    const winLossButtons = `
        <div class="d-flex justify-content-between mt-2">
            <button class="btn btn-success btn-sm me-1" onclick="handleWin(${enrollment.enrollment_id})">
                <i class="bi bi-check-circle"></i> Win
            </button>
            <button class="btn btn-danger btn-sm" onclick="showLostModal(${enrollment.enrollment_id})">
                <i class="bi bi-x-circle"></i> Lost
            </button>
        </div>
    `;

    card.innerHTML = `
        <h6>${enrollment.student_name}</h6>
        <p>${enrollment.course_name || 'No Course Assigned'}</p>
        <span><i class="bi bi-calendar-event"></i> ${followUp}</span>
        ${winLossButtons}
    `;
    return card;
}

/**
 * Loops through columns and updates the card count in the title
 */
function updateColumnCounts() {
    stages.forEach(stage => {
        const dropzone = document.getElementById(`stage-dropzone-${stage.stage_id}`);
        const title = document.getElementById(`stage-title-${stage.stage_id}`);
        if (dropzone && title) {
            const count = dropzone.childElementCount;
            title.textContent = `${stage.stage_name} (${count})`;
        }
    });
}

/**
 * Sets up all listeners for the HTML Drag and Drop API
 */
function setupDragAndDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const dropzones = document.querySelectorAll('.kanban-dropzone');

    let draggedCard = null;

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            draggedCard = e.target;
            setTimeout(() => e.target.classList.add('kanban-card--dragging'), 0);
        });

        card.addEventListener('dragend', (e) => {
            e.target.classList.remove('kanban-card--dragging');
            draggedCard = null;
        });
    });

    dropzones.forEach(zone => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault(); // Necessary to allow a drop
            zone.classList.add('kanban-dropzone--over');
        });

        zone.addEventListener('dragleave', (e) => {
            zone.classList.remove('kanban-dropzone--over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('kanban-dropzone--over');

            if (draggedCard && e.currentTarget !== draggedCard.parentNode) {
                // Move card in the UI
                e.currentTarget.appendChild(draggedCard);

                // Get data for API call
                const enrollmentId = draggedCard.dataset.id;
                const newStageId = e.currentTarget.dataset.stageId;

                // Update column counts
                updateColumnCounts();

                // Send update to server
                updateEnrollmentStage(enrollmentId, newStageId);
            }
        });
    });
}

/**
 * Sends the PUT request to the backend to save the new stage
 * @param {string} enrollmentId
 * @param {string} newStageId
 */
async function updateEnrollmentStage(enrollmentId, newStageId) {
    try {
        const response = await fetch(API_ENROLLMENTS, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                enrollment_id: enrollmentId,
                new_stage_id: newStageId
            })
        });

        if (!response.ok) throw new Error('Failed to update stage on server');

        const result = await response.json();
        console.log('Server update successful:', result.message);

    } catch (error) {
        console.error('Error updating enrollment:', error);
        alert('Failed to save change. Please refresh the page.');
        // In a real app, you'd move the card back to its original column
    }
}

/**
 * Handles the 'Win' action (Mark as Enrolled)
 * @param {number} enrollmentId 
 */
async function handleWin(enrollmentId) {
    if (!confirm('Mark this deal as ENROLLED (WON)? This will update the student status and fees paid.')) {
        return;
    }

    try {
        const response = await fetch(API_ENROLLMENTS, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                enrollment_id: enrollmentId,
                status: 'enrolled'
            })
        });

        if (!response.ok) throw new Error('Failed to mark as enrolled');

        // Remove card from UI
        document.getElementById(`enrollment-${enrollmentId}`).remove();
        updateColumnCounts();

        alert('Enrollment successfully marked as ENROLLED!');

    } catch (error) {
        console.error('Error marking deal as won:', error);
        alert('Failed to mark deal as won. ' + error.message);
    }
}

/**
 * Shows the Lost Modal
 * @param {number} enrollmentId 
 */
function showLostModal(enrollmentId) {
    lostEnrollmentId.value = enrollmentId;
    lostModal.show();
}

/**
 * Handles the 'Lost' form submission
 */
async function handleLostSubmission(event) {
    event.preventDefault();

    const enrollmentId = lostEnrollmentId.value;
    const selectedReason = document.getElementById('lost_reason').value;
    const detailReason = document.querySelector('[name="lost_reason_details"]').value;

    if (!selectedReason) {
        alert('Please select a Lost Reason.');
        return;
    }

    const finalReason = detailReason ? `${selectedReason} (${detailReason})` : selectedReason;

    try {
        const response = await fetch(API_ENROLLMENTS, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                enrollment_id: enrollmentId,
                status: 'lost',
                lost_reason: finalReason
            })
        });

        if (!response.ok) throw new Error('Failed to mark as lost');

        lostModal.hide();

        // Remove card from UI
        document.getElementById(`enrollment-${enrollmentId}`).remove();
        updateColumnCounts();

        lostEnrollmentForm.reset();
        alert('Enrollment successfully marked as LOST.');

    } catch (error) {
        console.error('Error marking deal as lost:', error);
        alert('Failed to mark deal as lost. ' + error.message);
    }
}


// --- Global Functions and Event Listeners ---
window.handleWin = handleWin;
window.showLostModal = showLostModal;

document.addEventListener('DOMContentLoaded', () => {
    buildKanbanBoard();
    lostEnrollmentForm.addEventListener('submit', handleLostSubmission);
});