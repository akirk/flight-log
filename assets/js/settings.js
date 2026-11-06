Object.keys(providerSettings).forEach((key) => {
    const select = document.getElementById(`provider-${key}`);
    const choices = [{id: 'none', name: 'No link'}, ...linkProviders.filter((provider) => provider.urls[key])];
    choices.forEach((provider) => {
        const option = document.createElement('option');
        option.value = provider.id;
        option.textContent = provider.name;
        select.append(option);
    });
    select.value = providerSettings[key];
    select.addEventListener('change', () => {
        providerSettings[key] = select.value;
        const status = document.getElementById('provider-settings-status');
        try {
            localStorage.setItem('flight-log-link-providers', JSON.stringify(providerSettings));
            status.textContent = 'Settings saved in this browser.';
        } catch (error) {
            status.textContent = 'Settings applied. Browser storage is unavailable, so they will reset on reload.';
        }
    });
});

