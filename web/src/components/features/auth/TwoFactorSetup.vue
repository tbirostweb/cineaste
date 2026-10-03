<script setup>
import { ref, onMounted } from 'vue'
import api from '/src/api/api.js'
import { logger } from '../../../utils/logger'

const emit = defineEmits(['close', 'enabled'])

const step = ref(1)
const qrCode = ref('')
const secret = ref('')
const verificationCode = ref('')
const backupCodes = ref([])
const loading = ref(false)
const error = ref('')

const setupTwoFactor = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.post('/2fa/setup')
    qrCode.value = response.data.qr_code
    secret.value = response.data.secret
    step.value = 2

    await new Promise(resolve => setTimeout(resolve, 100))
  } catch (err) {
    logger.error('Erreur setupTwoFactor', err)
    if (err.response) {
        logger.error('Détails erreur réponse', err.response.data)
        error.value = err.response.data.error || 'Erreur lors de la configuration'
        if (err.response.data.exception_message) {
            logger.error('Exception serveur', err.response.data.exception_message)
        }
    } else {
        error.value = 'Erreur réseau ou inconnue'
    }
  } finally {
    loading.value = false
  }
}

const verifyAndEnable = async () => {
  if (!verificationCode.value || verificationCode.value.length !== 6) {
    error.value = 'Le code doit contenir 6 chiffres'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await api.post('/2fa/enable', {
      code: verificationCode.value
    })

    backupCodes.value = response.data.backup_codes
    step.value = 3

    await new Promise(resolve => setTimeout(resolve, 100))
  } catch (err) {
    error.value = err.response?.data?.error || 'Code invalide'
  } finally {
    loading.value = false
  }
}

const copyBackupCodes = () => {
  const text = backupCodes.value.join('\n')
  navigator.clipboard.writeText(text)
  alert('Codes de secours copiés dans le presse-papier !')
}

const finish = () => {
  emit('enabled')
  emit('close')
}

onMounted(() => {
  setupTwoFactor()
})
</script>

<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="emit('close')">
    <div class="bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-lg shadow-2xl">

      <!-- Header -->
      <header class="p-6 border-b border-[var(--color-rule)] flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-[var(--color-ink)]">Activer l'authentification à deux facteurs</h2>
          <p class="text-sm text-[var(--color-ink-faint)] mt-1">Étape {{ step }}/3</p>
        </div>
        <button @click="emit('close')" class="p-2 rounded-full text-[var(--color-ink-faint)] hover:bg-[var(--color-paper-sunk)]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </header>

      <!-- Body -->
      <main class="p-6">

        <!-- Étape 1 & 2: QR Code et vérification -->
        <div v-if="step === 2" class="space-y-6">
          <div class="qr-container bg-white p-6 rounded-lg mx-auto w-fit">
            <img :src="qrCode" alt="QR Code" class="w-64 h-64">
          </div>

          <div class="space-y-4">
            <div class="bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-lg p-4">
              <p class="text-sm text-[var(--color-ink-soft)] mb-2">
                <strong class="text-[var(--color-ink)]">1.</strong> Scannez ce QR code avec votre application d'authentification (Google Authenticator, Authy, etc.)
              </p>
              <p class="text-sm text-[var(--color-ink-soft)]">
                <strong class="text-[var(--color-ink)]">2.</strong> Ou entrez manuellement cette clé :
              </p>
              <code class="block text-[var(--color-night)] space-y-0.5 bg-[var(--color-ink)]/70 px-3 py-2 rounded mt-2 text-xs break-all font-mono">{{ secret }}</code>
            </div>

            <div>
              <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-2">
                Entrez le code à 6 chiffres généré par votre application
              </label>
              <input
                  v-model="verificationCode"
                  type="text"
                  maxlength="6"
                  pattern="[0-9]*"
                  placeholder="123456"
                  class="w-full px-4 py-3 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] text-center text-2xl tracking-widest focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]"
              >
            </div>

            <div v-if="error" class="bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] p-3 rounded-md text-sm">
              {{ error }}
            </div>

            <button
                @click="verifyAndEnable"
                :disabled="loading || verificationCode.length !== 6"
                class="btn btn-primary w-full"
            >
              {{ loading ? 'Vérification...' : 'Vérifier et activer' }}
            </button>
          </div>
        </div>

        <!-- Étape 3: Codes de secours -->
        <div v-if="step === 3" class="backup-codes-container space-y-6">
          <div class="bg-[var(--color-warning)]/10 border border-[var(--color-warning)]/30 rounded-lg p-4">
            <div class="flex items-start gap-3">
              <svg class="w-6 h-6 text-[var(--color-warning)] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              <div>
                <h3 class="text-[var(--color-warning)] font-semibold mb-1">Important : Sauvegardez ces codes !</h3>
                <p class="text-sm text-[var(--color-ink-soft)]">
                  Ces codes de secours vous permettront de vous connecter si vous perdez l'accès à votre application d'authentification.
                  Chaque code ne peut être utilisé qu'une seule fois.
                </p>
              </div>
            </div>
          </div>

          <div class="bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-lg p-4">
            <h4 class="text-[var(--color-ink)] font-semibold mb-3">Vos codes de secours</h4>
            <div class="grid grid-cols-2 gap-2 font-mono text-sm">
              <div
                  v-for="(code, index) in backupCodes"
                  :key="index"
                  class="bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded px-3 py-2 text-[var(--color-night)] text-center"
              >
                {{ code }}
              </div>
            </div>
          </div>

          <div class="flex gap-3">
            <button
                @click="copyBackupCodes"
                class="flex-1 px-6 py-3 bg-[var(--color-paper-sunk)] hover:bg-[var(--color-rule)] border border-[var(--color-rule)] text-[var(--color-ink)] font-bold rounded-lg transition-all"
            >
              📋 Copier les codes
            </button>
            <button
                @click="finish"
                class="btn btn-primary flex-1"
            >
              Terminer
            </button>
          </div>
        </div>

        <!-- Loading initial -->
        <div v-if="step === 1" class="flex flex-col items-center justify-center py-12">
          <div class="flex gap-2 mb-4">
            <div class="w-3 h-3 bg-[var(--color-ink)] rounded-full animate-bounce"></div>
            <div class="w-3 h-3 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
            <div class="w-3 h-3 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
          </div>
          <p class="text-[var(--color-ink-soft)]">Configuration en cours...</p>
        </div>
      </main>
    </div>
  </div>
</template>