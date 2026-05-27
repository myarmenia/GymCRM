// resources/js/modules/config.js

export function initConfig() {
    console.log('Config initialized');

    // Проверяем существование window.Helpers
    if (typeof window === 'undefined') return;

    // Создаем метод getCssVar если его нет
    if (window.Helpers && !window.Helpers.getCssVar) {
        window.Helpers.getCssVar = function (cssVarName, isNumber = false) {
            try {
                const prefix = window.Helpers.prefix || 'bs-';
                const fullVarName = `--${prefix}${cssVarName}`;
                const value = getComputedStyle(document.documentElement)
                    .getPropertyValue(fullVarName)
                    .trim();

                if (isNumber && value) {
                    return parseFloat(value);
                }
                return value || (cssVarName === 'body-bg' ? '#f5f5f9' : '');
            } catch (e) {
                console.warn(`Error getting CSS var ${cssVarName}:`, e);
                return '';
            }
        };
    }

    // Создаем объект конфигурации
    window.config = {
        colors: {
            primary: window.Helpers?.getCssVar?.("primary") || '#696cff',
            secondary: window.Helpers?.getCssVar?.("secondary") || '#8592a3',
            success: window.Helpers?.getCssVar?.("success") || '#71dd37',
            info: window.Helpers?.getCssVar?.("info") || '#03c3ec',
            warning: window.Helpers?.getCssVar?.("warning") || '#ffab00',
            danger: window.Helpers?.getCssVar?.("danger") || '#ff3e1d',
            dark: window.Helpers?.getCssVar?.("dark") || '#233446',
            black: window.Helpers?.getCssVar?.("pure-black") || '#000000',
            white: window.Helpers?.getCssVar?.("white") || '#ffffff',
            cardColor: window.Helpers?.getCssVar?.("paper-bg") || '#ffffff',
            bodyBg: window.Helpers?.getCssVar?.("body-bg") || '#f5f5f9',
            bodyColor: window.Helpers?.getCssVar?.("body-color") || '#566a7f',
            headingColor: window.Helpers?.getCssVar?.("heading-color") || '#566a7f',
            textMuted: window.Helpers?.getCssVar?.("secondary-color") || '#a1acb8',
            borderColor: window.Helpers?.getCssVar?.("border-color") || '#eef2f6'
        },
        colors_label: {
            primary: window.Helpers?.getCssVar?.("primary-bg-subtle") || '#e9e7fd',
            secondary: window.Helpers?.getCssVar?.("secondary-bg-subtle") || '#f0f0f2',
            success: window.Helpers?.getCssVar?.("success-bg-subtle") || '#e8fadf',
            info: window.Helpers?.getCssVar?.("info-bg-subtle") || '#e2f5fc',
            warning: window.Helpers?.getCssVar?.("warning-bg-subtle") || '#fff2df',
            danger: window.Helpers?.getCssVar?.("danger-bg-subtle") || '#ffe5e3',
            dark: window.Helpers?.getCssVar?.("dark-bg-subtle") || '#e6e8ea'
        },
        fontFamily: window.Helpers?.getCssVar?.("font-family-base") || '"Inter", sans-serif',
        enableMenuLocalStorage: true
    };

    // Устанавливаем assets path
    window.assetsPath = document.documentElement.getAttribute("data-assets-path") || '/';
    window.templateName = document.documentElement.getAttribute("data-template") || 'vertical-menu-template';

    // Проверяем TemplateCustomizer (он может не существовать)
    if (typeof TemplateCustomizer !== 'undefined' && !window.templateCustomizer) {
        try {
            window.templateCustomizer = new TemplateCustomizer({
                displayCustomizer: false, // Отключаем для Vue/Inertia
                lang: localStorage.getItem(`templateCustomizer-${window.templateName}--Lang`) || "en",
                controls: ["color", "theme", "skins", "semiDark", "layoutCollapsed", "layoutNavbarOptions", "headerType", "contentLayout", "rtl"]
            });
        } catch (e) {
            console.warn('TemplateCustomizer not available:', e);
            window.templateCustomizer = null;
        }
    } else if (!window.templateCustomizer) {
        // Создаем заглушку для TemplateCustomizer
        window.templateCustomizer = {
            _getSetting: function (setting) {
                return localStorage.getItem(`templateCustomizer-${window.templateName}--${setting}`);
            },
            setSetting: function (setting, value) {
                localStorage.setItem(`templateCustomizer-${window.templateName}--${setting}`, value);
            }
        };
    }

    console.log('Config loaded:', window.config);
}
