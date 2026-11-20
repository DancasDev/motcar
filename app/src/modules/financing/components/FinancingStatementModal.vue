<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDisplay } from 'vuetify'
import { useBranchesStore } from '@/modules/branches/stores/branches.store'
import { usePermission } from '@/composables/usePermission'
import { fetchFinancingStatement, generateFinancingReceivables } from '../api/financing.api'
import type { FinancingStatement, FinancingStatementInstallment } from '../types'
import { money } from '@/utils/format'
import { resolveI18n } from '@/utils/metadataValidation'
import AppModal from '@/components/common/AppModal.vue'

interface Props {
  modelValue: boolean
  financingId: number | string | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'quotes-generated'): void
  (e: 'message', text: string, color?: 'success' | 'error' | 'warning' | 'info'): void
}>()

const { smAndDown } = useDisplay()
const branchesStore = useBranchesStore()
const { can } = usePermission()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val: boolean) => emit('update:modelValue', val)
})

const isLoading = ref(false)
const isGenerating = ref(false)
const isRefreshing = ref(false)
const recalculateDialogOpen = ref(false)
const statement = ref<FinancingStatement | null>(null)
const errorMsg = ref<string | null>(null)

const feedbackSnackbar = ref({
  show: false,
  text: '',
  color: 'success'
})

const headers = [
  { title: 'Descripción', key: 'description', align: 'start' as const },
  { title: 'Vencimiento', key: 'due_date', align: 'center' as const, width: '130px' },
  { title: 'Monto', key: 'total_amount', align: 'end' as const, width: '110px' },
  { title: 'Abonado', key: 'paid_amount', align: 'end' as const, width: '110px' },
  { title: 'Saldo', key: 'pending_amount', align: 'end' as const, width: '110px' },
  { title: 'Estado', key: 'status', align: 'center' as const, width: '110px' }
]

const activeScope = computed(() =>
  branchesStore.activeBranchId ? String(branchesStore.activeBranchId) : undefined
)

const canCalculateQuotes = computed(() => {
  if (!statement.value?.financing) return false
  if (String(statement.value.financing.status) !== '1') return false
  return can('fnc_financings', 'update', activeScope.value)
})

const hasGeneratedInstallments = computed(() => {
  return (statement.value?.installments?.length ?? 0) > 0
})

const frequencyLabel = computed(() => {
  const f = statement.value?.financing
  if (!f) return 'N/A'
  return resolveI18n(f.frequency_name) || f.frequency_code || 'N/A'
})

const idPrefix = computed(() => {
  const map: Record<string, string> = { '0': 'V', '1': 'E', '2': 'J', '3': 'P' }
  const t = String(statement.value?.client?.identification_type ?? '0')
  return map[t] || 'V'
})

const nextPendingDueDate = computed<string>(() => {
  const list = statement.value?.installments || []
  if (list.length === 0) return 'N/A'
  const pending = list.find((i) => Number(i.pending_amount) > 0)
  return pending ? pending.due_date : 'Al día'
})

function extractErrorMessage(err: unknown, fallback: string): string {
  const errorObj = err as { response?: { data?: { message?: string; error?: string; messages?: { error?: string } | Record<string, string> } } }
  const data = errorObj?.response?.data
  if (data?.messages && typeof data.messages === 'object') {
    if ('error' in data.messages && data.messages.error) return String(data.messages.error)
    const first = Object.values(data.messages).find(Boolean)
    if (first) return String(first)
  }
  return data?.message || data?.error || (err instanceof Error ? err.message : fallback)
}

async function loadStatement(silent = false): Promise<void> {
  if (!isOpen.value || !props.financingId || !branchesStore.activeBranchId) {
    statement.value = null
    return
  }

  if (!silent) {
    isLoading.value = true
  }
  errorMsg.value = null
  try {
    const res = await fetchFinancingStatement(branchesStore.activeBranchId, props.financingId)
    statement.value = res.data
  } catch (err: unknown) {
    console.error('Error al cargar estado de cuenta del financiamiento:', err)
    errorMsg.value = extractErrorMessage(err, 'No se pudo cargar el estado de cuenta del financiamiento.')
    statement.value = null
  } finally {
    if (!silent) {
      isLoading.value = false
    }
  }
}

async function handleRefreshStatement(): Promise<void> {
  isRefreshing.value = true
  try {
    await loadStatement(true)
  } finally {
    isRefreshing.value = false
  }
}

async function executeGenerateQuotes(): Promise<void> {
  const fId = props.financingId || statement.value?.financing?.id
  const bId = branchesStore.activeBranchId

  if (!fId || !bId || !canCalculateQuotes.value) return

  isGenerating.value = true
  try {
    const res = await generateFinancingReceivables(bId, fId)
    const count = res.data?.generated ?? 0
    const preserved = res.data?.preserved_installments ?? 0
    const msg =
      preserved > 0
        ? `Cronograma recalculado: ${count} cobros generados y ${preserved} con pagos preservados.`
        : `Cronograma calculado exitosamente (${count} ${count === 1 ? 'cobro generado' : 'cobros generados'}).`

    recalculateDialogOpen.value = false
    feedbackSnackbar.value = { show: true, text: msg, color: 'success' }
    emit('quotes-generated')
    emit('message', msg, 'success')
    await loadStatement(true)
  } catch (err: unknown) {
    const msg = extractErrorMessage(err, 'Error al calcular las cuotas del financiamiento.')
    feedbackSnackbar.value = { show: true, text: msg, color: 'error' }
    emit('message', msg, 'error')
  } finally {
    isGenerating.value = false
  }
}

function handleCalculateClick(): void {
  if (hasGeneratedInstallments.value) {
    recalculateDialogOpen.value = true
    return
  }
  executeGenerateQuotes()
}

watch(
  () => [isOpen.value, props.financingId],
  ([open, fId]) => {
    if (open && fId) {
      loadStatement()
    } else {
      statement.value = null
      errorMsg.value = null
      recalculateDialogOpen.value = false
    }
  }
)

function getInstallmentStatusColor(item: FinancingStatementInstallment): string {
  if (item.pending_amount <= 0) return 'success'
  if (item.is_overdue) return 'error'
  if (item.paid_amount > 0) return 'warning'
  return 'default'
}

function getInstallmentStatusText(item: FinancingStatementInstallment): string {
  if (item.pending_amount <= 0) return 'Pagada'
  if (item.is_overdue) return 'En Mora'
  if (item.paid_amount > 0) return 'Parcial'
  return 'Pendiente'
}
</script>

<template>
  <v-dialog
    v-model="isOpen"
    :fullscreen="smAndDown"
    max-width="960"
    scrollable
    persistent
  >
    <v-card class="d-flex flex-column" data-testid="financing-statement-modal">
      <v-toolbar color="primary" density="compact" class="px-2">
        <v-toolbar-title class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
          <v-icon size="20">mdi-file-document-outline</v-icon>
          <span>Estado de cuenta</span>
        </v-toolbar-title>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" size="small" @click="isOpen = false" />
      </v-toolbar>

      <v-card-text class="pa-4 flex-grow-1 overflow-y-auto">
        <div v-if="isLoading" class="text-center py-10" data-testid="financing-statement-loading">
          <v-progress-circular indeterminate color="primary" size="48" class="mb-3" />
          <div class="text-body-2 text-medium-emphasis">Cargando estado de cuenta...</div>
        </div>

        <v-alert
          v-else-if="errorMsg"
          type="error"
          variant="tonal"
          class="mb-4"
          data-testid="financing-statement-error"
        >
          {{ errorMsg }}
        </v-alert>

        <div v-else-if="statement">
          <!-- Cabecera de Cliente y Financiamiento -->
          <v-card elevation="0" rounded="lg" class="border bg-grey-lighten-5 pa-3 mb-4">
            <v-row density="compact" align="center">
              <v-col cols="12" md="7">
                <div class="d-flex align-center flex-wrap ga-2 mb-1">
                  <span class="text-h6 font-weight-bold" data-testid="financing-statement-client-name">
                    {{ statement.client.name }}
                  </span>
                  <v-chip
                    v-if="statement.client.identification_value"
                    size="small"
                    variant="outlined"
                    color="primary"
                    class="font-weight-medium"
                  >
                    {{ idPrefix }}-{{ statement.client.identification_value }}
                  </v-chip>
                </div>
                <div class="text-body-2 text-medium-emphasis d-flex flex-wrap align-center ga-2">
                  <span>Código: <strong class="font-mono">{{ statement.financing.code }}</strong></span>
                  <span>&bull;</span>
                  <span>Frecuencia: <strong>{{ frequencyLabel }}</strong></span>
                  <span>&bull;</span>
                  <span>Modalidad: <strong>{{ statement.financing.contract_type_name || 'Sin contrato / Efectivo' }}</strong></span>
                  <span>&bull;</span>
                  <span>Próx. Vencimiento: <strong>{{ nextPendingDueDate }}</strong></span>
                </div>
              </v-col>

              <v-col cols="12" md="5" class="text-md-end mt-2 mt-md-0">
                <v-chip
                  v-if="statement.financing.is_awarded"
                  color="purple"
                  variant="flat"
                  size="small"
                  class="font-weight-bold mr-2 mb-1"
                  prepend-icon="mdi-certificate-outline"
                >
                  Beneficio Entregado
                </v-chip>
                <v-chip
                  v-if="String(statement.financing.status) !== '1'"
                  :color="String(statement.financing.status) === '3' ? 'primary' : String(statement.financing.status) === '2' ? 'grey' : 'info'"
                  variant="flat"
                  size="small"
                  class="font-weight-bold mb-1"
                >
                  {{
                    String(statement.financing.status) === '3'
                      ? 'Culminado'
                      : String(statement.financing.status) === '2'
                        ? 'Anulado'
                        : 'En Espera'
                  }}
                </v-chip>
                <v-chip
                  v-else-if="statement.summary.overdue_amount > 0"
                  color="error"
                  variant="flat"
                  size="small"
                  class="font-weight-bold mb-1"
                  prepend-icon="mdi-alert-circle"
                >
                  En Mora ({{ statement.summary.installments_overdue }} cuotas)
                </v-chip>
                <v-chip
                  v-else
                  color="success"
                  variant="flat"
                  size="small"
                  class="font-weight-bold mb-1"
                  prepend-icon="mdi-check-circle"
                >
                  Al Día
                </v-chip>
              </v-col>
            </v-row>
          </v-card>

          <!-- Tarjetas de Métricas Resumen -->
          <v-row density="compact" class="mb-4">
            <v-col cols="6" sm="3">
              <v-card elevation="0" rounded="lg" class="border pa-3 text-center bg-white h-100">
                <div class="d-flex align-center justify-center ga-1 mb-1">
                  <v-icon icon="mdi-cash-multiple" size="18" color="medium-emphasis" />
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase" style="letter-spacing: 0.04em;">
                    Comprometido
                  </span>
                </div>
                <div class="text-subtitle-1 font-weight-bold text-high-emphasis" data-testid="financing-statement-total-committed">
                  {{ money(statement.summary.total_committed) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-0-5">
                  Ini: {{ money(statement.financing.initial_amount) }} &bull; Fin: {{ money(statement.financing.financed_amount) }}
                </div>
              </v-card>
            </v-col>
            <v-col cols="6" sm="3">
              <v-card elevation="0" rounded="lg" class="border pa-3 text-center bg-white h-100">
                <div class="d-flex align-center justify-center ga-1 mb-1">
                  <v-icon icon="mdi-cash-check" size="18" color="medium-emphasis" />
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase" style="letter-spacing: 0.04em;">
                    Cancelado
                  </span>
                </div>
                <div class="text-subtitle-1 font-weight-bold text-high-emphasis" data-testid="financing-statement-total-collected">
                  {{ money(statement.summary.total_collected) }}
                </div>
              </v-card>
            </v-col>
            <v-col cols="6" sm="3">
              <v-card elevation="0" rounded="lg" class="border pa-3 text-center bg-white h-100">
                <div class="d-flex align-center justify-center ga-1 mb-1">
                  <v-icon icon="mdi-cash-clock" size="18" color="medium-emphasis" />
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase" style="letter-spacing: 0.04em;">
                    Saldo Pendiente
                  </span>
                </div>
                <div class="text-subtitle-1 font-weight-bold text-high-emphasis" data-testid="financing-statement-total-pending">
                  {{ money(statement.summary.total_pending) }}
                </div>
              </v-card>
            </v-col>
            <v-col cols="6" sm="3">
              <v-card elevation="0" rounded="lg" class="border pa-3 text-center bg-white h-100">
                <div class="d-flex align-center justify-center ga-1 mb-1">
                  <v-icon icon="mdi-alert-circle-outline" size="18" color="medium-emphasis" />
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase" style="letter-spacing: 0.04em;">
                    En Mora
                  </span>
                </div>
                <div class="text-subtitle-1 font-weight-bold text-high-emphasis" data-testid="financing-statement-overdue-amount">
                  {{ money(statement.summary.overdue_amount) }}
                </div>
              </v-card>
            </v-col>
          </v-row>

          <!-- Barra de Título del Cronograma y Acción Calcular / Recalcular -->
          <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
            <div class="d-flex align-center ga-2">
              <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                Cronograma de Cuotas y Pagos
              </span>
              <v-chip
                v-if="hasGeneratedInstallments"
                size="x-small"
                variant="tonal"
                color="primary"
                class="font-weight-medium"
              >
                {{ statement.installments.length }} {{ statement.installments.length === 1 ? 'cobro' : 'cobros' }}
              </v-chip>
            </div>

            <div class="d-flex align-center ga-2">
              <v-btn
                v-if="canCalculateQuotes"
                :variant="hasGeneratedInstallments ? 'tonal' : 'flat'"
                color="primary"
                size="small"
                class="text-none font-weight-bold"
                :prepend-icon="hasGeneratedInstallments ? 'mdi-calculator-variant-outline' : 'mdi-calculator'"
                :loading="isGenerating"
                data-testid="financing-statement-calculate-btn"
                @click="handleCalculateClick"
              >
                {{ hasGeneratedInstallments ? 'Recalcular cuotas' : 'Calcular cuotas' }}
              </v-btn>

              <v-tooltip text="Recargar cronograma" location="top" color="#1E293B">
                <template #activator="{ props: tooltipProps }">
                  <v-btn
                    v-bind="tooltipProps"
                    icon="mdi-refresh"
                    variant="flat"
                    color="white"
                    size="small"
                    class="border rounded-circle flex-shrink-0"
                    :loading="isRefreshing"
                    data-testid="financing-statement-refresh-btn"
                    @click="handleRefreshStatement"
                  />
                </template>
              </v-tooltip>
            </div>
          </div>

          <!-- Tabla de Cuotas e Inicial -->
          <v-data-table
            :headers="headers"
            :items="statement.installments"
            :mobile="smAndDown"
            density="compact"
            class="border rounded-lg bg-white"
            data-testid="financing-statement-table"
            :items-per-page="50"
            hover
          >
            <template #no-data>
              <div class="text-center py-8">
                <v-icon icon="mdi-calculator-variant-outline" size="44" color="medium-emphasis" class="mb-2" />
                <div class="text-body-2 font-weight-medium text-high-emphasis mb-1">
                  No hay cuotas generadas
                </div>
                <div class="text-caption text-medium-emphasis mb-3">
                  {{
                    String(statement.financing.status) === '1'
                      ? 'Este financiamiento aún no tiene inicial ni cuotas generadas en su cronograma.'
                      : 'Activa el financiamiento para calcular su inicial y cronograma de cuotas.'
                  }}
                </div>
                <v-btn
                  v-if="canCalculateQuotes"
                  color="primary"
                  variant="flat"
                  size="small"
                  class="text-none font-weight-bold"
                  prepend-icon="mdi-calculator"
                  :loading="isGenerating"
                  data-testid="financing-statement-empty-calculate-btn"
                  @click="executeGenerateQuotes"
                >
                  Calcular cuotas
                </v-btn>
              </div>
            </template>

            <template #[`item.description`]="{ item }">
              <div class="py-1">
                <div class="d-flex align-center flex-wrap ga-2">
                  <span class="font-weight-medium text-body-2">{{ item.description }}</span>
                  <v-chip
                    v-if="item.is_initial"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    Inicial
                  </v-chip>
                </div>
                <div v-if="item.payments && item.payments.length > 0" class="text-caption text-medium-emphasis">
                  <span v-for="(p, idx) in item.payments" :key="idx" class="d-inline-block mr-2">
                    &bull; {{ p.receipt_code }} ({{ money(p.applied_amount) }})
                  </span>
                </div>
              </div>
            </template>

            <template #[`item.due_date`]="{ item }">
              <span class="text-caption font-mono font-weight-medium text-high-emphasis">
                {{ item.due_date }}
              </span>
            </template>

            <template #[`item.total_amount`]="{ item }">
              <span class="text-body-2 font-weight-medium">{{ money(item.total_amount) }}</span>
            </template>

            <template #[`item.paid_amount`]="{ item }">
              <span class="text-body-2 text-success font-weight-medium">{{ money(item.paid_amount) }}</span>
            </template>

            <template #[`item.pending_amount`]="{ item }">
              <span
                class="text-body-2 font-weight-bold"
                :class="item.pending_amount > 0 ? 'text-primary' : 'text-medium-emphasis'"
              >
                {{ money(item.pending_amount) }}
              </span>
            </template>

            <template #[`item.status`]="{ item }">
              <v-chip
                size="x-small"
                :color="getInstallmentStatusColor(item)"
                variant="tonal"
                class="font-weight-medium"
              >
                {{ getInstallmentStatusText(item) }}
              </v-chip>
            </template>

            <template #bottom></template>
          </v-data-table>
        </div>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-3 bg-grey-lighten-5 justify-start">
        <v-btn variant="plain" @click="isOpen = false">Cerrar</v-btn>
      </v-card-actions>

      <v-snackbar
        v-model="feedbackSnackbar.show"
        :color="feedbackSnackbar.color"
        timeout="3500"
        location="top"
      >
        <div class="d-flex align-center ga-2">
          <v-icon :icon="feedbackSnackbar.color === 'success' ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'" />
          <span>{{ feedbackSnackbar.text }}</span>
        </div>
      </v-snackbar>
    </v-card>

    <!-- Confirmación de Recálculo -->
    <AppModal
      v-model="recalculateDialogOpen"
      title="Recalcular cuotas"
      subtitle="Actualización del cronograma de pagos pendiente"
      max-width="460"
      :loading="isGenerating"
      submit-text="Recalcular"
      submit-icon="mdi-calculator-variant-outline"
      @submit="executeGenerateQuotes"
    >
      <div class="py-1">
        <p class="text-body-2 text-high-emphasis mb-3">
          ¿Deseas recalcular las cuotas pendientes de <strong>{{ statement?.financing?.code }}</strong> según las condiciones y fechas actuales?
        </p>
        <v-alert
          type="info"
          variant="tonal"
          density="compact"
          rounded="lg"
          text="Las cuotas ya pagadas o con abonos parciales se conservarán intactas; solo se regenerarán las obligaciones pendientes sin abonos."
        />
      </div>
    </AppModal>
  </v-dialog>
</template>
