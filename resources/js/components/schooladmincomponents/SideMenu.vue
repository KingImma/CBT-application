<template>
  <aside class="w-64 h-full shrink-0 bg-slate-900 text-white flex flex-col justify-between">
    
    <!-- Brand -->
    <div>
      <div class="px-6 py-5 text-lg font-semibold tracking-wide">
        EduCBT
      </div>

      <!-- Menu -->
      <nav class="px-3 space-y-1">
        <router-link
          v-for="item in menu"
          :key="item.name"
          :to="item.path"
          class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm transition hover:bg-slate-800"
          :class="{ 'bg-indigo-600': isActive(item.path) }"
        >
          <i :class="item.icon"></i>
          {{ item.name }}
        </router-link>
      </nav>
    </div>

    <!-- Profile -->
    <div class="px-4 py-4 border-t border-slate-800 flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center font-semibold">
        SA
      </div>
      <div class="text-sm">
        <p class="font-medium">School Admin</p>
        <p class="text-slate-400 text-xs">admin@educbt.ng</p>
      </div>
      <button
        type="button"
        class="group ml-auto relative text-slate-400 hover:text-white transition-colors cursor-pointer"
        aria-label="Sign out"
      >
        <i class="pi pi-sign-out text-lg"></i>
        <span class="pointer-events-none absolute right-0 -top-9 whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity">
          Sign out
        </span>
      </button>
    </div>

  </aside>
</template>

<script lang="ts">
export default {}
</script>

<script setup lang="ts">
import { useRoute } from 'vue-router';

const route = useRoute();
const menu = [
  { name: 'Dashboard', path: '/dashboard', icon: 'pi pi-home' },
  { name: 'Teachers', path: '/teachers', icon: 'pi pi-user' },
  { name: 'Students', path: '/students', icon: 'pi pi-users' },
  { name: 'Classes', path: '/classes', icon: 'pi pi-graduation-cap' },
  { name: 'Exams', path: '/exams', icon: 'pi pi-file' },
  { name: 'Results', path: '/results', icon: 'pi pi-chart-bar' },
  { name: 'Settings', path: '/settings', icon: 'pi pi-cog' }
];

function isActive(path: string) {
  return route.path === path || route.path.startsWith(`${path}/`);
}
</script>
