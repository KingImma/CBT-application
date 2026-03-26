<template>
  <section class="lp-reveal-section lp-section px-20 py-20">
    <div class="lp-card mx-auto max-w-4xl rounded-[30px] border border-slate-200 bg-white px-10 py-16 text-center shadow-sm md:px-20">
      <div class="lp-icon-chip mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 shadow-sm">
        <Bell class="h-6 w-6" />
      </div>

      <h2 class="mt-8 text-4xl font-black tracking-tight text-slate-800">
        Stay in the loop
      </h2>

      <p class="mx-auto mt-6 max-w-3xl text-lg leading-relaxed text-slate-600">
        Get tips on school management, product updates, and exclusive webinar invitations delivered
        to your inbox.
      </p>

      <form class="mx-auto mt-12 flex max-w-xl flex-col gap-4 sm:flex-row">
        <input
          type="email"
          placeholder="Enter your email address"
          class="h-12 flex-1 rounded-sm border border-slate-200 px-5 text-lg text-slate-700 outline-none transition duration-300 placeholder:text-slate-400 focus:-translate-y-0.5 focus:border-slate-300 focus:shadow-md"
        />
        <button
          type="submit"
          class="lp-button rounded-sm bg-slate-900 px-5 py-2 text-lg font-semibold text-white shadow-lg shadow-slate-900/10 hover:bg-slate-800"
        >
          Subscribe
        </button>
      </form>

      <p class="mt-5 text-sm text-slate-400">
        We care about your data. Read our Privacy Policy.
      </p>
    </div>
  </section>
</template>

<script setup lang="ts">
import { Bell } from 'lucide-vue-next'
</script>
