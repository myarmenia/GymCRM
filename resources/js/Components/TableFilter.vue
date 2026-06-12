<script setup>
import { computed, reactive, watch } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({}),
    },
    nameMode: {
        type: String,
        default: 'separate',
        validator: value => ['separate', 'full'].includes(value),
    },
    textFields: {
        type: Array,
        default: null,
    },
    selectFields: {
        type: Array,
        default: () => [],
    },
    dateFields: {
        type: Array,
        default: () => [
            { value: 'birth_date', label: 'Ծննդյան ամսաթիվ' },
            { value: 'created_at', label: 'Ստեղծման ամսաթիվ' },
        ],
    },
    defaultDateField: {
        type: String,
        default: 'created_at',
    },
    submitLabel: {
        type: String,
        default: 'Ֆիլտրել',
    },
    resetLabel: {
        type: String,
        default: 'Վերականգնել',
    },
})

const emit = defineEmits(['update:modelValue', 'filter', 'reset'])

const form = reactive({})

const defaultTextFields = computed(() => {
    const nameFields = props.nameMode === 'full'
        ? [{ name: 'full_name', label: 'Ամբողջական անուն', col: 'col-md-4' }]
        : [
            { name: 'name', label: 'Անուն', col: 'col-md-3' },
            { name: 'surname', label: 'Ազգանուն', col: 'col-md-3' },
        ]

    return [
        ...nameFields,
        { name: 'phone', label: 'Հեռախոս', col: 'col-md-3' },
        { name: 'email', label: 'Էլ. հասցե', type: 'email', col: 'col-md-3' },
    ]
})

const resolvedTextFields = computed(() => props.textFields ?? defaultTextFields.value)

const selectedDateField = computed(() => {
    return props.dateFields.find(field => field.value === form.date_field)
        ?? props.dateFields[0]
        ?? null
})

const syncFromModelValue = value => {
    resolvedTextFields.value.forEach(field => {
        form[field.name] = value[field.name] ?? ''
    })

    props.selectFields.forEach(field => {
        form[field.name] = value[field.name] ?? field.default ?? (field.multiple ? [] : '')
    })

    form.date_field = value.date_field ?? props.defaultDateField
    form.date_from = value.date_from ?? ''
    form.date_to = value.date_to ?? ''
}

const cleanPayload = () => {
    const payload = {
        date_field: form.date_field,
        date_from: form.date_from,
        date_to: form.date_to,
    }

    resolvedTextFields.value.forEach(field => {
        payload[field.name] = form[field.name]
    })

    props.selectFields.forEach(field => {
        payload[field.name] = form[field.name]
    })

    return Object.fromEntries(
        Object.entries(payload).filter(([, value]) => {
            if (Array.isArray(value)) {
                return value.length > 0
            }

            return value !== null && value !== '' && value !== undefined
        })
    )
}

const updateModel = () => {
    emit('update:modelValue', cleanPayload())
}

const submit = () => {
    const payload = cleanPayload()

    emit('update:modelValue', payload)
    emit('filter', payload)
}

const reset = () => {
    syncFromModelValue({})
    updateModel()
    emit('reset')
}

watch(
    () => props.modelValue,
    value => syncFromModelValue(value ?? {}),
    { immediate: true, deep: true }
)
</script>

<template>
    <form
        class="card mb-4"
        @submit.prevent="submit"
        @reset.prevent="reset"
    >
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div
                    v-for="field in resolvedTextFields"
                    :key="field.name"
                    :class="field.col ?? 'col-md-3'"
                >
                    <InputLabel
                        :value="field.label"
                        class="form-label"
                    />
                    <input
                        v-model="form[field.name]"
                        :type="field.type ?? 'text'"
                        class="form-control"
                        :placeholder="field.placeholder"
                        @input="updateModel"
                    />
                </div>

                <div
                    v-for="field in selectFields"
                    :key="field.name"
                    :class="field.col ?? 'col-md-3'"
                >
                    <InputLabel
                        :value="field.label"
                        class="form-label"
                    />
                    <select
                        v-model="form[field.name]"
                        class="form-select"
                        :multiple="Boolean(field.multiple)"
                        @change="updateModel"
                    >
                        <option
                            v-if="!field.multiple"
                            value=""
                        >
                            {{ field.placeholder ?? 'Բոլորը' }}
                        </option>
                        <option
                            v-for="option in field.options ?? []"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="dateFields.length"
                    class="col-md-3"
                >
                    <InputLabel
                        value="Ամսաթվի դաշտ"
                        class="form-label"
                    />
                    <select
                        v-model="form.date_field"
                        class="form-select"
                        @change="updateModel"
                    >
                        <option
                            v-for="field in dateFields"
                            :key="field.value"
                            :value="field.value"
                        >
                            {{ field.label }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="dateFields.length"
                    class="col-md-3"
                >
                    <InputLabel
                        :value="`${selectedDateField?.label ?? 'Ամսաթիվ'} սկսած`"
                        class="form-label"
                    />
                    <input
                        v-model="form.date_from"
                        type="date"
                        class="form-control"
                        @input="updateModel"
                    />
                </div>

                <div
                    v-if="dateFields.length"
                    class="col-md-3"
                >
                    <InputLabel
                        :value="`${selectedDateField?.label ?? 'Ամսաթիվ'} մինչև`"
                        class="form-label"
                    />
                    <input
                        v-model="form.date_to"
                        type="date"
                        class="form-control"
                        @input="updateModel"
                    />
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <PrimaryButton>
                        <i class="icon-base ti tabler-filter me-1"></i>
                        {{ submitLabel }}
                    </PrimaryButton>

                    <button
                        type="reset"
                        class="btn btn-secondary waves-effect"
                    >
                        <i class="icon-base ti tabler-restore me-1"></i>
                        {{ resetLabel }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
