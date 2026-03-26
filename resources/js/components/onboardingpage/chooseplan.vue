<template>
  <div class="mt-10 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_12px_40px_rgba(15,23,42,0.06)] sm:p-10">
    <div class="mx-auto max-w-4xl text-center">
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-100 bg-amber-50 text-amber-500 shadow-sm">
        <Sparkles class="h-6 w-6" />
      </div>

      <h1 class="mt-8 text-4xl font-bold tracking-tight text-slate-800">
        Choose Your Plan
      </h1>

      <p class="mt-5 text-lg leading-relaxed text-slate-600">
        Based on your school setup, we recommend the
        <span class="font-semibold text-slate-800">{{ recommendedPlan.name }}</span>
        plan.
      </p>

      <div class="mt-8 flex items-center justify-center gap-3 text-base">
        <span :class="isAnnual ? 'text-slate-400' : 'text-slate-800'" class="transition">Monthly</span>
        <button
          type="button"
          class="relative flex h-8 w-14 items-center rounded-full bg-slate-200 p-1 transition"
          @click="isAnnual = !isAnnual"
        >
          <span
            class="h-6 w-6 rounded-full bg-slate-800 transition-transform duration-300"
            :class="isAnnual ? 'translate-x-6' : 'translate-x-0'"
          ></span>
        </button>
        <span :class="isAnnual ? 'text-slate-800' : 'text-slate-400'" class="transition">Annually</span>
        <span class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-600">
          Save 20%
        </span>
      </div>
    </div>

    <div class="mt-12 grid grid-cols-1 gap-5 xl:grid-cols-4">
      <article
        v-for="plan in plans"
        :key="plan.name"
        :class="plan.name === recommendedPlan.name ? 'border-slate-300 bg-slate-50 shadow-md' : 'bg-white'"
        class="relative flex h-full flex-col rounded-2xl border border-slate-200 p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-md"
      >
        <div
          v-if="plan.name === recommendedPlan.name"
          class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 rounded-full bg-slate-800 px-4 py-1 text-sm font-semibold text-amber-400"
        >
          Recommended
        </div>

        <h2 class="text-2xl font-bold tracking-tight text-slate-800">
          {{ plan.name }}
        </h2>
        <p class="mt-2 text-base text-slate-500">{{ plan.range }}</p>

        <div class="mt-8">
          <template v-if="plan.price">
            <div class="flex items-end gap-2">
              <span class="text-2xl font-bold tracking-tight text-slate-800">
                {{ displayedPrice(plan.price) }}
              </span>
              <span class="pb-1 text-lg text-slate-500">/mo</span>
            </div>
            <p class="mt-2 text-base font-medium text-emerald-600">
              {{ isAnnual ? 'Billed annually' : 'Billed monthly' }}
            </p>
          </template>

          <template v-else>
            <div class="text-2xl font-bold tracking-tight text-slate-800">Custom</div>
          </template>
        </div>

        <ul class="mt-8 flex-1 space-y-4">
          <li
            v-for="feature in plan.features"
            :key="feature"
            class="flex items-start gap-3 text-base text-slate-700"
          >
            <span class="mt-1 flex h-5 w-5 items-center justify-center rounded-full bg-amber-50 text-amber-500">
              <Check class="h-3.5 w-3.5" />
            </span>
            <span>{{ feature }}</span>
          </li>
        </ul>

        <button
          type="button"
          :class="plan.name === recommendedPlan.name ? 'bg-slate-900 text-white hover:bg-slate-800' : 'border border-slate-200 bg-white text-slate-800 hover:bg-slate-50'"
          class="cursor-pointer mt-10 w-full rounded-xl px-5 py-4 text-base font-semibold shadow-sm transition duration-300 hover:-translate-y-0.5"
        >
          {{ plan.name === recommendedPlan.name ? 'Select Recommended' : 'Select Plan' }}
        </button>
      </article>
    </div>

    <div class="mt-10 flex items-center justify-center gap-3 text-base text-slate-500">
      <ShieldCheck class="h-5 w-5 text-amber-500" />
      <span>30-day money-back guarantee on all plans. No questions asked.</span>
    </div>

    <div class="mt-8 flex justify-start">
      <button
        type="button"
        class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-7 py-3 text-base font-medium text-slate-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
        @click="emit('back')"
      >
        Back
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, ShieldCheck, Sparkles } from 'lucide-vue-next'

const props = defineProps<{
  formData: {
    subjectCount: string
  }
}>()

const emit = defineEmits<{
  back: []
}>()

const isAnnual = ref(true)

const plans = [
  {
    name: 'Starter',
    range: 'Up to 200 students',
    price: 239,
    features: [
      'Basic student management',
      'Attendance tracking',
      'Parent portal access',
      'Standard reporting',
      'Email support',
    ],
  },
  {
    name: 'Growth',
    range: '201-500 students',
    price: 479,
    features: [
      'Everything in Starter',
      'Advanced curriculum planning',
      'Teacher evaluation tools',
      'Custom report cards',
      'Priority support',
    ],
  },
  {
    name: 'Premium',
    range: '501-1000 students',
    price: 799,
    features: [
      'Everything in Growth',
      'Multi-campus support',
      'API access & integrations',
      'Dedicated account manager',
      'Custom onboarding',
    ],
  },
  {
    name: 'Enterprise',
    range: '1000+ students',
    price: null,
    features: [
      'Unlimited everything',
      'Custom feature development',
      'On-premise deployment option',
      '24/7 phone support',
      'SLA guarantee',
    ],
  },
]

const recommendedPlan = computed(() => {
  const count = Number(props.formData.subjectCount || 0)

  if (count >= 20) return plans[2]
  if (count >= 10) return plans[1]
  if (count > 0) return plans[0]
  return plans[1]
})

const displayedPrice = (price: number) => {
  const value = isAnnual.value ? Math.round(price * 0.8) : price
  return `$ ${value}`
}
</script>
