<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    notification: {
        type: Object,
        required: true,
    },
})

defineEmits(['delete'])

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const isUnread = computed(() => props.notification.was_unread || !props.notification.seen)

const fullName = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || user?.email || '-'
const personName = person => person ? `${person.name ?? ''} ${person.surname ?? ''}`.trim() || `#${person.id}` : '-'
const formatDate = value => value ? String(value).slice(0, 16).replace('T', ' ') : '-'
</script>

<template>
    <article
        class="notification-card card"
        :class="{ 'notification-card-unread': isUnread }"
    >
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
                <div class="d-flex gap-3 align-items-start">
                    <div
                        class="notification-icon"
                        :class="isUnread ? 'bg-label-warning text-warning' : 'bg-label-primary text-primary'"
                    >
                        <i class="icon-base ti tabler-bell"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h5 class="mb-0">{{ notification.title }}</h5>
                            <span
                                class="badge"
                                :class="isUnread ? 'bg-label-warning' : 'bg-label-success'"
                            >
                                {{ isUnread ? 'Չկարդացված' : 'Կարդացված' }}
                            </span>
                        </div>
                        <div class="text-muted small">
                            <i class="icon-base ti tabler-user me-1"></i>
                            {{ fullName(notification.sender) }}
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small text-nowrap">{{ formatDate(notification.created_at) }}</span>
                    <button
                        type="button"
                        class="btn btn-icon btn-sm btn-label-danger"
                        title="Ջնջել"
                        @click="$emit('delete', notification)"
                    >
                        <i class="icon-base ti tabler-trash"></i>
                    </button>
                </div>
            </div>

            <p class="notification-description text-muted mb-3">
                {{ notification.description }}
            </p>

            <div class="notification-meta d-flex justify-content-between gap-3 align-items-center flex-wrap">
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <span class="badge bg-label-primary">
                        <i class="icon-base ti tabler-id me-1"></i>
                        Հաճախորդ
                    </span>
                    <Link
                        v-if="notification.about"
                        class="fw-medium person-link"
                        :href="route('person.profile', {
                            locale: currentLocale,
                            id: notification.about.id,
                        })"
                    >
                        {{ personName(notification.about) }}
                    </Link>
                    <span
                        v-else
                        class="text-muted"
                    >
                        -
                    </span>
                </div>
                <div class="text-muted small">
                    #{{ notification.id }}
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
.notification-card {
    border: 1px solid rgba(67, 89, 113, 0.12);
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.375rem rgba(67, 89, 113, 0.08);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.notification-card-unread {
    border-left: 4px solid var(--bs-warning);
    background: rgba(255, 171, 0, 0.06);
}

.notification-icon {
    align-items: center;
    border-radius: 0.5rem;
    display: inline-flex;
    flex: 0 0 2.5rem;
    height: 2.5rem;
    justify-content: center;
    width: 2.5rem;
}

.notification-description {
    white-space: pre-line;
    line-height: 1.55;
}

.notification-meta {
    border-top: 1px solid rgba(67, 89, 113, 0.1);
    padding-top: 0.875rem;
}

.person-link {
    color: var(--bs-primary);
}

.person-link:hover {
    color: var(--bs-primary);
    text-decoration: underline;
}
</style>
