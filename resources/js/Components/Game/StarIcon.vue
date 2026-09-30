<script setup lang="ts">
withDefaults(defineProps<{ filled?: boolean }>(), { filled: true });
</script>

<template>
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path
            d="M12 2l2.9 6.9 7.1.6-5.4 4.7 1.6 7L12 17.8 5.8 21.2l1.6-7L2 9.5l7.1-.6z"
            :fill="filled ? '#ffd23f' : '#ffffff'"
            stroke="#1c2541"
            stroke-width="1.8"
            stroke-linejoin="round"
        />
    </svg>
</template>
