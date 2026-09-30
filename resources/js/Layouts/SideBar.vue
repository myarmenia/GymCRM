<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useAuth } from "@/composables/useAuth";
import { useTrans } from "/resources/js/trans";

const page = usePage();
const currentLocale = computed(
    () => page.props.locale ?? page.props.lang ?? "hy",
);
const { hasRole, hasAnyRole } = useAuth();
</script>

<template>
    <aside id="layout-menu" class="layout-menu menu-vertical menu">
        <div class="app-brand demo">
            <Link
                :href="route('dashboard', { locale: currentLocale })"
                class="app-brand-link"
            >
                <span class="app-brand-logo demo">
                    <img
                        src="/favicon.svg"
                        alt="GymCRM"
                        class="gymcrm-brand-logo"
                    />
                </span>
                <span class="app-brand-text demo menu-text fw-bold ms-3"
                    >GymCRM</span
                >
            </Link>
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
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('user.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('user.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>
                        {{ useTrans(hasRole("owner") ? "app.sidebar.users" : "app.sidebar.staff") }}
                    </div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('trainer.index') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('trainer.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>{{ useTrans("app.sidebar.trainers") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'sales_manager',
                        'admin',
                        'super_admin',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('person.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('person.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-address-book"></i>
                    <div>{{ useTrans("app.sidebar.customers") }}</div>
                </Link>
            </li>

            <li
                v-if="hasRole('owner')"
                :class="[
                    'menu-item',
                    route().current('gym.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('gym.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-building"></i>
                    <div>{{ useTrans("app.sidebar.gym") }}</div>
                </Link>
            </li>

            <li
                v-if="hasRole('owner')"
                :class="[
                    'menu-item',
                    route().current('hdm-configurations.*') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('hdm-configurations.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-device-desktop-cog"></i>
                    <div>{{ useTrans("app.sidebar.hdm_configurations") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('warehouse.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('warehouse.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-packages"></i>
                    <div>{{ useTrans("app.sidebar.warehouses") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('categories.index') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('categories.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-list"></i>
                    <div>{{ useTrans("app.sidebar.categories") }}</div>
                </Link>
            </li>
            <li
                v-if="hasRole('trainer')"
                :class="[
                    'menu-item',
                    route().current('trainer.my-schedules') ? 'active' : '',
                ]"
            >
                <Link
                    :href="
                        route('trainer.my-schedules', { locale: currentLocale })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-calendar-time"></i>
                    <div>{{ useTrans("app.sidebar.my_schedules") }}</div>
                </Link>
            </li>

            <li
                v-if="hasRole('trainer')"
                :class="[
                    'menu-item',
                    route().current('trainer.my-salaries') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('trainer.my-salaries', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-cash"></i>
                    <div>{{ useTrans("app.sidebar.my_salaries") }}</div>
                </Link>
            </li>

            <li
                v-if="hasRole('trainer')"
                :class="[
                    'menu-item',
                    route().current('trainer.my-customers') ? 'active' : '',
                ]"
            >
                <Link
                    :href="
                        route('trainer.my-customers', { locale: currentLocale })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-address-book"></i>
                    <div>{{ useTrans("app.sidebar.my_customers") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                        'trainer',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('products.index') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('products.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-list-details"></i>
                    <div>{{ useTrans("app.sidebar.products") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('product-consumptions.index')
                        ? 'active'
                        : '',
                ]"
            >
                <Link
                    :href="
                        route('product-consumptions.index', {
                            locale: currentLocale,
                        })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-package-export"></i>
                    <div>{{ useTrans("app.sidebar.product_consumption") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('schedule.index') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('schedule.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-calendar-time"></i>
                    <div>{{ useTrans("app.sidebar.schedule") }}</div>
                </Link>
            </li>

            <li
                v-if="hasAnyRole(['owner', 'admin', 'super_admin'])"
                :class="[
                    'menu-item',
                    route().current('logs.*') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('logs.index', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-history"></i>
                    <div>{{ useTrans("app.sidebar.logs") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('schedule.trainer_occupancy')
                        ? 'active'
                        : '',
                ]"
            >
                <Link
                    :href="
                        route('schedule.trainer_occupancy', {
                            locale: currentLocale,
                        })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-calendar-stats"></i>
                    <div>{{ useTrans("app.sidebar.trainer_occupancy") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('entry-code.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('entry-code.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-qrcode"></i>
                    <div>{{ useTrans("app.sidebar.entry_codes") }}</div>
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
                    <i
                        class="menu-icon icon-base ti tabler-report-analytics"
                    ></i>
                    <div data-i18n="Entry Reports">
                        {{ useTrans("app.sidebar.entry_report") }}
                    </div>
                </Link>
            </li>

            <!-- ======== membership plans ========== -->
            <li
                v-if="
                    hasAnyRole([
                        'owner',
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('membership_plan.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="
                        route('membership_plan.list', { locale: currentLocale })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>{{ useTrans("app.sidebar.membership_plans") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('membership-category.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="
                        route('membership-category.list', {
                            locale: currentLocale,
                        })
                    "
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-category"></i>
                    <div>{{ useTrans("app.sidebar.membership_categories") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'admin',
                        'super_admin',
                        'sales_manager',
                        'manager',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('discount.list') ? 'active' : '',
                ]"
            >
                <Link
                    :href="route('discount.list', { locale: currentLocale })"
                    class="menu-link"
                >
                    <i class="menu-icon icon-base ti tabler-percentage"></i>
                    <div>{{ useTrans("app.sidebar.discounts") }}</div>
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
                    route().current('reports.*') ? 'active open' : '',
                ]"
            >
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i
                        class="menu-icon icon-base ti tabler-report-analytics"
                    ></i>
                    <div>{{ useTrans("app.sidebar.reports") }}</div>
                </a>
                <ul class="menu-sub">
                    <li
                        :class="[
                            'menu-item',
                            route().current('reports.membership-sales')
                                ? 'active'
                                : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('reports.membership-sales', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.report_memberships") }}</div>
                        </Link>
                    </li>
                    <li
                        :class="[
                            'menu-item',
                            route().current('reports.entry-exit')
                                ? 'active'
                                : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('reports.entry-exit', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.report_entry_exit") }}</div>
                        </Link>
                    </li>
                    <li
                        :class="[
                            'menu-item',
                            route().current('reports.trainer-commissions')
                                ? 'active'
                                : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('reports.trainer-commissions', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.trainer_commissions") }}</div>
                        </Link>
                    </li>
                    <li
                        :class="[
                            'menu-item',
                            route().current('reports.trainer-monthly-salaries')
                                ? 'active'
                                : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('reports.trainer-monthly-salaries', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.trainer_salaries") }}</div>
                        </Link>
                    </li>
                    <li
                        :class="[
                            'menu-item',
                            route().current('reports.salesperson-commissions')
                                ? 'active'
                                : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('reports.salesperson-commissions', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.salesperson_commissions") }}</div>
                        </Link>
                    </li>
                </ul>
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
                    v-if="
                        hasAnyRole([
                            'sales_manager',
                            'admin',
                            'super_admin',
                            'owner',
                            'manager',
                        ])
                    "
                    :class="[
                        'menu-link',
                        route().current('membership_sale.list') ? 'active' : '',
                    ]"
                >
                    <i class="menu-icon icon-base ti tabler-receipt"></i>
                    <div>{{ useTrans("app.sidebar.membership_sales") }}</div>
                </Link>
            </li>

            <li
                v-if="
                    hasAnyRole([
                        'admin',
                        'super_admin',
                        'owner',
                        'sales_manager',
                        'manager',
                        'accountant',
                    ])
                "
                :class="[
                    'menu-item',
                    route().current('purchase.*') ||
                    route().current('finance.*') ||
                    route().current('salary-payouts.*')
                        ? 'active open'
                        : '',
                ]"
            >
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-cash-register"></i>
                    <div>{{ useTrans("app.sidebar.cashier") }}</div>
                </a>

                <ul class="menu-sub">
                    <li
                        :class="[
                            'menu-item',
                            route().current('finance.*') ? 'active' : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('finance.index', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.finance") }}</div>
                        </Link>
                    </li>

                    <li
                        v-if="
                            hasAnyRole([
                                'owner',
                                'admin',
                                'super_admin',
                                'accountant',
                            ])
                        "
                        :class="[
                            'menu-item',
                            route().current('salary-payouts.*') ? 'active' : '',
                        ]"
                    >
                        <Link
                            :href="
                                route('salary-payouts.index', {
                                    locale: currentLocale,
                                })
                            "
                            class="menu-link"
                        >
                            <div>{{ useTrans("app.sidebar.salary_payouts") }}</div>
                        </Link>
                    </li>

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
                            <div>{{ useTrans("app.sidebar.sale") }}</div>
                        </Link>
                    </li>

                    <li
                        :class="[
                            'menu-item',
                            route().current('purchase.history') ? 'active' : '',
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
                            <div>{{ useTrans("app.sidebar.sales_history") }}</div>
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
