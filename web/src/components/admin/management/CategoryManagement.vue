<script setup>
import { ref, onMounted, computed } from "vue"
import CategoryForm from "../forms/CategoryForm.vue"
import ConfirmDeleteCategory from "../modals/ConfirmDeleteCategory.vue"
import api from '../../../api/api'

const loading = ref(false)
const showForm = ref(false)
const showConfirm = ref(false)
const selectedCategory = ref(null)
const categoryToDelete = ref(null)
const errorMessage = ref("")
const categories = ref([])

// Pagination
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = 30

const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage))

const fetchCategories = async () => {
  loading.value = true
  errorMessage.value = ""
  try {
    const response = await api.get('/categories', {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage
      }
    })
    const data = response.data
    categories.value = data['hydra:member'] || data['member'] || []
    totalItems.value = data['hydra:totalItems'] || data['totalItems'] || categories.value.length
  } catch (err) {
    errorMessage.value = "Error loading categories."
  } finally {
    loading.value = false
  }
}

const editCategory = (category) => {
  selectedCategory.value = { ...category }
  showForm.value = true
}

const confirmDelete = (category) => {
  categoryToDelete.value = category
  showConfirm.value = true
}

const deleteCategory = async () => {
  if (!categoryToDelete.value) return;
  try {
    if (categoryToDelete.value.moviesCount > 0) {
      alert("This category cannot be deleted as it is linked to movies.")
      showConfirm.value = false
      return
    }
    await api.delete(`/categories/${categoryToDelete.value.id}`)
    showConfirm.value = false
    categoryToDelete.value = null
    fetchCategories()
  } catch {
    errorMessage.value = "An error occurred during deletion."
  }
}

const onFormSaved = async () => {
  showForm.value = false;
  await fetchCategories();
};

const changePage = (newPage) => {
  if (newPage >= 1 && newPage <= totalPages.value) {
    page.value = newPage
    fetchCategories()
  }
}

onMounted(async () => {
  await fetchCategories()
})
</script>

<template>
  <div class="universal-card p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-3xl font-bold text-[var(--color-ink)]">Gestion des Catégories</h2>
      <div class="flex items-center gap-4">
        <span class="text-sm text-[var(--color-ink-soft)]">Total: <span class="text-[var(--color-night)] font-bold">{{ totalItems }}</span></span>
        <button
          @click="selectedCategory = null; showForm = true"
          class="btn btn-primary"
        >
          + Ajouter
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-10">
      <div class="flex justify-center gap-2 mb-2">
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce"></div>
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
      </div>
      <p class="text-[var(--color-ink-soft)] text-sm">Chargement des catégories...</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead class="border-b border-[var(--color-rule)] bg-[var(--color-paper-sunk)]">
          <tr>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Nom</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Films</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="category in categories" :key="category.id" class="border-b border-[var(--color-rule)] hover:bg-[var(--color-paper-sunk)] transition-colors">
            <td class="p-4 text-[var(--color-ink)] font-medium">{{ category.name }}</td>
            <td class="p-4 text-[var(--color-ink-soft)] text-sm">{{ category.moviesCount || 0 }}</td>
            <td class="p-4 text-right">
              <button @click="editCategory(category)" class="text-[var(--color-night)] hover:text-[var(--color-ink)] text-xs font-bold uppercase tracking-wider transition-colors border border-[var(--color-rule)] hover:bg-[var(--color-paper-sunk)] px-3 py-1.5 rounded mr-2">
                Modifier
              </button>
              <button @click="confirmDelete(category)" class="text-[var(--color-danger)] hover:text-[var(--color-danger)] text-xs font-bold uppercase tracking-wider transition-colors border border-[var(--color-danger)]/40 hover:bg-[var(--color-danger)]/10 px-3 py-1.5 rounded">
                Supprimer
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center items-center gap-4 mt-8 pt-4 border-t border-[var(--color-rule)]">
        <button
          @click="changePage(page - 1)"
          :disabled="page === 1"
          class="px-4 py-2 bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded text-[var(--color-ink)] hover:border-[var(--color-ink)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
        >
          Précédent
        </button>
        <span class="text-[var(--color-ink-soft)] text-sm">Page <span class="text-[var(--color-night)] font-bold">{{ page }}</span> sur {{ totalPages }}</span>
        <button
          @click="changePage(page + 1)"
          :disabled="page === totalPages"
          class="px-4 py-2 bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded text-[var(--color-ink)] hover:border-[var(--color-ink)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
        >
          Suivant
        </button>
      </div>
    </div>

    <CategoryForm v-if="showForm" :category="selectedCategory" @close="showForm = false" @refresh="onFormSaved" />
    <ConfirmDeleteCategory v-if="showConfirm" :category="categoryToDelete" @cancel="showConfirm = false" @confirm="deleteCategory" />
  </div>
</template>
