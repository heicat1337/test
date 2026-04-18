<template>
  <div class="search-bar">
    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8" />
      <path d="m21 21-4.35-4.35" />
    </svg>
    <input
      :value="modelValue"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      type="text"
      :placeholder="placeholder"
      class="search-input"
    />
    <button v-if="modelValue" class="search-clear" @click="$emit('update:modelValue', '')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6 6 18M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
}>(), {
  placeholder: '搜索 Web3 项目...'
})
defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.search-bar {
  position: relative;
  width: 100%;
  max-width: 420px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: var(--text-tertiary);
  transition: color var(--transition-base);
}

.search-input {
  width: 100%;
  height: 42px;
  padding: 0 40px 0 42px;
  border: 1px solid var(--border-color);
  border-radius: 999px;
  background: var(--bg-card);
  color: var(--text-primary);
  font-size: 14px;
  font-family: var(--font-sans);
  outline: none;
  transition: all var(--transition-base);
  @include glass(var(--bg-card), 8px);

  &::placeholder { color: var(--text-tertiary); }

  &:focus {
    border-color: var(--neon-blue);
    box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
  }
}

.search-bar:focus-within .search-icon {
  color: var(--neon-blue);
}

.search-clear {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 50%;
  background: var(--border-color);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all var(--transition-fast);

  svg { width: 14px; height: 14px; }

  &:hover {
    background: rgba(255, 255, 255, 0.15);
    color: var(--text-primary);
  }
}
</style>
