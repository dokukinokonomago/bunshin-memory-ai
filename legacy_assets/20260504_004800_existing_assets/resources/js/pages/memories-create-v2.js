document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-memory-shot]');
  if (!root) return;

  const form = root.querySelector('[data-memory-form]');
  const toast = root.querySelector('[data-toast]');
  const contentInput = root.querySelector('[data-content-input]');
  const tagInput = root.querySelector('[data-tag-input]');
  const cancelButton = root.querySelector('[data-cancel-button]');
  const previewPeriod = root.querySelector('[data-preview-period]');
  const previewEmotion = root.querySelector('[data-preview-emotion]');
  const previewState = root.querySelector('[data-preview-state]');
  const currentEmotion = root.querySelector('[data-current-emotion]');
  const progressFill = root.querySelector('[data-progress-fill]');
  const progressSteps = {
    period: root.querySelector('[data-progress-step="period"]'),
    content: root.querySelector('[data-progress-step="content"]'),
    emotion: root.querySelector('[data-progress-step="emotion"]'),
  };
  const emotionGroupInput = root.querySelector('[data-emotion-group-input]');
  const emotionModal = root.querySelector('[data-emotion-modal]');
  const emotionOpenButtons = Array.from(root.querySelectorAll('[data-emotion-open]'));
  const emotionPanels = Array.from(root.querySelectorAll('[data-emotion-panel]'));
  const emotionCloseButtons = Array.from(root.querySelectorAll('[data-emotion-close]'));
  const groupSelectionLabels = Array.from(root.querySelectorAll('[data-group-selected]'));
  const addEmotionModal = root.querySelector('[data-add-emotion-modal]');
  const addEmotionInput = root.querySelector('[data-add-emotion-input]');
  const addEmotionSubmit = root.querySelector('[data-add-emotion-submit]');
  const addEmotionOpenButtons = Array.from(root.querySelectorAll('[data-add-emotion-open]'));
  const addEmotionCloseButtons = Array.from(root.querySelectorAll('[data-add-emotion-close]'));

  if (!form) return;

  const extraOrbLayouts = {
    good: [
      { left: 48, top: 22, size: 70, delay: '1.6s' },
      { left: 70, top: 70, size: 68, delay: '2.8s' },
      { left: 30, top: 78, size: 72, delay: '0.7s' },
      { left: 58, top: 50, size: 66, delay: '3.3s' },
    ],
    normal: [
      { left: 44, top: 38, size: 70, delay: '1.1s' },
      { left: 68, top: 60, size: 66, delay: '2.2s' },
      { left: 34, top: 76, size: 72, delay: '3.4s' },
      { left: 58, top: 78, size: 68, delay: '1.9s' },
    ],
    bad: [
      { left: 46, top: 44, size: 70, delay: '1.3s' },
      { left: 62, top: 28, size: 66, delay: '2.5s' },
      { left: 34, top: 72, size: 72, delay: '0.8s' },
      { left: 62, top: 78, size: 68, delay: '3.7s' },
      { left: 78, top: 58, size: 66, delay: '2.1s' },
    ],
  };
  const baseOrbCounts = { good: 11, normal: 5, bad: 14 };

  let toastTimer = null;
  let activeEmotionGroup = '';
  let addEmotionTargetGroup = '';
  let addEmotionTargetTone = 'neutral';

  function showToast(message) {
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.add('show');

    toastTimer = setTimeout(() => {
      toast.classList.remove('show');
    }, 1800);
  }

  function getFieldValue(name) {
    const field = form.elements.namedItem(name);

    if (!field) return '';
    if (typeof field.value === 'string') return field.value;

    return '';
  }

  function getCheckedEmotionInput() {
    return form.querySelector('input[name="emotion"]:checked');
  }

  function getCheckedTone() {
    return getCheckedEmotionInput()?.dataset.tone ?? 'neutral';
  }

  function getCheckedGroup() {
    return getCheckedEmotionInput()?.dataset.group ?? emotionGroupInput?.value ?? '';
  }

  function getPreviewState(length) {
    if (length === 0) return { level: 'soft', text: '軽く入力中' };
    if (length < 30) return { level: 'soft', text: '輪郭が見えはじめた' };
    if (length < 90) return { level: 'medium', text: '記憶が形になっている' };
    return { level: 'dense', text: '濃く保存されそう' };
  }

  function closeAddEmotionModal() {
    if (!addEmotionModal) return;
    addEmotionModal.hidden = true;
    addEmotionTargetGroup = '';
    addEmotionTargetTone = 'neutral';
    if (addEmotionInput) addEmotionInput.value = '';
  }

  function openAddEmotionModal(group, tone) {
    if (!addEmotionModal) return;
    addEmotionTargetGroup = group;
    addEmotionTargetTone = tone;
    addEmotionModal.hidden = false;
    if (addEmotionInput) {
      addEmotionInput.value = '';
      addEmotionInput.focus();
    }
  }

  function closeEmotionModal() {
    if (!emotionModal) return;
    closeAddEmotionModal();
    emotionModal.hidden = true;
    emotionModal.setAttribute('aria-hidden', 'true');
    activeEmotionGroup = '';
    document.body.classList.remove('mcv2-modal-open');
  }

  function openEmotionModal(group) {
    if (!emotionModal) return;

    closeAddEmotionModal();
    activeEmotionGroup = group;
    emotionModal.hidden = false;
    emotionModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mcv2-modal-open');

    emotionPanels.forEach((panel) => {
      const isActive = panel.dataset.emotionPanel === group;
      panel.hidden = !isActive;
    });
  }

  function clearEmotionSelection() {
    const checkedInput = getCheckedEmotionInput();
    if (checkedInput) checkedInput.checked = false;
    if (emotionGroupInput) emotionGroupInput.value = '';
  }

  function syncEmotionSummary() {
    const emotion = getFieldValue('emotion') || '未選択';
    const activeGroup = getCheckedGroup();

    if (currentEmotion) currentEmotion.textContent = emotion;
    if (emotionGroupInput && getCheckedEmotionInput()) {
      emotionGroupInput.value = activeGroup;
    }

    emotionOpenButtons.forEach((button) => {
      const isActive = button.dataset.emotionOpen === activeGroup && emotion !== '未選択';
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    groupSelectionLabels.forEach((labelNode) => {
      const isActive = labelNode.dataset.groupSelected === activeGroup && emotion !== '未選択';
      labelNode.textContent = isActive ? emotion : 'タップして選ぶ';
    });
  }

  function updateProgressStatus() {
    const isPeriodDone = Boolean(getFieldValue('period'));
    const isContentDone = Boolean(contentInput?.value.trim());
    const isEmotionDone = Boolean(getFieldValue('emotion'));
    const completeCount = [isPeriodDone, isContentDone, isEmotionDone].filter(Boolean).length;

    const currentStep = completeCount === 3
      ? ''
      : !isPeriodDone
        ? 'period'
        : !isContentDone
          ? 'content'
          : 'emotion';

    Object.entries(progressSteps).forEach(([key, node]) => {
      if (!node) return;
      const isComplete = key === 'period'
        ? isPeriodDone
        : key === 'content'
          ? isContentDone
          : isEmotionDone;

      node.classList.toggle('is-complete', isComplete);
      node.classList.toggle('is-current', !isComplete && key === currentStep);
    });

    if (progressFill) {
      const widths = ['0%', '34%', '67%', '100%'];
      progressFill.style.width = widths[completeCount] || '0%';
    }
  }

  function refreshPreview() {
    const period = getFieldValue('period') || '未選択';
    const emotion = getFieldValue('emotion') || '未選択';
    const tone = getFieldValue('emotion') ? getCheckedTone() : 'neutral';
    const length = contentInput ? contentInput.value.trim().length : 0;
    const state = getPreviewState(length);

    if (previewPeriod) previewPeriod.textContent = period;
    if (previewEmotion) previewEmotion.textContent = emotion;
    if (previewState) previewState.textContent = state.text;

    form.classList.remove('tone-positive', 'tone-neutral', 'tone-negative', 'tone-violet');
    form.classList.add(`tone-${tone}`);

    form.classList.remove('preview-soft', 'preview-medium', 'preview-dense');
    form.classList.add(`preview-${state.level}`);

    syncEmotionSummary();
    updateProgressStatus();
  }

  function createEmotionOrb(group, tone, labelText, layout) {
    const panel = root.querySelector(`[data-emotion-panel="${group}"]`);
    const sphere = panel?.querySelector('.mcv2-emotion-sphere');
    if (!sphere) return null;

    const orb = document.createElement('label');
    orb.className = `mcv2-emotion-orb tone-${tone}`;
    orb.dataset.customEmotion = 'true';
    orb.style.setProperty('--orb-left', `${layout.left}%`);
    orb.style.setProperty('--orb-top', `${layout.top}%`);
    orb.style.setProperty('--orb-size', `${layout.size}px`);
    orb.style.setProperty('--orb-delay', layout.delay);

    const input = document.createElement('input');
    input.type = 'radio';
    input.name = 'emotion';
    input.value = labelText;
    input.dataset.tone = tone;
    input.dataset.group = group;

    const span = document.createElement('span');
    span.textContent = labelText;

    orb.append(input, span);
    sphere.appendChild(orb);

    return input;
  }

  function getNextOrbLayout(group) {
    const sphere = root.querySelector(`[data-emotion-panel="${group}"] .mcv2-emotion-sphere`);
    const currentCount = sphere?.querySelectorAll('.mcv2-emotion-orb').length ?? 0;
    const presets = extraOrbLayouts[group] ?? extraOrbLayouts.normal;
    const baseCount = baseOrbCounts[group] ?? 0;
    const extraIndex = Math.max(0, currentCount - baseCount);
    return presets[extraIndex] ?? presets[extraIndex % presets.length];
  }

  function resetSection(sectionName) {
    if (sectionName === 'period') {
      const periodSelect = form.querySelector('select[name="period"]');
      if (periodSelect) periodSelect.value = '';
      refreshPreview();
      showToast('年代をリセットしました。');
      return;
    }

    if (sectionName === 'content') {
      if (contentInput) contentInput.value = '';
      if (tagInput) tagInput.value = '';
      refreshPreview();
      showToast('内容をリセットしました。');
      return;
    }

    if (sectionName === 'emotion') {
      clearEmotionSelection();
      closeEmotionModal();
      refreshPreview();
      showToast('感情をリセットしました。');
    }
  }

  emotionCloseButtons.forEach((button) => {
    button.addEventListener('click', closeEmotionModal);
  });

  addEmotionCloseButtons.forEach((button) => {
    button.addEventListener('click', closeAddEmotionModal);
  });

  addEmotionSubmit?.addEventListener('click', () => {
    const labelText = addEmotionInput?.value.trim() ?? '';
    if (!labelText) {
      showToast('感情の名前を入力してください。');
      return;
    }

    const existingInput = Array.from(form.querySelectorAll('input[name="emotion"]')).find(
      (input) => input.value === labelText
    );

    if (existingInput instanceof HTMLInputElement) {
      existingInput.checked = true;
      if (emotionGroupInput) emotionGroupInput.value = existingInput.dataset.group || addEmotionTargetGroup;
      closeAddEmotionModal();
      refreshPreview();
      closeEmotionModal();
      showToast(`「${labelText}」を選択しました。`);
      return;
    }

    const layout = getNextOrbLayout(addEmotionTargetGroup);
    const createdInput = createEmotionOrb(addEmotionTargetGroup, addEmotionTargetTone, labelText, layout);
    if (!createdInput) return;

    createdInput.checked = true;
    if (emotionGroupInput) emotionGroupInput.value = addEmotionTargetGroup;
    closeAddEmotionModal();
    refreshPreview();
    closeEmotionModal();
    showToast(`「${labelText}」を追加しました。`);
  });

  root.addEventListener('click', (event) => {
    const resetButton = event.target instanceof Element ? event.target.closest('[data-reset-section]') : null;
    if (resetButton instanceof HTMLButtonElement) {
      event.preventDefault();
      event.stopPropagation();
      resetSection(resetButton.dataset.resetSection || '');
      return;
    }

    const emotionOpenButton = event.target instanceof Element ? event.target.closest('[data-emotion-open]') : null;
    if (emotionOpenButton instanceof HTMLButtonElement) {
      event.preventDefault();
      openEmotionModal(emotionOpenButton.dataset.emotionOpen || '');
      return;
    }

    const addEmotionOpenButton = event.target instanceof Element ? event.target.closest('[data-add-emotion-open]') : null;
    if (addEmotionOpenButton instanceof HTMLButtonElement) {
      event.preventDefault();
      openAddEmotionModal(
        addEmotionOpenButton.dataset.addEmotionOpen || '',
        addEmotionOpenButton.dataset.addEmotionTone || 'neutral'
      );
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !addEmotionModal?.hidden) {
      closeAddEmotionModal();
      return;
    }

    if (event.key === 'Escape' && !emotionModal?.hidden) {
      closeEmotionModal();
    }
  });

  cancelButton?.addEventListener('click', () => {
    const ok = window.confirm('入力内容をクリアしますか？');
    if (!ok) return;

    form.reset();

    const periodSelect = form.querySelector('select[name="period"]');
    const defaultEmotion = form.querySelector('input[name="emotion"][value="普通"]');
    const customOrbs = root.querySelectorAll('[data-custom-emotion="true"]');

    customOrbs.forEach((orb) => orb.remove());

    if (periodSelect) periodSelect.value = '高校生';
    clearEmotionSelection();
    if (defaultEmotion instanceof HTMLInputElement) {
      defaultEmotion.checked = true;
      if (emotionGroupInput) emotionGroupInput.value = defaultEmotion.dataset.group || 'normal';
    }
    if (contentInput) contentInput.value = '';
    if (tagInput) tagInput.value = '';

    closeEmotionModal();
    refreshPreview();
    showToast('入力をクリアしました。');
  });

  form.addEventListener('change', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.name === 'emotion') {
      if (emotionGroupInput) emotionGroupInput.value = event.target.dataset.group || '';
      refreshPreview();
      showToast(`「${event.target.value}」を選択しました。`);
      window.setTimeout(closeEmotionModal, 120);
      return;
    }

    refreshPreview();
  });

  contentInput?.addEventListener('input', refreshPreview);
  tagInput?.addEventListener('input', refreshPreview);
  addEmotionInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      addEmotionSubmit?.click();
    }
  });

  refreshPreview();
});
