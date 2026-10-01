document.querySelectorAll('.choice-editor').forEach(form => {
    const body = form.querySelector('.choice-rows');
    const template = form.querySelector('template');
    const status = form.querySelector('[data-row-status]');
    function renumber() {
        const rows = [...body.rows];
        rows.forEach((row, index) => {
            row.querySelectorAll('input').forEach(input => {
                const match = input.name.match(/\[([^\]]+)\]$/);
                const key = match ? match[1] : input.name;
                input.name = `rows[${index}][${key}]`;
            });
            row.querySelector('.choice-order').value = index + 1;
            row.querySelector('[data-move="up"]').disabled = index === 0;
            row.querySelector('[data-move="down"]').disabled = index === rows.length - 1;
        });
        form.querySelector('[data-add]').disabled = rows.length >= 500;
        status.textContent = `${rows.length} choices`;
    }
    function add() {
        if (body.rows.length >= 500) return;
        const row = template.content.firstElementChild.cloneNode(true);
        body.appendChild(row); renumber(); row.querySelector('input[name$="[code]"]').focus();
    }
    form.querySelector('[data-add]').addEventListener('click', add);
    body.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const row = button.closest('tr');
        if (button.hasAttribute('data-remove')) row.remove();
        else if (button.dataset.move === 'up' && row.previousElementSibling) body.insertBefore(row, row.previousElementSibling);
        else if (button.dataset.move === 'down' && row.nextElementSibling) body.insertBefore(row.nextElementSibling, row);
        renumber();
    });
    form.addEventListener('submit', renumber);
    if (!body.rows.length) add(); else renumber();
});
