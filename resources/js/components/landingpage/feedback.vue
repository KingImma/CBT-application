<template>
  <section class="lp-reveal-section lp-section">
    <div class="mx-auto px-20 py-20">
      <div class="mx-auto max-w-4xl text-center">
        <h2 class="text-4xl font-black tracking-tight text-slate-800">
          Loved by School Administrators
        </h2>
      </div>

      <div class="mt-14 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <article
          v-for="testimonial in testimonials"
          :key="testimonial.name"
          :class="testimonial.cardClass"
          class="lp-card rounded-2xl border border-slate-200 px-8 py-8 shadow-sm"
        >
          <div class="flex items-center gap-1 text-amber-400">
            <Star v-for="star in 5" :key="star" class="h-3 w-3 fill-current" />
          </div>

          <p class="mt-6 text-base font-semibold leading-relaxed text-slate-800">
            "{{ testimonial.quote }}"
          </p>

          <div class="mt-10 flex items-center gap-4">
            <div
              :class="testimonial.avatarClass"
              class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-sm"
            >
              <UserRound class="h-5 w-5" />
            </div>
            <div>
              <p class="text-base font-bold tracking-tight text-slate-800">
                {{ testimonial.name }}
              </p>
              <p class="text-base text-slate-500">
                {{ testimonial.role }}
              </p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { Star, UserRound } from 'lucide-vue-next'

const testimonials = [
  {
    quote:
      'EduBoard completely transformed how we manage our high school. The interface is so friendly that even our least tech teachers adopted it immediately.',
    name: 'David Daniel',
    role: 'Principal, Wonderful Secondary School',
    avatarClass: 'text-amber-500',
    cardClass: 'bg-slate-50',
  },
  {
    quote:
      'The onboarding process was incredibly smooth. We imported 800 students and 50 staff members in an afternoon. Highly recommended!',
    name: 'Martha Francis',
    role: 'IT Director, Abuja Academy',
    avatarClass: 'text-violet-500',
    cardClass: 'bg-violet-50/60',
  },
  {
    quote:
      'The analytics dashboard alone saved us 10 hours per week. We can now make data-driven decisions about curriculum and resource allocation.',
    name: 'James Okafor',
    role: 'VP Academic Affairs, Enugu College',
    avatarClass: 'text-emerald-500',
    cardClass: 'bg-emerald-50/60',
  },
]
</script>
