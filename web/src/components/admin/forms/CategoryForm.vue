<script setup>
import { ref, watch, onMounted } from "vue"
import api from "/src/api/api.js"
import { logger } from '../../../utils/logger'

const props = defineProps({
  category: Object
})
const emit = defineEmits(["close", "refresh"])

const name = ref(props.category?.name || "")
const loading = ref(false)
const error = ref("")

watch(
    () => props.category,
    (newVal) => {
      name.value = newVal?.name || ""
      error.value = ""
    }
)

onMounted(() => {
})

const submit = async () => {
  if (!name.value.trim()) {
    error.value = "Le nom de la catégorie est requis."
    return
  }

  loading.value = true
  try {
    const payload = {
      name: name.value,
    }

    if (props.category?.id) {
      await api.patch(`/categories/${props.category.id}`, payload, {
        headers: { "Content-Type": "application/merge-patch+json" },
      })
    } else {
      await api.post("/categories", payload, {
        headers: { "Content-Type": "application/ld+json" },
      })
    }

    emit("refresh")
    emit("close")
  } catch (err) {
    logger.error('Erreur sauvegarde', err.response?.data || err)
    error.value = err.response?.data?.detail || "Une erreur est survenue."
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="emit('close')">
    <div class="form-container bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-md shadow-2xl">
      <header class="p-6 flex items-center justify-between border-b border-[var(--color-rule)]">
        <h2 class="text-2xl font-bold text-[var(--color-ink)]">
          {{ props.category?.id ? "Modifier" : "Ajouter" }} une catégorie
        </h2>
        <button @click="emit('close')" class="p-2 rounded-full text-[var(--color-ink-faint)] hover:bg-[var(--color-paper-sunk)]" aria-label="Fermer">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </header>

      <main class="p-6 space-y-4">
        <div>
          <label for="category-name" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Nom de la catégorie</label>
          <input id="category-name" v-model="name" type="text" placeholder="ex: Action, Comédie..." class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
        </div>

        <div v-if="error" class="bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] p-3 rounded-md text-sm">
          {{ error }}
        </div>
      </main>

      <footer class="p-6 flex justify-end gap-4 border-t border-[var(--color-rule)]">
        <button @click="emit('close')" class="btn btn-quiet">
          Annuler
        </button>
        <button @click="submit" :disabled="loading" class="btn btn-primary">
          {{ loading ? "Sauvegarde..." : props.category?.id ? "Sauvegarder" : "Ajouter" }}
        </button>
      </footer>
    </div>
  </div>
</template>
