<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Pagination from "@/Components/Pagination.vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    logs: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const page = usePage();
const currentLocale = computed(
    () => page.props.locale ?? page.props.lang ?? "hy",
);
const logs = computed(() => props.logs.data ?? []);
const actionLabels = {
    "person.created": t('logs.person_registration'),
    "membership_sale.created": t('staff_reports.membership_sale'),
    "membership_sale.payment_added": t('logs.payment_addition'),
    "membership_sale.refund_added": t('logs.refund'),
    "membership_sale.cancelled": t('logs.membership_cancellation'),
    "membership_sale.trainer_changed": t('logs.trainer_change'),
    "membership_sale.frozen": t('logs.membership_freeze'),
    "membership_sale.updated": t('logs.membership_edit'),
};
const actionLabel = (action) => actionLabels[action] ?? action;
</script>

<template>
    <Head :title="t('sidebar.logs')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('sidebar.logs') }}
            </h2>
        </template>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('sidebar.logs') }}</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ t('people.action') }}</th>
                                <th>{{ t('logs.model_object') }}</th>
                                <th>{{ t('logs.user') }}</th>
                                <th>{{ t('inventory.created_at') }}</th>
                                <th>{{ t('logs.show') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="log in logs" :key="log.id">
                                <td>{{ log.id }}</td>
                                <td>
                                    <span class="badge bg-label-primary">
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>
                                <td>{{ log.subject }}</td>
                                <td>{{ log.user || "-" }}</td>
                                <td>{{ log.created_at }}</td>
                                <td>
                                    <Link
                                        :href="route('logs.show', {
                                            locale: currentLocale,
                                            log: log.id,
                                        })"
                                        class="btn btn-sm btn-icon btn-text-secondary"
                                        :title="t('logs.log_details')"
                                    >
                                        <i class="icon-base ti tabler-eye"></i>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="!logs.length">
                                <td colspan="6" class="text-center text-muted">
                                    {{ t('logs.no_activity_logs_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="props.logs.links?.length" class="card-footer">
                <Pagination :links="props.logs.links" />
            </div>
        </div>
    </Index>
</template>
