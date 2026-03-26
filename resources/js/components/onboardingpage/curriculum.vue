<template>
  <div class="mt-10 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_12px_40px_rgba(15,23,42,0.06)] sm:p-10">
    <div class="flex items-start gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-emerald-100 bg-emerald-50 text-emerald-600 shadow-sm">
        <BookOpen class="h-6 w-6" />
      </div>

      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800">Curriculum Setup</h1>
        <p class="mt-2 text-base text-slate-500">Configure the classes and subject structure your school wants to start with.</p>
      </div>
    </div>

    <form class="mt-10 space-y-8" @submit.prevent="emit('continue')">
      <div class="space-y-4">
        <label class="block text-base font-semibold text-slate-700">What classes do you want to start with?</label>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-3">
          <label
            v-for="grade in grades"
            :key="grade"
            class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-700 transition duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-sm"
          >
            <input
              type="checkbox"
              :checked="formData.grades.includes(grade)"
              class="h-4 w-4 rounded border-slate-300 text-slate-800 focus:ring-slate-300"
              @change="toggleGrade(grade)"
            />
            <span>{{ grade }}</span>
          </label>
        </div>

        <p class="text-sm leading-relaxed text-slate-400">
          Selecting classes or grades here is optional. School admins will still be able to create classes later from their dashboards.
        </p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <label for="subjects-count" class="block text-base font-semibold text-slate-700">Number of Subjects</label>
          <input
            id="subjects-count"
            v-model="formData.subjectCount"
            type="number"
            min="1"
            placeholder="e.g. 12"
            class="h-14 w-full rounded-xl border border-slate-200 px-4 text-base text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:border-slate-300 focus:shadow-sm"
          />
          <p class="text-sm leading-relaxed text-slate-400">
            This value is not permanent. Teachers will still be able to add or create subjects from their dashboards later.
          </p>
        </div>

        <div class="space-y-3">
          <label for="term-system" class="block text-base font-semibold text-slate-700">Academic Term System</label>
          <div class="relative">
            <select
              id="term-system"
              v-model="formData.termSystem"
              :class="formData.termSystem ? 'text-slate-800' : 'text-slate-400'"
              class="h-14 w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 pr-12 text-base outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
            >
              <option value="" disabled>Select a term structure</option>
              <option value="3 Term System">3 Term System</option>
              <option value="6 Month System">6 Month System</option>
              <option value="Quarter System">Quarter System</option>
            </select>
            <ChevronDown class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-700" />
          </div>
        </div>
      </div>

      <div class="space-y-3">
        <label for="grading-scale" class="block text-base font-semibold text-slate-700">Preferred Grading Scale</label>
        <div class="relative">
          <select
            id="grading-scale"
            v-model="formData.gradingScale"
            :class="formData.gradingScale ? 'text-slate-800' : 'text-slate-400'"
            class="h-14 w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 pr-12 text-base outline-none transition duration-300 focus:border-slate-300 focus:shadow-sm"
          >
            <option value="" disabled>Select a grading scale</option>
            <option value="A - F">A - F</option>
            <option value="Percentage">Percentage</option>
          </select>
          <ChevronDown class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-700" />
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
import { ArrowLeft, ArrowRight, BookOpen, ChevronDown } from 'lucide-vue-next'

const props = defineProps<{
  formData: {
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

const grades = ['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']

const toggleGrade = (grade: string) => {
  const index = props.formData.grades.indexOf(grade)
  if (index >= 0) {
    props.formData.grades.splice(index, 1)
    return
  }
  props.formData.grades.push(grade)
}
</script>
