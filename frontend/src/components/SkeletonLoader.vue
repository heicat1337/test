<template>
  <div class="skeleton-wrap" aria-hidden="true">
    <section
      v-for="g in groups"
      :key="g"
      class="skeleton-section"
    >
      <div class="skeleton-header">
        <div class="sk sk-icon"></div>
        <div class="sk sk-title"></div>
        <div class="sk sk-divider"></div>
      </div>
      <div class="skeleton-grid">
        <div v-for="i in cardsPerGroup" :key="i" class="sk sk-card"></div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  groups?: number
  cardsPerGroup?: number
}>(), {
  groups: 3,
  cardsPerGroup: 6
})
</script>

<style scoped lang="scss">
.skeleton-wrap { padding-bottom: 40px; }

.skeleton-section { margin-bottom: 40px; }

.skeleton-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
}

.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
}

.sk {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0.04) 0%,
    rgba(255, 255, 255, 0.08) 50%,
    rgba(255, 255, 255, 0.04) 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  border-radius: var(--radius-sm);
}

.sk-icon { width: 22px; height: 22px; border-radius: 6px; }
.sk-title { width: 120px; height: 20px; }
.sk-divider { flex: 1; height: 1px; }
.sk-card {
  height: 92px;
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (max-width: 768px) {
  .skeleton-grid { grid-template-columns: 1fr; }
}
</style>
