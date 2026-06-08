<script setup>
import { computed, ref } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useTrans } from '/resources/js/trans'
import ToggleStatus from '@/Components/ToggleStatus.vue'
import DeleteButton from '@/Components/DeleteButton.vue'
import Pagination from '@/Components/Pagination.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    categories: Object,
})

const categoriesList = ref(props.categories.data)
const pagination = ref(props.categories)

const categoryName = category => {
    return category.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? category.name
        ?? '-'
}
</script>

<template>
    <Head title="Կատեգորիաներ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Կատեգորիաներ
            </h2>
        </template>

        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">
                    Ցանկ
                </h5>

                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    aria-controls="DataTables_Table_0"
                    type="button"
                    :href="route('membership-category.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block">
                                Ստեղծել նոր կատեգորիա
                            </span>
                        </span>
                    </span>
                </Link>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Slug</th>
                                <th>Անվանում</th>
                                <th>Ակտիվ</th>
                                <th>Գործողություններ</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="category in categoriesList"
                                :key="category.id"
                            >
                                <td>{{ category.id }}</td>
                                <td>{{ category.slug }}</td>
                                <td>{{ categoryName(category) }}</td>
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="
                                            category.active
                                                ? 'bg-label-success'
                                                : 'bg-label-danger'
                                        "
                                    >
                                        {{
                                            category.active
                                                ? useTrans('app.status.active')
                                                : useTrans('app.status.inactive')
                                        }}
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
                                            <a
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <ToggleStatus
                                                    :model="'membership-categories'"
                                                    :model-id="category.id"
                                                    :active="category.active"
                                                    :prefix="'tables'"
                                                    :label="useTrans('app.status.status')"
                                                    @update="category.active = $event"
                                                />
                                            </a>

                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('membership-category.edit', {
                                                        locale: currentLocale,
                                                        id: category.id,
                                                    })
                                                "
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Խմբագրել
                                            </Link>

                                            <a
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <DeleteButton
                                                    :model="'membership-categories'"
                                                    :model-id="category.id"
                                                    :prefix="'tables'"
                                                    @deleted="
                                                        categoriesList =
                                                            categoriesList.filter(
                                                                item => item.id !== $event,
                                                            )
                                                    "
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

            <div class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>
