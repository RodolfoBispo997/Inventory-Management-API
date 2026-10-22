<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Boxes, Check, Eye, EyeOff, LockKeyhole, Mail, Package, ShieldCheck, Sparkles, UserRound } from '@lucide/vue'
import FeedbackMessage from '../components/FeedbackMessage.vue'
import { useFeedback } from '../composables/useFeedback'
import { request, saveToken } from '../services/api'
import type { User } from '../types/api'

const router = useRouter(); const { busy, error, setError, fieldError } = useFeedback()
const registerMode = ref(false); const showPassword = ref(false); const name = ref(''); const email = ref(''); const password = ref('')
const heading = computed(() => registerMode.value ? 'Crie sua conta' : 'Bem-vindo de volta')
async function submit() {
  busy.value = true; error.value = ''
  try {
    const endpoint = registerMode.value ? '/auth/register' : '/auth/login'
    const body = registerMode.value ? { name: name.value, email: email.value, password: password.value } : { email: email.value, password: password.value }
    const response = await request<{ data: { user: User; token: string } }>(endpoint, { method: 'POST', body: JSON.stringify(body) })
    saveToken(response.data.token); await router.replace({ name: 'dashboard' })
  } catch (cause) { setError(cause) } finally { busy.value = false }
}
</script>

<template>
  <div class="auth-layout"><section class="auth-showcase"><div class="showcase-inner"><div class="brand auth-brand"><span class="brand-mark"><Boxes :size="20" /></span><span class="brand-name">armazém<span>.</span></span></div><div class="showcase-content"><span class="showcase-badge"><Sparkles :size="13" /> GESTÃO QUE FLUI</span><h1>Estoque em<br />ordem. <span>De verdade.</span></h1><p>Saiba o que tem, o que falta e o que não pode faltar. Tudo em um lugar só, sem complicação.</p><div class="showcase-dashboard"><div class="showcase-window-top"><span class="window-dots"><i></i><i></i><i></i></span><span>PAINEL DE ESTOQUE</span><span class="window-status">ATIVO</span></div><div class="showcase-stat-row"><div class="showcase-stat"><span>PRODUTOS</span><strong>Em tempo real</strong><small><Package :size="13" /> Direto da sua API</small></div><div class="showcase-stat showcase-stat-green"><span>SEU ESTOQUE</span><strong>Sempre claro</strong><small><Check :size="13" /> Atualizado a cada movimento</small></div></div><div class="showcase-bottom"><span class="showcase-bar"><i></i></span><span>Movimentação sob controle</span><ArrowRight :size="14" /></div></div></div><div class="showcase-footer"><ShieldCheck :size="16" /><span>Seu espaço de trabalho. Seus dados.</span><span>01 / 01</span></div></div></section>
    <section class="auth-panel"><div class="auth-form-wrap"><div class="form-topline"><span class="live-dot"></span> AMBIENTE SEGURO</div><span class="auth-icon"><LockKeyhole :size="20" /></span><p class="auth-eyebrow">{{ registerMode ? 'PRIMEIRO ACESSO' : 'ACESSE SUA CONTA' }}</p><h2>{{ heading }}</h2><p class="auth-subtitle">{{ registerMode ? 'Vamos colocar o seu estoque em ordem.' : 'Entre com suas credenciais para continuar.' }}</p><FeedbackMessage :message="error" />
      <form class="auth-form" @submit.prevent="submit"><label v-if="registerMode" class="field-label">Nome completo<div class="field-wrap"><UserRound :size="17" /><input v-model="name" required autocomplete="name" placeholder="Como podemos te chamar?" /></div><small v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</small></label><label class="field-label">E-mail<div class="field-wrap"><Mail :size="17" /><input v-model="email" required type="email" autocomplete="email" placeholder="voce@empresa.com.br" /></div><small v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</small></label><label class="field-label">Senha<div class="field-wrap"><LockKeyhole :size="17" /><input v-model="password" required :type="showPassword ? 'text' : 'password'" autocomplete="current-password" :placeholder="registerMode ? 'Mínimo de 8 caracteres' : 'Digite sua senha'" /><button type="button" class="password-toggle" aria-label="Mostrar ou ocultar senha" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" :size="16" /><Eye v-else :size="16" /></button></div><small v-if="fieldError('password')" class="field-error">{{ fieldError('password') }}</small></label><button class="button button-dark button-full auth-submit" type="submit" :disabled="busy">{{ busy ? 'Aguarde…' : registerMode ? 'Criar minha conta' : 'Entrar no sistema' }}<ArrowRight v-if="!busy" :size="17" /></button></form><p class="auth-switch">{{ registerMode ? 'Já tem uma conta?' : 'Primeiro acesso?' }} <button type="button" @click="registerMode = !registerMode; error = ''">{{ registerMode ? 'Faça login' : 'Crie sua conta' }}</button></p><div class="auth-footnote"><span><ShieldCheck :size="15" /></span> Sua sessão é protegida com autenticação segura.</div></div><div class="auth-panel-footer"><Boxes :size="15" /> ARMAZÉM <span>·</span> GESTÃO DE ESTOQUE <span>·</span> 2025</div></section></div>
</template>
