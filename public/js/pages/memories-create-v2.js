(() => {
    const root = document.querySelector("[data-memory-create-v2]");

    if (!root) {
        return;
    }

    const configNode = root.querySelector(".memory-create-v2__config");

    if (!(configNode instanceof HTMLScriptElement)) {
        return;
    }

    const config = JSON.parse(configNode.textContent || "{}");
    const form = root.querySelector("form");
    const contentInput = root.querySelector("[data-content-input]");
    const charCount = root.querySelector("[data-char-count]");
    const previewBubble = root.querySelector("[data-preview-bubble]");
    const previewTone = root.querySelector("[data-preview-tone]");
    const previewPeriod = root.querySelector("[data-preview-period]");
    const previewEmotion = root.querySelector("[data-preview-emotion]");
    const previewLabel = root.querySelector("[data-preview-label]");
    const summaryPeriod = root.querySelector("[data-summary-period]");
    const summaryEmotion = root.querySelector("[data-summary-emotion]");
    const summaryState = root.querySelector("[data-summary-state]");
    const draftButton = root.querySelector("[data-draft-save]");
    const draftStatus = root.querySelector("[data-draft-status]");
    const groupButtons = Array.from(root.querySelectorAll("[data-group-button]"));
    const groupFields = Array.from(root.querySelectorAll("[data-group-field]"));
    const groupMeta = config.groupMeta || {};
    const emotionToGroup = config.emotionToGroup || {};
    const initialGroup = config.initialGroup || "warm";
    const hasErrors = Boolean(config.hasErrors);
    const storageKey = config.storageKey || "memory-create-v2-draft";
    const filledClasses = ["is-empty", "is-soft", "is-medium", "is-dense"];

    if (!(form instanceof HTMLFormElement) || !(contentInput instanceof HTMLTextAreaElement)) {
        return;
    }

    const countCharacters = (value) => Array.from(value.trim()).length;

    const getSelectedPeriod = () => form.querySelector('input[name="period"]:checked')?.value ?? "年代未選択";
    const getSelectedEmotion = () => form.querySelector('input[name="emotion"]:checked')?.value ?? "感情を選択";
    const getSelectedGroup = () => {
        const emotion = form.querySelector('input[name="emotion"]:checked')?.value;

        return emotionToGroup[emotion] ?? initialGroup;
    };

    const getFilledLevel = () => {
        const length = countCharacters(contentInput.value);

        if (length === 0) {
            return "empty";
        }

        if (length < 60) {
            return "soft";
        }

        if (length < 140) {
            return "medium";
        }

        return "dense";
    };

    const setTheme = (group) => {
        root.classList.remove("theme-warm", "theme-calm", "theme-sway", "theme-heavy");
        root.classList.add(`theme-${group}`);

        groupButtons.forEach((button) => {
            button.classList.toggle("is-selected", button.dataset.groupButton === group);
        });

        groupFields.forEach((field) => {
            field.classList.toggle("is-active", field.dataset.groupField === group);
        });

        const meta = groupMeta[group];

        if (meta) {
            previewTone.textContent = meta.tone;
            previewLabel.textContent = meta.previewLabel;
        }
    };

    const refreshPreview = () => {
        const selectedGroup = getSelectedGroup();
        const selectedPeriodValue = getSelectedPeriod();
        const selectedEmotionValue = getSelectedEmotion();
        const filledLevel = getFilledLevel();
        const trimmedLength = countCharacters(contentInput.value);

        setTheme(selectedGroup);
        previewPeriod.textContent = selectedPeriodValue;
        previewEmotion.textContent = selectedEmotionValue;
        summaryPeriod.textContent = selectedPeriodValue;
        summaryEmotion.textContent = selectedEmotionValue;
        summaryState.textContent = trimmedLength > 0 ? "入力中" : "保存前";
        charCount.textContent = `${trimmedLength} 文字`;

        filledClasses.forEach((className) => previewBubble.classList.remove(className));
        previewBubble.classList.add(`is-${filledLevel}`);
    };

    const setGroupSelection = (group) => {
        const field = root.querySelector(`[data-group-field="${group}"]`);

        if (!(field instanceof HTMLElement)) {
            return;
        }

        const selectedEmotion = form.querySelector('input[name="emotion"]:checked');
        const sameGroupSelected = selectedEmotion instanceof HTMLInputElement && emotionToGroup[selectedEmotion.value] === group;

        if (!sameGroupSelected) {
            const nextInput = field.querySelector('input[name="emotion"]');

            if (nextInput instanceof HTMLInputElement) {
                nextInput.checked = true;
            }
        }

        refreshPreview();
    };

    const hydrateDraft = () => {
        if (hasErrors) {
            return;
        }

        try {
            const rawDraft = window.localStorage.getItem(storageKey);

            if (!rawDraft) {
                return;
            }

            const draft = JSON.parse(rawDraft);

            if (countCharacters(contentInput.value) > 0) {
                return;
            }

            if (typeof draft.period === "string") {
                const periodInput = form.querySelector(`input[name="period"][value="${CSS.escape(draft.period)}"]`);

                if (periodInput instanceof HTMLInputElement) {
                    periodInput.checked = true;
                }
            }

            if (typeof draft.content === "string") {
                contentInput.value = draft.content;
            }

            if (typeof draft.emotion === "string") {
                const emotionInput = form.querySelector(`input[name="emotion"][value="${CSS.escape(draft.emotion)}"]`);

                if (emotionInput instanceof HTMLInputElement) {
                    emotionInput.checked = true;
                }
            }

            draftStatus.textContent = "前回の下書きを読み込みました。";
        } catch (error) {
            draftStatus.textContent = "";
        }
    };

    groupButtons.forEach((button) => {
        button.addEventListener("click", () => {
            setGroupSelection(button.dataset.groupButton || initialGroup);
        });
    });

    form.addEventListener("change", (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.name === "emotion" || target.name === "period") {
            refreshPreview();
        }
    });

    contentInput.addEventListener("input", refreshPreview);

    draftButton?.addEventListener("click", () => {
        try {
            const draft = {
                period: form.querySelector('input[name="period"]:checked')?.value ?? null,
                content: contentInput.value,
                emotion: form.querySelector('input[name="emotion"]:checked')?.value ?? null,
            };

            window.localStorage.setItem(storageKey, JSON.stringify(draft));
            draftStatus.textContent = "このブラウザに下書きを保存しました。";
        } catch (error) {
            draftStatus.textContent = "下書き保存に失敗しました。";
        }
    });

    hydrateDraft();
    refreshPreview();
})();
