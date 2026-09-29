<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Index from '@/Layouts/Index.vue';
import Pagination from '@/Components/Pagination.vue';
import { useTrans } from '/resources/js/trans';

defineProps({
    configs: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const trans = useTrans;
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? 'hy');

const removeConfig = (config) => {
    if (!window.confirm(trans('page.messages.confirm_delete_config'))) {
        return;
    }

    router.delete(route('hdm-configurations.destroy', {
        locale: currentLocale.value,
        hdmConfig: config.id,
    }), {
        preserveScroll: true,
    });
};

const toggleStatus = (config) => {
    router.patch(route('hdm-configurations.status', {
        locale: currentLocale.value,
        hdmConfig: config.id,
    }), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="trans('page.title')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ trans('page.title') }}
            </h2>
        </template>

        <div v-if="page.props.flash?.success" class="alert alert-success" role="alert">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="alert alert-danger" role="alert">
            {{ page.props.flash.error }}
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">{{ trans('page.title') }}</h5>
                    <div class="text-muted small">{{ trans('page.subtitle') }}</div>
                </div>
                <Link
                    :href="route('hdm-configurations.create', { locale: currentLocale })"
                    class="btn btn-primary"
                >
                    <i class="ti tabler-plus me-1"></i>
                    {{ trans('page.actions.add_config') }}
                </Link>
            </div>

            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>{{ trans('page.fields.id') }}</th>
                                <th>{{ trans('page.fields.gym') }}</th>
                                <th>{{ trans('page.fields.name') }}</th>
                                <th>{{ trans('page.fields.ip') }}</th>
                                <th>{{ trans('page.fields.port') }}</th>
                                <th>{{ trans('page.fields.cashiers') }}</th>
                                <th>{{ trans('page.fields.status') }}</th>
                                <th class="text-end">{{ trans('page.fields.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="config in configs.data" :key="config.id">
                                <td>{{ config.id }}</td>
                                <td>{{ config.gym?.name || '—' }}</td>
                                <td class="fw-semibold">{{ config.name }}</td>
                                <td class="font-monospace">{{ config.ip }}</td>
                                <td>{{ config.port }}</td>
                                <td><span class="badge bg-label-info">{{ config.cashiers_count }}</span></td>
                                <td>
                                    <span class="badge" :class="config.status ? 'bg-label-success' : 'bg-label-danger'">
                                        {{ config.status ? trans('page.status.active') : trans('page.status.inactive') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown d-flex justify-content-end">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ti tabler-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <button type="button" class="dropdown-item" @click="toggleStatus(config)">
                                                <i class="ti me-2" :class="config.status ? 'tabler-toggle-left' : 'tabler-toggle-right'"></i>
                                                {{ config.status ? trans('page.actions.deactivate') : trans('page.actions.activate') }}
                                            </button>
                                            <Link
                                                :href="route('hdm-configurations.edit', { locale: currentLocale, hdmConfig: config.id })"
                                                class="dropdown-item"
                                            >
                                                <i class="ti tabler-pencil me-2"></i>
                                                {{ trans('page.actions.edit') }}
                                            </Link>
                                            <button type="button" class="dropdown-item text-danger" @click="removeConfig(config)">
                                                <i class="ti tabler-trash me-2"></i>
                                                {{ trans('page.actions.delete') }}
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!configs.data?.length">
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="ti tabler-device-desktop-off fs-2 d-block mb-2"></i>
                                    {{ trans('page.messages.no_configs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="configs.links?.length > 3" :links="configs.links" />
            </div>
        </div>
    </Index>
</template>
