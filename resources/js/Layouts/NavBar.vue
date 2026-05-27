<script setup>
import { ref, computed } from "vue";
import { router, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useTrans } from '/resources/js/trans';


const { user } = useAuth();

const userInitials = computed(() => {
    if (!user.value) return 'U';

    const firstName = user.value.name || '';
    const lastName = user.value.surname || '';
console.log( 'name:', firstName, 'surname:', lastName );
    const firstInitial = firstName.charAt(0).toUpperCase();
    const lastInitial = lastName.charAt(0).toUpperCase();

    if (firstInitial && lastInitial) {
        return `${firstInitial}${lastInitial}`;
    }
    if (firstInitial) {
        return firstInitial;
    }
    return 'U';
});



const changeLanguage = (lang) => {
    const path = window.location.pathname.split('/');
    path[1] = lang;

    const searchParams = new URLSearchParams(window.location.search);
    const newUrl = `${path.join('/')}?${searchParams.toString()}`;

    router.get(newUrl, {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <nav  class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0   d-xl-none ">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="icon-base ti tabler-menu-2 icon-md"></i>
            </a>
        </div>


        <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">

            <!-- Search -->
            <div class="navbar-nav align-items-center">
            <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
                <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
                <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
                </a>
            </div>
            </div>

            <!-- /Search -->





        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                <!-- <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                        <i class="icon-base ti tabler-language icon-22px text-heading"></i>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button class="dropdown-item" @click="changeLanguage('hy')">HY</button>
                        </li>
                        <li>
                            <button class="dropdown-item" @click="changeLanguage('en')">EN</button>
                        </li>
                        <li>
                            <button class="dropdown-item" @click="changeLanguage('ru')">RU</button>
                        </li>
                    </ul>
                </li> -->
                <!--/ Language -->

            <!-- User -->
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
                    <span class="avatar-initial rounded-circle bg-label-dark">{{userInitials}}</span>                    <img src="../../assets/img/avatars/1.png" alt class="rounded-circle" />
                </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item mt-0" href="pages-account-settings-account.html">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-2">
                        <div class="avatar avatar-online">
                            <span class="avatar-initial rounded-circle bg-label-dark">{{userInitials}}</span>
                        </div>
                        </div>
                        <div class="flex-grow-1">
                        <h6 class="mb-0">{{ user.name}}  {{ user.surname ?? ''}}</h6>
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
                <!-- <li>
                    <a class="dropdown-item" href="pages-profile-user.html"> <i class="icon-base ti tabler-user me-3 icon-md"></i><span class="align-middle">My Profile</span> </a>
                </li>
                <li>
                    <a class="dropdown-item" href="pages-account-settings-account.html"> <i class="icon-base ti tabler-settings me-3 icon-md"></i><span class="align-middle">Settings</span> </a>
                </li> -->
                <!-- <li>
                    <a class="dropdown-item" href="pages-account-settings-billing.html">
                    <span class="d-flex align-items-center align-middle">
                        <i class="flex-shrink-0 icon-base ti tabler-file-dollar me-3 icon-md"></i><span class="flex-grow-1 align-middle">Billing</span>
                        <span class="flex-shrink-0 badge bg-danger d-flex align-items-center justify-content-center">4</span>
                    </span>
                    </a>
                </li> -->
                <!-- <li>
                    <div class="dropdown-divider my-1 mx-n2"></div>
                </li>
                <li>
                    <a class="dropdown-item" href="pages-pricing.html"> <i class="icon-base ti tabler-currency-dollar me-3 icon-md"></i><span class="align-middle">Pricing</span> </a>
                </li>
                <li>
                    <a class="dropdown-item" href="pages-faq.html"> <i class="icon-base ti tabler-question-mark me-3 icon-md"></i><span class="align-middle">FAQ</span> </a>
                </li> -->
                <li>
                    <div class="d-grid px-2 pt-2 pb-1">
                    <Link :href="route('logout', { locale: usePage().props.locale })" method="post" class="btn btn-sm btn-danger d-flex">
                        <small class="align-middle">Logout</small>
                        <i class="icon-base ti tabler-logout ms-2 icon-14px"></i>
                    </Link>

                    <!-- <a class="btn btn-sm btn-danger d-flex" href="auth-login-cover.html" target="_blank">
                        <small class="align-middle">Logout</small>
                        <i class="icon-base ti tabler-logout ms-2 icon-14px"></i>
                    </a> -->
                    </div>
                </li>
                </ul>
            </li>
            <!--/ User -->

        </ul>
        </div>
    </nav>

</template>

