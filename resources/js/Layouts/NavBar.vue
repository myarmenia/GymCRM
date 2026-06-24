<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useAuth } from '@/composables/useAuth'
import { useTrans } from '/resources/js/trans'

const page = usePage()
const { user } = useAuth()

const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? 'hy')
const unreadCount = ref(Number(page.props.notificationUnreadCount || 0))

watch(
    () => page.props.notificationUnreadCount,
    value => {
        unreadCount.value = Number(value || 0)
    },
)

const userInitials = computed(() => {
    if (!user.value) {
        return 'U'
    }

    const firstInitial = (user.value.name || '').charAt(0).toUpperCase()
    const lastInitial = (user.value.surname || '').charAt(0).toUpperCase()

    return `${firstInitial}${lastInitial}` || firstInitial || 'U'
})

const changeLanguage = lang => {
    const path = window.location.pathname.split('/')
    path[1] = lang

    const searchParams = new URLSearchParams(window.location.search)
    const query = searchParams.toString()
    const newUrl = `${path.join('/')}${query ? `?${query}` : ''}`

    router.get(newUrl, {}, { preserveState: true, preserveScroll: true })
}

const handleNotification = event => {
    unreadCount.value = Number(event?.unread_count ?? unreadCount.value + 1)
}

onMounted(() => {
    const userId = user.value?.id

    if (!userId || !window.Echo) {
        return
    }

    window.Echo.private(`App.Models.User.${userId}`)
        .listen('.user.notification.created', handleNotification)
})

onBeforeUnmount(() => {
    const userId = user.value?.id

    if (userId && window.Echo) {
        window.Echo.leave(`App.Models.User.${userId}`)
    }
})
</script>

<template>
    <nav
        id="layout-navbar"
        class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
    >
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a
                class="nav-item nav-link px-0 me-xl-6"
                href="javascript:void(0)"
            >
                <i class="icon-base ti tabler-menu-2 icon-md"></i>
            </a>
        </div>

        <div
            id="navbar-collapse"
            class="navbar-nav-right d-flex align-items-center justify-content-end"
        >
            <div class="navbar-nav align-items-center">
                <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
                    <a
                        class="nav-item nav-link search-toggler d-flex align-items-center px-0"
                        href="javascript:void(0);"
                    >
                        <span
                            id="autocomplete"
                            class="d-inline-block text-body-secondary fw-normal"
                        ></span>
                    </a>
                </div>
            </div>

            <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                    <a
                        class="nav-link dropdown-toggle hide-arrow"
                        href="javascript:void(0);"
                        data-bs-toggle="dropdown"
                    >
                        <i class="icon-base ti tabler-language icon-22px text-heading"></i>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button
                                class="dropdown-item"
                                @click="changeLanguage('hy')"
                            >
                                HY
                            </button>
                        </li>
                        <li>
                            <button
                                class="dropdown-item"
                                @click="changeLanguage('en')"
                            >
                                EN
                            </button>
                        </li>
                        <li>
                            <button
                                class="dropdown-item"
                                @click="changeLanguage('ru')"
                            >
                                RU
                            </button>
                        </li>
                    </ul>
                </li>

                <li class="nav-item me-2 me-xl-0">
                    <Link
                        class="nav-link hide-arrow notification-link"
                        :href="route('notifications.index', { locale: currentLocale })"
                    >
                        <i class="icon-base ti tabler-bell icon-22px text-heading"></i>
                        <span
                            v-if="unreadCount > 0"
                            class="badge rounded-pill bg-danger badge-notifications"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </Link>
                </li>

                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                    <a
                        class="nav-link dropdown-toggle hide-arrow p-0"
                        href="javascript:void(0);"
                        data-bs-toggle="dropdown"
                    >
                        <div class="avatar avatar-online">
                            <span class="avatar-initial rounded-circle bg-label-dark">{{ userInitials }}</span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a
                                class="dropdown-item mt-0"
                                href="javascript:void(0);"
                            >
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-2">
                                        <div class="avatar avatar-online">
                                            <span class="avatar-initial rounded-circle bg-label-dark">{{ userInitials }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0">{{ user.name }} {{ user.surname ?? '' }}</h6>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <span
                                                v-for="role in user.roles"
                                                :key="role.id"
                                                class="badge bg-label-primary"
                                            >
                                                {{ useTrans(`app.roles.${role.name}`) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <div class="dropdown-divider my-1 mx-n2"></div>
                        </li>
                        <li>
                            <div class="d-grid px-2 pt-2 pb-1">
                                <Link
                                    :href="route('logout', { locale: currentLocale })"
                                    method="post"
                                    class="btn btn-sm btn-danger d-flex"
                                >
                                    <small class="align-middle">Դուրս գալ</small>
                                    <i class="icon-base ti tabler-logout ms-2 icon-14px"></i>
                                </Link>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</template>

<style scoped>
.notification-link {
    position: relative;
}

.badge-notifications {
    font-size: .65rem;
    line-height: 1;
    min-width: 1.1rem;
    padding: .25rem .35rem;
    position: absolute;
    right: -.25rem;
    top: .2rem;
}
</style>
