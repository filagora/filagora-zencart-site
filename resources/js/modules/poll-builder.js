(() => {
    // Optional "add a poll to this thread" checkbox (thread/new.blade.php
    // only — poll/create.blade.php has no toggle, the builder is always
    // shown there). Mirrors plugin-submit.js's create_support_thread toggle.
    const toggle = document.querySelector('[data-poll-toggle]');
    const toggleFields = document.querySelector('[data-poll-toggle-fields]');
    if (toggle instanceof HTMLInputElement && toggleFields instanceof HTMLElement) {
        // The builder's inputs are `required`. While the section is hidden a
        // `required` empty field blocks the whole thread form on submit with
        // "An invalid form control ... is not focusable" and no visible error.
        // Disabling them takes them out of constraint validation (and out of
        // the submitted payload) until the poll is actually wanted.
        const sync = () => {
            const enabled = toggle.checked;
            toggleFields.hidden = !enabled;
            toggleFields.querySelectorAll('input, select, textarea').forEach((field) => {
                if (
                    field instanceof HTMLInputElement
                    || field instanceof HTMLSelectElement
                    || field instanceof HTMLTextAreaElement
                ) {
                    field.disabled = !enabled;
                }
            });
        };

        toggle.addEventListener('change', sync);
        sync();
    }

    const root = document.querySelector('[data-poll-builder]');
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const questionsContainer = root.querySelector('[data-poll-questions]');
    const questionTemplate = root.querySelector('template[data-poll-question-template]');
    const optionTemplate = root.querySelector('template[data-poll-option-template]');

    if (
        !(questionsContainer instanceof HTMLElement)
        || !(questionTemplate instanceof HTMLTemplateElement)
        || !(optionTemplate instanceof HTMLTemplateElement)
    ) {
        return;
    }

    // Ever-incrementing, never reused — so removing a question never causes
    // two fieldsets to collide on the same poll_questions[N] array key.
    let nextQuestionIndex = questionsContainer.querySelectorAll('[data-poll-question]').length;

    const updateQuestionRemoveButtons = () => {
        const fieldsets = questionsContainer.querySelectorAll('[data-poll-question]');
        const disableAll = fieldsets.length <= 1;
        fieldsets.forEach((fieldset) => {
            const button = fieldset.querySelector('[data-poll-remove-question]');
            if (button instanceof HTMLButtonElement) {
                button.disabled = disableAll;
            }
        });
    };

    const updateOptionRemoveButtons = (fieldset) => {
        const optionsContainer = fieldset.querySelector('[data-poll-options]');
        if (!(optionsContainer instanceof HTMLElement)) {
            return;
        }

        const options = optionsContainer.querySelectorAll('[data-poll-option]');
        const disableAll = options.length <= 2;
        options.forEach((option) => {
            const button = option.querySelector('[data-poll-remove-option]');
            if (button instanceof HTMLButtonElement) {
                button.disabled = disableAll;
            }
        });
    };

    const addQuestion = () => {
        const index = nextQuestionIndex;
        nextQuestionIndex += 1;

        const fragment = questionTemplate.content.cloneNode(true);
        const fieldset = fragment.querySelector('[data-poll-question]');
        if (!(fieldset instanceof HTMLElement)) {
            return;
        }

        fieldset.innerHTML = fieldset.innerHTML.replaceAll('__QUESTION_INDEX__', String(index));
        fieldset.dataset.questionIndex = String(index);

        questionsContainer.appendChild(fragment);
        updateQuestionRemoveButtons();
    };

    const addOption = (fieldset) => {
        const optionsContainer = fieldset.querySelector('[data-poll-options]');
        if (!(optionsContainer instanceof HTMLElement)) {
            return;
        }

        const questionIndex = fieldset.dataset.questionIndex ?? '0';
        const optionIndex = Number.parseInt(fieldset.dataset.nextOptionIndex ?? '2', 10);
        fieldset.dataset.nextOptionIndex = String(optionIndex + 1);

        const fragment = optionTemplate.content.cloneNode(true);
        const optionRow = fragment.querySelector('[data-poll-option]');
        if (!(optionRow instanceof HTMLElement)) {
            return;
        }

        optionRow.innerHTML = optionRow.innerHTML
            .replaceAll('__QUESTION_INDEX__', questionIndex)
            .replaceAll('__OPTION_INDEX__', String(optionIndex));

        optionsContainer.appendChild(fragment);
        updateOptionRemoveButtons(fieldset);
    };

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (target.closest('[data-poll-add-question]')) {
            addQuestion();
            return;
        }

        const addOptionButton = target.closest('[data-poll-add-option]');
        if (addOptionButton instanceof HTMLElement) {
            const fieldset = addOptionButton.closest('[data-poll-question]');
            if (fieldset instanceof HTMLElement) {
                addOption(fieldset);
            }
            return;
        }

        const removeQuestionButton = target.closest('[data-poll-remove-question]');
        if (removeQuestionButton instanceof HTMLButtonElement && !removeQuestionButton.disabled) {
            removeQuestionButton.closest('[data-poll-question]')?.remove();
            updateQuestionRemoveButtons();
            return;
        }

        const removeOptionButton = target.closest('[data-poll-remove-option]');
        if (removeOptionButton instanceof HTMLButtonElement && !removeOptionButton.disabled) {
            const fieldset = removeOptionButton.closest('[data-poll-question]');
            removeOptionButton.closest('[data-poll-option]')?.remove();
            if (fieldset instanceof HTMLElement) {
                updateOptionRemoveButtons(fieldset);
            }
        }
    });
})();
