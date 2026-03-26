<template>
  <div ref="landingPageRef" class="landing-page min-h-screen bg-[#f6f7fb] text-slate-900">
    <Navbar />
    <Hero />
    <Trusted />
    <Needs />
    <Tracking />
    <Grade />
    <GetStarted />
    <Feedback />
    <Loop />
    <Onboard />
    <Footer />
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import Feedback from './feedback.vue'
import Footer from './footer.vue'
import GetStarted from './getstarted.vue'
import Grade from './grade.vue'
import Navbar from './navbar.vue'
import Hero from './hero.vue'
import Loop from './loop.vue'
import Needs from './needs.vue'
import Onboard from './onboard.vue'
import Tracking from './tracking.vue'
import Trusted from './trusted.vue'

const landingPageRef = ref<HTMLElement | null>(null)
let sectionObserver: IntersectionObserver | null = null

onMounted(() => {
  const sections = landingPageRef.value?.querySelectorAll<HTMLElement>('.lp-reveal-section')

  if (!sections?.length) return

  sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible')
          sectionObserver?.unobserve(entry.target)
        }
      })
    },
    {
      threshold: 0.18,
      rootMargin: '0px 0px -10% 0px',
    },
  )

  sections.forEach((section) => sectionObserver?.observe(section))
})

onBeforeUnmount(() => {
  sectionObserver?.disconnect()
  sectionObserver = null
})
</script>
