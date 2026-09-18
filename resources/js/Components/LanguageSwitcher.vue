<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useTrans } from '/resources/js/trans'

const page = usePage()
const languages = [
    { code: 'hy', label: 'Հայերեն' },
    { code: 'en', label: 'English' },
    { code: 'ru', label: 'Русский' },
]
const currentLocale = computed(() => page.props.locale ?? 'hy')

function changeLanguage(event) {
    const locale = event.target.value

    if (locale === currentLocale.value || !languages.some(language => language.code === locale)) {
        return
    }

    const url = new URL(window.location.href)
    const segments = url.pathname.split('/')

    if (languages.some(language => language.code === segments[1])) {
        segments[1] = locale
    } else {
        segments.splice(1, 0, locale)
    }

    url.pathname = segments.join('/')
    router.get(`${url.pathname}${url.search}${url.hash}`, {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <select
        class="form-select form-select-sm"
        :aria-label="useTrans('app.ui.language')"
        :value="currentLocale"
        @change="changeLanguage"
    >
        <option v-for="language in languages" :key="language.code" :value="language.code">
            {{ language.label }}
        </option>
    </select>
</template>
