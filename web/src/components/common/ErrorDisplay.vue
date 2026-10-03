<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { bus } from '../../bus'

/**
 * Boîte de dialogue d'erreur globale, alimentée par le bus d'événements.
 *
 * Les transitions passent par le composant <Transition> et du CSS : cela sort
 * GSAP du chemin critique (ce composant est monté dans App.vue) et supprime
 * l'ancien problème d'animation de sortie jouée sur un élément déjà retiré
 * du DOM par `v-if`.
 */
const COUNTDOWN_PATTERN = /Veuillez réessayer dans (\d+)/

const isVisible = ref(false)
const rawMessage = ref('')
const countdown = ref(0)
const dialog = ref(null)

let intervalId = null

const isCountdown = computed(() => COUNTDOWN_PATTERN.test(rawMessage.value))

const message = computed(() =>
    isCountdown.value
        ? `Trop de requêtes. Réessayez dans ${countdown.value} seconde${countdown.value > 1 ? 's' : ''}.`
        : rawMessage.value
)

const stopCountdown = () => {
    if (intervalId) clearInterval(intervalId)
    intervalId = null
}

const close = () => {
    stopCountdown()
    isVisible.value = false
}

const startCountdown = (seconds) => {
    stopCountdown()
    countdown.value = seconds
    intervalId = setInterval(() => {
        countdown.value -= 1
        if (countdown.value <= 0) close()
    }, 1000)
}

const handleError = (text) => {
    rawMessage.value = String(text ?? '')
    isVisible.value = true

    const match = rawMessage.value.match(COUNTDOWN_PATTERN)
    if (match) startCountdown(Number(match[1]))
    else stopCountdown()
}

const onKeydown = (event) => {
    if (event.key === 'Escape' && !isCountdown.value) close()
}

// Le focus part sur la boîte : le message est annoncé et Échap fonctionne
watch(isVisible, async (visible) => {
    if (!visible) {
        window.removeEventListener('keydown', onKeydown)
        return
    }
    window.addEventListener('keydown', onKeydown)
    await nextTick()
    dialog.value?.focus()
})

bus.on('error', handleError)

onUnmounted(() => {
    bus.off('error', handleError)
    window.removeEventListener('keydown', onKeydown)
    stopCountdown()
})
</script>

<template>
    <Transition name="modal">
        <div
            v-if="isVisible"
            class="fixed inset-0 z-[80] flex items-center justify-center px-4"
        >
            <div
                class="absolute inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm"
                @click="isCountdown ? null : close()"
            />

            <div
                ref="dialog"
                class="modal-card relative w-full max-w-md rounded-lg border border-[var(--color-ink)]/30 bg-[var(--color-paper-raised)] p-8 text-center shadow-2xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="error-title"
                aria-describedby="error-message"
                tabindex="-1"
            >
                <svg
                    class="mx-auto mb-4 h-14 w-14 text-[#EF4444]"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>

                <h2 id="error-title" class="mb-2 text-2xl font-bold text-[var(--color-ink)]">
                    Une erreur est survenue
                </h2>

                <p id="error-message" class="mb-6 text-[var(--color-ink-soft)]">{{ message }}</p>

                <button
                    v-if="!isCountdown"
                    type="button"
                    class="btn btn-primary"
                    @click="close"
                >
                    Fermer
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 260ms var(--ease-cinema);
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .modal-card {
    transition: transform 320ms var(--ease-cinema);
}
.modal-leave-active .modal-card {
    transition: transform 200ms var(--ease-in-expo);
}
.modal-enter-from .modal-card,
.modal-leave-to .modal-card {
    transform: scale(0.9);
}

@media (prefers-reduced-motion: reduce) {
    .modal-enter-from .modal-card,
    .modal-leave-to .modal-card {
        transform: none;
    }
}
</style>
