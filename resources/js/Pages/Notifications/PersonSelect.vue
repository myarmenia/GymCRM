<script setup>
import { computed } from 'vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Ընտրեք հաճախորդին',
    },
})

const emit = defineEmits(['update:modelValue'])

const selected = computed({
    get() {
        return props.options.find(option => String(option.value) === String(props.modelValue)) ?? null
    },
    set(value) {
        emit('update:modelValue', value?.value ?? '')
    },
})
</script>

<template>
    <Multiselect
        v-model="selected"
        :options="options"
        :multiple="false"
        :close-on-select="true"
        :clear-on-select="false"
        :allow-empty="true"
        :preserve-search="true"
        :placeholder="placeholder"
        label="label"
        track-by="value"
    >
        <template #noResult>
            Արդյունքներ չկան
        </template>
        <template #noOptions>
            Հաճախորդներ չկան
        </template>
    </Multiselect>
</template>

<style scoped>
:deep(.multiselect) {
    min-height: 38px;
    color: var(--bs-body-color);
}

:deep(.multiselect__tags) {
    border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius);
    min-height: 38px;
    padding: 4px 40px 4px 8px;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
}

:deep(.multiselect:not(.multiselect--active):hover .multiselect__tags) {
    border-color: rgba(var(--bs-primary-rgb), .45);
}

:deep(.multiselect__tags-wrap) {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

:deep(.multiselect__tag) {
    background: rgba(var(--bs-primary-rgb), .12) !important;
    color: var(--bs-primary) !important;
    border: 1px solid rgba(var(--bs-primary-rgb), .28);
    border-radius: 4px;
    padding: 4px 26px 4px 10px;
    margin-bottom: 0;
    font-size: 13px;
    font-weight: 500;
}

:deep(.multiselect__tag-icon) {
    border-radius: 0 4px 4px 0;
}

:deep(.multiselect__tag-icon:after) {
    color: var(--bs-primary) !important;
    font-size: 14px;
}

:deep(.multiselect__tag-icon:hover) {
    background: rgba(var(--bs-primary-rgb), .16) !important;
}

:deep(.multiselect__tag-icon:hover:after) {
    color: var(--bs-primary) !important;
}

:deep(.multiselect__option--highlight) {
    background: rgba(var(--bs-primary-rgb), .12) !important;
    color: var(--bs-primary) !important;
}

:deep(.multiselect__option--selected) {
    background: rgba(var(--bs-primary-rgb), .08) !important;
    color: var(--bs-primary) !important;
    font-weight: 500;
}

:deep(.multiselect__option--highlight.multiselect__option--selected) {
    background: rgba(var(--bs-primary-rgb), .16) !important;
    color: var(--bs-primary) !important;
}

:deep(.multiselect__input),
:deep(.multiselect__single) {
    font-size: 14px;
    margin-bottom: 0;
    background: transparent !important;
    color: var(--bs-body-color);
}

:deep(.multiselect__input::placeholder) {
    color: var(--bs-secondary-color);
}

:deep(.multiselect__input:focus) {
    outline: none !important;
    box-shadow: none !important;
}

:deep(.multiselect--active .multiselect__tags) {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), .16);
}

:deep(.multiselect__placeholder) {
    color: var(--bs-secondary-color);
    margin-bottom: 0;
    padding-top: 2px;
}

:deep(.multiselect__content-wrapper) {
    background: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    box-shadow: 0 .25rem .75rem rgba(var(--bs-body-color-rgb), .08);
}

:deep(.multiselect__option) {
    color: var(--bs-body-color);
    font-size: 14px;
}

:deep(.multiselect__option:after) {
    display: none !important;
}

:deep(.multiselect__select:before) {
    border-color: var(--bs-secondary-color) transparent transparent;
}
</style>
