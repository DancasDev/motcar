<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDisplay } from 'vuetify'
import { useBranchesStore } from '@/modules/branches/stores/branches.store'
import { useFinancingStore } from '../stores/financing.store'
import { useDataQuery } from '@/composables/useDataQuery'
import { usePermission } from '@/composables/usePermission'
import {
  fetchFinancings,
  createFinancing,
  updateFinancing,
  deleteFinancing,
  fetchPaymentFrequencies,
  fetchContractTypes
} from '../api/financing.api'
import { fetchPersons, fetchPersonById } from '@/modules/catalogs/api/catalogs.api'
import { fetchMetadataSchemaRender } from '@/modules/system/api/system.api'
import type { PersonItem } from '@/modules/catalogs/types'
import type {
  FinancingItem,
  PaymentFrequencyItem,
  ContractTypeItem,
  CreateFinancingPayload,
  UpdateFinancingPayload
} from '../types'
import type { DqbFilterTuple } from '@/types/query'
import { money } from '@/utils/format'
import { resolveI18n } from '@/utils/metadataValidation'
import AppChipFilter from '@/components/common/AppChipFilter.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppPaginatedSelect, { type PaginatedQueryParams } from '@/components/common/AppPaginatedSelect.vue'
import AppMetadataRenderer, { type MetadataSchemaPayload } from '@/components/common/AppMetadataRenderer.vue'
import FinancingStatementModal from '../components/FinancingStatementModal.vue'
import FinancingContractModal from '../components/FinancingContractModal.vue'

const { smAndDown } = useDisplay()
const branchesStore = useBranchesStore()
const financingStore = useFinancingStore()
const { can } = usePermission()

const activeScope = computed(() =>
  branchesStore.activeBranchId ? String(branchesStore.activeBranchId) : undefined
)

const canCreate = computed(() => can('fnc_financings', 'create', activeScope.value))
const canUpdate = computed(() => can('fnc_financings', 'update', activeScope.value))
const canDelete = computed(() => can('fnc_financings', 'delete', activeScope.value))
const canReadContract = computed(() => can('fnc_contract_documents', 'read', activeScope.value))

// ---------------------------------------------------------------------
// 1. Consulta Paginada y Tabla
// ---------------------------------------------------------------------
const financingsQuery = useDataQuery<FinancingItem>(
  async (params) => {
    if (!branchesStore.activeBranchId) {
      return { data: [], count: 0, limit: 10, in_trash: false }
    }
    return fetchFinancings(branchesStore.activeBranchId, params)
  },
  {
    initialItemsPerPage: 10,
    searchFields: ['code', 'client_first_name', 'client_last_name', 'client_identification_value'],
    defaultOrder: { id: 'DESC' }
  }
)

const headers = [
  { title: 'Plan / Código', key: 'code', align: 'start' as const, sortable: true },
  { title: 'Cliente', key: 'client', align: 'start' as const, sortable: false },
  { title: 'Condiciones', key: 'total_amount', align: 'end' as const, sortable: true, width: '210px' },
  { title: 'Estado', key: 'status', align: 'center' as const, sortable: true, width: '120px' },
  { title: 'Morosidad', key: 'is_overdue', align: 'center' as const, sortable: true, width: '110px' },
  { title: 'Adjudicación', key: 'awarded_at', align: 'center' as const, sortable: true, width: '135px' },
  { title: 'Acciones', key: 'actions', align: 'end' as const, sortable: false, width: '80px' }
]

const statusOptions = [
  { text: 'En Espera', title: 'En Espera', value: '0' },
  { text: 'Activo', title: 'Activo', value: '1' },
  { text: 'Anulado', title: 'Anulado', value: '2' },
  { text: 'Culminado', title: 'Culminado', value: '3' }
]

const statusMap: Record<string, { label: string; color: string }> = {
  '0': { label: 'En Espera', color: 'info' },
  '1': { label: 'Activo', color: 'success' },
  '2': { label: 'Anulado', color: 'grey' },
  '3': { label: 'Culminado', color: 'primary' }
}

function getStatusBadge(status: string | number): { label: string; color: string } {
  return statusMap[String(status ?? '0')] || { label: 'En Espera', color: 'info' }
}

function handleStatusFilterChange(dqbFilter: DqbFilterTuple[] | null): void {
  financingsQuery.staticFilters.value = dqbFilter && dqbFilter.length > 0 ? dqbFilter : []
  financingsQuery.page.value = 1
  financingsQuery.refresh()
}

// ---------------------------------------------------------------------
// 2. Opciones de Autocompletado Paginado (Personas, Frecuencias, Contratos)
// ---------------------------------------------------------------------
async function fetchPersonsOptions(params: PaginatedQueryParams): Promise<{ items: any[]; total: number }> {
  try {
    const dqbFilters: any[] = []
    if (params.search && params.search.trim()) {
      const term = params.search.trim()
      dqbFilters.push({
        orGroupSearch: [
          ['first_name', term, 'LIKE', 'AND', 'BOTH'],
          ['last_name', term, 'LIKE', 'OR', 'BOTH'],
          ['identification_value', term, 'LIKE', 'OR', 'BOTH']
        ] as DqbFilterTuple[]
      })
    }
    const res = await fetchPersons({
      page: params.page,
      itemsPerPage: params.pageSize,
      filters: dqbFilters.length > 0 ? JSON.stringify(dqbFilters) : undefined
    })
    const items = (res.data || []).map((p: PersonItem) => ({
      ...p,
      id: Number(p.id),
      name: `${p.first_name} ${p.last_name} (${p.identification_value ? `CI: ${p.identification_value}` : `ID: #${p.id}`})`
    }))
    return { items, total: res.count ?? items.length }
  } catch (err) {
    console.error('Error al consultar personas:', err)
    return { items: [], total: 0 }
  }
}

async function fetchPersonSingle(id: any): Promise<any> {
  if (!id) return null
  try {
    const p = await fetchPersonById(id)
    if (!p) return null
    return {
      ...p,
      id: Number(p.id),
      name: `${p.first_name} ${p.last_name} (${p.identification_value ? `CI: ${p.identification_value}` : `ID: #${p.id}`})`
    }
  } catch {
    return null
  }
}

async function fetchFrequenciesOptions(params: PaginatedQueryParams): Promise<{ items: any[]; total: number }> {
  try {
    const dqbFilters: any[] = []
    if (params.search && params.search.trim()) {
      dqbFilters.push(['name', params.search.trim(), 'LIKE', 'AND', 'BOTH'])
    }
    const res = await fetchPaymentFrequencies({
      page: params.page,
      itemsPerPage: params.pageSize,
      filters: dqbFilters.length > 0 ? JSON.stringify(dqbFilters) : undefined
    })
    const items = (res.data || []).map((f: PaymentFrequencyItem) => ({
      ...f,
      id: Number(f.id),
      name: resolveI18n(f.name) || f.code
    }))
    return { items, total: res.count ?? items.length }
  } catch (err) {
    console.error('Error al consultar frecuencias:', err)
    return { items: [], total: 0 }
  }
}

async function fetchFrequencySingle(id: any): Promise<any> {
  if (!id) return null
  const found = financingStore.frequencies.find((f) => String(f.id) === String(id))
  if (found) {
    return { ...found, id: Number(found.id), name: resolveI18n(found.name) || found.code }
  }
  try {
    const res = await fetchPaymentFrequencies({ filters: JSON.stringify([['id', id]]) })
    const item = res.data?.[0]
    if (!item) return null
    return { ...item, id: Number(item.id), name: resolveI18n(item.name) || item.code }
  } catch {
    return null
  }
}

async function fetchContractTypesOptions(params: PaginatedQueryParams): Promise<{ items: any[]; total: number }> {
  try {
    const dqbFilters: any[] = [['scope', '1']]
    if (params.search && params.search.trim()) {
      dqbFilters.push(['name', params.search.trim(), 'LIKE', 'AND', 'BOTH'])
    }
    const res = await fetchContractTypes({
      page: params.page,
      itemsPerPage: params.pageSize,
      filters: JSON.stringify(dqbFilters)
    })
    const items = (res.data || []).map((c: ContractTypeItem) => ({
      ...c,
      id: Number(c.id),
      name: c.name || c.code
    }))
    return { items, total: res.count ?? items.length }
  } catch (err) {
    console.error('Error al consultar tipos de contrato:', err)
    return { items: [], total: 0 }
  }
}

async function fetchContractTypeSingle(id: any): Promise<any> {
  if (!id) return null
  const found = financingStore.contractTypes.find((c) => String(c.id) === String(id))
  if (found) {
    return { ...found, id: Number(found.id), name: found.name || found.code }
  }
  try {
    const res = await fetchContractTypes({ filters: JSON.stringify([['id', id]]) })
    const item = res.data?.[0]
    if (!item) return null
    return { ...item, id: Number(item.id), name: item.name || item.code }
  } catch {
    return null
  }
}

// ---------------------------------------------------------------------
// 3. Crear / Editar Financiamiento
// ---------------------------------------------------------------------
const formDialogOpen = ref(false)
const isSubmittingForm = ref(false)
const editingFinancingId = ref<string | number | null>(null)
const isInstallmentManuallyEdited = ref(false)

interface FinancingFormState {
  client_id: number | null
  contract_type_id: number | null
  frequency_id: number | null
  code: string
  total_amount: number | null
  initial_amount: number | null
  installment_count: number | null
  installment_amount: number | null
  initial_due_date: string
  frequency_base_date: string
  status: string
}

const formData = ref<FinancingFormState>({
  client_id: null,
  contract_type_id: null,
  frequency_id: null,
  code: '',
  total_amount: null,
  initial_amount: null,
  installment_count: null,
  installment_amount: null,
  initial_due_date: '',
  frequency_base_date: '',
  status: '1'
})

const round2 = (n: number): number => Math.round((n + Number.EPSILON) * 100) / 100

function recalculateInstallmentPreview(): void {
  if (isInstallmentManuallyEdited.value) return
  const total = Number(formData.value.total_amount || 0)
  const initial = Number(formData.value.initial_amount || 0)
  const count = Number(formData.value.installment_count || 0)
  const balance = round2(total - initial)

  if (total > 0 && initial > 0 && balance > 0 && count > 0) {
    formData.value.installment_amount = round2(balance / count)
  } else {
    formData.value.installment_amount = null
  }
}

function onFinancialInputChange(): void {
  isInstallmentManuallyEdited.value = false
  recalculateInstallmentPreview()
}

function onManualInstallmentChange(): void {
  isInstallmentManuallyEdited.value = true
}

const financialPreview = computed(() => {
  const total = Number(formData.value.total_amount || 0)
  const initial = Number(formData.value.initial_amount || 0)
  const count = Math.trunc(Number(formData.value.installment_count || 0))
  const inst = Number(formData.value.installment_amount || 0)
  const financedBalance = round2(total - initial)
  const lastInstallment = count > 0 ? round2(financedBalance - (count - 1) * inst) : 0

  let error: string | null = null
  if (total > 0 && initial <= 0) {
    error = 'La cuota inicial es obligatoria y debe ser mayor a cero.'
  } else if (total > 0 && initial >= total) {
    error = 'La cuota inicial debe ser estrictamente menor al monto total.'
  } else if (count > 0 && inst > 0 && lastInstallment <= 0) {
    error = 'El monto por cuota excede el saldo financiado disponible (última cuota <= 0).'
  }

  return {
    isValidInputs: total > 0 && initial > 0 && financedBalance > 0 && count > 0 && inst > 0,
    financedBalance,
    count,
    inst,
    lastInstallment,
    hasRemainder: Math.round(Math.abs(lastInstallment - inst) * 100) >= 1,
    error
  }
})

function openCreateDialog(): void {
  editingFinancingId.value = null
  isInstallmentManuallyEdited.value = false
  formData.value = {
    client_id: null,
    contract_type_id: null,
    frequency_id: null,
    code: '',
    total_amount: null,
    initial_amount: null,
    installment_count: null,
    installment_amount: null,
    initial_due_date: '',
    frequency_base_date: new Date().toISOString().split('T')[0],
    status: '1'
  }
  formDialogOpen.value = true
}

function openEditDialog(item: FinancingItem): void {
  editingFinancingId.value = item.id
  isInstallmentManuallyEdited.value = true
  formData.value = {
    client_id: Number(item.client_id),
    contract_type_id: item.contract_type_id ? Number(item.contract_type_id) : null,
    frequency_id: item.frequency_id ? Number(item.frequency_id) : null,
    code: item.code || '',
    total_amount: Number(item.total_amount),
    initial_amount: Number(item.initial_amount),
    installment_count: Number(item.installment_count),
    installment_amount: Number(item.installment_amount),
    initial_due_date: item.initial_due_date ? item.initial_due_date.substring(0, 10) : '',
    frequency_base_date: item.frequency_base_date ? item.frequency_base_date.substring(0, 10) : '',
    status: String(item.status ?? '1')
  }
  formDialogOpen.value = true
}

function validateForm(): string | null {
  if (!branchesStore.activeBranchId) return 'Selecciona una sucursal activa válida.'
  if (!editingFinancingId.value && !formData.value.client_id) return 'Selecciona el cliente.'
  if (!formData.value.frequency_id) return 'Selecciona la frecuencia de pago.'
  if (!formData.value.total_amount || Number(formData.value.total_amount) <= 0) {
    return 'Ingresa un monto total mayor a cero.'
  }
  if (!formData.value.initial_amount || Number(formData.value.initial_amount) <= 0) {
    return 'La cuota inicial debe ser mayor a cero.'
  }
  if (!formData.value.installment_count || Number(formData.value.installment_count) <= 0) {
    return 'Ingresa la cantidad de cuotas.'
  }
  if (!formData.value.installment_amount || Number(formData.value.installment_amount) <= 0) {
    return 'El monto por cuota debe ser mayor a cero.'
  }
  if (financialPreview.value.error) return financialPreview.value.error
  if (!formData.value.frequency_base_date) return 'Ingresa la fecha base / inicio de cobros.'
  if (
    formData.value.initial_due_date &&
    formData.value.initial_due_date > formData.value.frequency_base_date
  ) {
    return 'La fecha de la cuota inicial no puede ser posterior a la fecha base de cobros.'
  }
  return null
}

async function handleSaveFinancing(): Promise<void> {
  const validationError = validateForm()
  if (validationError) {
    showMessage(validationError, 'error')
    return
  }
  if (!branchesStore.activeBranchId) return

  isSubmittingForm.value = true
  try {
    const trimmedCode = formData.value.code.trim()

    if (editingFinancingId.value) {
      const payload: UpdateFinancingPayload = {
        contract_type_id: formData.value.contract_type_id ? Number(formData.value.contract_type_id) : null,
        frequency_id: Number(formData.value.frequency_id),
        total_amount: Number(formData.value.total_amount),
        initial_amount: Number(formData.value.initial_amount),
        installment_count: Number(formData.value.installment_count),
        installment_amount: Number(formData.value.installment_amount),
        initial_due_date: formData.value.initial_due_date || null,
        frequency_base_date: formData.value.frequency_base_date,
        status: formData.value.status
      }
      if (trimmedCode) {
        payload.code = trimmedCode
      }
      await updateFinancing(branchesStore.activeBranchId, editingFinancingId.value, payload)
      showMessage('Financiamiento actualizado exitosamente.')
    } else {
      const payload: CreateFinancingPayload = {
        client_id: Number(formData.value.client_id),
        contract_type_id: formData.value.contract_type_id ? Number(formData.value.contract_type_id) : null,
        frequency_id: Number(formData.value.frequency_id),
        total_amount: Number(formData.value.total_amount),
        initial_amount: Number(formData.value.initial_amount),
        installment_count: Number(formData.value.installment_count),
        installment_amount: Number(formData.value.installment_amount),
        initial_due_date: formData.value.initial_due_date || null,
        frequency_base_date: formData.value.frequency_base_date,
        status: formData.value.status
      }
      if (trimmedCode) {
        payload.code = trimmedCode
      }
      await createFinancing(branchesStore.activeBranchId, payload)
      showMessage('Financiamiento registrado exitosamente.')
    }

    formDialogOpen.value = false
    financingsQuery.refresh()
  } catch (err: unknown) {
    showMessage(extractErrorMessage(err, 'Error al guardar el financiamiento.'), 'error')
  } finally {
    isSubmittingForm.value = false
  }
}

// ---------------------------------------------------------------------
// 4. Adjudicación y Metadatos Dinámicos (benefit_metadata + awarded_at)
// ---------------------------------------------------------------------
const benefitDialogOpen = ref(false)
const selectedBenefitFinancing = ref<FinancingItem | null>(null)
const isSubmittingBenefit = ref(false)
const isLoadingSchema = ref(false)
const benefitSchema = ref<MetadataSchemaPayload | null>(null)
const benefitFormData = ref<Record<string, any>>({})
const benefitAwardedAt = ref<string>('')

const isBenefitUpdate = computed(() => {
  if (selectedBenefitFinancing.value?.awarded_at) return true
  const b = selectedBenefitFinancing.value?.benefit_metadata
  if (!b) return false
  if (typeof b === 'object') return Object.keys(b).length > 0
  if (typeof b === 'string') {
    try {
      return Object.keys(JSON.parse(b)).length > 0
    } catch {
      return false
    }
  }
  return false
})

async function loadBenefitSchema(item: FinancingItem): Promise<void> {
  let schemaId = item.contract_type_metadata_schema_id
  if (!schemaId && item.contract_type_id) {
    if (financingStore.contractTypes.length === 0) {
      await financingStore.loadCatalogs()
    }
    const found = financingStore.contractTypes.find(
      (c) => String(c.id) === String(item.contract_type_id)
    )
    schemaId = found?.metadata_schema_id
  }

  if (!schemaId) {
    benefitSchema.value = null
    return
  }

  isLoadingSchema.value = true
  try {
    const res = await fetchMetadataSchemaRender(schemaId)
    benefitSchema.value = (res.data || null) as MetadataSchemaPayload | null
  } catch (err) {
    console.error('Error al cargar esquema de adjudicación:', err)
    benefitSchema.value = null
  } finally {
    isLoadingSchema.value = false
  }
}

function openBenefitDialog(item: FinancingItem): void {
  selectedBenefitFinancing.value = item
  benefitAwardedAt.value = item.awarded_at ? item.awarded_at.substring(0, 10) : ''

  let metadata: Record<string, any> = {}
  if (typeof item.benefit_metadata === 'string') {
    try {
      metadata = JSON.parse(item.benefit_metadata)
    } catch {
      metadata = {}
    }
  } else if (item.benefit_metadata) {
    metadata = { ...(item.benefit_metadata as Record<string, any>) }
  }

  if (item.contract_type_id) {
    if (!metadata.reference_value && item.total_amount) {
      metadata.reference_value = Number(item.total_amount)
    }
    if (!metadata.year) {
      metadata.year = new Date().getFullYear()
    }
  }

  benefitFormData.value = metadata
  benefitSchema.value = null
  benefitDialogOpen.value = true
  loadBenefitSchema(item)
}

async function handleSaveBenefit(): Promise<void> {
  if (!selectedBenefitFinancing.value || !branchesStore.activeBranchId) return

  isSubmittingBenefit.value = true
  try {
    const payload: UpdateFinancingPayload = {}
    if (benefitAwardedAt.value && benefitAwardedAt.value.trim()) {
      payload.awarded_at = benefitAwardedAt.value.trim()
    } else if (selectedBenefitFinancing.value.awarded_at) {
      payload.awarded_at = null
    }

    if (selectedBenefitFinancing.value.contract_type_id && benefitSchema.value) {
      payload.benefit_metadata = { ...benefitFormData.value }
    }

    await updateFinancing(
      branchesStore.activeBranchId,
      selectedBenefitFinancing.value.id,
      payload
    )

    if (payload.awarded_at) {
      showMessage('Adjudicación registrada exitosamente.')
    } else if (payload.awarded_at === null) {
      showMessage('Adjudicación revocada exitosamente.')
    } else {
      showMessage('Datos del beneficio guardados exitosamente.')
    }

    benefitDialogOpen.value = false
    selectedBenefitFinancing.value = null
    financingsQuery.refresh()
  } catch (err: unknown) {
    showMessage(extractErrorMessage(err, 'Error al guardar los datos de adjudicación.'), 'error')
  } finally {
    isSubmittingBenefit.value = false
  }
}

// ---------------------------------------------------------------------
// 5. Eliminación
// ---------------------------------------------------------------------
const deleteDialogOpen = ref(false)
const financingToDelete = ref<FinancingItem | null>(null)
const isDeletingFinancing = ref(false)

function promptDeleteFinancing(item: FinancingItem): void {
  financingToDelete.value = item
  deleteDialogOpen.value = true
}

async function handleConfirmDelete(): Promise<void> {
  if (!financingToDelete.value || !branchesStore.activeBranchId) return
  isDeletingFinancing.value = true
  try {
    await deleteFinancing(branchesStore.activeBranchId, financingToDelete.value.id)
    showMessage('Financiamiento eliminado exitosamente.')
    deleteDialogOpen.value = false
    financingToDelete.value = null
    financingsQuery.refresh()
  } catch (err: unknown) {
    showMessage(extractErrorMessage(err, 'Error al eliminar el financiamiento.'), 'error')
  } finally {
    isDeletingFinancing.value = false
  }
}

// ---------------------------------------------------------------------
// 6. Modales de Estado de Cuenta y Contrato
// ---------------------------------------------------------------------
const statementDialogOpen = ref(false)
const selectedStatementFinancing = ref<FinancingItem | null>(null)

function openStatementModal(item: FinancingItem): void {
  selectedStatementFinancing.value = item
  statementDialogOpen.value = true
}

const contractModalOpen = ref(false)
const selectedContractFinancing = ref<FinancingItem | null>(null)

function openContractModal(item: FinancingItem): void {
  selectedContractFinancing.value = item
  contractModalOpen.value = true
}

// ---------------------------------------------------------------------
// 7. Feedback y Reactividad
// ---------------------------------------------------------------------
const snackbar = ref<{
  show: boolean
  text: string
  color: 'success' | 'error' | 'warning' | 'info'
}>({
  show: false,
  text: '',
  color: 'success'
})

function showMessage(text: string, color: 'success' | 'error' | 'warning' | 'info' = 'success'): void {
  if (snackbar.value.show) {
    snackbar.value.show = false
    setTimeout(() => {
      snackbar.value = { show: true, text, color }
    }, 80)
  } else {
    snackbar.value = { show: true, text, color }
  }
}

function extractErrorMessage(err: unknown, fallback: string): string {
  const errorObj = err as {
    response?: {
      data?: {
        message?: string
        error?: string
        messages?: { error?: string } | Record<string, string> | string
      }
    }
  }
  const data = errorObj?.response?.data
  if (typeof data?.messages === 'string') return data.messages
  if (data?.messages && typeof data.messages === 'object') {
    if ('error' in data.messages && data.messages.error) return String(data.messages.error)
    const first = Object.values(data.messages).find(Boolean)
    if (first) return String(first)
  }
  return data?.message || data?.error || (err instanceof Error ? err.message : fallback)
}

watch(
  () => branchesStore.activeBranchId,
  (newBranchId) => {
    if (newBranchId) {
      financingsQuery.refresh()
    }
  }
)
</script>

<template>
  <div class="financings-module">
    <v-alert
      v-if="!branchesStore.activeBranchId"
      type="warning"
      variant="tonal"
      title="Sucursal no seleccionada"
      text="Para gestionar financiamientos debes seleccionar una Sucursal Activa en la barra superior."
      class="mb-4"
      data-testid="no-branch-alert"
    />

    <!-- 1. Encabezado -->
    <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-9">
      <div>
        <h1 class="text-h5 font-weight-bold text-high-emphasis mb-0 leading-tight">
          Financiamientos
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0 mt-0">
          Gestión de financiamientos individuales, cronogramas de pago y formalización de contratos
        </p>
      </div>

      <v-btn
        v-if="canCreate"
        color="primary"
        prepend-icon="mdi-plus"
        class="text-none font-weight-bold px-4"
        data-testid="btn-create-financing"
        @click="openCreateDialog"
      >
        Registrar
      </v-btn>
    </div>

    <!-- 2. Búsqueda -->
    <v-row density="compact" class="mb-3">
      <v-col cols="12" md="7">
        <v-text-field
          v-model="financingsQuery.search.value"
          prepend-inner-icon="mdi-magnify"
          placeholder="Buscar por código, nombre o cédula del cliente..."
          variant="solo"
          flat
          bg-color="white"
          density="comfortable"
          rounded="lg"
          hide-details
          clearable
          class="border search-field elevation-0"
          data-testid="financing-search-input"
        />
      </v-col>
    </v-row>

    <!-- 3. Filtros de Estado y Recarga -->
    <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-3">
      <div class="d-flex align-center flex-wrap ga-2">
        <AppChipFilter
          field="status"
          :items="statusOptions"
          all-text="Todos"
          color="primary"
          density="comfortable"
          @change="handleStatusFilterChange"
        />
      </div>

      <v-tooltip text="Recargar la tabla" location="top" color="#1E293B">
        <template #activator="{ props: tooltipProps }">
          <v-btn
            v-bind="tooltipProps"
            icon="mdi-refresh"
            variant="flat"
            color="white"
            size="small"
            class="border rounded-circle flex-shrink-0"
            :loading="financingsQuery.isLoading.value"
            data-testid="btn-refresh-financings"
            @click="financingsQuery.refresh()"
          />
        </template>
      </v-tooltip>
    </div>

    <!-- 4. Tabla de Financiamientos -->
    <v-card elevation="0" rounded="lg" class="border bg-white overflow-hidden">
      <v-data-table-server
        v-model:items-per-page="financingsQuery.itemsPerPage.value"
        v-model:page="financingsQuery.page.value"
        v-model:sort-by="financingsQuery.sortBy.value"
        :headers="headers"
        :items="financingsQuery.items.value"
        :items-length="financingsQuery.totalItems.value"
        :loading="financingsQuery.isLoading.value"
        :mobile="smAndDown"
        no-data-text="No hay datos disponibles"
        loading-text="Cargando financiamientos..."
        density="comfortable"
        hover
        class="bg-white"
        data-testid="financings-table"
        @update:options="financingsQuery.onUpdateOptions"
      >
        <template #[`item.code`]="{ item }">
          <div>
            <div class="font-weight-bold text-body-2 text-high-emphasis font-mono">
              {{ item.code }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ resolveI18n(item.frequency_name) || item.frequency_code || 'N/A' }} &bull;
              {{ item.contract_type_name || 'Sin contrato / Efectivo' }}
            </div>
          </div>
        </template>

        <template #[`item.client`]="{ item }">
          <div class="d-flex align-center">
            <v-avatar color="primary" size="32" class="mr-2 text-caption text-white font-weight-bold elevation-1">
              {{ (item.client_first_name?.[0] || 'C').toUpperCase() }}
            </v-avatar>
            <div>
              <div class="font-weight-bold text-body-2 text-high-emphasis">
                {{ item.client_first_name || 'Cliente' }} {{ item.client_last_name || '' }}
              </div>
              <div v-if="item.client_identification_value" class="text-caption text-medium-emphasis">
                CI: {{ item.client_identification_value }}
              </div>
            </div>
          </div>
        </template>

        <template #[`item.total_amount`]="{ item }">
          <div class="text-end">
            <div class="font-weight-bold text-body-2 text-high-emphasis">
              {{ money(item.total_amount) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Ini: {{ money(item.initial_amount) }} &bull; {{ item.installment_count }} &times; {{ money(item.installment_amount) }}
            </div>
          </div>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip
            size="small"
            :color="getStatusBadge(item.status).color"
            variant="tonal"
            label
            class="font-weight-medium"
          >
            {{ getStatusBadge(item.status).label }}
          </v-chip>
        </template>

        <template #[`item.is_overdue`]="{ item }">
          <span v-if="String(item.status) !== '1'" class="text-caption text-medium-emphasis font-weight-medium">
            N/A
          </span>
          <v-chip
            v-else
            size="small"
            :color="item.is_overdue === '1' || item.is_overdue === 1 ? 'error' : 'success'"
            variant="tonal"
            label
            class="font-weight-medium"
          >
            {{ item.is_overdue === '1' || item.is_overdue === 1 ? 'En Mora' : 'Al Día' }}
          </v-chip>
        </template>

        <template #[`item.awarded_at`]="{ item }">
          <span v-if="item.awarded_at" class="text-caption text-success font-weight-bold d-inline-flex align-center">
            <v-icon icon="mdi-check-decagram" size="small" color="success" class="mr-1" />
            {{ item.awarded_at }}
          </span>
          <span v-else-if="String(item.status) === '0' || String(item.status) === '2'" class="text-caption text-medium-emphasis font-weight-medium">
            N/A
          </span>
          <span v-else class="text-caption text-medium-emphasis">Pendiente</span>
        </template>

        <template #[`item.actions`]="{ item }">
          <div class="d-flex align-center justify-end">
            <v-menu location="bottom end">
              <template #activator="{ props: menuProps }">
                <v-btn
                  v-bind="menuProps"
                  icon="mdi-dots-vertical"
                  variant="text"
                  size="small"
                  color="medium-emphasis"
                  data-testid="btn-financing-actions-menu"
                />
              </template>
              <v-list density="compact" rounded="md" elevation="4" class="py-1 min-w-160">
                <v-list-item
                  prepend-icon="mdi-file-document-outline"
                  title="Estado de cuenta"
                  data-testid="menu-item-financing-statement"
                  @click="openStatementModal(item)"
                />

                <v-list-item
                  v-if="item.contract_type_id && canReadContract"
                  prepend-icon="mdi-file-certificate-outline"
                  title="Contrato"
                  data-testid="menu-item-financing-contract"
                  @click="openContractModal(item)"
                />

                <v-divider v-if="canUpdate || canDelete" class="my-1" />

                <v-list-item
                  v-if="canUpdate"
                  prepend-icon="mdi-gift-outline"
                  title="Adjudicación"
                  data-testid="menu-item-financing-benefit"
                  @click="openBenefitDialog(item)"
                />

                <v-list-item
                  v-if="canUpdate"
                  prepend-icon="mdi-pencil-outline"
                  title="Editar"
                  data-testid="menu-item-edit-financing"
                  @click="openEditDialog(item)"
                />

                <v-list-item
                  v-if="canDelete"
                  prepend-icon="mdi-trash-can-outline"
                  title="Eliminar"
                  base-color="error"
                  data-testid="menu-item-delete-financing"
                  @click="promptDeleteFinancing(item)"
                />
              </v-list>
            </v-menu>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Modal: Registrar / Editar Financiamiento -->
    <AppModal
      v-model="formDialogOpen"
      :title="editingFinancingId ? 'Editar' : 'Registrar'"
      :subtitle="editingFinancingId ? 'Modifica las condiciones financieras y fechas de cobro' : 'Configura el financiamiento individual, inicial y plan de cuotas'"
      max-width="640"
      persistent
      :loading="isSubmittingForm"
      :submit-text="editingFinancingId ? 'Actualizar' : 'Registrar'"
      :submit-icon="editingFinancingId ? 'mdi-check' : 'mdi-plus'"
      form-id="form-financing"
      @submit="handleSaveFinancing"
    >
      <v-form id="form-financing" @submit.prevent="handleSaveFinancing">
        <v-row density="compact" class="mb-1">
          <v-col cols="12" sm="7">
            <AppPaginatedSelect
              v-model="formData.client_id"
              :fetch-options="fetchPersonsOptions"
              :fetch-by-id="fetchPersonSingle"
              label="Cliente *"
              placeholder="Buscar por nombre o cédula..."
              value-key="id"
              label-key="name"
              :disabled="Boolean(editingFinancingId)"
              density="comfortable"
              data-testid="select-financing-client"
            />
          </v-col>
          <v-col cols="12" sm="5">
            <AppPaginatedSelect
              v-model="formData.contract_type_id"
              :fetch-options="fetchContractTypesOptions"
              :fetch-by-id="fetchContractTypeSingle"
              label="Tipo de Contrato"
              placeholder="Opcional (solo efectivo si está vacío)"
              value-key="id"
              label-key="name"
              clearable
              density="comfortable"
              data-testid="select-financing-contract-type"
            />
          </v-col>
        </v-row>

        <v-row density="compact" class="mb-1">
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="formData.code"
              label="Código"
              placeholder="Autogenerado si se deja vacío"
              variant="outlined"
              density="comfortable"
              data-testid="input-financing-code"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="formData.status"
              :items="[
                { title: 'Activo', value: '1' },
                { title: 'En Espera', value: '0' },
                { title: 'Anulado', value: '2' },
                { title: 'Culminado', value: '3' }
              ]"
              item-title="title"
              item-value="value"
              label="Estado *"
              variant="outlined"
              density="comfortable"
              data-testid="select-financing-status"
            />
          </v-col>
        </v-row>

        <v-divider class="my-2" />
        <div class="text-subtitle-2 font-weight-bold text-medium-emphasis mb-2">
          Condiciones Financieras
        </div>

        <v-row density="compact" class="mb-1">
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="formData.total_amount"
              label="Monto Total ($) *"
              type="number"
              min="0.01"
              step="0.01"
              variant="outlined"
              density="comfortable"
              data-testid="input-financing-total-amount"
              @update:model-value="onFinancialInputChange"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="formData.initial_amount"
              label="Cuota Inicial ($) *"
              type="number"
              min="0.01"
              step="0.01"
              variant="outlined"
              density="comfortable"
              data-testid="input-financing-initial-amount"
              @update:model-value="onFinancialInputChange"
            />
          </v-col>
        </v-row>

        <v-row density="compact" class="mb-2">
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="formData.installment_count"
              label="Cantidad de Cuotas *"
              type="number"
              min="1"
              max="65535"
              variant="outlined"
              density="comfortable"
              data-testid="input-financing-installment-count"
              @update:model-value="onFinancialInputChange"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="formData.installment_amount"
              label="Monto por Cuota ($) *"
              type="number"
              min="0.01"
              step="0.01"
              variant="outlined"
              density="comfortable"
              data-testid="input-financing-installment-amount"
              @update:model-value="onManualInstallmentChange"
            />
          </v-col>
        </v-row>

        <!-- Vista previa en vivo del plan de cuotas -->
        <v-alert
          v-if="financialPreview.error"
          type="error"
          variant="tonal"
          density="compact"
          rounded="lg"
          class="mb-3"
        >
          {{ financialPreview.error }}
        </v-alert>
        <v-alert
          v-else-if="financialPreview.isValidInputs"
          type="info"
          variant="tonal"
          density="compact"
          rounded="lg"
          class="mb-3"
        >
          <div class="text-caption">
            <strong>Saldo a financiar:</strong> {{ money(financialPreview.financedBalance) }} &bull;
            <template v-if="financialPreview.hasRemainder && financialPreview.count > 1">
              {{ financialPreview.count - 1 }} cuotas de {{ money(financialPreview.inst) }} + última cuota de
              <strong>{{ money(financialPreview.lastInstallment) }}</strong>
            </template>
            <template v-else>
              {{ financialPreview.count }} {{ financialPreview.count === 1 ? 'cuota' : 'cuotas' }} de
              <strong>{{ money(financialPreview.inst) }}</strong>
            </template>
          </div>
        </v-alert>

        <v-divider class="my-2" />
        <div class="text-subtitle-2 font-weight-bold text-medium-emphasis mb-2">
          Frecuencia y Fechas de Cobro
        </div>

        <v-row density="compact">
          <v-col cols="12" sm="4">
            <AppPaginatedSelect
              v-model="formData.frequency_id"
              :fetch-options="fetchFrequenciesOptions"
              :fetch-by-id="fetchFrequencySingle"
              label="Frecuencia *"
              placeholder="Seleccionar..."
              value-key="id"
              label-key="name"
              density="comfortable"
              data-testid="select-financing-frequency"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="formData.frequency_base_date"
              label="Fecha Base / Inicio *"
              type="date"
              variant="outlined"
              density="comfortable"
              data-testid="input-financing-base-date"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="formData.initial_due_date"
              label="Fecha Cuota Inicial"
              type="date"
              variant="outlined"
              density="comfortable"
              clearable
              hint="Opcional. Si se omite, la inicial cae en la Fecha Base y la Cuota #1 en el siguiente período."
              persistent-hint
              data-testid="input-financing-initial-date"
            />
          </v-col>
        </v-row>
      </v-form>
    </AppModal>

    <!-- Modal: Adjudicación / Entrega -->
    <AppModal
      v-model="benefitDialogOpen"
      title="Adjudicación"
      :subtitle="selectedBenefitFinancing ? `Especificaciones de entrega para ${selectedBenefitFinancing.client_first_name || 'Cliente'} ${selectedBenefitFinancing.client_last_name || ''} (${selectedBenefitFinancing.code})` : 'Especificaciones de entrega'"
      max-width="580"
      persistent
      :loading="isSubmittingBenefit"
      :submit-text="isBenefitUpdate ? 'Actualizar' : 'Registrar'"
      submit-icon="mdi-check"
      form-id="form-financing-benefit"
      @submit="handleSaveBenefit"
    >
      <v-form id="form-financing-benefit" @submit.prevent="handleSaveBenefit">
        <div class="mb-4">
          <v-text-field
            v-model="benefitAwardedAt"
            label="Fecha de Adjudicación / Entrega"
            type="date"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            clearable
            hint="Opcional. Define una fecha para confirmar formalmente la entrega o desembolso"
            persistent-hint
            data-testid="input-financing-awarded-at"
          />
        </div>

        <div v-if="isLoadingSchema" class="py-4 text-center">
          <v-progress-circular indeterminate color="primary" size="28" />
          <div class="text-caption text-medium-emphasis mt-2">Cargando campos del beneficio...</div>
        </div>
        <div v-else-if="benefitSchema && Object.keys(benefitSchema.fields || {}).length > 0">
          <AppMetadataRenderer
            :schema="benefitSchema"
            v-model="benefitFormData"
            density="comfortable"
            display-mode="flat"
          />
        </div>
        <div v-else class="text-caption text-medium-emphasis py-3 text-center">
          {{
            selectedBenefitFinancing?.contract_type_id
              ? 'Este tipo de contrato no tiene campos dinámicos configurados.'
              : 'Financiamiento sin tipo de contrato (solo efectivo).'
          }}
        </div>
      </v-form>
    </AppModal>

    <!-- Modal: Confirmar Eliminación -->
    <AppModal
      v-model="deleteDialogOpen"
      title="Eliminar"
      subtitle="Esta acción eliminará el financiamiento y sus cuentas por cobrar pendientes"
      max-width="460"
      :loading="isDeletingFinancing"
      submit-text="Eliminar"
      submit-color="error"
      submit-icon="mdi-trash-can"
      @submit="handleConfirmDelete"
    >
      <div class="py-2">
        <p class="text-body-2 text-high-emphasis mb-3">
          ¿Estás seguro de que deseas eliminar el financiamiento
          <strong>{{ financingToDelete?.code }}</strong> de
          <strong>{{ financingToDelete?.client_first_name }} {{ financingToDelete?.client_last_name }}</strong>?
        </p>
        <v-alert
          type="warning"
          variant="tonal"
          density="compact"
          text="También se eliminarán todas las cuentas por cobrar asociadas. Si ya existen pagos o recibos registrados, el sistema bloqueará la eliminación."
          rounded="lg"
        />
      </div>
    </AppModal>

    <!-- Modal: Estado de Cuenta y Cálculo de Cuotas -->
    <FinancingStatementModal
      v-model="statementDialogOpen"
      :financing-id="selectedStatementFinancing?.id || null"
      @quotes-generated="financingsQuery.refresh"
      @message="showMessage"
    />

    <!-- Modal: Contrato del Financiamiento -->
    <FinancingContractModal
      v-model="contractModalOpen"
      :financing="selectedContractFinancing"
      @contract-updated="financingsQuery.refresh"
      @message="showMessage"
    />

    <!-- Snackbar de Notificaciones -->
    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="3500"
      location="bottom right"
      data-testid="financing-direct-snackbar"
    >
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>
