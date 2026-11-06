document.querySelectorAll('[data-reference-section]').forEach((section) => {
    const rows = section.querySelector('[data-new-references]');
    let nextIndex = section.querySelectorAll('.reference-row').length;
    section.querySelector('.add-reference').addEventListener('click', () => {
        const row = rows.firstElementChild.cloneNode(true);
        row.querySelectorAll('input').forEach((input) => {
            input.value = '';
            input.placeholder = '';
            input.name = input.name.replace(/\[\d+\]/, `[${nextIndex}]`);
            input.id = input.id.replace(/-\d+-/, `-${nextIndex}-`);
            input.setAttribute('aria-label', `${input.name.match(/\[([^\]]+)\]$/)[1]} new entry`);
        });
        nextIndex++;
        rows.append(row);
        row.querySelector('input').focus();
    });
    section.addEventListener('click', (event) => {
        if (!event.target.closest('.remove-reference')) return;
        const row = event.target.closest('.reference-row');
        row.querySelectorAll('input').forEach((input) => {
            if (!input.name.endsWith('[code]')) input.value = '';
        });
    });
});
