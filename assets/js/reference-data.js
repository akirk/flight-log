document.querySelectorAll('[data-reference-section]').forEach((section) => {
    const updateSeatDescription = (row) => {
        const description = row.querySelector('[data-seat-automatic-description]');
        if (!description) return;
        const body = row.querySelector('[name$="[body_type]"]');
        const layout = row.querySelector('[name$="[seat_layout]"]');
        const bodyType = body.value || body.dataset.automaticBody || 'Unknown';
        layout.options[0].textContent = `Automatic: ${bodyType}`;
        description.textContent = JSON.parse(description.dataset.descriptions)[bodyType];
        description.hidden = Boolean(layout.value);
    };
    section.addEventListener('change', (event) => {
        const row = event.target.closest('.reference-row');
        if (row) updateSeatDescription(row);
    });
    const rows = section.querySelector('[data-new-references]');
    let nextIndex = section.querySelectorAll('.reference-row').length;
    section.querySelector('.add-reference').addEventListener('click', () => {
        const row = rows.firstElementChild.cloneNode(true);
        row.querySelectorAll('.reference-flights, .reference-seat-maps').forEach((link) => link.remove());
        row.querySelectorAll('input, select').forEach((input) => {
            input.value = '';
            input.placeholder = '';
            if (input.tagName === 'SELECT') input.options[0].textContent = input.name.endsWith('[seat_layout]') ? 'Automatic (based on body type)' : 'Automatic: Unknown';
            if (input.hasAttribute('data-automatic-body')) input.dataset.automaticBody = 'Unknown';
            input.name = input.name.replace(/\[\d+\]/, `[${nextIndex}]`);
            input.id = input.id.replace(/-\d+-/, `-${nextIndex}-`);
            input.setAttribute('aria-label', `${input.name.match(/\[([^\]]+)\]$/)[1]} new entry`);
        });
        nextIndex++;
        rows.append(row);
        updateSeatDescription(row);
        row.querySelector('input').focus();
    });
    section.addEventListener('click', (event) => {
        if (!event.target.closest('.remove-reference')) return;
        const row = event.target.closest('.reference-row');
        row.querySelectorAll('input, select').forEach((input) => {
            if (!input.name.endsWith('[code]')) input.value = '';
        });
        updateSeatDescription(row);
    });
});
