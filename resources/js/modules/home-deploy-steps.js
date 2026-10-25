// Dynamically imported from app.js only once #deploySteps is confirmed
// present (see docs/homepage-performance-fixes-plan.md, Stage 5), so the
// DOM is already ready - runs immediately instead of waiting for
// DOMContentLoaded, which may have already fired by the time an async
// import resolves.
(() => {
    const deployImage = document.getElementById('deploySteps');
    const deploySteps = Array.from(document.querySelectorAll('details[data-step-image]'));

    if (!(deployImage instanceof HTMLImageElement) || deploySteps.length === 0) {
        return;
    }

    const applyStepMedia = (step) => {
        const imagePath = step.getAttribute('data-step-image');
        const imagePosition = step.getAttribute('data-step-position');

        if (imagePath) {
            deployImage.src = imagePath;
        }

        if (imagePosition) {
            deployImage.style.objectPosition = imagePosition;
        }
    };

    const openedStep = deploySteps.find((step) => step.open) ?? deploySteps[0];
    if (openedStep) {
        applyStepMedia(openedStep);
    }

    deploySteps.forEach((step) => {
        step.addEventListener('toggle', () => {
            if (!step.open) {
                return;
            }

            deploySteps.forEach((otherStep) => {
                if (otherStep !== step) {
                    otherStep.open = false;
                }
            });

            applyStepMedia(step);
        });
    });
})();
