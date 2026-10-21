<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/composables/useAuth'
import { useConfirm } from '@/composables/useConfirm'
import { translate } from '/resources/js/trans'

const props = defineProps({
    contact: { type: Object, required: true },
    canSellMembership: { type: Boolean, default: false },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'hy')
const contactText = key => translate(page.props.translations, `app.contact_notes.${key}`)
const personText = key => translate(page.props.translations, `app.people.${key}`)
const { hasAnyRole } = useAuth()
const { confirm } = useConfirm()
const toast = useToast()
const blocking = ref(false)
const blocked = ref(false)
const canManageVisits = computed(() => hasAnyRole(['manager', 'sales_manager', 'super_admin', 'admin']))
const canEditPerson = canManageVisits
const canBlockPerson = computed(() => hasAnyRole(['sales_manager', 'super_admin', 'admin']))
const isBlocked = computed(() => Boolean(props.contact.existing_person_blocked || blocked.value))

const blockPerson = async () => {
    if (!props.contact.existing_person_id || isBlocked.value || blocking.value) return

    const approved = await confirm(personText('confirm_block_user'), {
        confirmText: personText('block_user'),
        confirmClass: 'btn-danger',
    })
    if (!approved) return

    blocking.value = true
    router.patch(route('person.block', { locale: locale.value, id: props.contact.existing_person_id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            blocked.value = true
            toast.success(personText('block_success'))
        },
        onError: () => toast.error(personText('block_failed')),
        onFinish: () => { blocking.value = false },
    })
}
</script>

<template>
    <div class="dropdown" @click.stop>
        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" :aria-label="contactText('actions')" aria-expanded="false">
            <i class="icon-base ti tabler-dots-vertical" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            <Link v-if="!contact.existing_person_id" class="dropdown-item waves-effect" :href="route('person.create', { locale, phone: contact.phone_number })">
                <i class="icon-base ti tabler-user-plus me-1"></i>{{ contactText('create_visitor') }}
            </Link>
            <template v-else>
                <Link class="dropdown-item waves-effect" :href="route('person.profile', { locale, id: contact.existing_person_id })">
                    <i class="icon-base ti tabler-eye me-1"></i>{{ personText('view_profile') }}
                </Link>
                <Link v-if="canSellMembership" class="dropdown-item waves-effect" :href="route('membership_sale.create', { locale, person: contact.existing_person_id })">
                    <i class="icon-base ti tabler-credit-card-pay me-1"></i>{{ personText('sell_membership') }}
                </Link>
                <Link v-if="canManageVisits" class="dropdown-item waves-effect" :href="route('person.visits', { locale, id: contact.existing_person_id })">
                    <i class="icon-base ti tabler-walk me-1"></i>{{ personText('visits_management') }}
                </Link>
                <Link v-if="canEditPerson" class="dropdown-item waves-effect" :href="route('person.edit', { locale, id: contact.existing_person_id })">
                    <i class="icon-base ti tabler-pencil me-1"></i>{{ personText('edit') }}
                </Link>
                <button v-if="canBlockPerson && !isBlocked" type="button" class="dropdown-item waves-effect text-danger" :disabled="blocking" @click="blockPerson">
                    <i class="icon-base ti tabler-ban me-1"></i>{{ personText('block_user') }}
                </button>
            </template>
        </div>
    </div>
</template>
