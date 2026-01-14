<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <!-- Card -->
    <div class="bg-white shadow-lg rounded-2xl p-8 sm:p-12 w-full max-w-md">
      
      <!-- Logo -->
      <div class="flex justify-center mb-6">
        <img src="#" alt="Logo" class="w-20 h-20" />
      </div>

      <!-- Title -->
      <h2 class="text-3xl font-bold text-gray-800 text-center mb-8">Welcome Back</h2>

      <!-- Login Form -->
      <form @submit.prevent="login" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            placeholder="you@example.com"
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Password</label>
          <input
            v-model="form.password"
            type="password"
            placeholder="********"
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        <button
          type="submit"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200"
        >
          Login
        </button>
      </form>

      <!-- Footer -->
      <p class="mt-6 text-sm text-center text-gray-500">
        Don't have an account?
        <a href="#" class="text-blue-600 hover:underline">Sign up</a>
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()

const form = reactive({
  email: 'test@example.com',
  password: 'password'
})

const login = async () => {
  try {
    const res = await axios.post('/login', form)
    localStorage.setItem('token', res.data.access_token)
    // Optional: Store user info if needed
    // localStorage.setItem('user', JSON.stringify(res.data.user))
    
    router.push('/dashboard')
  } catch (err) {
    alert('Login failed')
  }
}
</script>
