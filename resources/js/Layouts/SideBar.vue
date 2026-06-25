<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'

const page = usePage()
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? 'hy')
const { hasRole, hasAnyRole } = useAuth()
</script>

<template>
    <aside
        id="layout-menu"
        class="layout-menu menu-vertical menu"
    >
        <div class="app-brand demo">
            <a
                href="index.html"
                class="app-brand-link"
            >
                <span class="app-brand-logo demo">
                    <span class="text-primary">
                        <svg
                            width="32"
                            height="22"
                            viewBox="0 0 32 22"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                                fill="currentColor"
                            />
                            <path
                                opacity="0.06"
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z"
                                fill="#161616"
                            />
                            <path
                                opacity="0.06"
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z"
                                fill="#161616"
                            />
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                                fill="currentColor"
                            />
                        </svg>
                    </span>
                </span>
                <span class="app-brand-text demo menu-text fw-bold ms-3">Vuexy</span>
            </a>
            <a
                href="javascript:void(0);"
                class="layout-menu-toggle menu-link text-large ms-auto"
            >
                <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                <i class="icon-base ti tabler-x d-block d-xl-none"></i>
            </a>
        </div>

        <div class="menu-inner-shadow"></div>
        <ul class="menu-inner py-1">
            <li
                v-if="hasAnyRole(['owner', 'admin', 'super_admin'])"
                :class="['menu-item', route().current('user.list') ? 'active' : '']"
            >
                <Link
                    :href="route('user.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>{{ hasRole('owner') ? 'Օգտատերեր' : 'Անձնակազմ' }}</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['owner', 'admin', 'super_admin'])"
                :class="['menu-item', route().current('trainer.index') ? 'active' : '']"
            >
                <Link
                    :href="route('trainer.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>Մարզիչներ</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['sales_manager', 'admin', 'super_admin'])"
                :class="['menu-item', route().current('person.list') ? 'active' : '']"
            >
                <Link
                    :href="route('person.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-address-book"></i>
                    <div>Հաճախորդներ</div>
                </Link>
            </li>

            <li
                v-if="hasRole('owner')"
                :class="['menu-item', route().current('gym.list') ? 'active' : '']"
            >
                <Link
                    :href="route('gym.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-building"></i>
                    <div>Մարզադահլիճ</div>
                </Link>
            </li>

            <li

               

                v-if="!hasRole('cleaner')"
                :class="['menu-item', route().current('warehouse.list') ? 'active' : '']"

            >
                <Link
                    :href="route('warehouse.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-packages"></i>
                    <div>Պահեստներ</div>
                </Link>
            </li>

            <li
                v-if="!hasRole('cleaner')"
                :class="['menu-item', route().current('categories.index') ? 'active' : '']"
            >
                <Link
                    :href="route('categories.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-list"></i>
                    <div>Կատեգորիաներ</div>
                </Link>
            </li>

            <li
                v-if="!hasRole('cleaner')"
                :class="['menu-item', route().current('products.index') ? 'active' : '']"
            >
                <Link
                    :href="route('products.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-list-details"></i>
                    <div>Ապրանքներ</div>
                </Link>
            </li>

            <li
                v-if="!hasRole('cleaner')"
                :class="['menu-item', route().current('product-consumptions.index') ? 'active' : '']"
            >
                <Link
                    :href="route('product-consumptions.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-package-export"></i>
                    <div>Ապրանքների սպառում</div>
                </Link>
            </li>

            <li
                v-if="!hasRole('cleaner')"
                :class="['menu-item', route().current('schedule.index') ? 'active' : '']"
            >
                <Link
                    :href="route('schedule.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-calendar-time"></i>
                    <div>Ժամային գրաֆիկ</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager'])"
                :class="['menu-item', route().current('schedule.trainer_occupancy') ? 'active' : '']"
            >
                <Link
                    :href="route('schedule.trainer_occupancy', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-calendar-stats"></i>
                    <div>Մարզիչների զբաղվածություն</div>
                </Link>
            </li>

            <li :class="['menu-item', route().current('entry-code.list') ? 'active' : '']">
                <Link
                    :href="route('entry-code.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-qrcode"></i>
                    <div>Մուտքի կոդեր</div>
                </Link>
            </li>


            <li
                v-if="hasAnyRole(['manager', 'admin', 'super_admin', 'owner'])"
                :class="[
                    'menu-item',
                    route().current('entry-reports.*') ? 'active' : '',
                ]"
            >
                <Link
                    :href="
                        route('entry-reports.index', {
                            locale: currentLocale,
                        })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-report-analytics"></i>
                    <div data-i18n="Entry Reports">
                        Մուտք/ելք հաշվետվություն
                    </div>
                </Link>
            </li>

            <!-- ======== membership plans ========== -->
            <li
                :class="[
                    'menu-item',
                    route().current('membership_plan.list') ? 'active' : '',
                ]"
            >            
                <Link
                    :href="route('membership_plan.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>Աբոնեմենտներ</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['admin', 'super_admin', 'owner'])"
                :class="['menu-item', route().current('membership-category.list') ? 'active' : '']"
            >
                <Link
                    :href="route('membership-category.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-category"></i>
                    <div>Աբոնեմենտների Կատեգորիաներ</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['admin', 'super_admin', 'owner'])"
                :class="['menu-item', route().current('discount.list') ? 'active' : '']"
            >
                <Link
                    :href="route('discount.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-percentage"></i>
                    <div>Զեղչեր</div>
                </Link>
            </li>

            <li

                v-if="
                    hasAnyRole([
                        'sales_manager',
                        'admin',
                        'super_admin',
                        'owner',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('membership_sale.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="
                        route('membership_sale.list', { locale: currentLocale })
                    "

                v-if="hasAnyRole(['sales_manager', 'admin', 'super_admin', 'owner'])"
                :class="['menu-item', route().current('membership_sale.list') ? 'active' : '']"
            >
               
                    <i class="menu-icon icon-base ti tabler-receipt"></i>
                    <div>Աբոնեմենտների վաճառքներ</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['admin', 'super_admin', 'owner','sales_manager','manager'])"
                :class="[
                    'menu-item',
                    route().current('purchase.*') ? 'active open' : '',
                ]"
            >
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-cash-register"></i>
                    <div data-i18n="Cashier">Դրամարկղ</div>
                </a>

                <ul class="menu-sub">
                    <li
                        :class="[
                            'menu-item',
                            route().current('purchase.index') ? 'active' : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('purchase.index', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div data-i18n="Sale">Վաճառք</div>
                        </Link>
                    </li>

                    <li
                        :class="[
                            'menu-item',
                            route().current('purchase.history')
                                ? 'active'
                                : '',
                        ]"
                    >
                        <Link
                            :href="
                                route().has?.('purchase.history')
                                    ? route('purchase.history', {
                                          locale: currentLocale,
                                      })
                                    : 'javascript:void(0);'
                            "
                            class="menu-link"
                        >
                            <div data-i18n="Sales History">
                                Վաճառքների պատմություն
                            </div>
                        </Link>
                    </li>
                </ul>
            </li>
        </ul>
    </aside>

    <div class="menu-mobile-toggler d-xl-none rounded-1">
        <a
            href="javascript:void(0);"
            class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1"
        >
            <i class="ti tabler-menu icon-base"></i>
            <i class="ti tabler-chevron-right icon-base"></i>
        </a>
    </div>
</template>
