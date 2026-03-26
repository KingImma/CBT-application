<template>
  <section class="lp-reveal-section lp-section">
    <div class="mx-auto px-20 py-20">
      <div class="mx-auto max-w-4xl text-center">
        <h2 class="text-4xl font-black tracking-tight text-slate-800">
          Everything you need to run your school
        </h2>
        <p class="mt-5 text-xl leading-relaxed text-slate-500">
          Powerful features wrapped in a beautiful, intuitive interface that your staff will
          actually love using.
        </p>
      </div>

      <div class="mt-14 grid grid-cols-1 gap-8 xl:grid-cols-4">
        <article
        v-for="feature in features"
        :key="feature.title"
        :class="feature.cardClass"
        class="lp-card flex flex-col rounded-2xl border border-slate-200 px-10 py-9 shadow-sm"
        >
          <div class="lp-icon-chip flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm">
            <component :is="feature.icon" class="h-6 w-8" />
          </div>

          <h3 class="mt-8 text-lg font-bold tracking-tight text-slate-800">
            {{ feature.title }}
          </h3>
          <p class="mt-5 text-base leading-relaxed text-slate-600">
            {{ feature.description }}
          </p>

          <a
            href="#"
            class="lp-link mt-auto inline-flex items-center gap-2 pt-5 text-sm font-semibold text-amber-300 hover:text-amber-500"
            >
            Learn more
            <ArrowRight class="h-4 w-4" />
            </a>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ArrowRight, BarChart3, BookOpen, Building2, Users } from 'lucide-vue-next'

const features = [
  {
    title: 'Student Management',
    description: 'Track attendance, grades, and behavior in one unified profile.',
    icon: Users,
    cardClass: 'bg-rose-50/60',
  },
  {
    title: 'Curriculum Planning',
    description: 'Collaborative lesson planning and resource sharing for teachers.',
    icon: BookOpen,
    cardClass: 'bg-indigo-50/70',
  },
  {
    title: 'Staff Portal',
    description: 'Streamline HR, payroll, and professional development.',
    icon: Building2,
    cardClass: 'bg-emerald-50/60',
  },
  {
    title: 'Analytics Dashboard',
    description: 'Real-time insights into school performance and student success.',
    icon: BarChart3,
    cardClass: 'bg-amber-50/50',
  },
]
</script>
