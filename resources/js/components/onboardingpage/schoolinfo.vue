<template>
  <div class="mt-10 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_12px_40px_rgba(15,23,42,0.06)] sm:p-10">
    <div class="flex items-start gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 shadow-sm">
        <School2 class="h-6 w-6" />
      </div>

      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800">School Information</h1>
        <p class="mt-2 text-base text-slate-500">Let's start with the basics about your institution.</p>
      </div>
    </div>

    <form class="mt-10 space-y-7" @submit.prevent="emit('continue')">
      <div class="space-y-3">
        <label for="school-name" class="block text-base font-semibold text-slate-700">School Name</label>
        <input
          id="school-name"
          v-model="formData.schoolName"
          type="text"
          placeholder="e.g. Enugu Secondary School"
          class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <label for="school-type" class="block text-base font-semibold text-slate-700">School Type</label>
          <div class="relative">
            <select
              id="school-type"
              v-model="formData.schoolType"
              :class="formData.schoolType ? 'text-slate-800' : 'text-slate-400'"
              class="cursor-pointer h-14 w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 pr-12 text-base outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
            >
              <option value="" disabled>Select School Type</option>
              <option value="Secondary School">Secondary School</option>
              <option value="Primary School">Primary School</option>
              <option value="Mixed">Mixed</option>
            </select>
            <ChevronDown class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-700" />
          </div>
        </div>

        <div class="space-y-3">
          <label for="website" class="block text-base font-semibold text-slate-700">Website (Optional)</label>
          <input
            id="website"
            v-model="formData.website"
            type="url"
            placeholder="https://www.example.edu"
            class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
          />
        </div>
      </div>

      <div class="space-y-3">
        <label for="address" class="block text-base font-semibold text-slate-700">Address</label>
        <input
          id="address"
          v-model="formData.address"
          type="text"
          placeholder="123 Education Lane"
          class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <label for="state" class="block text-base font-semibold text-slate-700">State / Province</label>
          <input
            id="state"
            v-model="formData.state"
            type="text"
            class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
          />
        </div>
        <div class="space-y-3">
          <label for="city" class="block text-base font-semibold text-slate-700">City</label>
          <input
            id="city"
            v-model="formData.city"
            type="text"
            class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
          />
        </div>
      </div>

      <div class="flex justify-end pt-6">
        <button
          type="submit"
          class="cursor-pointer inline-flex items-center gap-3 rounded-xl bg-slate-900 px-7 py-3 text-base font-semibold text-white shadow-lg shadow-slate-900/10 transition duration-300 hover:-translate-y-0.5 hover:bg-slate-800 hover:shadow-xl"
        >
          Continue
          <ArrowRight class="h-5 w-5" />
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ArrowRight, ChevronDown, School2 } from 'lucide-vue-next'

defineProps<{
  formData: {
    schoolName: string
    schoolType: string
    website: string
    address: string
    state: string
    city: string
  }
}>()

const emit = defineEmits<{
  continue: []
}>()
</script>
