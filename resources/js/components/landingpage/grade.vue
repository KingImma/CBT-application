<template>
  <section class="lp-reveal-section lp-section">
    <div class="mx-auto grid grid-cols-1 gap-16 px-20 py-14 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
      <div class="lp-card rounded-3xl border border-slate-200 bg-slate-50 p-8 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <h3 class="text-lg font-bold tracking-tight text-slate-800">
            Gradebook
          </h3>
          <span class="rounded-lg bg-white px-4 py-2 text-base font-bold text-slate-700 shadow-sm">
            Term 2
          </span>
        </div>

        <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div class="grid grid-cols-3 border-b border-slate-200 px-6 py-5 text-sm font-bold text-slate-500">
            <p>Student</p>
            <p>Math</p>
            <p>Science</p>
          </div>

          <div
            v-for="student in students"
            :key="student.name"
            class="grid grid-cols-3 items-center border-b border-slate-100 px-6 py-5 text-sm text-slate-800 last:border-b-0"
          >
            <p>{{ student.name }}</p>
            <p class="font-semibold text-emerald-600">{{ student.math }}</p>
            <p :class="student.scienceClass" class="font-semibold">{{ student.science }}</p>
          </div>
        </div>
      </div>

      <div>
        <div class="lp-icon-chip flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 shadow-sm">
          <FileText class="h-6 w-6" />
        </div>

        <h2 class="mt-8 text-4xl font-black tracking-tight text-slate-800">
          Beautiful Grade Management
        </h2>

        <p class="mt-7 max-w-2xl text-lg leading-relaxed text-slate-600">
          A flexible gradebook that adapts to your grading system. Generate report cards with one
          click.
        </p>

        <ul class="mt-12 space-y-6">
          <li
            v-for="item in checklist"
            :key="item"
            class="flex items-center gap-4 text-base font-bold tracking-tight text-slate-800"
          >
            <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-emerald-500 text-emerald-500">
              <Check class="h-4 w-4" />
            </span>
            <span>{{ item }}</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { Check, FileText } from 'lucide-vue-next'

const students = [
  {
    name: 'Alex J.',
    math: 'A (92%)',
    science: 'B+ (88%)',
    scienceClass: 'text-slate-700',
  },
  {
    name: 'Maria G.',
    math: 'A+ (98%)',
    science: 'A (95%)',
    scienceClass: 'text-emerald-600',
  },
]

const checklist = [
  'Custom grading scales',
  'Automated GPA calculation',
  'One-click report card generation',
]
</script>
