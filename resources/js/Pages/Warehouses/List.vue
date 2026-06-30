<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import DeleteButton from '@/Components/DeleteButton.vue';
import ToggleStatus from '@/Components/ToggleStatus.vue';
import { useAuth } from '@/composables/useAuth';

const page = usePage();
const currentLocale = page.props.locale ?? "en";
const { hasAnyRole } = useAuth();
const canManageWarehouses = hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager', 'manager']);

const props = defineProps({
    warehouses: Object
});

const warehousesList = ref(props.warehouses.data);


</script>

<template>
    <Head title="Warehouses List" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Warehouses</h2>
        </template>

        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Պահեստների ցուցակ</h5>
                <button v-if="canManageWarehouses" @click="router.get(route('warehouse.create', { locale: currentLocale }))" class="btn btn-primary">
                    + Ավելացնել նոր պահեստ
                </button>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Անուն</th>
                            <th>Մարզասրահ</th>
                            <th>Տիպ</th>
                            <th>Հեռախոս</th>
                            <th>Կարգավիճակ</th>
                            <th>Գործողություններ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="warehouse in warehousesList" :key="warehouse.id">
                            <td>{{ warehouse.id }}</td>
                            <td><strong>{{ warehouse.name }}</strong></td>
                            <td>{{ warehouse.gym?.name }}</td>
                            <td>{{ warehouse.type ?? '-' }}</td>
                            <td>{{ warehouse.phone ?? '-' }}</td>
                            <td>
                                <span :class="warehouse.status ? 'badge bg-label-success' : 'badge bg-label-danger'">
                                    {{ warehouse.status ? 'Ակտիվ' : 'Պասիվ' }}
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
                                        <div v-if="canManageWarehouses" class="dropdown-item waves-effect">
                                            <ToggleStatus
                                                :model="'warehouses'"
                                                :model-id="warehouse.id"
                                                :prefix="'warehouse'"
                                                :column="'status'"
                                                :locale="currentLocale"
                                                :active="warehouse.status"
                                                :label="'Կարգավիճակ'"
                                                @update="warehouse.status = $event"
                                            />

                                        </div>

                                        <Link
                                            v-if="canManageWarehouses"
                                            class="dropdown-item waves-effect"
                                            :href="route('warehouse.edit', { locale: currentLocale, id: warehouse.id })"
                                        >
                                            <i class="icon-base ti tabler-pencil me-1"></i>
                                            Խմբագրել
                                        </Link>

                                        <a
                                                v-if="canManageWarehouses"
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <DeleteButton
                                                :model="'warehouses'"
                                                :prefix="'warehouse'"
                                                :locale="currentLocale"
                                                :model-id="warehouse.id"
                                                @deleted="warehousesList = warehousesList.filter((w) => w.id !== $event)"
                                            />
                                            </a>

                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Index>
</template>
