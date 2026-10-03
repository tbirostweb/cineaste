<script setup>
import { watch } from 'vue'
import { useConsent } from '../../composables/useConsent'
import { isAnalyticsConfigured, loadAnalytics, unloadAnalytics } from '../../analytics'

const { needsChoice, hasConsented, accept, decline } = useConsent()

// Le chargement suit l'état du consentement, dans les deux sens.
watch(
    hasConsented,
    (granted) => (granted ? loadAnalytics() : unloadAnalytics()),
    { immediate: true }
)

// Sans traceur configuré, le bandeau n'a pas lieu d'être : la CNIL n'exige
// aucun consentement pour un site qui ne dépose que des cookies fonctionnels.
const showBanner = () => needsChoice.value && isAnalyticsConfigured()
</script>

<template>
    <Transition name="page">
        <div
            v-if="showBanner()"
            class="fixed inset-x-0 bottom-0 z-[70] border-t border-[var(--color-rule)] bg-[var(--color-paper)] backdrop-blur-lg"
            role="dialog"
            aria-modal="false"
            aria-labelledby="cookie-title"
        >
            <div
                class="mx-auto flex max-w-5xl flex-col gap-5 px-6 py-6 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h2 id="cookie-title" class="text-xl font-bold text-[var(--color-ink)]">
                        Mesure d'audience
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-[var(--color-ink-soft)]">
                        Nous aimerions mesurer la fréquentation du site pour l'améliorer.
                        Aucun traceur n'est déposé tant que vous n'avez pas accepté.
                        <router-link
                            to="/confidentialite"
                            class="text-[var(--color-night)] underline underline-offset-4 hover:text-[var(--color-night-soft)]"
                        >
                            En savoir plus
                        </router-link>
                    </p>
                </div>

                <div class="flex flex-shrink-0 gap-3">
                    <button type="button" class="btn btn-ghost" @click="decline">
                        Refuser
                    </button>
                    <button type="button" class="btn btn-primary" @click="accept">
                        Accepter
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
