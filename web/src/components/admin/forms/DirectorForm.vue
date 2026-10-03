<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="$emit('close')">
    <div class="form-container bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-2xl shadow-2xl max-h-[90vh] flex flex-col">
      <header class="p-6 flex items-center justify-between border-b border-[var(--color-rule)]">
        <h2 class="text-2xl font-bold text-[var(--color-ink)]">
          {{ props.director ? 'Modifier le réalisateur' : 'Nouveau réalisateur' }}
        </h2>
        <button @click="$emit('close')" class="p-2 rounded-full text-[var(--color-ink-faint)] hover:bg-[var(--color-paper-sunk)]" aria-label="Fermer">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </header>

      <form @submit.prevent="saveDirector" class="flex flex-col min-h-0">
        <main class="p-6 overflow-y-auto">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="director-firstname" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Prénom</label>
              <input id="director-firstname" v-model="form.firstname" type="text" placeholder="ex: Christopher" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
            </div>
            <div>
              <label for="director-lastname" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Nom</label>
              <input id="director-lastname" v-model="form.lastname" type="text" placeholder="ex: Nolan" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
            </div>
          </div>
        </main>

        <footer class="p-6 flex justify-end gap-4 border-t border-[var(--color-rule)]">
          <button type="button" @click="$emit('close')" class="btn btn-quiet">Annuler</button>
          <button type="submit" class="btn btn-primary">Sauvegarder</button>
        </footer>
      </form>
    </div>
  </div>
</template>


<script setup>
import { ref, watch } from 'vue';
import api from '/src/api/api.js';
import { logger } from '../../../utils/logger'

const props = defineProps({
  director: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'refresh']);

const form = ref({
  firstname: '',
  lastname: '',
});

watch(() => props.director, (newDirector) => {
  if (newDirector) {
    form.value = { ...newDirector };
  } else {
    form.value = { firstname: '', lastname: '' };
  }
}, { immediate: true });

const saveDirector = async () => {
  try {
    if (props.director) {
      await api.put(`/directors/${props.director.id}`, form.value);
    } else {
      await api.post('/directors', form.value);
    }
    emit('refresh');
    emit('close');
  } catch (error) {
    logger.error('Error saving director', error);
  }
};
</script>
