<script setup>
defineProps({
    weekRangeLabel: {
        type: String,
        default: '-',
    },
})

defineEmits(['previous', 'today', 'next'])
</script>

<template>
    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                    Մարզիչների զբաղվածության օրացույց
                </h2>
                <div class="text-muted">{{ weekRangeLabel }}</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="$emit('previous')"
                >
                    Նախորդ շաբաթ
                </button>
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    @click="$emit('today')"
                >
                    Այսօր
                </button>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="$emit('next')"
                >
                    Հաջորդ շաբաթ
                </button>
            </div>
        </div>
    </div>
</template>
