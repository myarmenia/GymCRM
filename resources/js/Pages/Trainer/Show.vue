<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue';
import { Head, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const props = defineProps({
    user: Object,
    roles: Array,
    selectedEntryCodeId: Number,
});
</script>

<template>
    <Head :title="t('staff_reports.user_data')" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('staff_reports.user_details') }}
            </h2>
        </template>

        <div class="card">
            <h5 class="card-header">{{ t('staff_reports.user_details_2') }}</h5>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">{{ t('auth.name') }}</div>
                    <div class="col-md-9">{{ user.name }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">{{ t('filter.surname') }}</div>
                    <div class="col-md-9">{{ user.surname }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">{{ t('auth.email') }}</div>
                    <div class="col-md-9">{{ user.email }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">{{ t('filter.phone') }}</div>
                    <div class="col-md-9">{{ user.phone }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">{{ t('staff_reports.roles') }}</div>
                    <div class="col-md-9">
                        <span v-for="role in roles" :key="role" class="badge bg-primary me-1">
                            {{ role }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">{{ t('status.status') }}</div>
                    <div class="col-md-9">
                        <span :class="user.active ? 'text-success' : 'text-danger'">
                            {{ user.active ? t('status.active') : t('status.inactive') }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3" v-if="selectedEntryCodeId">
                    <div class="col-md-3 fw-bold">{{ t('staff_reports.entry_code') }}</div>
                    <div class="col-md-9">{{ selectedEntryCodeId }}</div>
                </div>
            </div>
            <div class="card-footer">
                
                <Link :href="route('user.list', { locale: currentLocale })" class="btn btn-secondary">
                    {{ t('staff_reports.return_to_list') }}
                </Link>
            </div>
        </div>
    </Index>
</template>