<script setup>
import { computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import { useConfirm } from '@/composables/useConfirm'
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const { confirm } = useConfirm()

defineProps({
    reminders: {
        type: Object,
        default: () => ({}),
    },
})

const fullName = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || user?.email || '-'
const personName = person => person ? `${person.name ?? ''} ${person.surname ?? ''}`.trim() || `#${person.id}` : '-'
const formatDate = value => value ? String(value).slice(0, 16).replace('T', ' ') : '-'
const statusLabel = status => ({
    scheduled: t('operations.scheduled'),
    processing: t('operations.sending'),
    failed: t('operations.failed'),
}[status] ?? status)

const cancelReminder = async reminder => {
    const ok = await confirm(translate(page.props.translations, 'app.confirm.cancel_reminder_message'), {
        title: translate(page.props.translations, 'app.confirm.cancel_reminder_title'),
        confirmText: translate(page.props.translations, 'app.confirm.cancel_reminder_title'),
        cancelText: translate(page.props.translations, 'app.confirm.close'),
        confirmClass: 'btn-danger',
    })

    if (!ok) {
        return
    }

    router.patch(route('reminders.cancel', {
        locale: currentLocale.value,
        reminder: reminder.id,
    }), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('operations.reminders')" />

    <Index>
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">{{ t('operations.reminders') }}</h2>
                <div class="text-muted">{{ t('operations.scheduled_reminders_not_yet_sent') }}</div>
            </div>
            <Link
                class="btn btn-primary"
                :href="route('notifications.create', { locale: currentLocale })"
            >
                <i class="icon-base ti tabler-calendar-plus me-1"></i>
                {{ t('people.add_reminder') }}
            </Link>
        </div>

        <div class="d-flex gap-2 flex-wrap mb-4">
            <Link
                class="btn btn-outline-primary"
                :href="route('notifications.index', { locale: currentLocale, tab: 'received' })"
            >
                {{ t('operations.received_notifications') }}
            </Link>
            <Link
                class="btn btn-outline-primary"
                :href="route('notifications.index', { locale: currentLocale, tab: 'sent' })"
            >
                {{ t('operations.notifications_i_sent') }}
            </Link>
            <Link
                class="btn btn-primary"
                :href="route('reminders.index', { locale: currentLocale })"
            >
                {{ t('operations.reminders') }}
            </Link>
        </div>

        <div
            v-if="reminders?.data?.length"
            class="reminder-list"
        >
            <article
                v-for="reminder in reminders.data"
                :key="reminder.id"
                class="card"
            >
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <span class="badge bg-label-info">{{ reminder.category?.name }}</span>
                                <span
                                    class="badge"
                                    :class="reminder.status === 'failed' ? 'bg-label-danger' : 'bg-label-warning'"
                                >
                                    {{ statusLabel(reminder.status) }}
                                </span>
                            </div>
                            <h5 class="mb-2">{{ reminder.title || reminder.category?.name }}</h5>
                            <p class="text-muted mb-3 reminder-description">{{ reminder.description || '-' }}</p>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-label-danger"
                            :disabled="reminder.status === 'processing'"
                            @click="cancelReminder(reminder)"
                        >
                            <i class="icon-base ti tabler-x me-1"></i>
                            {{ t('confirm.cancel') }}
                        </button>
                    </div>

                    <div class="row g-3 small">
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ t('people.send_at') }}</span>
                            <strong>{{ formatDate(reminder.scheduled_at) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ t('people.about_person') }}</span>
                            <strong>{{ personName(reminder.about) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ t('operations.created_by') }}</span>
                            <strong>{{ fullName(reminder.creator) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ t('people.recipients') }}</span>
                            <strong>{{ reminder.recipients?.map(fullName).join(', ') || '-' }}</strong>
                        </div>
                    </div>

                    <div
                        v-if="reminder.last_error"
                        class="alert alert-danger mt-3 mb-0 py-2"
                    >
                        {{ reminder.last_error }}
                    </div>
                </div>
            </article>
        </div>

        <div
            v-else
            class="card"
        >
            <div class="card-body text-center text-muted py-5">
                {{ t('operations.there_are_no_scheduled_reminders') }}
            </div>
        </div>

        <div
            v-if="reminders?.links?.length"
            class="d-flex gap-2 flex-wrap mt-4"
        >
            <Link
                v-for="link in reminders.links"
                :key="link.label"
                class="btn btn-sm"
                :class="link.active ? 'btn-primary' : 'btn-outline-secondary'"
                :href="link.url || '#'"
                v-html="link.label"
            />
        </div>
    </Index>
</template>

<style scoped>
.reminder-list {
    display: grid;
    gap: 1rem;
}

.reminder-description {
    white-space: pre-line;
}
</style>
