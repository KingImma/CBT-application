<template>
  <section class="lp-reveal-section lp-section overflow-hidden bg-[#f6f7fb]">
    <div class="mx-auto grid grid-cols-1 gap-10 px-20 pb-20 pt-28 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700 transition duration-300 hover:border-amber-300 hover:bg-amber-100/80">
          <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-amber-600">
            <Star class="h-3.5 w-3.5 fill-current" />
          </span>
          The #1 School Management Platform
        </div>

        <h1 class="mt-6 text-4xl font-black leading-tight text-slate-900 lg:text-7xl">
          Manage your<br class="hidden sm:block" />
          school with joy.
        </h1>

        <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">
          EduBoard simplifies administration, engages students, and empowers teachers. All in one
          beautiful, easy-to-use platform.
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-4">
          <button class="lp-button cursor-pointer rounded-xl bg-slate-900 px-8 py-6 text-base font-semibold text-white shadow-lg shadow-slate-900/10 hover:bg-slate-800">
            Onboard Your School
            <ArrowRight class="ml-2 inline h-4 w-4" />
          </button>
          <button class="lp-button cursor-pointer rounded-xl border border-slate-200 bg-white px-8 py-6 text-base font-semibold text-slate-700 shadow-sm hover:border-slate-300">
            Explore Features
          </button>
        </div>

        <div class="mt-8 flex items-center gap-3 text-sm text-slate-500">
          <div class="flex -space-x-2">
            <div class="lp-icon-chip flex h-8 w-8 items-center justify-center rounded-full border border-white bg-amber-100 text-xs">
              <Building2 class="h-4 w-4" />
            </div>
            <div class="lp-icon-chip flex h-8 w-8 items-center justify-center rounded-full border border-white bg-rose-100 text-xs">
              <Landmark class="h-4 w-4" />
            </div>
            <div class="lp-icon-chip flex h-8 w-8 items-center justify-center rounded-full border border-white bg-emerald-100 text-xs">
              <UserRound class="h-4 w-4" />
            </div>
            <div class="lp-icon-chip flex h-8 w-8 items-center justify-center rounded-full border border-white bg-blue-100 text-xs">
              <GraduationCap class="h-4 w-4" />
            </div>
          </div>
          <span>Trusted by 500+ schools worldwide</span>
        </div>
      </div>

      <div class="relative mx-auto flex items-center justify-center">
        <div class="absolute -inset-6 rounded-[32px] bg-white/70 blur-xl"></div>
        <div class="lp-card relative h-102 rounded-[28px] border border-slate-200 bg-white p-6 shadow-xl">
          <div class="h-90 rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm">
                <Building2 class="h-5 w-5 text-slate-900" />
              </div>
              <div>
                <p class="text-lg font-semibold text-slate-800">University</p>
                <p class="text-sm text-slate-500">Active Dashboard</p>
              </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4">
              <div class="lp-card rounded-xl bg-white px-4 py-3 shadow-sm">
                <p class="text-xs text-slate-400">Total Students</p>
                <p class="text-sm font-semibold text-slate-800">1,284</p>
              </div>
              <div class="lp-card rounded-xl bg-white px-4 py-3 shadow-sm">
                <p class="text-xs text-slate-400">Active Teachers</p>
                <p class="text-sm font-semibold text-slate-800">96</p>
              </div>
              <div class="lp-card rounded-xl bg-white px-4 py-3 shadow-sm">
                <p class="text-xs text-slate-400">Events</p>
                <p class="text-sm font-semibold text-slate-800">12</p>
              </div>
              <div class="lp-card rounded-xl bg-white px-4 py-3 shadow-sm">
                <p class="text-xs text-slate-400">Growth</p>
                <p class="text-sm font-semibold text-slate-800">+18%</p>
              </div>
            </div>
          </div>

          <div
            class="lp-card absolute -right-10 -top-6 w-64 rotate-[var(--teacher-rotate)] rounded-2xl border border-slate-200 bg-white p-4 shadow-xl"
            :style="{ '--teacher-rotate': teacherCardRotation }"
          >
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50">
                <UserRound class="h-4 w-4 text-emerald-700" />
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-800">Ms. Sarah</p>
                <p class="text-xs text-slate-500">Math Teacher</p>
              </div>
            </div>
            <div class="mt-3 space-y-2">
              <div class="h-2 w-full rounded-full bg-slate-100"></div>
              <div class="h-2 w-4/5 rounded-full bg-slate-100"></div>
            </div>
          </div>

          <div
            class="lp-card absolute -left-15 bottom-4 w-64 rotate-[var(--teacher-rotate)] rounded-2xl border border-slate-200 bg-white p-4 shadow-xl"
            style="--teacher-rotate: 0deg"
          >
            <div class="flex items-center justify-between">
              <p class="text-sm font-semibold text-slate-700">Attendance</p>
              <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">98%</span>
            </div>
            <div class="mt-4 flex items-end gap-2">
              <div class="h-5 w-8 rounded bg-slate-700"></div>
              <div class="h-7 w-8 rounded bg-slate-700"></div>
              <div class="h-6 w-8 rounded bg-slate-700"></div>
              <div class="h-9 w-8 rounded bg-slate-700"></div>
              <div class="h-6 w-8 rounded bg-slate-700"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ArrowRight, Building2, GraduationCap, Landmark, Star, UserRound } from 'lucide-vue-next'

const props = withDefaults(defineProps<{ teacherCardRotation?: string }>(), {
  teacherCardRotation: '10deg',
})

const { teacherCardRotation } = props
</script>
