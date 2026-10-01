export function createChoiceSelection(options, initial = []) {
    const order = options.map(option => String(option.id));
    const allowed = new Set(order);
    let selected = [...new Set(initial.map(String))].filter(id => allowed.has(id));
    return {
        selected: () => [...selected],
        available: () => order.filter(id => !selected.includes(id)),
        add(id, target = null, after = false) {
            id = String(id); target = target === null ? null : String(target);
            if (!allowed.has(id) || id === target) return;
            selected = selected.filter(value => value !== id);
            const index = target === null ? -1 : selected.indexOf(target);
            if (index < 0) selected.push(id); else selected.splice(index + Number(after), 0, id);
        },
        remove(id) { selected = selected.filter(value => value !== String(id)); },
        addAll() { selected.push(...order.filter(id => !selected.includes(id))); },
        removeAll() { selected = []; },
        move(id, delta) {
            const index = selected.indexOf(String(id)); const next = index + delta;
            if (index < 0 || next < 0 || next >= selected.length) return;
            [selected[index], selected[next]] = [selected[next], selected[index]];
        },
    };
}
