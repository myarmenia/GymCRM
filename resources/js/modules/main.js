// resources/js/modules/main.js

export function initMain() {
    console.log('Main initialized');

    // Проверяем существование window.Helpers
    if (typeof window === 'undefined' || !window.Helpers) {
        console.warn('Helpers not initialized yet');
        return;
    }

    // Для Inertia - всегда переинициализируем, но с очисткой старых обработчиков
    if (window._mainInitialized) {
        console.log('Reinitializing main after navigation');
        // Очищаем старые обработчики перед повторной инициализацией
        cleanupMain();
    }

    window.isRtl = window.Helpers.isRtl?.() || false;
    window.isDarkStyle = window.Helpers.isDarkStyle?.() || false;

    let menu, animate;
    let isHorizontalLayout = false;

    // Проверяем наличие layout-menu
    const layoutMenu = document.getElementById("layout-menu");
    if (layoutMenu) {
        isHorizontalLayout = layoutMenu.classList.contains("menu-horizontal");
    }

    // Функция обработчика скролла
    function handleScrollEffect() {
        var layoutPage = document.querySelector(".layout-page");
        if (layoutPage) {
            if (window.scrollY > 0) {
                layoutPage.classList.add("window-scrolled");
            } else {
                layoutPage.classList.remove("window-scrolled");
            }
        }
    }

    // Удаляем старый обработчик скролла
    if (window._mainScrollHandler) {
        window.removeEventListener('scroll', window._mainScrollHandler);
    }

    // Добавляем новый обработчик
    const scrollHandler = () => handleScrollEffect();
    window.addEventListener('scroll', scrollHandler);
    window._mainScrollHandler = scrollHandler;

    // Вызываем сразу
    setTimeout(() => {
        try {
            handleScrollEffect();
        } catch (e) {
            console.warn('Scroll effect error:', e);
        }
    }, 200);

    // Инициализация Custom Option Check
    setTimeout(function () {
        try {
            if (window.Helpers && typeof window.Helpers.initCustomOptionCheck === 'function') {
                window.Helpers.initCustomOptionCheck();
            }
        } catch (e) {
            console.warn('initCustomOptionCheck error:', e);
        }
    }, 1000);

    // Инициализация Waves (если загружен)
    if (typeof Waves !== 'undefined') {
        try {
            Waves.init();
            Waves.attach(".btn[class*='btn-']:not(.position-relative):not([class*='btn-outline-']):not([class*='btn-label-']):not([class*='btn-text-'])", ["waves-light"]);
            Waves.attach("[class*='btn-outline-']:not(.position-relative)");
            Waves.attach("[class*='btn-label-']:not(.position-relative)");
            Waves.attach("[class*='btn-text-']:not(.position-relative)");
            Waves.attach('.pagination:not([class*="pagination-outline-"]) .page-item.active .page-link', ["waves-light"]);
            Waves.attach(".pagination .page-item .page-link");
            Waves.attach(".dropdown-menu .dropdown-item");
        } catch (e) {
            console.warn('Waves init error:', e);
        }
    }

    // Инициализация Menu (если класс Menu существует)
    const menuElements = document.querySelectorAll("#layout-menu");
    if (menuElements.length && typeof Menu !== 'undefined') {
        try {
            menuElements.forEach(function (e) {
                const templateName = window.templateName || 'vertical-menu-template';
                const showDropdownOnHover = localStorage.getItem("templateCustomizer-" + templateName + "--ShowDropdownOnHover");

                menu = new Menu(e, {
                    orientation: isHorizontalLayout ? "horizontal" : "vertical",
                    closeChildren: !!isHorizontalLayout,
                    showDropdownOnHover: showDropdownOnHover ? showDropdownOnHover === "true" : true
                });

                if (window.Helpers && typeof window.Helpers.scrollToActive === 'function') {
                    window.Helpers.scrollToActive(false);
                }

                window.Helpers.mainMenu = menu;
            });
        } catch (e) {
            console.warn('Menu init error:', e);
        }
    }

    // ============ КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ: перепривязываем toggle buttons ============
    // Удаляем старые обработчики через клонирование кнопок
    const toggleButtons = document.querySelectorAll(".layout-menu-toggle");
    toggleButtons.forEach(btn => {
        // Создаем клон без обработчиков
        const newBtn = btn.cloneNode(true);
        if (btn.parentNode) {
            btn.parentNode.replaceChild(newBtn, btn);
        }

        // Добавляем новый обработчик
        newBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.Helpers?.toggleCollapsed === 'function') {
                window.Helpers.toggleCollapsed();
            }

            const config = window.config || {};
            const templateName = window.templateName || 'vertical-menu-template';

            if (config.enableMenuLocalStorage && window.Helpers && !window.Helpers.isSmallScreen?.()) {
                try {
                    localStorage.setItem("templateCustomizer-" + templateName + "--LayoutCollapsed", String(window.Helpers.isCollapsed?.()));
                    const n = document.querySelector(".template-customizer-layouts-options");
                    if (n) {
                        const t = window.Helpers.isCollapsed?.() ? "collapsed" : "expanded";
                        const input = n.querySelector(`input[value="${t}"]`);
                        if (input) input.click();
                    }
                } catch (err) { }
            }
        });
    });

    // Swipe handlers (если есть)
    if (typeof window.Helpers?.swipeIn === 'function') {
        window.Helpers.swipeIn(".drag-target", function (e) {
            window.Helpers?.setCollapsed?.(false);
        });
    }

    if (typeof window.Helpers?.swipeOut === 'function') {
        window.Helpers.swipeOut("#layout-menu", function (e) {
            if (window.Helpers?.isSmallScreen?.()) {
                window.Helpers?.setCollapsed?.(true);
            }
        });
    }

    // Menu inner shadow
    const menuInner = document.getElementsByClassName("menu-inner");
    const menuInnerShadow = document.getElementsByClassName("menu-inner-shadow")[0];
    if (menuInner.length > 0 && menuInnerShadow) {
        // Удаляем старый обработчик если есть
        if (window._menuScrollHandler) {
            menuInner[0].removeEventListener("ps-scroll-y", window._menuScrollHandler);
        }

        const scrollHandlerInner = function () {
            const thumb = this.querySelector(".ps__thumb-y");
            if (thumb && thumb.offsetTop) {
                menuInnerShadow.style.display = "block";
            } else {
                menuInnerShadow.style.display = "none";
            }
        };

        menuInner[0].addEventListener("ps-scroll-y", scrollHandlerInner);
        window._menuScrollHandler = scrollHandlerInner;
    }

    // Theme handling
    const templateName = window.templateName || 'vertical-menu-template';
    let storedTheme = localStorage.getItem("templateCustomizer-" + templateName + "--Theme");
    if (!storedTheme && window.templateCustomizer?.settings) {
        storedTheme = window.templateCustomizer.settings.defaultStyle;
    }
    if (!storedTheme) {
        storedTheme = document.documentElement.getAttribute("data-bs-theme") || 'light';
    }

    // Function to update scrollbar width
    function updateScrollbarWidth() {
        const width = window.innerWidth - document.documentElement.clientWidth;
        document.body.style.setProperty("--bs-scrollbar-width", width + "px");
    }

    // Switch image based on theme
    if (typeof window.Helpers?.switchImage === 'function') {
        window.Helpers.switchImage(storedTheme);
    }

    if (typeof window.Helpers?.setTheme === 'function') {
        window.Helpers.setTheme(window.Helpers.getPreferredTheme?.() || 'light');
    }

    // Listen for system theme changes
    if (window.matchMedia) {
        if (window._themeListener) {
            window.matchMedia("(prefers-color-scheme: dark)").removeEventListener("change", window._themeListener);
        }

        window._themeListener = () => {
            const theme = window.Helpers?.getStoredTheme?.();
            if (theme !== 'light' && theme !== 'dark') {
                window.Helpers?.setTheme?.(window.Helpers.getPreferredTheme?.());
            }
        };

        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", window._themeListener);
    }

    updateScrollbarWidth();

    // DOM Content Loaded handlers
    const initDomHandlers = () => {
        if (typeof window.Helpers?.showActiveTheme === 'function') {
            window.Helpers.showActiveTheme(window.Helpers.getPreferredTheme?.());
        }
        updateScrollbarWidth();

        if (typeof window.Helpers?.initSidebarToggle === 'function') {
            window.Helpers.initSidebarToggle();
        }

        // Theme value buttons - перепривязываем
        const themeBtns = document.querySelectorAll("[data-bs-theme-value]");
        if (window._themeBtnHandlers) {
            themeBtns.forEach(btn => {
                if (btn._handler) {
                    btn.removeEventListener("click", btn._handler);
                }
            });
        }

        themeBtns.forEach(btn => {
            const handler = () => {
                const theme = btn.getAttribute("data-bs-theme-value");
                if (typeof window.Helpers?.setStoredTheme === 'function') {
                    window.Helpers.setStoredTheme(templateName, theme);
                }
                if (typeof window.Helpers?.setTheme === 'function') {
                    window.Helpers.setTheme(theme);
                }
                if (typeof window.Helpers?.showActiveTheme === 'function') {
                    window.Helpers.showActiveTheme(theme, true);
                }
                if (typeof window.Helpers?.syncCustomOptions === 'function') {
                    window.Helpers.syncCustomOptions(theme);
                }

                let actualTheme = theme;
                if (theme === "system") {
                    actualTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
                }

                const semiDark = document.querySelector(".template-customizer-semiDark");
                if (semiDark) {
                    if (theme === "dark") {
                        semiDark.classList.add("d-none");
                    } else {
                        semiDark.classList.remove("d-none");
                    }
                }

                if (typeof window.Helpers?.switchImage === 'function') {
                    window.Helpers.switchImage(actualTheme);
                }
            };

            btn._handler = handler;
            btn.addEventListener("click", handler);
        });

        window._themeBtnHandlers = true;
    };

    // Проверяем состояние документа
    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initDomHandlers);
    } else {
        initDomHandlers();
    }

    // Tooltips - переинициализируем
    if (typeof bootstrap !== 'undefined') {
        // Удаляем старые tooltips
        const oldTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        oldTooltips.forEach(el => {
            const tooltip = bootstrap.Tooltip.getInstance(el);
            if (tooltip) tooltip.dispose();
        });

        // Создаем новые
        [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function (e) {
            return new bootstrap.Tooltip(e);
        });
    }

    // Accordion handlers - перепривязываем
    function handleAccordion(e) {
        if (e.type === "show.bs.collapse") {
            e.target.closest(".accordion-item")?.classList.add("active");
        } else {
            e.target.closest(".accordion-item")?.classList.remove("active");
        }
    }

    // Удаляем старые обработчики
    if (window._accordionHandlers) {
        const oldAccordions = document.querySelectorAll(".accordion");
        oldAccordions.forEach(accordion => {
            accordion.removeEventListener("show.bs.collapse", handleAccordion);
            accordion.removeEventListener("hide.bs.collapse", handleAccordion);
        });
    }

    // Добавляем новые
    [].slice.call(document.querySelectorAll(".accordion")).map(function (e) {
        e.addEventListener("show.bs.collapse", handleAccordion);
        e.addEventListener("hide.bs.collapse", handleAccordion);
    });
    window._accordionHandlers = true;

    // Auto update
    if (typeof window.Helpers?.setAutoUpdate === 'function') {
        window.Helpers.setAutoUpdate(true);
    }

    // Additional helpers
    if (typeof window.Helpers?.initPasswordToggle === 'function') {
        window.Helpers.initPasswordToggle();
    }

    if (typeof window.Helpers?.initSpeechToText === 'function') {
        window.Helpers.initSpeechToText();
    }

    if (typeof window.Helpers?.initNavbarDropdownScrollbar === 'function') {
        window.Helpers.initNavbarDropdownScrollbar();
    }

    // Horizontal menu handling
    const isHorizontalMenu = document.querySelector("[data-template^='horizontal-menu']");
    if (isHorizontalMenu && typeof window.Helpers?.setNavbarFixed === 'function') {
        if (window.innerWidth < (window.Helpers.LAYOUT_BREAKPOINT || 1200)) {
            window.Helpers.setNavbarFixed("fixed");
        } else {
            window.Helpers.setNavbarFixed("");
        }
    }

    // Resize handler - удаляем старый
    if (window._resizeHandler) {
        window.removeEventListener("resize", window._resizeHandler);
    }

    window._resizeHandler = function () {
        if (isHorizontalMenu && typeof window.Helpers?.setNavbarFixed === 'function') {
            if (window.innerWidth < (window.Helpers.LAYOUT_BREAKPOINT || 1200)) {
                window.Helpers.setNavbarFixed("fixed");
            } else {
                window.Helpers.setNavbarFixed("");
            }

            setTimeout(function () {
                const layoutMenuEl = document.getElementById("layout-menu");
                if (window.innerWidth < (window.Helpers.LAYOUT_BREAKPOINT || 1200)) {
                    if (layoutMenuEl && layoutMenuEl.classList.contains("menu-horizontal") && typeof menu?.switchMenu === 'function') {
                        menu.switchMenu("vertical");
                    }
                } else {
                    if (layoutMenuEl && layoutMenuEl.classList.contains("menu-vertical") && typeof menu?.switchMenu === 'function') {
                        menu.switchMenu("horizontal");
                    }
                }
            }, 100);
        }
    };

    window.addEventListener("resize", window._resizeHandler, true);

    // Menu collapsed state from localStorage
    const config = window.config || {};
    if (!isHorizontalLayout && !window.Helpers?.isSmallScreen?.()) {
        if (typeof window.templateCustomizer !== 'undefined' && window.templateCustomizer.settings) {
            if (window.templateCustomizer.settings.defaultMenuCollapsed) {
                window.Helpers?.setCollapsed?.(true, false);
            } else {
                window.Helpers?.setCollapsed?.(false, false);
            }

            if (window.templateCustomizer.settings.semiDark) {
                document.querySelector("#layout-menu")?.setAttribute("data-bs-theme", "dark");
            }
        }

        if (config.enableMenuLocalStorage) {
            try {
                const collapsed = localStorage.getItem("templateCustomizer-" + templateName + "--LayoutCollapsed");
                if (collapsed !== null) {
                    window.Helpers?.setCollapsed?.(collapsed === "true", false);
                }
            } catch (e) { }
        }
    }

    // Mark as initialized
    window._mainInitialized = true;

    // Обновляем Helpers после инициализации
    setTimeout(() => {
        if (window.Helpers && typeof window.Helpers.update === 'function') {
            window.Helpers.update();
        }
    }, 100);
}

// Cleanup function
export function cleanupMain() {
    if (window._mainScrollHandler) {
        window.removeEventListener('scroll', window._mainScrollHandler);
        window._mainScrollHandler = null;
    }

    if (window._resizeHandler) {
        window.removeEventListener("resize", window._resizeHandler);
        window._resizeHandler = null;
    }

    if (window._themeListener) {
        window.matchMedia("(prefers-color-scheme: dark)").removeEventListener("change", window._themeListener);
        window._themeListener = null;
    }

    if (window._menuScrollHandler && window._menuScrollHandler.element) {
        window._menuScrollHandler.element.removeEventListener("ps-scroll-y", window._menuScrollHandler);
        window._menuScrollHandler = null;
    }

    window._mainInitialized = false;
}

export function destroyMain() {
    cleanupMain();
}
