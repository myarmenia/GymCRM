<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import { useAuth } from "@/composables/useAuth";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const { user } = useAuth();
</script>

<template>
    <Head :title="useTrans('app.dashboard.title')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ useTrans("app.dashboard.title") }}
            </h2>
        </template>

        <div class="row g-6">
            <div class="col">
                <div class="card">
                    <h5 class="card-header">{{ useTrans("app.dashboard.welcome", { name: user.name }) }}</h5>
                </div>
            </div>
        </div>
    </Index>
</template>
