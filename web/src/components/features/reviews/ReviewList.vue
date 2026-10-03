<template>
  <div class="mt-12">
    <h2 class="text-2xl md:text-3xl font-bold text-[var(--color-ink)] mb-6">Avis des spectateurs</h2>

    <div v-if="reviews && reviews.length > 0" class="space-y-6">
      <div v-for="review in reviews" :key="review.id" class="bg-[var(--color-paper-raised)] p-6 rounded-xl border border-[var(--color-rule)] transition-all hover:border-[var(--color-ink)]/30">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[var(--color-rule)] flex items-center justify-center text-[var(--color-night)] font-bold text-lg">
              {{ getUserInitial(review.user) }}
            </div>
            <div>
              <p class="font-bold text-[var(--color-ink)] text-sm">
                {{ getUserName(review.user) }}
              </p>
              <p class="text-[var(--color-ink-faint)] text-xs">Utilisateur vérifié</p>
            </div>
          </div>

          <div class="flex text-[var(--color-night)] text-sm">
            <span v-for="n in 5" :key="n">
              {{ n <= review.rating ? '★' : '☆' }}
            </span>
          </div>
        </div>

        <p class="text-[var(--color-ink-soft)] text-sm leading-relaxed">
          {{ review.comment }}
        </p>
      </div>
    </div>

    <div v-else class="text-center py-12 bg-[var(--color-paper-raised)] rounded-xl border border-[var(--color-rule)] border-dashed">
      <p class="text-[var(--color-ink-faint)]">Aucun avis pour le moment. Soyez le premier à donner votre opinion !</p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  reviews: {
    type: Array,
    default: () => [],
  },
});

const getUserInitial = (user) => {
  if (user && user.firstname) {
    return user.firstname.charAt(0).toUpperCase();
  }
  return '?';
};

const getUserName = (user) => {
  if (user && user.firstname && user.lastname) {
    return `${user.firstname} ${user.lastname}`;
  }
  if (user && user.email) {
    return user.email.split('@')[0];
  }
  return 'Utilisateur inconnu';
};
</script>
