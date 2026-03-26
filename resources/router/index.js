import { createRouter, createWebHistory } from 'vue-router'

// pages
import Dashboard from '../js/components/schooladmincomponents/Dashboard/Dashboard.vue';
import LandingPage from '../js/components/landingpage/landingPage.vue';
import Onboarding from '../js/components/onboardingpage/onboarding.vue';
import SignIn from '../views/SignIn.vue'

const routes = [
  { path: '/', name: 'LandingPage', component: LandingPage },
  { path: '/onboarding', name: 'Onboarding', component: Onboarding },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard },
  { path: '/signin', name: 'SignIn', component: SignIn }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
