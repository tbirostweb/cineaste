<template>
  <div class="min-h-screen bg-[var(--color-paper)] text-text-[var(--color-ink)]">
    <div class="container mx-auto px-6 py-20">
      <h1 class="text-5xl font-bold text-[var(--color-ink)] mb-8">Admin Panel</h1>
      <div class="flex border-b border-[var(--color-rule)]">
        <button
          v-for="tab in tabs"
          :key="tab"
          @click="currentTab = tab"
          :class="[
            'px-6 py-3 text-sm font-medium tracking-wider uppercase transition-colors',
            {
              'border-b-2 border-[var(--color-ink)] text-[var(--color-night)]': currentTab === tab,
              'text-[var(--color-ink-soft)] hover:text-[var(--color-ink)]': currentTab !== tab
            }
          ]"
          :aria-selected="currentTab === tab"
          role="tab"
        >
          {{ tab }}
        </button>
      </div>
      <div class="mt-8" role="tabpanel">
        <component :is="currentTabComponent"></component>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import MovieManagement from '../../components/admin/management/MovieManagement.vue';
import ActorManagement from '../../components/admin/management/ActorManagement.vue';
import DirectorManagement from '../../components/admin/management/DirectorManagement.vue';
import CategoryManagement from '../../components/admin/management/CategoryManagement.vue';
import UserManagement from '../../components/admin/management/UserManagement.vue';
import ReviewManagement from '../../components/admin/management/ReviewManagement.vue';

const currentTab = ref('Movies');
const tabs = ['Movies', 'Actors', 'Directors', 'Categories', 'Users', 'Reviews'];

const currentTabComponent = computed(() => {
  switch (currentTab.value) {
    case 'Movies':
      return MovieManagement;
    case 'Actors':
      return ActorManagement;
    case 'Directors':
      return DirectorManagement;
    case 'Categories':
      return CategoryManagement;
    case 'Users':
      return UserManagement;
    case 'Reviews':
      return ReviewManagement;
    default:
      return null;
  }
});
</script>
