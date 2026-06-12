(function(){
    // Determine script base URL where clima.js is hosted
    const baseUrl = (function(){
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++){
            const src = scripts[i].src || '';
            if (src.match(/clima\.js(\?.*)?$/)){
                return src.replace(/\/clima\.js(?:\?.*)?$/,'');
            }
        }
        // Fallback: try to locate '/clima/' in the current path
        const p = window.location.pathname;
        const idx = p.lastIndexOf('/clima/');
        if (idx !== -1) return window.location.origin + p.substring(0, idx+7);
        return '';
    })();

    function resolve(path) {
        if (!baseUrl) return path; // relative fallback
        // if path already absolute (starts with http or /), return as-is
        if (/^(https?:)?\//.test(path)) return path;
        return baseUrl + '/' + path.replace(/^\//,'');
    }

    // Safe fetch helper
    async function fetchJsonSafe(url, opts){
        const response = await fetch(resolve(url), opts);
        const text = await response.text();
        try {
            const data = JSON.parse(text);
            return { response, data };
        } catch (err) {
            console.error('Non-JSON response for', url, '\n---- RESPONSE START ----\n', text.slice(0,2000), '\n---- RESPONSE END ----');
            throw new Error('Resposta inválida do servidor (não-JSON). Veja console para detalhes.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchBtn = document.getElementById('search-btn');
        const panelSearchBtn = document.getElementById('panel-search-btn');
        const cityInput = document.getElementById('city-input');
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error-message');
        const resultDiv = document.getElementById('weather-result');
        const weatherCard = document.querySelector('.weather-card');
        const openFiltersBtn = document.getElementById('open-filters-btn');
        const closeFiltersBtn = document.getElementById('close-filters-btn');
        const filterBackdrop = document.getElementById('filter-backdrop');
        const locationPanelEl = document.getElementById('location-panel');
        const locationStatus = document.getElementById('location-status');
        const selectionSummary = document.getElementById('selection-summary');
        const continentList = document.getElementById('continent-list');
        const countryList = document.getElementById('country-list');
        const stateList = document.getElementById('state-list');
        const cityList = document.getElementById('city-list');
        const countrySearch = document.getElementById('country-list-search');
        const stateSearch = document.getElementById('state-list-search');
        const citySearch = document.getElementById('city-list-search');
        const countEls = {
            continents: document.getElementById('continent-count'),
            countries: document.getElementById('country-count'),
            states: document.getElementById('state-count'),
            cities: document.getElementById('city-count')
        };
        const bodyThemeClasses = [
            'bg-clear','bg-clouds','bg-rain','bg-drizzle','bg-thunderstorm','bg-snow','bg-mist','bg-hot','bg-cold','bg-spring','bg-autumn','bg-night'
        ];
        const cardThemeClasses = ['clima-dia','clima-tarde','clima-tempestade','clima-noite'];
        const continentOrder = ['África','América do Norte','América Central','América do Sul','Ásia','Europa','Oceania','Antártida'];
        const locality = {countries:[],cities:[],citiesCache:new Map(),selected:{continent:'',country:null,state:null,city:''},loadingCities:false};

        const els = {
            cityName: document.getElementById('city-name'),
            coordinates: document.getElementById('coordinates'),
            temperature: document.getElementById('temperature'),
            visual: document.getElementById('weather-visual'),
            description: document.getElementById('description'),
            feelsLike: document.getElementById('feels-like'),
            tempRangeLabel: document.getElementById('temp-range-label'),
            tempRange: document.getElementById('temp-range'),
            humidity: document.getElementById('humidity'),
            windSpeed: document.getElementById('wind-speed'),
            pressure: document.getElementById('pressure'),
            visibility: document.getElementById('visibility'),
            sunrise: document.getElementById('sunrise'),
            sunset: document.getElementById('sunset')
        };

        openFiltersBtn && openFiltersBtn.addEventListener('click', openFilters);
        closeFiltersBtn && closeFiltersBtn.addEventListener('click', closeFilters);
        filterBackdrop && filterBackdrop.addEventListener('click', closeFilters);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.body.classList.contains('filters-open')) {
                closeFilters();
            }
        });

        if (searchBtn) searchBtn.addEventListener('click', fetchWeather);
        panelSearchBtn && panelSearchBtn.addEventListener('click', () => { fetchWeather(); closeFilters({ restoreFocus: false }); });
        cityInput && cityInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') fetchWeather(); });
        cityInput && cityInput.addEventListener('input', () => { if (cityInput.value.trim() !== locality.selected.city) { locality.selected.city = ''; renderLocationUi(); } });
        [countrySearch, stateSearch, citySearch].forEach((input) => { if (input) input.addEventListener('input', renderLocationUi); });

        function openFilters() {
            document.body.classList.add('filters-open');
            locationPanelEl.setAttribute('aria-hidden', 'false');
            locationPanelEl.removeAttribute('inert');
            openFiltersBtn.setAttribute('aria-expanded', 'true');
            closeFiltersBtn.focus({ preventScroll: true });
        }

        function closeFilters(options = {}) {
            const { restoreFocus = true } = options;

            document.body.classList.remove('filters-open');
            locationPanelEl.setAttribute('aria-hidden', 'true');
            locationPanelEl.setAttribute('inert', '');
            openFiltersBtn.setAttribute('aria-expanded', 'false');

            if (restoreFocus) {
                openFiltersBtn.focus({ preventScroll: true });
            }
        }

        function formatTime(unixTimestamp) {
            const date = new Date(unixTimestamp * 1000);
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        function getSeasonTheme(month) {
            if (month >= 8 && month <= 10) return 'bg-spring';
            if (month >= 2 && month <= 4) return 'bg-autumn';
            return null;
        }

        function getWeatherTheme(data) {
            const weatherId = data.weather[0].id;
            const weatherMain = data.weather[0].main.toLowerCase();
            const temp = data.main.temp;
            const now = data.dt;
            const sunrise = data.sys.sunrise;
            const sunset = data.sys.sunset;
            const localMonth = new Date((now + data.timezone) * 1000).getUTCMonth();
            const isNight = now < sunrise || now > sunset;

            if (isNight) return { body: 'bg-night', card: 'clima-noite' };
            if (weatherId >= 200 && weatherId < 300) return { body: 'bg-thunderstorm', card: 'clima-tempestade' };
            if (weatherId >= 300 && weatherId < 400) return { body: 'bg-drizzle', card: 'clima-tempestade' };
            if (weatherId >= 500 && weatherId < 600) return { body: 'bg-rain', card: 'clima-tempestade' };
            if (weatherId >= 600 && weatherId < 700) return { body: 'bg-snow', card: 'clima-noite' };
            if (weatherId >= 700 && weatherId < 800) return { body: 'bg-mist', card: 'clima-tempestade' };
            if (temp >= 32) return { body: 'bg-hot', card: 'clima-tarde' };
            if (temp <= 10) return { body: 'bg-cold', card: 'clima-noite' };
            if (weatherMain === 'clouds') return { body: 'bg-clouds', card: 'clima-dia' };

            return { body: getSeasonTheme(localMonth) || 'bg-clear', card: 'clima-dia' };
        }

        function applyWeatherTheme(data) {
            const theme = getWeatherTheme(data);

            document.body.classList.remove(...bodyThemeClasses);
            weatherCard.classList.remove(...cardThemeClasses);

            document.body.classList.add(theme.body);
            weatherCard.classList.add(theme.card);
        }

        function isNightWeather(data) {
            return data.weather[0].icon?.includes('n') || data.dt < data.sys.sunrise || data.dt > data.sys.sunset;
        }

        function renderWeatherVisual(weather, isNight) {
            const id = weather.id || 800;
            const main = String(weather.main || '').toLowerCase();
            let markup;

            if (id >= 200 && id < 300) {
                markup = `...`;
            } else if ((id >= 300 && id < 400) || (id >= 500 && id < 600)) {
                markup = `...`;
            } else if (id >= 600 && id < 700) {
                markup = `...`;
            } else if (id >= 700 && id < 800) {
                markup = `...`;
            } else if (main === 'clouds') {
                markup = `...`;
            } else if (isNight) {
                markup = `...`;
            } else {
                markup = `...`;
            }

            els.visual.innerHTML = markup;
            els.visual.setAttribute('aria-label', `Ilustração de ${weather.description || 'clima atual'}`);
        }

        function normalizeText(text) {
            return String(text || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function setStatus(message, type = 'muted') {
            locationStatus.textContent = message;
            locationStatus.className = `location-status ${type}`;
        }

        function setListMessage(container, message) {
            container.textContent = '';
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.textContent = message;
            container.appendChild(empty);
        }

        function renderOptionList(container, items, config) {
            container.textContent = '';

            if (items.length === 0) {
                setListMessage(container, config.emptyMessage);
                return;
            }

            const fragment = document.createDocumentFragment();
            items.forEach((item) => {
                const key = config.key(item);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'option-btn';
                button.dataset.key = key;
                if (key === config.selectedKey) button.classList.add('is-active');

                const label = document.createElement('span');
                label.textContent = config.label(item);
                button.appendChild(label);

                const metaText = config.meta ? config.meta(item) : '';
                if (metaText) {
                    const meta = document.createElement('small');
                    meta.textContent = metaText;
                    button.appendChild(meta);
                }

                button.addEventListener('click', () => config.onSelect(item));
                fragment.appendChild(button);
            });

            container.appendChild(fragment);
        }

        function filterItems(items, query, fields) {
            const normalizedQuery = normalizeText(query);
            if (!normalizedQuery) return items;

            return items.filter((item) => {
                return fields.some((field) => normalizeText(field(item)).includes(normalizedQuery));
            });
        }

        function getContinents() {
            const available = new Set(locality.countries.map((country) => country.continente).filter(Boolean));
            return continentOrder.filter((continent) => available.has(continent));
        }

        function getVisibleCountries() {
            const baseCountries = locality.selected.continent
                ? locality.countries.filter((country) => country.continente === locality.selected.continent)
                : locality.countries;

            return filterItems(baseCountries, countrySearch.value, [
                (country) => country.nome,
                (country) => country.nome_api,
                (country) => country.codigo
            ]);
        }

        function getVisibleStates() {
            if (!locality.selected.country) return [];
            const states = locality.selected.country.estados || [];

            return filterItems(states, stateSearch.value, [
                (state) => state.nome,
                (state) => state.codigo
            ]);
        }

        function getVisibleCities() {
            return filterItems(locality.cities, citySearch.value, [(city) => city]);
        }

        function resetAfter(level) {
            if (level === 'continent') {
                locality.selected.country = null;
                locality.selected.state = null;
                locality.selected.city = '';
                locality.cities = [];
                countrySearch.value = '';
                stateSearch.value = '';
                citySearch.value = '';
                cityInput.value = '';
            }

            if (level === 'country') {
                locality.selected.state = null;
                locality.selected.city = '';
                locality.cities = [];
                stateSearch.value = '';
                citySearch.value = '';
                cityInput.value = '';
            }

        }

        function selectContinent(continent) {
            locality.selected.continent = continent;
            resetAfter('continent');
            setStatus('Países atualizados pelo continente.', 'ok');
            renderLocationUi();
            document.getElementById('picker-country').open = true;
        }

        function selectCountry(country) {
            locality.selected.country = country;
            locality.selected.continent = country.continente || locality.selected.continent;
            resetAfter('country');

            if ((country.estados || []).length === 0) {
                loadCitiesForSelection();
                document.getElementById('picker-city').open = true;
            } else {
                setStatus('Estados e províncias atualizados pelo país.', 'ok');
                document.getElementById('picker-state').open = true;
            }

            renderLocationUi();
        }

        function selectState(state) {
            locality.selected.state = state;
            resetAfter('state');
            renderLocationUi();
            loadCitiesForSelection();
            document.getElementById('picker-city').open = true;
        }

        function selectCity(city) {
            locality.selected.city = city;
            cityInput.value = city;
            renderLocationUi();
            fetchWeather();
            closeFilters({ restoreFocus: false });
        }

        function updateSelectionSummary() {
            const parts = [
                locality.selected.continent,
                locality.selected.country?.nome,
                locality.selected.state?.nome,
                locality.selected.city || cityInput.value.trim()
            ].filter(Boolean);

            selectionSummary.textContent = parts.length ? parts.join(' > ') : 'Nenhum local selecionado';
            panelSearchBtn.disabled = !buildWeatherParams();
        }

        function renderLocationUi() {
            const continents = getContinents();
            const countries = getVisibleCountries();
            const states = getVisibleStates();
            const cities = getVisibleCities();
            const countryHasStates = (locality.selected.country?.estados || []).length > 0;

            countEls.continents.textContent = continents.length;
            countEls.countries.textContent = countries.length;
            countEls.states.textContent = states.length;
            countEls.cities.textContent = cities.length;
            stateSearch.disabled = !locality.selected.country || !countryHasStates;
            citySearch.disabled = !locality.selected.country || (countryHasStates && !locality.selected.state);

            renderOptionList(continentList, continents, {
                key: (continent) => continent,
                label: (continent) => continent,
                selectedKey: locality.selected.continent,
                emptyMessage: 'Nenhum continente carregado',
                onSelect: selectContinent
            });

            renderOptionList(countryList, countries, {
                key: (country) => country.codigo,
                label: (country) => country.nome,
                meta: (country) => `${country.codigo} · ${country.continente}`,
                selectedKey: locality.selected.country?.codigo || '',
                emptyMessage: 'Nenhum país correspondente',
                onSelect: selectCountry
            });

            if (!locality.selected.country) {
                setListMessage(stateList, 'Selecione um país');
            } else if (!countryHasStates) {
                setListMessage(stateList, 'Sem estados cadastrados');
            } else {
                renderOptionList(stateList, states, {
                    key: (state) => state.nome,
                    label: (state) => state.nome,
                    meta: (state) => state.codigo,
                    selectedKey: locality.selected.state?.nome || '',
                    emptyMessage: 'Nenhum estado correspondente',
                    onSelect: selectState
                });
            }

            if (locality.loadingCities) {
                setListMessage(cityList, 'Carregando cidades...');
            } else if (!locality.selected.country) {
                setListMessage(cityList, 'Selecione um país');
            } else if (countryHasStates && !locality.selected.state) {
                setListMessage(cityList, 'Selecione um estado');
            } else {
                renderOptionList(cityList, cities, {
                    key: (city) => city,
                    label: (city) => city,
                    selectedKey: locality.selected.city,
                    emptyMessage: 'Nenhuma cidade correspondente',
                    onSelect: selectCity
                });
            }

            updateSelectionSummary();
        }

        async function loadLocationData() {
            setStatus('Carregando opções...', 'muted');
            try {
                const { response, data } = await fetchJsonSafe('localidades-api.php?tipo=paises');

                if (!response.ok || data.erro) {
                    throw new Error(data.erro || 'Erro ao carregar localidades.');
                }

                locality.countries = data.paises || [];
                setStatus('Opções carregadas.', 'ok');
                renderLocationUi();
            } catch (error) {
                setStatus(error.message, 'error');
                renderLocationUi();
            }
        }

        async function loadCitiesForSelection() {
            if (!locality.selected.country) return;

            const countryHasStates = (locality.selected.country.estados || []).length > 0;
            if (countryHasStates && !locality.selected.state) return;

            const cacheKey = [
                locality.selected.country.nome_api,
                locality.selected.state?.nome || ''
            ].join('|');

            if (locality.citiesCache.has(cacheKey)) {
                locality.cities = locality.citiesCache.get(cacheKey);
                setStatus('Cidades atualizadas.', 'ok');
                renderLocationUi();
                return;
            }

            locality.loadingCities = true;
            setStatus('Carregando cidades...', 'muted');
            renderLocationUi();

            const params = new URLSearchParams({
                tipo: 'cidades',
                pais_api: locality.selected.country.nome_api
            });

            if (locality.selected.state?.nome) {
                params.set('estado', locality.selected.state.nome);
            }

            try {
                const { response, data } = await fetchJsonSafe(`localidades-api.php?${params.toString()}`);

                if (!response.ok || data.erro) {
                    throw new Error(data.erro || 'Erro ao carregar cidades.');
                }

                locality.cities = data.cidades || [];
                locality.citiesCache.set(cacheKey, locality.cities);
                setStatus('Cidades carregadas.', 'ok');
            } catch (error) {
                locality.cities = [];
                setStatus(error.message, 'error');
            } finally {
                locality.loadingCities = false;
                renderLocationUi();
            }
        }

        function buildWeatherParams() {
            const city = locality.selected.city || cityInput.value.trim();
            if (!city) return null;

            const params = new URLSearchParams({ cidade: city });
            const countryCode = locality.selected.country?.codigo || '';
            const state = locality.selected.state?.nome || '';
            const continent = locality.selected.continent || '';

            if (countryCode) params.set('pais', countryCode);
            if (state) params.set('estado', state);
            if (continent) params.set('continente', continent);

            return params;
        }

        async function fetchWeather() {
            const params = buildWeatherParams();
            if (!params) {
                showError("Escolha uma cidade no painel ou digite o nome da cidade.");
                return;
            }

            hideAll();
            loadingDiv.style.display = 'block';

            try {
                const { response, data } = await fetchJsonSafe(`clima-api.php?${params.toString()}`);

                if (!response.ok || data.erro) {
                    throw new Error(data.erro || 'Erro ao buscar dados da cidade.');
                }

                const tempMin = Math.round(data.main.temp_min);
                const tempMax = Math.round(data.main.temp_max);
                const hasEqualRange = tempMin === tempMax;

                els.cityName.textContent = data.localizacao?.nome_completo || `${data.name}, ${data.sys.country}`;
                els.coordinates.textContent = `Lat: ${data.coord.lat.toFixed(2)} | Lon: ${data.coord.lon.toFixed(2)}`;
                els.temperature.textContent = `${Math.round(data.main.temp)}°C`;
                els.description.textContent = data.weather[0].description;
                els.feelsLike.textContent = `${Math.round(data.main.feels_like)}°C`;
                els.tempRangeLabel.textContent = hasEqualRange ? 'Temperatura' : 'Min / Máx';
                els.tempRange.textContent = hasEqualRange ? `${tempMin}°C` : `${tempMin}°C / ${tempMax}°C`;
                renderWeatherVisual(data.weather[0], isNightWeather(data));
                els.humidity.textContent = `${data.main.humidity}%`;
                els.pressure.textContent = `${data.main.pressure} hPa`;
                els.windSpeed.textContent = `${Math.round(data.wind.speed * 3.6)} km/h`;
                els.visibility.textContent = `${(data.visibility / 1000).toFixed(1)} km`;
                els.sunrise.textContent = formatTime(data.sys.sunrise);
                els.sunset.textContent = formatTime(data.sys.sunset);

                applyWeatherTheme(data);

                loadingDiv.style.display = 'none';
                resultDiv.style.display = 'block';

            } catch (error) {
                loadingDiv.style.display = 'none';
                console.error('fetchWeather error:', error);
                showError(error.message);
            }
        }

        function showError(message) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            resultDiv.style.display = 'none';
        }

        function hideAll() {
            loadingDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            resultDiv.style.display = 'none';
        }

        renderLocationUi();
        loadLocationData();
    });
})();
