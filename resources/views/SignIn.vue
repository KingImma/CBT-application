<template>
  <div class="min-h-screen flex">    <div class="relative hidden overflow-hidden text-white lg:flex lg:basis-[50%] lg:max-w-[50%]">
      <video
        autoplay
        muted
        loop
        playsinline
        class="absolute inset-0 h-full w-full object-cover"
      >
        <source src="@/assets/signin_bg_vid.mp4" type="video/mp4" />
      </video>

      <div class="absolute inset-0 bg-gradient-to-br from-blue-950/80 via-blue-950/80 to-purple-900/80"></div>

      <div class="relative z-10 flex w-full flex-col justify-between p-16">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-white/30">
            <Shield class="h-5 w-5" />
          </div>
          <span class="text-xl font-semibold tracking-wide">EduCBT</span>
        </div>

        <div class="max-w-md">
          <h1 class="mb-6 text-4xl font-bold leading-tight">
            Empowering Education Through Technology
          </h1>

          <p class="mb-8 text-white/80">
            The complete Computer-Based Testing platform for modern schools.
          </p>

          <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
              <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
              <span class="text-sm">1,284 Students Enrolled</span>
            </div>

            <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
              <span class="h-2 w-2 rounded-full bg-blue-400"></span>
              <span class="text-sm">48 Teachers Active</span>
            </div>

            <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
              <span class="h-2 w-2 rounded-full bg-yellow-400"></span>
              <span class="text-sm">36 Exams Conducted</span>
            </div>
          </div>
        </div>

        <div></div>
      </div>
    </div>

    <div class="hidden bg-gray-50 px-6 lg:flex lg:basis-[50%] lg:max-w-[50%] lg:items-center lg:justify-center">
      <div class="w-full max-w-md">
        <div class="mb-8 text-center">
          <h2 class="mt-4 text-3xl font-semibold text-gray-800">
            Welcome back
          </h2>

          <p class="mt-2 text-sm text-gray-500">
            Sign in to your school admin account
          </p>
        </div>

        <form class="space-y-5">
          <div>
            <label class="mb-2 block text-base font-medium text-gray-700">
              Email Address
            </label>

            <div class="relative">
              <Mail class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-700" />

              <input
                type="email"
                placeholder="admin@school.edu"
                class="w-full rounded-lg border border-gray-700 py-3 pl-10 pr-4 text-lg text-gray-600 outline-none transition placeholder:text-gray-600 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div>
            <label class="mb-2 block text-base font-medium text-gray-700">
              Password
            </label>

            <div class="relative">
              <Lock class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-700" />

              <input
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                class="w-full rounded-lg border border-gray-700 py-3 pl-10 pr-10 text-lg text-gray-600 outline-none transition placeholder:text-gray-600 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
              />

              <component
                :is="showPassword ? Eye : EyeOff"
                class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 cursor-pointer text-gray-700"
                @click="togglePasswordVisibility"
              />
            </div>
          </div>

          <div class="flex items-center justify-between text-base">
            <label class="flex items-center gap-2 text-gray-600">
              <input type="checkbox" class="rounded border-gray-300" />
              Remember me
            </label>

            <a href="#" class="text-indigo-600 hover:underline">
              Forgot password?
            </a>
          </div>

          <button
            type="submit"
            class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 py-3 font-medium text-white transition hover:opacity-95"
          >
            Sign In
          </button>

          <div class="flex items-center gap-4 text-base text-gray-700">
            <div class="h-px flex-1 bg-gray-700"></div>
            or
            <div class="h-px flex-1 bg-gray-700"></div>
          </div>

          <p class="text-center text-base text-gray-500">
            Don't have an account?
            <Link to="/signup" class="font-medium text-indigo-600 hover:underline">
              Sign up
            </Link>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Eye, EyeOff, Lock, Mail, Shield } from 'lucide-vue-next'
import { ref } from 'vue'
import { RouterLink as Link } from 'vue-router'

const showPassword = ref(false)

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}
</script>
