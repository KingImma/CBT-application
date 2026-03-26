<template>
  <div class="mt-10 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_12px_40px_rgba(15,23,42,0.06)] sm:p-10">
    <div class="flex items-start gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-100 bg-amber-50 text-amber-600 shadow-sm">
        <UserRound class="h-6 w-6" />
      </div>

      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800">Administrator Details</h1>
        <p class="mt-2 text-base text-slate-500">Who will be managing the EduBoard account?</p>
      </div>
    </div>

    <form class="mt-10 space-y-7" @submit.prevent="emit('continue')">
      <div class="space-y-3">
        <label for="full-name" class="block text-base font-semibold text-slate-700">Full Name</label>
        <input
          id="full-name"
          v-model="formData.fullName"
          type="text"
          placeholder="Ndubuisi Paul"
          class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <label for="email-address" class="block text-base font-semibold text-slate-700">Email Address</label>
          <input
            id="email-address"
            v-model="formData.email"
            type="email"
            placeholder="paul@school.edu"
            class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
          />
        </div>

        <div class="space-y-3">
          <label for="phone-number" class="block text-base font-semibold text-slate-700">Phone Number</label>
          <input
            id="phone-number"
            v-model="formData.phone"
            type="tel"
            placeholder="+234 000 000 0000"
            class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <label for="role-title" class="block text-base font-semibold text-slate-700">Role / Title</label>
          <div class="relative">
            <select
              id="role-title"
              v-model="formData.role"
              :class="formData.role ? 'text-slate-800' : 'text-slate-400'"
              class="cursor-pointer h-14 w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 pr-12 text-base outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
            >
              <option value="" disabled>Select a role</option>
              <option value="Principal">Principal</option>
              <option value="Vice Principal">Vice Principal</option>
              <option value="Administrator">Administrator</option>
              <option value="IT Manager">IT Manager</option>
            </select>
            <ChevronDown class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-700" />
          </div>
        </div>

        <div class="space-y-3">
          <label for="referral-source" class="block text-base font-semibold text-slate-700">How did you hear about us?</label>
          <div class="relative">
            <select
              id="referral-source"
              v-model="formData.referralSource"
              :class="formData.referralSource ? 'text-slate-800' : 'text-slate-400'"
              class="h-14 w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 pr-12 text-base outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
            >
              <option value="" disabled>Select an option...</option>
              <option value="Google Search">Google Search</option>
              <option value="Social Media">Social Media</option>
              <option value="Referral">Referral</option>
              <option value="Conference or Event">Conference or Event</option>
            </select>
            <ChevronDown class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-700" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <label for="password" class="block text-base font-semibold text-slate-700">Password</label>
          <div class="relative">
            <input
              id="password"
              v-model="formData.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Create a strong password"
              class="h-14 w-full rounded-xl border border-slate-200 px-4 pr-12 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
            />
            <button
              type="button"
              class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
              @click="showPassword = !showPassword"
            >
              <component :is="showPassword ? EyeOff : Eye" class="h-5 w-5" />
            </button>
          </div>
        </div>

        <div class="space-y-3">
          <label for="confirm-password" class="block text-base font-semibold text-slate-700">Confirm Password</label>
          <div class="relative">
            <input
              id="confirm-password"
              v-model="formData.confirmPassword"
              :type="showConfirmPassword ? 'text' : 'password'"
              placeholder="Re-enter your password"
              class="h-14 w-full rounded-xl border border-slate-200 px-4 pr-12 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
            />
            <button
              type="button"
              class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
              @click="showConfirmPassword = !showConfirmPassword"
            >
              <component :is="showConfirmPassword ? EyeOff : Eye" class="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <button
          type="button"
          class="cursor-pointer inline-flex items-center gap-3 self-start rounded-xl border border-slate-200 bg-white px-7 py-3 text-base font-medium text-slate-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
          @click="emit('back')"
        >
          <ArrowLeft class="h-5 w-5" />
          Back
        </button>

        <button
          type="submit"
          class="cursor-pointer inline-flex items-center gap-3 self-start rounded-xl bg-slate-900 px-7 py-3 text-base font-semibold text-white shadow-lg shadow-slate-900/10 transition duration-300 hover:-translate-y-0.5 hover:bg-slate-800 hover:shadow-xl sm:self-auto"
        >
          Continue
          <ArrowRight class="h-5 w-5" />
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ArrowLeft, ArrowRight, ChevronDown, Eye, EyeOff, UserRound } from 'lucide-vue-next'

defineProps<{
  formData: {
    fullName: string
    email: string
    phone: string
    role: string
    referralSource: string
    password: string
    confirmPassword: string
  }
}>()

const emit = defineEmits<{
  back: []
  continue: []
}>()

const showPassword = ref(false)
const showConfirmPassword = ref(false)
</script>
