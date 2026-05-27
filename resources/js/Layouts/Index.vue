<script setup>
import { onMounted, onUnmounted, watch, nextTick  } from 'vue';
import Footer from '@/Layouts/Footer.vue';
import NavBar from '@/Layouts/NavBar.vue';
import SideBar from '@/Layouts/SideBar.vue';
import { usePage } from '@inertiajs/vue3';

// import { initHelper } from '../modules/helper';
// import { initConfig } from '../modules/config';
// import { initMain } from '../modules/main';
import GlobalConfirm from '@/Components/GlobalConfirm.vue';

// onMounted(() => {

//     document.documentElement.setAttribute(
//         'data-template',
//         'vertical-menu-template'
//     );

//     initHelper();
//     initConfig();
//     initMain();


// });

const reinitializePlugins = () => {
    nextTick(() => {
        if (typeof window.initTemplatePlugins === 'function') {
            window.initTemplatePlugins();
        }
    });
};

// При монтировании Layout
onMounted(() => {
    document.documentElement.setAttribute('data-template', 'vertical-menu-template');

    // Первая инициализация
    reinitializePlugins();
});

// Следим за изменением страницы (навигация в Inertia)
watch(() => usePage().component, () => {
    // Даем DOM время обновиться
    setTimeout(() => {
        reinitializePlugins();
    }, 100);
});

// Очистка при размонтировании (опционально)
onUnmounted(() => {
    // Очищаем select2 инстансы
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('select').each(function() {
            if ($(this).data('select2')) {
                $(this).select2('destroy');
            }
        });
    }
});



</script>

<template >
    <div class="layout-wrapper layout-content-navbar " >
        <div class="layout-container">
            <SideBar />
            <div class="layout-page">

                <NavBar></NavBar>

                <div class="content-wrapper">
                <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <slot></slot>

                    </div>
                </div>
            </div>
        </div>
    </div>


    <Footer></Footer>
    <GlobalConfirm />
    <GlobalToast />
</template>
