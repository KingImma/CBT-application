<template>
  <section class="lp-reveal-section lp-section">
    <div class="mx-auto grid grid-cols-1 gap-16 px-20 py-14 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
      <div>
        <div class="lp-icon-chip flex h-12 w-12 items-center justify-center rounded-xl border border-rose-100 bg-rose-50 text-amber-600 shadow-sm">
          <CalendarDays class="h-6 w-6" />
        </div>

        <h2 class="mt-8 text-4xl font-black tracking-tight text-slate-800">
          Effortless Attendance Tracking
        </h2>

        <p class="mt-7 max-w-2xl text-lg leading-relaxed text-slate-600">
          Say goodbye to paper registers. Teachers can mark attendance in seconds, and parents get
          instantly notified of absences.
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

      <div class="lp-card rounded-3xl border border-rose-100 bg-rose-50/70 p-8 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <h3 class="text-lg font-bold tracking-tight text-slate-800">
            Class 10-A Attendance
          </h3>
          <span class="rounded-lg bg-white px-4 py-2 text-base font-semibold text-amber-500 shadow-sm">
            Today
          </span>
        </div>

        <div class="mt-8 space-y-4">
          <article
            v-for="student in students"
            :key="student.name"
            class="lp-card flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-white px-5 py-4 shadow-sm"
          >
            <div class="flex items-center gap-4">
              <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-amber-500">
                <UserRound class="h-5 w-5" />
              </div>
              <p class="text-base font-semibold tracking-tight text-slate-800">
                {{ student.name }}
              </p>
            </div>

            <span
              :class="student.badgeClass"
              class="rounded-lg px-4 py-2 text-base font-semibold"
            >
              {{ student.status }}
            </span>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { CalendarDays, Check, UserRound } from 'lucide-vue-next'

const checklist = [
  'One-click daily roll call',
  'Automated parent notifications',
  'Term-by-term attendance reports',
]

const students = [
  {
    name: 'Uche Nwoso',
    status: 'Present',
    badgeClass: 'bg-emerald-50 text-emerald-600',
  },
  {
    name: 'Amaka Theresa',
    status: 'Absent',
    badgeClass: 'bg-rose-50 text-rose-600',
  },
  {
    name: 'Samuel Okonkwo',
    status: 'Late',
    badgeClass: 'bg-amber-50 text-amber-600',
  },
]
</script>
