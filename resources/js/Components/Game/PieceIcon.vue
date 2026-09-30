<script setup lang="ts">
import { computed } from 'vue';

/** A piece letter as used on the board: uppercase white, lowercase black. */
const props = defineProps<{ piece: string }>();

const href = computed(() => {
    const color = props.piece === props.piece.toUpperCase() ? 'w' : 'b';
    return `/pieces/standard.svg#${color}${props.piece.toLowerCase()}`;
});
</script>

<template>
    <svg viewBox="0 0 40 40" aria-hidden="true" class="block h-full w-full">
        <use :href="href" />
    </svg>
</template>
