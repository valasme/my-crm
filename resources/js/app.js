const KANBAN_BOARD_SELECTOR = "[data-deals-kanban]";
const KANBAN_STAGE_SELECTOR = "[data-deal-stage]";
const KANBAN_CARD_SELECTOR = "[data-deal-id]";

const STAGE_HIGHLIGHT_CLASSES = [
    "ring-2",
    "ring-zinc-300",
    "dark:ring-zinc-500",
];

const DRAGGING_CARD_CLASSES = [
    "opacity-60",
    "ring-2",
    "ring-zinc-300",
    "dark:ring-zinc-500",
    "shadow-md",
];

/**
 * @param {HTMLElement} boardElement
 */
function clearStageHighlights(boardElement) {
    boardElement
        .querySelectorAll(KANBAN_STAGE_SELECTOR)
        .forEach((stageElement) => {
            stageElement.classList.remove(...STAGE_HIGHLIGHT_CLASSES);
        });
}

/**
 * @param {HTMLElement} cardElement
 * @param {boolean} isDragging
 */
function setCardDraggingState(cardElement, isDragging) {
    cardElement.classList.toggle("transition-none", isDragging);
    cardElement.classList.toggle("z-20", isDragging);
    cardElement.classList.toggle("cursor-grabbing", isDragging);
    cardElement.classList.toggle("cursor-grab", !isDragging);

    cardElement.classList.toggle("opacity-100", !isDragging);

    DRAGGING_CARD_CLASSES.forEach((className) => {
        cardElement.classList.toggle(className, isDragging);
    });

    cardElement.setAttribute("aria-grabbed", isDragging ? "true" : "false");
}

/**
 * @param {HTMLElement} stageElement
 * @param {number} pointerY
 * @param {HTMLElement} draggedCard
 * @returns {HTMLElement|null}
 */
function findCardAfterPointer(stageElement, pointerY, draggedCard) {
    const cards = Array.from(
        stageElement.querySelectorAll(KANBAN_CARD_SELECTOR),
    ).filter((cardElement) => cardElement !== draggedCard);

    let closestOffset = Number.NEGATIVE_INFINITY;
    let closestCard = null;

    cards.forEach((cardElement) => {
        const boundingBox = cardElement.getBoundingClientRect();
        const offset = pointerY - boundingBox.top - boundingBox.height / 2;

        if (offset < 0 && offset > closestOffset) {
            closestOffset = offset;
            closestCard = cardElement;
        }
    });

    return closestCard;
}

/**
 * @param {HTMLElement} boardElement
 * @returns {any|null}
 */
function findLivewireComponent(boardElement) {
    const componentElement = boardElement.closest("[wire\\:id]");

    if (componentElement === null) {
        return null;
    }

    const componentId = componentElement.getAttribute("wire:id");

    if (
        componentId === null ||
        window.Livewire === undefined ||
        typeof window.Livewire.find !== "function"
    ) {
        return null;
    }

    return window.Livewire.find(componentId);
}

/**
 * @param {HTMLElement} cardElement
 * @param {HTMLElement} sourceStage
 * @param {Element|null} sourceNextSibling
 */
function restoreCardPosition(cardElement, sourceStage, sourceNextSibling) {
    if (!sourceStage.isConnected || !cardElement.isConnected) {
        return;
    }

    if (
        sourceNextSibling !== null &&
        sourceNextSibling.parentElement === sourceStage
    ) {
        sourceStage.insertBefore(cardElement, sourceNextSibling);

        return;
    }

    sourceStage.appendChild(cardElement);
}

/**
 * @param {HTMLElement} boardElement
 */
function initializeDealsKanbanBoard(boardElement) {
    if (boardElement.dataset.kanbanInitialized === "true") {
        return;
    }

    boardElement.dataset.kanbanInitialized = "true";

    boardElement
        .querySelectorAll(KANBAN_CARD_SELECTOR)
        .forEach((cardElement) => {
            if (cardElement instanceof HTMLElement) {
                cardElement.setAttribute("aria-grabbed", "false");
            }
        });

    const dragState = {
        draggedCard: null,
        sourceStage: null,
        sourceNextSibling: null,
        droppedOnValidStage: false,
    };

    boardElement.addEventListener("dragstart", (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const cardElement = event.target.closest(KANBAN_CARD_SELECTOR);

        if (
            !(cardElement instanceof HTMLElement) ||
            !boardElement.contains(cardElement)
        ) {
            return;
        }

        const sourceStage = cardElement.closest(KANBAN_STAGE_SELECTOR);

        if (!(sourceStage instanceof HTMLElement)) {
            return;
        }

        dragState.draggedCard = cardElement;
        dragState.sourceStage = sourceStage;
        dragState.sourceNextSibling = cardElement.nextElementSibling;
        dragState.droppedOnValidStage = false;

        setCardDraggingState(cardElement, true);
        boardElement.classList.add("select-none");

        if (event.dataTransfer !== null) {
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData(
                "text/plain",
                cardElement.dataset.dealId ?? "",
            );
        }
    });

    boardElement.addEventListener("dragover", (event) => {
        if (
            dragState.draggedCard === null ||
            !(event.target instanceof Element)
        ) {
            return;
        }

        const stageElement = event.target.closest(KANBAN_STAGE_SELECTOR);

        if (
            !(stageElement instanceof HTMLElement) ||
            !boardElement.contains(stageElement)
        ) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer !== null) {
            event.dataTransfer.dropEffect = "move";
        }

        clearStageHighlights(boardElement);
        stageElement.classList.add(...STAGE_HIGHLIGHT_CLASSES);

        const cardAfterPointer = findCardAfterPointer(
            stageElement,
            event.clientY,
            dragState.draggedCard,
        );

        if (cardAfterPointer === null) {
            stageElement.appendChild(dragState.draggedCard);

            return;
        }

        stageElement.insertBefore(dragState.draggedCard, cardAfterPointer);
    });

    boardElement.addEventListener("drop", (event) => {
        if (
            dragState.draggedCard === null ||
            !(event.target instanceof Element)
        ) {
            return;
        }

        const stageElement = event.target.closest(KANBAN_STAGE_SELECTOR);

        if (
            !(stageElement instanceof HTMLElement) ||
            !boardElement.contains(stageElement)
        ) {
            return;
        }

        event.preventDefault();

        clearStageHighlights(boardElement);

        const targetStatus = stageElement.dataset.dealStage;
        const dealId = Number.parseInt(
            dragState.draggedCard.dataset.dealId ?? "",
            10,
        );

        if (targetStatus === undefined || Number.isNaN(dealId)) {
            return;
        }

        const stageCards = Array.from(
            stageElement.querySelectorAll(KANBAN_CARD_SELECTOR),
        );
        const targetPosition = stageCards.findIndex(
            (cardElement) => cardElement === dragState.draggedCard,
        );

        if (targetPosition < 0) {
            return;
        }

        const component = findLivewireComponent(boardElement);

        if (component === null || typeof component.call !== "function") {
            return;
        }

        dragState.droppedOnValidStage = true;

        const sourceStage = dragState.sourceStage;
        const sourceNextSibling = dragState.sourceNextSibling;
        const draggedCard = dragState.draggedCard;

        const callResult = component.call(
            "moveDealStage",
            dealId,
            targetStatus,
            targetPosition,
        );

        if (
            callResult !== undefined &&
            typeof callResult.catch === "function"
        ) {
            callResult.catch(() => {
                if (
                    sourceStage instanceof HTMLElement &&
                    draggedCard instanceof HTMLElement
                ) {
                    restoreCardPosition(
                        draggedCard,
                        sourceStage,
                        sourceNextSibling,
                    );
                }
            });
        }
    });

    boardElement.addEventListener("dragend", () => {
        if (
            !dragState.droppedOnValidStage &&
            dragState.draggedCard instanceof HTMLElement &&
            dragState.sourceStage instanceof HTMLElement
        ) {
            restoreCardPosition(
                dragState.draggedCard,
                dragState.sourceStage,
                dragState.sourceNextSibling,
            );
        }

        if (dragState.draggedCard instanceof HTMLElement) {
            setCardDraggingState(dragState.draggedCard, false);
        }

        boardElement.classList.remove("select-none");

        clearStageHighlights(boardElement);

        dragState.draggedCard = null;
        dragState.sourceStage = null;
        dragState.sourceNextSibling = null;
        dragState.droppedOnValidStage = false;
    });
}

function initializeDealsKanbanBoards() {
    document.querySelectorAll(KANBAN_BOARD_SELECTOR).forEach((boardElement) => {
        if (boardElement instanceof HTMLElement) {
            initializeDealsKanbanBoard(boardElement);
        }
    });
}

document.addEventListener("DOMContentLoaded", initializeDealsKanbanBoards);
document.addEventListener("livewire:initialized", initializeDealsKanbanBoards);
document.addEventListener("livewire:navigated", initializeDealsKanbanBoards);
