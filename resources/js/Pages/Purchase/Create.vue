<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)
</script>

<template>
    <Head :title="t('ui.purchase_create')" />

    <Index>
        <div class="card">
            <div class="card-body py-5 text-center text-muted">
                {{ t('ui.purchase_unconfigured') }}
            </div>
        </div>
    </Index>
</template>
