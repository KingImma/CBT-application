<template>
  <section class="lp-reveal-section lp-section">
    <div class="mx-auto px-20 py-20">
      <div class="mx-auto max-w-4xl text-center">
        <h2 class="text-4xl font-black tracking-tight text-slate-800">
          Get started in 3 simple steps
        </h2>
      </div>

      <div class="relative mx-auto mt-20 max-w-6xl">
        <div class="absolute left-[8%] right-[8%] top-6 hidden h-px bg-slate-200 md:block"></div>

        <div class="grid grid-cols-1 gap-12 text-center md:grid-cols-3">
          <article v-for="step in steps" :key="step.number" class="relative">
            <div class="lp-icon-chip mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-slate-200 bg-white text-lg font-semibold text-slate-800 shadow-sm">
              {{ step.number }}
            </div>
            <h3 class="mt-7 text-lg font-bold tracking-tight text-slate-800">
              {{ step.title }}
            </h3>
            <p class="mt-3 text-lg text-slate-500">
              {{ step.description }}
            </p>
          </article>
        </div>
      </div>

      <div class="mt-16 text-center">
        <button class="lp-button rounded-xl bg-slate-900 px-5 py-3 text-lg font-semibold text-white shadow-lg shadow-slate-900/10 hover:bg-slate-800">
          Start Step 1 Now
        </button>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const steps = [
  { number: 1, title: 'Onboard Your School', description: 'Tell us about your school' },
  { number: 2, title: 'Set Up', description: 'Import your data easily' },
  { number: 3, title: 'Launch', description: 'Invite staff & students' },
]
</script>
