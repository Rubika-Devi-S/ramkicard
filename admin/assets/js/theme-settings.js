(() => {
    'use strict';

    const root = document.getElementById(
        'professionalThemeSettings'
    );

    if (!root) {
        return;
    }

    const canEdit = root.dataset.canEdit === '1';
    const definition =
        window.RAMKI_THEME_DEFINITION || {};
    const presets =
        window.RAMKI_THEME_PRESETS || {};

    const preview =
        document.getElementById('themePreview');

    const previewMode =
        document.getElementById('themePreviewMode');

    const csrfToken =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.content || '';

    let originalSettings = null;
    let activePreset = '';

    const colourPattern = /^(#[0-9A-Fa-f]{6}|rgba?\(\s*(?:25[0-5]|2[0-4]\d|1?\d?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\))$/i;

    function notify(type, message) {
        if (
            window.RamkiAdmin
            && typeof RamkiAdmin.toast === 'function'
        ) {
            RamkiAdmin.toast(type, message);
            return;
        }

        window.alert(message);
    }

    function normaliseColour(value) {
        const trimmed =
            String(value || '').trim();

        return trimmed.startsWith('#')
            ? trimmed.toUpperCase()
            : trimmed;
    }

    function normaliseThemeNumber(
        key,
        rawValue
    ) {
        const row = definition[key] || {};

        const fallbackRaw =
            row.light
            ?? row.dark
            ?? '0';

        let cleaned =
            String(rawValue ?? '')
                .trim()
                .replace(',', '.');

        /*
         * Accept legacy database values such as:
         * 0px, 1.5 px, 14rem.
         * The field definitions still enforce the final range.
         */
        cleaned = cleaned.replace(
            /\s*(px|rem|em|%)\s*$/i,
            ''
        ).trim();

        if (cleaned === '') {
            cleaned =
                String(fallbackRaw)
                    .trim()
                    .replace(',', '.')
                    .replace(
                        /\s*(px|rem|em|%)\s*$/i,
                        ''
                    )
                    .trim();
        }

        let number = Number(cleaned);

        if (!Number.isFinite(number)) {
            number = Number(
                String(fallbackRaw)
                    .trim()
                    .replace(',', '.')
                    .replace(
                        /\s*(px|rem|em|%)\s*$/i,
                        ''
                    )
            );
        }

        if (!Number.isFinite(number)) {
            number = 0;
        }

        const minimum =
            row.min !== undefined
                ? Number(row.min)
                : null;

        const maximum =
            row.max !== undefined
                ? Number(row.max)
                : null;

        if (
            Number.isFinite(minimum)
            && number < minimum
        ) {
            number = minimum;
        }

        if (
            Number.isFinite(maximum)
            && number > maximum
        ) {
            number = maximum;
        }

        return String(
            Number(number.toFixed(4))
        );
    }

    function sanitiseThemeNumbers() {
        document.querySelectorAll(
            '.js-theme-option[type="number"]'
        ).forEach(input => {
            input.value = normaliseThemeNumber(
                input.dataset.themeKey,
                input.value
            );

            input.classList.remove(
                'is-invalid'
            );
        });
    }

    function normaliseSelectText(value) {
        return String(value ?? '')
            .trim()
            .toLowerCase()
            .replace(/[\s_-]+/g, '');
    }

    function weightMeaning(value) {
        const token = normaliseSelectText(value);

        const meanings = {
            'normal': 'regular',
            'regular': 'regular',
            '400': 'regular',
            'medium': 'medium',
            '500': 'medium',
            'semibold': 'semibold',
            '600': 'semibold',
            'bold': 'bold',
            '700': 'bold',
            'extrabold': 'extrabold',
            '800': 'extrabold',
            'black': 'black',
            '900': 'black',
        };

        return meanings[token] || token;
    }

    function resolveSelectValue(
        key,
        requestedValue,
        control
    ) {
        const requested =
            String(requestedValue ?? '').trim();

        const options =
            Array.from(control.options || []);

        if (!options.length) {
            return '';
        }

        const exact = options.find(
            option =>
                String(option.value) === requested
        );

        if (exact) {
            return exact.value;
        }

        const requestedToken =
            normaliseSelectText(requested);

        const caseInsensitive = options.find(
            option =>
                normaliseSelectText(option.value)
                    === requestedToken
                || normaliseSelectText(option.textContent)
                    === requestedToken
        );

        if (caseInsensitive) {
            return caseInsensitive.value;
        }

        if (
            key === 'font_weight'
            || key === 'heading_font_weight'
        ) {
            const requestedMeaning =
                weightMeaning(requested);

            const semantic = options.find(
                option =>
                    weightMeaning(option.value)
                        === requestedMeaning
                    || weightMeaning(option.textContent)
                        === requestedMeaning
            );

            if (semantic) {
                return semantic.value;
            }
        }

        /*
         * Never assign an unavailable value to a select.
         * Doing so changes select.value to an empty string and
         * produces "Select a valid option" from the API.
         */
        const currentIsValid = options.some(
            option =>
                option.value === control.value
        );

        if (currentIsValid) {
            return control.value;
        }

        return options[0].value;
    }

    function sanitiseAllThemeSelects() {
        document.querySelectorAll(
            'select.js-theme-option'
        ).forEach(control => {
            control.value = resolveSelectValue(
                control.dataset.themeKey,
                control.value,
                control
            );
        });
    }

    function collectSettings() {
        const settings = {};

        document.querySelectorAll(
            '.js-theme-colour'
        ).forEach(input => {
            const key = input.dataset.themeKey;
            const mode = input.dataset.mode;

            settings[key] ||= {};
            settings[key][mode] =
                normaliseColour(input.value);
        });

        document.querySelectorAll(
            '.js-theme-option'
        ).forEach(input => {
            const key = input.dataset.themeKey;

            let value =
                String(input.value ?? '').trim();

            if (input.tagName === 'SELECT') {
                value = resolveSelectValue(
                    key,
                    value,
                    input
                );

                input.value = value;
            } else if (
                input.type === 'number'
                || input.dataset.themeType
                    === 'number'
            ) {
                value = normaliseThemeNumber(
                    key,
                    value
                );

                input.value = value;
            }

            settings[key] = {
                light: value,
                dark: value,
            };
        });

        return settings;
    }

    function setControlValue(key, values) {
        const row = definition[key] || {};
        const type = row.type || 'string';

        const pair =
            typeof values === 'object'
            && values !== null
                ? values
                : {
                    light: values,
                    dark: values,
                };

        if (type === 'color') {
            ['light', 'dark'].forEach(mode => {
                const text = document.querySelector(
                    `.js-theme-colour[data-theme-key="${CSS.escape(key)}"][data-mode="${mode}"]`
                );

                const picker = document.querySelector(
                    `.js-theme-picker[data-theme-key="${CSS.escape(key)}"][data-mode="${mode}"]`
                );

                const value = normaliseColour(
                    pair[mode]
                    ?? pair.light
                    ?? ''
                );

                if (text) {
                    text.value = value;
                }

                if (
                    picker
                    && /^#[0-9A-F]{6}$/i.test(value)
                ) {
                    picker.value = value;
                }
            });

            return;
        }

        const control = document.querySelector(
            `.js-theme-option[data-theme-key="${CSS.escape(key)}"]`
        );

        if (control) {
            const requestedValue = String(
                pair.light
                ?? pair.dark
                ?? ''
            );

            if (control.tagName === 'SELECT') {
                control.value = resolveSelectValue(
                    key,
                    requestedValue,
                    control
                );
            } else if (
                control.type === 'number'
                || control.dataset.themeType
                    === 'number'
            ) {
                control.value = normaliseThemeNumber(
                    key,
                    requestedValue
                );
            } else {
                control.value = requestedValue;
            }
        }
    }

    function valueFor(
        settings,
        key,
        mode,
        fallback = ''
    ) {
        return settings[key]?.[mode]
            ?? settings[key]?.light
            ?? fallback;
    }

    function previewVariableName(key) {
        return `--preview-${key.replaceAll('_', '-')}`;
    }

    function applyPreview() {
        if (!preview) {
            return;
        }


        const settings = collectSettings();
        const mode = previewMode?.value || 'light';

        Object.entries(settings).forEach(
            ([key, pair]) => {
                preview.style.setProperty(
                    previewVariableName(key),
                    valueFor(
                        settings,
                        key,
                        mode
                    )
                );
            }
        );

        const option = (key, fallback) =>
            valueFor(
                settings,
                key,
                'light',
                fallback
            );

        preview.style.setProperty(
            '--preview-font-family',
            option(
                'font_family',
                'Poppins, Arial, sans-serif'
            )
        );

        preview.style.setProperty(
            '--preview-heading-font-family',
            option(
                'heading_font_family',
                '"Playfair Display", Georgia, serif'
            )
        );

        preview.style.setProperty(
            '--preview-font-size',
            `${option('base_font_size', '14')}px`
        );

        preview.style.setProperty(
            '--preview-heading-size',
            `${option('heading_font_size', '25')}px`
        );

        preview.style.setProperty(
            '--preview-font-weight',
            option('font_weight', '400')
        );

        preview.style.setProperty(
            '--preview-heading-weight',
            option('heading_font_weight', '700')
        );

        preview.style.setProperty(
            '--preview-line-height',
            option('line_height', '1.5')
        );

        preview.style.setProperty(
            '--preview-letter-spacing',
            `${option('letter_spacing', '0')}px`
        );

        preview.style.setProperty(
            '--preview-card-radius',
            `${option('card_radius', '18')}px`
        );

        preview.style.setProperty(
            '--preview-button-radius',
            `${option('button_radius', '11')}px`
        );

        preview.style.setProperty(
            '--preview-button-transform',
            option(
                'button_text_transform',
                'none'
            )
        );

        preview.dataset.sidebarStyle =
            option('sidebar_style', 'gradient');

        preview.dataset.navbarStyle =
            option('navbar_style', 'solid');

        preview.dataset.cardStyle =
            option('card_style', 'elevated');

        preview.dataset.buttonStyle =
            option('button_style', 'rounded');

        preview.dataset.tableStyle =
            option('table_style', 'clean');

        preview.dataset.tableDensity =
            option('table_density', 'comfortable');
    }

    function validateControls() {
        let valid = true;

        document.querySelectorAll(
            '.js-theme-colour'
        ).forEach(input => {
            const value =
                String(input.value || '').trim();

            const colourValid =
                colourPattern.test(value);

            input.classList.toggle(
                'is-invalid',
                !colourValid
            );

            valid = valid && colourValid;
        });

        document.querySelectorAll(
            '.js-theme-option[type="number"]'
        ).forEach(input => {
            input.value = normaliseThemeNumber(
                input.dataset.themeKey,
                input.value
            );

            const numberValid =
                input.checkValidity()
                && Number.isFinite(
                    Number(input.value)
                );

            input.classList.toggle(
                'is-invalid',
                !numberValid
            );

            valid = valid && numberValid;
        });

        return valid;
    }

    function changedSettings() {
        const current = collectSettings();
        const changed = {};

        Object.entries(current).forEach(
            ([key, values]) => {
                if (
                    JSON.stringify(values)
                    !== JSON.stringify(
                        originalSettings?.[key]
                        || null
                    )
                ) {
                    changed[key] = values;
                }
            }
        );

        return changed;
    }

    function markPreset(key = '') {
        activePreset = key;

        document.querySelectorAll(
            '[data-theme-preset]'
        ).forEach(button => {
            button.classList.toggle(
                'active',
                button.dataset.themePreset === key
            );
        });
    }

    function applyPreset(key) {
        const preset = presets[key];

        if (!preset?.settings) {
            return;
        }

        Object.entries(
            preset.settings
        ).forEach(([settingKey, values]) => {
            /*
             * Colour presets must not unexpectedly replace
             * the font pair selected in Typography.
             */
            if (
                settingKey === 'font_family'
                || settingKey === 'heading_font_family'
            ) {
                return;
            }

            setControlValue(
                settingKey,
                values
            );
        });

        sanitiseAllThemeSelects();
        sanitiseThemeNumbers();
        markPreset(key);
        applyPreview();

        notify(
            'success',
            `${preset.label || 'Theme'} preview applied. Click Save All Settings to keep it.`
        );
    }

    function resetPreview() {
        if (!originalSettings) {
            return;
        }

        Object.entries(
            originalSettings
        ).forEach(([key, values]) => {
            setControlValue(key, values);
        });

        sanitiseAllThemeSelects();
        sanitiseThemeNumbers();
        markPreset('');
        applyPreview();

        notify(
            'info',
            'Theme preview was reset.'
        );
    }

    async function request(data) {
        const requestData = {
            ...data,
            _token: csrfToken,
            csrf_token: csrfToken,
        };

        return new Promise((resolve, reject) => {
            if (
                window.RamkiAdmin
                && typeof RamkiAdmin.request === 'function'
            ) {
                RamkiAdmin.request(
                    'api/theme-settings.php',
                    requestData
                )
                    .done(resolve)
                    .fail(xhr => {
                        reject(new Error(
                            xhr.responseJSON?.message
                            || 'Theme request failed.'
                        ));
                    });

                return;
            }

            fetch('api/theme-settings.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-Token': csrfToken,
                },
                body: new URLSearchParams(
                    requestData
                ),
            })
                .then(async response => {
                    const json =
                        await response.json();

                    if (
                        !response.ok
                        || !json.success
                    ) {
                        throw new Error(
                            json.message
                            || 'Theme request failed.'
                        );
                    }

                    return json;
                })
                .then(resolve)
                .catch(reject);
        });
    }

    document.querySelectorAll(
        '[data-theme-preset]'
    ).forEach(button => {
        button.addEventListener(
            'click',
            () => {
                if (!canEdit) {
                    return;
                }

                applyPreset(
                    button.dataset.themePreset
                );
            }
        );
    });

    document.querySelectorAll(
        '.js-theme-picker'
    ).forEach(picker => {
        picker.addEventListener(
            'input',
            () => {
                const text =
                    document.querySelector(
                        `.js-theme-colour[data-theme-key="${CSS.escape(picker.dataset.themeKey)}"][data-mode="${picker.dataset.mode}"]`
                    );

                if (text) {
                    text.value =
                        picker.value.toUpperCase();

                    text.classList.remove(
                        'is-invalid'
                    );
                }

                markPreset('');
                applyPreview();
            }
        );
    });

    document.querySelectorAll(
        '.js-theme-colour'
    ).forEach(input => {
        input.addEventListener(
            'input',
            () => {
                const value = normaliseColour(
                    input.value
                );

                input.value = value;

                input.classList.toggle(
                    'is-invalid',
                    !colourPattern.test(value)
                );

                const picker =
                    document.querySelector(
                        `.js-theme-picker[data-theme-key="${CSS.escape(input.dataset.themeKey)}"][data-mode="${input.dataset.mode}"]`
                    );

                if (
                    picker
                    && /^#[0-9A-F]{6}$/i.test(value)
                ) {
                    picker.value = value;
                }

                markPreset('');
                applyPreview();
            }
        );
    });

    document.querySelectorAll(
        '.js-theme-option'
    ).forEach(input => {
        input.addEventListener(
            'input',
            () => {
                markPreset('');
                applyPreview();
            }
        );

        input.addEventListener(
            'change',
            () => {
                markPreset('');
                applyPreview();
            }
        );
    });

    previewMode?.addEventListener(
        'change',
        applyPreview
    );

    document.getElementById(
        'resetThemePreview'
    )?.addEventListener(
        'click',
        resetPreview
    );

    document.getElementById(
        'saveThemeSettings'
    )?.addEventListener(
        'click',
        async () => {
            if (!canEdit) {
                return;
            }

            if (!validateControls()) {
                notify(
                    'error',
                    'Correct the invalid theme values before saving.'
                );
                return;
            }

                sanitiseAllThemeSelects();
            sanitiseThemeNumbers();

            const changed = changedSettings();

            if (!Object.keys(changed).length) {
                notify(
                    'info',
                    'All theme settings are already saved.'
                );
                return;
            }

            const button =
                document.getElementById(
                    'saveThemeSettings'
                );

            button.disabled = true;

            try {
                const response = await request({
                    action: 'save',
                    settings_json:
                        JSON.stringify(changed),
                    preset_key: activePreset,
                });

                notify(
                    'success',
                    response.message
                    || 'Theme settings saved successfully.'
                );

                window.setTimeout(
                    () => window.location.reload(),
                    550
                );
            } catch (error) {
                notify('error', error.message);
            } finally {
                button.disabled = false;
            }
        }
    );

    document.getElementById(
        'restoreThemeDefaults'
    )?.addEventListener(
        'click',
        () => {
            if (!canEdit) {
                return;
            }

            const execute = async () => {
                try {
                    const response = await request({
                        action: 'reset_defaults',
                    });

                    notify(
                        'success',
                        response.message
                        || 'Default theme restored.'
                    );

                    window.setTimeout(
                        () => window.location.reload(),
                        550
                    );
                } catch (error) {
                    notify('error', error.message);
                }
            };

            if (
                window.RamkiAdmin
                && typeof RamkiAdmin.confirm === 'function'
            ) {
                RamkiAdmin.confirm(
                    'Restore all colours, typography and component settings to the Ramki Cards defaults?',
                    execute,
                    'Restore default theme'
                );
            } else if (
                window.confirm(
                    'Restore the default Ramki Cards theme?'
                )
            ) {
                execute();
            }
        }
    );

    sanitiseAllThemeSelects();
    sanitiseThemeNumbers();

    if (previewMode) {
        previewMode.value =
            document.documentElement.dataset.theme
            === 'dark'
                ? 'dark'
                : 'light';
    }

    originalSettings = collectSettings();
    applyPreview();
})();
