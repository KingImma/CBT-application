<template>
  <div
    class="flex bg-gray-100"
    :class="showSideMenu ? 'h-screen overflow-hidden' : 'min-h-screen'"
  >
    <SideMenu v-if="showSideMenu" />

    <!-- Main Area -->
    <div
      class="flex-1 flex flex-col"
      :class="showSideMenu ? 'overflow-y-auto' : ''"
    >
      <router-view />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import SideMenu from '../SideMenu.vue';

const route = useRoute();
const showSideMenu = computed(() => route.path !== '/signin' && route.path !== '/signup' && route.path !== '/landing');
</script>
