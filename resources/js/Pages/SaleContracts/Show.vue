<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import { useAuth } from '@/composables/useAuth'
import { translate } from '/resources/js/trans'

const props = defineProps({ sale: Object, contracts: Array })
const page = usePage()
const t = (key, replacements = {}) => translate(page.props.translations, `app.sale_contract.${key}`, replacements)
const statusLabel = status => ['ready', 'pending', 'failed'].includes(status) ? t(`status.${status}`) : status
const locale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const { hasAnyRole } = useAuth()
const canManage = computed(() => hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager']))
const hasPending = computed(() => props.contracts.some(contract => contract.status === 'pending'))
const refreshing = ref(false)
const submitting = ref(false)
let pollTimer
const refreshPending = () => {
    if (!hasPending.value || refreshing.value || submitting.value || document.visibilityState !== 'visible') return
    refreshing.value = true
    router.reload({
        only: ['contracts'],
        preserveScroll: true,
        onFinish: () => { refreshing.value = false },
    })
}
onMounted(() => { pollTimer = window.setInterval(refreshPending, 3000) })
onUnmounted(() => { window.clearInterval(pollTimer) })
const submit = url => {
    if (submitting.value) return
    submitting.value = true
    router.post(url, {}, { preserveScroll: true, onFinish: () => { submitting.value = false } })
}
const reissue = () => submit(route('sale_contract.reissue', { locale: locale.value, sale: props.sale.id }))
const retry = contract => submit(route('sale_contract.retry', { locale: locale.value, sale: props.sale.id, contract: contract.id }))
</script>

<template>
    <Head :title="t('title')" />
    <Index>
        <template #header><h2 class="text-xl font-semibold">{{ t('title') }}</h2></template>
        <div class="card">
            <div class="card-body">
                <p><strong>{{ sale.person?.name }} {{ sale.person?.surname }}</strong> · {{ sale.gym?.name }}</p>
                <p v-if="hasPending" class="text-primary" role="status">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ t('generating_notice') }}
                </p>
                <p v-if="!contracts.length" class="text-muted">{{ t('no_contract') }}</p>
                <div v-for="contract in contracts" :key="contract.id" class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>№ {{ contract.number }}</strong> · {{ t('revision', { revision: contract.revision }) }} · {{ contract.price }} {{ t('currency') }}
                        <span class="badge ms-2" :class="contract.status === 'ready' ? 'bg-success' : 'bg-warning'">{{ statusLabel(contract.status) }}</span>
                        <div v-if="contract.error" class="text-danger small">{{ contract.error }}</div>
                    </div>
                    <div class="d-flex gap-2">
                        <a v-if="contract.status === 'ready'" class="btn btn-primary btn-sm" :href="route('sale_contract.download', { locale, sale: sale.id, contract: contract.id })">{{ t('download_pdf') }}</a>
                        <button v-else-if="canManage && contract.status === 'failed'" type="button" class="btn btn-outline-primary btn-sm" :disabled="submitting" @click="retry(contract)">{{ t('retry') }}</button>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button v-if="canManage && contracts.length" type="button" class="btn btn-outline-primary" :disabled="hasPending || submitting" @click="reissue">{{ submitting ? t('creating') : t('create_revision') }}</button>
                    <Link :href="route('membership_sale.list', { locale })" class="btn btn-outline-secondary">{{ t('back') }}</Link>
                </div>
            </div>
        </div>
    </Index>
</template>
