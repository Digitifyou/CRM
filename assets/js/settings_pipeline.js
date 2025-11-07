// /assets/js/settings_pipeline.js

const API_STAGES = '/api/v1/pipeline.php';

// --- DOM Elements ---
const pipelineStagesList = document.getElementById('pipeline-stages-list');
const addStageForm = document.getElementById('add-stage-form');
const addStageModal = new bootstrap.Modal(document.getElementById('addStageModal'));
const pipelinePane = document.getElementById('pipeline-pane');

let stages = [];
let draggedStage = null; // Used for reordering

/**
 * Renders the list of pipeline stages
 */
function renderPipelineStages() {
    pipelineStagesList.innerHTML = ''; // Clear list

    if (stages.length === 0) {
        pipelineStagesList.innerHTML = '<li class="list-group-item text-center text-muted">No stages defined.</li>';
        return;
    }

    stages.forEach(stage => {
        const item = document.createElement('li');
        item.className = 'list-group-item d-flex justify-content-between align-items-center mb-1 shadow-sm';
        item.dataset.id = stage.stage_id;
        item.draggable = true;
        item.style.cursor = 'grab';

        item.innerHTML = `
            <div>
                <i class="bi bi-grip-vertical me-2 text-muted"></i>
                <span class="stage-name-text">${stage.stage_name}</span>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteStage(${stage.stage_id})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        pipelineStagesList.appendChild(item);
    });

    // Re-attach drag-and-drop listeners after re-rendering
    setupDragAndDrop();
}

/**
 * Fetches all pipeline stages from the API and renders them
 */
async function loadPipelineStages() {
    try {
        pipelineStagesList.innerHTML = '<li class="list-group-item text-center text-muted">Loading stages...</li>';
        const response = await fetch(API_STAGES);
        if (!response.ok) throw new Error('Could not fetch pipeline stages');

        stages = await response.json();
        renderPipelineStages();

    } catch (error) {
        console.error('Error loading pipeline stages:', error);
        pipelineStagesList.innerHTML = `<li class="list-group-item text-center text-danger">Failed to load stages: ${error.message}</li>`;
    }
}

/**
 * Handles the "Add Stage" form submission (POST request)
 */
async function handleAddStage(event) {
    event.preventDefault();

    const formData = new FormData(addStageForm);
    const stageData = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(API_STAGES, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(stageData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to add stage');
        }

        addStageForm.reset(); // Clear the form
        addStageModal.hide(); // Hide the modal
        await loadPipelineStages(); // Refresh the list

    } catch (error) {
        console.error('Failed to add stage:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Deletes a stage (DELETE request)
 * @param {number} stageId
 */
async function deleteStage(stageId) {
    if (!confirm('WARNING: Deleting a stage can break existing enrollments. Are you absolutely sure?')) {
        return;
    }

    try {
        const response = await fetch(`${API_STAGES}?id=${stageId}`, {
            method: 'DELETE',
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to delete stage');
        }

        // Remove item from UI instantly
        const item = document.querySelector(`li[data-id="${stageId}"]`);
        if (item) item.remove();

        // Reload to re-check all stages
        await loadPipelineStages();

    } catch (error) {
        console.error('Failed to delete stage:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Sends the PUT request to the backend to save the new order
 */
async function updateStageOrder(stageId, newOrder) {
    try {
        const response = await fetch(API_STAGES, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                stage_id: stageId,
                stage_order: newOrder
            })
        });

        if (!response.ok) throw new Error('Failed to update stage order on server');

        const result = await response.json();
        console.log('Server update successful:', result.message);

    } catch (error) {
        console.error('Error updating stage order:', error);
        alert('Failed to save reorder. Please refresh the page.');
    }
}


/**
 * Sets up listeners for the HTML Drag and Drop API for reordering
 */
function setupDragAndDrop() {
    const stageItems = pipelineStagesList.querySelectorAll('li');

    stageItems.forEach(item => {
        item.addEventListener('dragstart', (e) => {
            draggedStage = e.target;
            e.dataTransfer.effectAllowed = 'move';
            e.target.style.opacity = '0.5';
        });

        item.addEventListener('dragend', (e) => {
            e.target.style.opacity = '1';
            draggedStage = null;
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            const bounding = item.getBoundingClientRect();
            const offset = bounding.y + (bounding.height / 2);

            // Dragging up or down?
            if (e.clientY < offset) {
                // Move current item above the item being hovered over
                if (draggedStage !== item) {
                    pipelineStagesList.insertBefore(draggedStage, item);
                }
            } else {
                // Move current item below the item being hovered over
                if (draggedStage !== item) {
                    pipelineStagesList.insertBefore(draggedStage, item.nextSibling);
                }
            }
        });
    });

    // Drop listener to finalize the order and call the API
    pipelineStagesList.addEventListener('drop', async(e) => {
        e.preventDefault();

        // 1. Recalculate and save the new order for ALL stages
        const updatedStages = Array.from(pipelineStagesList.children).map((item, index) => {
            const stageId = item.dataset.id;
            const newOrder = index + 1;

            // Only update the server if the stage being dropped is the one we dragged
            if (stageId === draggedStage.dataset.id) {
                updateStageOrder(stageId, newOrder);
            }
            return { id: stageId, order: newOrder };
        });

        // Although only one API call is made above for the dragged item, 
        // a full reorder API call to re-sequence all stages might be better 
        // in a production app to prevent order conflicts, but this simple
        // approach will suffice for the MVP.

        // Reload all stages to show the new persistent order, which will also update the local 'stages' array
        await loadPipelineStages();
    });
}


// --- Global Functions and Event Listeners ---

// Attach to window so it can be called from onclick in renderPipelineStages
window.deleteStage = deleteStage;

// Event listener for when the Pipeline tab is clicked
pipelinePane.addEventListener('show.bs.tab', loadPipelineStages);


document.addEventListener('DOMContentLoaded', () => {
    // Only load if the pipeline tab is the active one on initial load
    if (pipelinePane.classList.contains('active')) {
        loadPipelineStages();
    }

    // Add stage form submission
    addStageForm.addEventListener('submit', handleAddStage);
});