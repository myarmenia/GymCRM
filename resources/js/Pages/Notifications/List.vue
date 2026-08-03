<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import { useConfirm } from '@/composables/useConfirm'
import NotificationCard from './NotificationCard.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const { confirm } = useConfirm()

const props = defineProps({
    notifications: Object,
    activeTab: {
        type: String,
        default: 'received',
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
})

const notificationRows = ref([...(props.notifications?.data ?? [])])
const unreadCount = ref(props.unreadCount)
const activeTab = computed(() => props.activeTab === 'sent' ? 'sent' : 'received')
const isReceivedTab = computed(() => activeTab.value === 'received')
const tabLinks = computed(() => [
    {
        key: 'received',
        label: 'Ստացված notification-ները',
        href: route('notifications.index', {
            locale: currentLocale.value,
            tab: 'received',
        }),
    },
    {
        key: 'sent',
        label: 'Իմ ուղարկած notification-ները',
        href: route('notifications.index', {
            locale: currentLocale.value,
            tab: 'sent',
        }),
    },
    {
        key: 'reminders',
        label: 'Հիշեցումներ',
        href: route('reminders.index', {
            locale: currentLocale.value,
        }),
    },
])
const pageTitle = computed(() => isReceivedTab.value ? 'Ստացված notification-ներ' : 'Իմ ուղարկած notification-ները')
const emptyText = computed(() => isReceivedTab.value ? 'Ստացված notification-ներ չկան։' : 'Ուղարկած notification-ներ չկան։')

watch(
    () => props.notifications?.data,
    value => {
        notificationRows.value = [...(value ?? [])]
    },
)

watch(
    () => props.unreadCount,
    value => {
        unreadCount.value = Number(value || 0)
    },
)

const handleNotification = event => {
    if (!event?.notification) {
        return
    }

    if (isReceivedTab.value) {
        notificationRows.value = [event.notification, ...notificationRows.value]
    }

    unreadCount.value = Number(event.unread_count ?? unreadCount.value + 1)
}

const deleteNotification = async notification => {
    const ok = await confirm('Վստա՞հ եք, որ ցանկանում եք ջնջել այս notification-ը։', {
        title: 'Ջնջել ծանուցումը',
        confirmText: 'Հաստատել',
        cancelText: 'Չեղարկել',
        confirmClass: 'btn-danger',
    })

    if (!ok) {
        return
    }

    router.delete(route('notifications.destroy', {
        locale: currentLocale.value,
        notification: notification.id,
    }), {
        preserveScroll: true,
    })
}

const deleteAllNotifications = async () => {
    const ok = await confirm('Վստա՞հ եք, որ ցանկանում եք ջնջել բոլոր notification-ները։', {
        title: 'Ջնջել բոլոր ծանուցումները',
        confirmText: 'Հաստատել',
        cancelText: 'Չեղարկել',
        confirmClass: 'btn-danger',
    })

    if (!ok) {
        return
    }

    router.delete(route('notifications.destroy-all', {
        locale: currentLocale.value,
    }), {
        preserveScroll: true,
    })
}

onMounted(() => {
    const userId = page.props.auth?.user?.id

    if (!userId || !window.Echo) {
        return
    }

    window.Echo.private(`App.Models.User.${userId}`)
        .listen('.user.notification.created', handleNotification)
})

onBeforeUnmount(() => {
    const userId = page.props.auth?.user?.id

    if (userId && window.Echo) {
        window.Echo.leave(`App.Models.User.${userId}`)
    }
})
</script>

<template>
    <Head title="Ծանուցումներ" />

    <Index>
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">Ծանուցումներ</h2>
                <div class="text-muted">Չկարդացված՝ {{ unreadCount }}</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button
                    v-if="isReceivedTab"
                    type="button"
                    class="btn btn-label-danger"
                    :disabled="!notificationRows.length"
                    @click="deleteAllNotifications"
                >
                    <i class="icon-base ti tabler-trash me-1"></i>
                    Ջնջել բոլորը
                </button>
                <Link
                    class="btn btn-primary"
                    :href="route('notifications.create', { locale: currentLocale })"
                >
                    Ուղարկել ծանուցում
                </Link>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap mb-4">
            <Link
                v-for="tab in tabLinks"
                :key="tab.key"
                class="btn"
                :class="activeTab === tab.key ? 'btn-primary' : 'btn-outline-primary'"
                :href="tab.href"
                preserve-scroll
            >
                {{ tab.label }}
            </Link>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <h5 class="mb-0">{{ pageTitle }}</h5>
            <span class="badge bg-label-primary">{{ notifications?.total ?? notificationRows.length }}</span>
        </div>

        <div
            v-if="notificationRows.length"
            class="notification-list"
        >
            <NotificationCard
                v-for="notification in notificationRows"
                :key="notification.id"
                :notification="notification"
                :mode="activeTab"
                :can-delete="isReceivedTab"
                @delete="deleteNotification"
            />
        </div>

        <div
            v-else
            class="card"
        >
            <div class="card-body text-center text-muted py-5">
                {{ emptyText }}
            </div>
        </div>

        <div
            v-if="notifications?.links?.length"
            class="d-flex gap-2 flex-wrap mt-4"
        >
            <Link
                v-for="link in notifications.links"
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
.notification-list {
    display: grid;
    gap: 1rem;
}
</style>
