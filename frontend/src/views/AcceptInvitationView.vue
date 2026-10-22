<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ArrowRight, Boxes, Check, LockKeyhole, Mail, UserRound } from "@lucide/vue";
import FeedbackMessage from "../components/FeedbackMessage.vue";
import { useFeedback } from "../composables/useFeedback";
import { request } from "../services/api";
import type { User } from "../types/api";

const route = useRoute();
const router = useRouter();
const { busy, error, setError, fieldError } = useFeedback();
const queryToken = route.query.token;
const token = ref(typeof queryToken === "string" ? queryToken : "");
const name = ref("");
const password = ref("");
const accepted = ref(false);

onMounted(() => {
  if (typeof queryToken === "string") {
    void router.replace({
      path: route.path,
      query: { ...route.query, token: undefined },
      hash: route.hash,
    });
  }
});

async function submit(): Promise<void> {
  busy.value = true;
  error.value = "";

  try {
    await request<{ data: User }>("/invitations/accept", {
      method: "POST",
      body: JSON.stringify({
        token: token.value,
        name: name.value,
        password: password.value,
      }),
    });

    accepted.value = true;
  } catch (cause) {
    setError(cause);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="auth-panel">
    <div class="auth-form-wrap">
      <div class="brand" style="margin-bottom: 28px">
        <span class="brand-mark"><Boxes :size="20" /></span>
        <span class="brand-name">armazém<span>.</span></span>
      </div>
      <span class="auth-icon"><Check v-if="accepted" :size="20" /><Mail v-else :size="20" /></span>
      <p class="auth-eyebrow">CONVITE DE OPERADOR</p>
      <h2>{{ accepted ? "Convite aceito" : "Entre na organização" }}</h2>
      <p class="auth-subtitle">
        {{ accepted ? "Sua conta de operador foi criada. Agora você já pode entrar." : "Informe o token recebido por e-mail e crie sua senha de acesso." }}
      </p>

      <FeedbackMessage :message="error" />
      <FeedbackMessage
        v-if="accepted"
        message="Convite aceito com sucesso. Faça login com seu e-mail e a senha criada."
        success
      />

      <form v-if="!accepted" class="auth-form" @submit.prevent="submit">
        <label class="field-label">
          Token do convite
          <div class="field-wrap">
            <Mail :size="17" />
            <input
              v-model="token"
              required
              minlength="64"
              maxlength="64"
              pattern="[a-f0-9]{64}"
              autocomplete="off"
              placeholder="Cole o token de 64 caracteres"
            />
          </div>
          <small v-if="fieldError('token')" class="field-error">{{ fieldError("token") }}</small>
        </label>
        <label class="field-label">
          Nome completo
          <div class="field-wrap">
            <UserRound :size="17" />
            <input v-model="name" required maxlength="255" autocomplete="name" placeholder="Seu nome" />
          </div>
          <small v-if="fieldError('name')" class="field-error">{{ fieldError("name") }}</small>
        </label>
        <label class="field-label">
          Senha
          <div class="field-wrap">
            <LockKeyhole :size="17" />
            <input
              v-model="password"
              required
              type="password"
              minlength="8"
              maxlength="255"
              autocomplete="new-password"
              placeholder="Mínimo de 8 caracteres"
            />
          </div>
          <small v-if="fieldError('password')" class="field-error">{{ fieldError("password") }}</small>
        </label>
        <button class="button button-dark button-full auth-submit" type="submit" :disabled="busy">
          {{ busy ? "Validando convite…" : "Aceitar convite" }}
          <ArrowRight v-if="!busy" :size="17" />
        </button>
      </form>

      <button
        v-else
        class="button button-dark button-full auth-submit"
        type="button"
        @click="router.replace({ name: 'login' })"
      >
        Ir para o login <ArrowRight :size="17" />
      </button>
      <p v-if="!accepted" class="auth-switch">
        Já tem uma conta? <RouterLink to="/login">Faça login</RouterLink>
      </p>
    </div>
    <div class="auth-panel-footer">
      <Boxes :size="15" /> ARMAZÉM <span>·</span> GESTÃO DE ESTOQUE
    </div>
  </section>
</template>
