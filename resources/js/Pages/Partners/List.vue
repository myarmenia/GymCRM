<script setup>
import { ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, usePage } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import ToggleStatus from "@/Components/ToggleStatus.vue";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    partners: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

// 💡 Պահպանում ենք քո պրոյեկտի reactivity-ի ստանդարտը
const partnersList = ref(props.partners?.data ?? props.partners ?? []);
const pagination = ref(props.partners?.links ? props.partners : null);
</script>

<template>
    <Head title="Partners List" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Partners List
            </h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Partners List</h5>
                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    type="button"
                    :href="route('partner.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block">Add New Partner</span>
                        </span>
                    </span>
                </Link>
            </div>
            
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Partner Name</th>
                                <th>Account Number</th>
                                <th>Contract Number</th>
                                <th>Contact Details</th>
                                <th>Contact Person</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="partner in partnersList" :key="partner.id">
                                <td>{{ partner.id }}</td>
                                
                                <td>
                                    <div class="fw-semibold text-heading">{{ partner.name }}</div>
                                    <small class="text-muted">{{ partner.address }}</small>
                                </td>

                                <td>
                                    <span class="font-monospace">{{ partner.account_number }}</span>
                                </td>

                                <td>
                                    <span class="font-monospace">{{ partner.contract_number }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="ti tabler-mail icon-sm me-2"></i>
                                        <span>{{ partner.email }}</span>
                                    </div>
                                    <div class="text-muted small d-flex align-items-center">
                                        <i class="ti tabler-phone icon-sm me-2"></i>
                                        <span>{{ partner.phone }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="text-heading fw-medium mb-1">{{ partner.contact_full_name }}</div>
                                    
                                    <div v-if="partner.contact_phone" class="text-muted small d-flex align-items-center">
                                        <i class="ti tabler-device-mobile icon-sm me-2"></i>
                                        <span>{{ partner.contact_phone }}</span>
                                    </div>
                                    <small v-else class="text-light d-block">No Phone</small>
                                </td>

                                <td>
                                    <span 
                                        class="badge" 
                                        :class="partner.status ? 'bg-label-success' : 'bg-label-danger'"
                                    >
                                        {{ partner.status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                
                                <td>
                                    <div class="dropdown">
                                        <button
                                            type="button"
                                            class="btn p-0 dropdown-toggle hide-arrow"
                                            data-bs-toggle="dropdown"
                                        >
                                            <i class="icon-base ti tabler-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">

                                            <div class="dropdown-item waves-effect">
                                                <ToggleStatus
                                                     :model="'partners'"
                                                    :model-id="partner.id"
                                                    :prefix="'partner'"
                                                    :locale="currentLocale"
                                                    :active="Boolean(partner.status)"
                                                    :column="'status'"
                                                    :label="''"
                                                    @update="partner.status = $event"
                                                />
                                            
                                            </div>
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="route('partner.edit', { locale: currentLocale, id: partner.id })" 
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Edit
                                            </Link>
                                            
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);">
                                                <DeleteButton
                                                    :model="'partners'"
                                                    :prefix="'partner'"
                                                    :locale="currentLocale"
                                                    :model-id="partner.id"
                                                    @deleted="
                                                        partnersList = partnersList.filter((p) => p.id !== $event)
                                                    "
                                                />
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="partnersList.length === 0">
                                <td colspan="7" class="text-center text-muted">No partners found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="pagination && pagination.links" class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>