<template>
  <section class="lp-reveal-section lp-section">
    <div class="mx-auto px-20 py-10">
      <p class="text-center text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">
        Trusted by innovative schools worldwide
      </p>

      <div class="mt-10 grid grid-cols-1 gap-x-12 gap-y-8 text-slate-400 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="school in topRowSchools"
          :key="school"
          class="lp-link flex items-center justify-center gap-3 text-center text-2xl font-semibold tracking-tight"
        >
          <School class="h-6 w-6 shrink-0" />
          <span>{{ school }}</span>
        </div>
      </div>

      <div class="mt-14 grid grid-cols-1 gap-y-8 text-slate-400 sm:grid-cols-2 lg:mx-auto lg:max-w-3xl lg:gap-x-20">
        <div
          v-for="school in bottomRowSchools"
          :key="school"
          class="lp-link flex items-center justify-center gap-3 text-center text-2xl font-semibold tracking-tight"
        >
          <School class="h-6 w-6 shrink-0" />
          <span>{{ school }}</span>
        </div>
      </div>
    </div>

    <div class="rounded-t-[28px] border-t border-slate-200 bg-[#fbfbfd]">
      <div class="mx-auto grid grid-cols-1 gap-7 px-20 py-20 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="stat in stats"
          :key="stat.label"
          :class="stat.cardClass"
          class="lp-card rounded-2xl border border-slate-200 px-8 py-10 text-center shadow-sm"
        >
          <p class="text-4xl font-black tracking-tight text-slate-800">
            {{ stat.value }}
          </p>
          <p class="mt-3 text-lg tracking-tight text-slate-700">
            {{ stat.label }}
          </p>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { School } from 'lucide-vue-next'

const topRowSchools = [
  'Westside Academy',
  "St. Patrick's College",
  'Greenfield High',
  'Oakridge International',
]

const bottomRowSchools = [
    'Maple Leaf School', 
    'Brighton Secondary'
]

const stats = [
  { value: '500+', label: 'Schools', cardClass: 'bg-rose-50/60' },
  { value: '200k+', label: 'Students', cardClass: 'bg-slate-100/80' },
  { value: '30+', label: 'Countries', cardClass: 'bg-emerald-50/70' },
  { value: '99.9%', label: 'Uptime', cardClass: 'bg-amber-50/60' },
]
</script>
