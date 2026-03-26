<template>
  <header class="fixed left-0 right-0 top-0 z-50 w-full border-b border-slate-200 bg-white/70 backdrop-blur-md backdrop-saturate-150">
    <div class="mx-auto flex h-20 items-center justify-between px-20 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-900 font-semibold">
          E
        </div>
        <span class="text-base font-semibold text-slate-900">EduCBT</span>
      </div>

      <nav class="hidden items-center gap-8 text-base text-slate-600 md:flex font-medium underline underline-offset-4">
        <a href="#" class="hover:text-slate-900">Features</a>
        <a href="#" class="hover:text-slate-900">How It Works</a>
        <a href="#" class="hover:text-slate-900">Pricing</a>
        <a href="#" class="hover:text-slate-900">FAQ</a>
      </nav>

      <Link
        to="/onboarding"
        class="cursor-pointer rounded-lg bg-slate-900 px-5 py-2 text-base font-medium text-white shadow-base transition hover:bg-slate-700"
      >
        Onboard Your School
      </Link>
    </div>
  </header>
</template>

<script setup lang="ts">
import { RouterLink as Link } from 'vue-router'
</script>
