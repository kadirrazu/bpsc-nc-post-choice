import { createChoiceSelection } from './choice-selection-state.js';
const form = document.getElementById('candidate-choice-form');
if (form) {
    const data = JSON.parse(document.getElementById('candidate-choice-data').textContent);
    const options = new Map(data.options.map(option => [String(option.id), option]));
    const state = createChoiceSelection(data.options, data.selected || []);
    const availableList = document.getElementById('available-choices');
    const selectedList = document.getElementById('selected-choices');
    const inputs = document.getElementById('choice-inputs');
    const status = document.getElementById('selection-status');
    let draggedId = null;
    function button(action, text, label) {
        const node = document.createElement('button'); node.type = 'button'; node.dataset.action = action;
        node.className = 'btn btn-sm btn-outline-secondary'; node.textContent = text; node.setAttribute('aria-label', label); return node;
    }
    function row(id, index, panel, count) {
        const option = options.get(id); const node = document.createElement('li');
        node.className = 'choice-panel-item'; node.dataset.id = id; node.draggable = true;
        const position = document.createElement('span'); position.className = 'choice-position';
        position.textContent = panel === 'selected' ? String(index + 1) : String(data.options.findIndex(item => String(item.id) === id) + 1);
        node.append(position);
        const transfer = document.createElement('button'); transfer.type = 'button'; transfer.className = 'choice-transfer';
        transfer.dataset.action = panel === 'selected' ? 'remove' : 'add';
        transfer.setAttribute('aria-label', `${panel === 'selected' ? 'Remove' : 'Add'} ${option.title}`);
        const title = document.createElement('strong'); title.textContent = `${option.title} (${option.posts === null ? 'posts not specified' : `${option.posts} posts`})`;
        const code = document.createElement('small'); code.textContent = `Code: ${option.code} · ${panel === 'selected' ? 'Click to remove' : 'Click to add'}`;
        transfer.append(title, code); node.append(transfer);
        const actions = document.createElement('div'); actions.className = 'choice-row-actions';
        if (panel === 'selected') {
            const up = button('up','↑','Move choice up'); up.disabled = index === 0;
            const down = button('down','↓','Move choice down'); down.disabled = index === count - 1;
            actions.append(up, down, button('remove','×','Remove choice'));
        } else actions.append(button('add','→','Add choice'));
        node.append(actions); return node;
    }
    function render(message = '') {
        const available = state.available(); const selected = state.selected();
        availableList.replaceChildren(...available.map((id,i) => row(id,i,'available',available.length)));
        selectedList.replaceChildren(...selected.map((id,i) => row(id,i,'selected',selected.length)));
        inputs.replaceChildren(...selected.map(id => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'choices[]'; input.value = id; return input; }));
        document.getElementById('available-count').textContent = available.length;
        document.getElementById('selected-count').textContent = selected.length;
        document.getElementById('available-empty').hidden = available.length > 0;
        document.getElementById('selected-empty').hidden = selected.length > 0;
        document.getElementById('add-all').disabled = available.length === 0;
        document.getElementById('remove-all').disabled = selected.length === 0;
        document.getElementById('review-choices').disabled = selected.length === 0;
        status.textContent = message || `${selected.length} selected · ${available.length} available`;
    }
    for (const panel of document.querySelectorAll('[data-panel]')) {
        panel.addEventListener('click', event => {
            const node = event.target.closest('[data-id]'); if (!node) return;
            const action = event.target.closest('[data-action]')?.dataset.action || (panel.dataset.panel === 'selected' ? 'remove' : 'add');
            const id = node.dataset.id;
            if (action === 'add') state.add(id);
            if (action === 'remove') state.remove(id);
            if (action === 'up') state.move(id,-1);
            if (action === 'down') state.move(id,1);
            render();
            const focusRow = form.querySelector(`[data-id="${id}"]`);
            focusRow?.querySelector(`[data-action="${action}"]`)?.focus();
        });
        panel.addEventListener('dragstart', event => {
            const node = event.target.closest('[data-id]'); if (!node) return;
            draggedId = node.dataset.id; event.dataTransfer.setData('text/plain',draggedId); event.dataTransfer.effectAllowed = 'move';
        });
        panel.addEventListener('dragover', event => {
            if (!draggedId) return; event.preventDefault(); event.dataTransfer.dropEffect = 'move'; panel.classList.add('is-drag-over');
            panel.querySelectorAll('.is-drop-before,.is-drop-after').forEach(node => node.classList.remove('is-drop-before','is-drop-after'));
            const node = event.target.closest('[data-id]');
            if (node && panel.dataset.panel === 'selected' && node.dataset.id !== draggedId) node.classList.add(event.clientY > node.getBoundingClientRect().top + node.getBoundingClientRect().height / 2 ? 'is-drop-after' : 'is-drop-before');
        });
        panel.addEventListener('dragleave', event => { if (!panel.contains(event.relatedTarget)) panel.classList.remove('is-drag-over'); });
        panel.addEventListener('drop', event => {
            if (!draggedId) return; event.preventDefault();
            const node = event.target.closest('[data-id]');
            if (panel.dataset.panel === 'available') state.remove(draggedId);
            else state.add(draggedId,node?.dataset.id || null,node ? event.clientY > node.getBoundingClientRect().top + node.getBoundingClientRect().height / 2 : false);
            draggedId = null; document.querySelectorAll('.is-drag-over').forEach(item => item.classList.remove('is-drag-over')); render();
        });
        panel.addEventListener('dragend', () => { draggedId = null; document.querySelectorAll('.is-drag-over,.is-drop-before,.is-drop-after').forEach(item => item.classList.remove('is-drag-over','is-drop-before','is-drop-after')); });
    }
    document.getElementById('add-all').addEventListener('click', () => { state.addAll(); render('All choices selected. Check your preference order.'); });
    document.getElementById('remove-all').addEventListener('click', () => { state.removeAll(); render('All choices returned to their original order.'); });
    form.addEventListener('submit', event => { if (!state.selected().length) { event.preventDefault(); status.textContent = 'Select at least one choice.'; } });
    render();
}
