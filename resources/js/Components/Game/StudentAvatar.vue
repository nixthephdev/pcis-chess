<script setup lang="ts">
import { computed } from 'vue';
import PieceIcon from './PieceIcon.vue';

const props = withDefaults(defineProps<{ avatar: string; size?: number }>(), { size: 40 });

const LOOK: Record<string, { piece: string; bg: string }> = {
    king: { piece: 'K', bg: '#ffd23f' },
    queen: { piece: 'Q', bg: '#e8547a' },
    rook: { piece: 'R', bg: '#ff8a3d' },
    bishop: { piece: 'B', bg: '#7bc6ff' },
    knight: { piece: 'N', bg: '#3fae73' },
    pawn: { piece: 'P', bg: '#b69cff' },
};

const look = computed(() => LOOK[props.avatar] ?? LOOK.knight);
</script>

<template>
    <span
        class="inline-grid shrink-0 place-items-center rounded-full border-[3px] border-ink"
        :style="{ width: size + 'px', height: size + 'px', background: look.bg }"
        aria-hidden="true"
    >
        <span class="block h-[78%] w-[78%]"><PieceIcon :piece="look.piece" /></span>
    </span>
</template>
