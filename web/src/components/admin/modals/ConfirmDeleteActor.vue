<script setup>
import { onMounted } from 'vue'

const emit = defineEmits(['cancel', 'confirm'])
const props = defineProps({ actor: Object })

onMounted(() => {
})
</script>

<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="emit('cancel')">
    <div class="confirm-dialog bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-md shadow-2xl">
      <header class="p-6 border-b border-[var(--color-rule)] flex items-center gap-4">
        <div class="w-12 h-12 flex-shrink-0 rounded-full bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 flex items-center justify-center">
          <svg class="w-6 h-6 text-[var(--color-danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <h2 class="text-2xl font-bold text-[var(--color-ink)]">Confirmer la suppression</h2>
      </header>
      <main class="p-6">
        <p class="text-[var(--color-ink-soft)]">
          Êtes-vous sûr de vouloir supprimer l'acteur
          <strong class="text-[var(--color-ink)]">{{ props.actor?.firstname }} {{ props.actor?.lastname }}</strong> ? Cette action est irréversible.
        </p>
      </main>
      <footer class="p-6 flex justify-end gap-4 bg-[var(--color-paper)] rounded-b-lg">
        <button @click="emit('cancel')" class="btn btn-quiet">
          Annuler
        </button>
        <button @click="emit('confirm')" class="btn btn-danger">
          Supprimer
        </button>
      </footer>
    </div>
  </div>
</template>
