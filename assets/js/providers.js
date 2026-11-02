function text(value) {
    return value == null ? '' : String(value);
}

const linkProviders = [
    {
        id: 'flightradar24',
        name: 'Flightradar24',
        urls: {
            flightnr: 'https://www.flightradar24.com/data/flights/',
            regnr: 'https://www.flightradar24.com/data/aircraft/'
        }
    },
    {
        id: 'airfleets',
        name: 'Airfleets',
        urls: {regnr: 'https://www.airfleets.net/recherche/?key='}
    }
];

const providerSettings = {flightnr: 'flightradar24', regnr: 'airfleets'};
try {
    const saved = JSON.parse(localStorage.getItem('flight-log-link-providers') || '{}');
    Object.keys(providerSettings).forEach((key) => {
        if (saved && (saved[key] === 'none' || linkProviders.some((provider) => provider.id === saved[key] && provider.urls[key]))) {
            providerSettings[key] = saved[key];
        }
    });
} catch (error) {
    // Keep defaults when browser storage is unavailable or invalid.
}

function appendProviderLinks(container, key, value) {
    let identifier = text(value).replace(/\s+/g, '').toLowerCase();
    const provider = linkProviders.find((item) => item.id === providerSettings[key] && item.urls[key]);
    if (!identifier || !provider) {
        container.textContent = text(value);
        return;
    }
    if (key === 'flightnr' && provider.id === 'flightradar24') {
        identifier = identifier.replace(/^([a-z0-9]{2}|[a-z]{3})0+(?=\d)/, '$1');
    }

    const link = document.createElement('a');
    link.className = 'provider-link';
    link.href = provider.urls[key] + encodeURIComponent(identifier);
    link.textContent = text(value);
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.setAttribute('aria-label', `${text(value)} on ${provider.name} (opens in a new tab)`);
    container.append(link);
}

