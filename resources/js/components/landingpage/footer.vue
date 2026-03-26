<template>
  <footer class="lp-reveal-section w-full bg-white">
    <div class="w-full px-20 pb-8 pt-4">
      
        <div class="grid grid-cols-1 gap-10 md:grid-cols-2 xl:grid-cols-4">
          <div v-for="group in footerGroups" :key="group.title">
            <h3 class="text-base font-bold tracking-tight text-slate-800">
              {{ group.title }}
            </h3>

            <ul class="mt-8 space-y-6">
              <li v-for="item in group.links" :key="item">
                <a href="#" class="lp-link text-base text-slate-500 hover:text-amber-800">
                  {{ item }}
                </a>
              </li>
            </ul>
          </div>
        </div>

        <div class="mt-14 flex flex-col gap-8 border-t border-slate-200 pt-8 md:flex-row md:items-center md:justify-between">
          <div class="flex items-center gap-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-lg font-semibold text-slate-800">
              E
            </div>
            <p class="text-base text-slate-500">
              (c) 2026 EduBoard. All rights reserved.
            </p>
          </div>

          <div class="flex items-center gap-4">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              href="#"
              class="lp-icon-chip flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:text-slate-800"
              :aria-label="social.label"
            >
              <component :is="social.icon" class="h-4 w-4" />
            </a>
          </div>
        </div>
      
    </div>
  </footer>
</template>

<script setup lang="ts">
import { Facebook, Instagram, Linkedin, Twitter } from 'lucide-vue-next'

const footerGroups = [
  {
    title: 'Product',
    links: ['Features', 'Pricing'],
  },
  {
    title: 'Resources',
    links: ['Help Center'],
  },
  {
    title: 'Company',
    links: ['About Us', 'Careers', 'Contact'],
  },
  {
    title: 'Legal',
    links: ['Privacy Policy', 'Terms of Service', 'Cookie Policy', 'Security'],
  },
]

const socialLinks = [
  { label: 'Twitter', icon: Twitter },
  { label: 'LinkedIn', icon: Linkedin },
  { label: 'Facebook', icon: Facebook },
  { label: 'Instagram', icon: Instagram },
]
</script>
