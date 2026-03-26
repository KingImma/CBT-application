<template>
  <div class="mt-10 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_12px_40px_rgba(15,23,42,0.06)] sm:p-10">
    <div class="flex items-start gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-emerald-100 bg-emerald-50 text-emerald-600 shadow-sm">
        <BadgeCheck class="h-6 w-6" />
      </div>

      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800">Review Details</h1>
        <p class="mt-2 text-base text-slate-500">Make sure everything looks correct before proceeding.</p>
      </div>
    </div>

    <div class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold tracking-tight text-slate-800">School Info</h2>
          <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm">
            <Pencil class="h-4 w-4" />
          </button>
        </div>

        <div class="mt-6 space-y-4">
          <div
            v-for="item in schoolInfo"
            :key="item.label"
            class="flex items-center justify-between gap-6 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0"
          >
            <span class="text-base text-slate-500">{{ item.label }}</span>
            <span class="text-base font-semibold text-slate-800">{{ item.value }}</span>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold tracking-tight text-slate-800">Administrator</h2>
          <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm">
            <Pencil class="h-4 w-4" />
          </button>
        </div>

        <div class="mt-6 space-y-4">
          <div
            v-for="item in administratorInfo"
            :key="item.label"
            class="flex items-center justify-between gap-6 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0"
          >
            <span class="text-base text-slate-500">{{ item.label }}</span>
            <span class="text-base font-semibold text-slate-800">{{ item.value }}</span>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold tracking-tight text-slate-800">Curriculum</h2>
          <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm">
            <Pencil class="h-4 w-4" />
          </button>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div
            v-for="item in curriculumInfo"
            :key="item.label"
            class="flex items-center justify-between gap-6 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0"
          >
            <span class="text-base text-slate-500">{{ item.label }}</span>
            <span class="text-base font-semibold text-slate-800">{{ item.value }}</span>
          </div>
        </div>
      </section>
    </div>

    <div class="mt-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <button
        type="button"
        class="cursor-pointer inline-flex items-center gap-3 self-start rounded-xl border border-slate-200 bg-white px-7 py-3 text-base font-medium text-slate-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
        @click="emit('back')"
      >
        <ArrowLeft class="h-5 w-5" />
        Back
      </button>

      <button
        type="button"
        class="cursor-pointer inline-flex items-center gap-3 self-start rounded-xl border border-emerald-200 bg-emerald-50 px-7 py-3 text-base font-semibold text-emerald-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-emerald-100 hover:shadow-md sm:self-auto"
        @click="emit('continue')"
      >
        Looks Good, Continue
        <ArrowRight class="h-5 w-5" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ArrowLeft, ArrowRight, BadgeCheck, Pencil } from 'lucide-vue-next'

const props = defineProps<{
  formData: {
    schoolName: string
    schoolType: string
    website: string
    address: string
    state: string
    city: string
    fullName: string
    email: string
    phone: string
    role: string
    referralSource: string
    grades: string[]
    subjectCount: string
    termSystem: string
    gradingScale: string
  }
}>()

const emit = defineEmits<{
  back: []
  continue: []
}>()

const display = (value: string) => value?.trim() || '-'
const displayArray = (value: string[]) => (value.length ? value.join(', ') : 'None selected')
const displayLocation = computed(() => {
  const pieces = [props.formData.address, props.formData.city, props.formData.state].filter(Boolean)
  return pieces.length ? pieces.join(', ') : '-'
})

const schoolInfo = computed(() => [
  { label: 'Name', value: display(props.formData.schoolName) },
  { label: 'Type', value: display(props.formData.schoolType) },
  { label: 'Location', value: displayLocation.value },
  { label: 'Website', value: display(props.formData.website) },
])

const administratorInfo = computed(() => [
  { label: 'Name', value: display(props.formData.fullName) },
  { label: 'Role', value: display(props.formData.role) },
  { label: 'Email', value: display(props.formData.email) },
  { label: 'Phone', value: display(props.formData.phone) },
])

const curriculumInfo = computed(() => [
  { label: 'Type', value: display(props.formData.termSystem) },
  { label: 'Grades', value: displayArray(props.formData.grades) },
  { label: 'Subjects', value: display(props.formData.subjectCount) },
  { label: 'Grading Scale', value: display(props.formData.gradingScale) },
])
</script>
