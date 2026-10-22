import { ref } from 'vue'
import { ApiError } from '../services/api'

export function useFeedback() {
  const busy = ref(false)
  const error = ref('')
  const validation = ref<Record<string, string[]>>({})
  function setError(value: unknown) {
    if (value instanceof ApiError) { error.value = value.message; validation.value = value.errors ?? {} }
    else error.value = 'Ocorreu um erro inesperado. Tente novamente.'
  }
  function fieldError(field: string): string { return validation.value[field]?.[0] ?? '' }
  return { busy, error, validation, setError, fieldError }
}
